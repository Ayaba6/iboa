<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\CashAccount;
use App\Models\Client;
use App\Models\ClientPayment;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Order;
use App\Models\ProductStock;
use App\Models\SupplierPayment;
use App\Services\UserHomeRoute;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        // Rediriger si l'utilisateur n'a pas accès au tableau de bord
        if (! UserHomeRoute::canSeeDashboard()) {
            return redirect(UserHomeRoute::resolve())
                ->with('info', "Vous n'avez pas accès au tableau de bord.");
        }

        // [SAGE parité] « Mes validations en attente » — mêmes données que la
        // page Mes validations, limitées aux 6 plus anciennes pour le tableau.
        $pendingAll      = app(\App\Services\PendingValidationsService::class)->for(auth()->user());
        $pendingCount    = $pendingAll->count();
        $mesValidations  = $pendingAll->sortBy('submitted_at')->take(6)->values();

        // [SEC §15] Profils sans reports.view : accueil neutre (bandeau
        // validations + message) — aucun KPI financier calculé ni transmis.
        if (! auth()->user()->can('reports.view')) {
            return view('dashboard', compact('pendingCount', 'mesValidations'));
        }

        $now       = now();
        $month     = $now->month;
        $year      = $now->year;
        $prevMonth = $now->copy()->subMonth();

        $invoiceStatuses = ['emise', 'envoyee', 'partiellement_payee', 'payee', 'en_retard'];

        // ── KPI block — was 14 separate queries, now 3 ────────────────────────
        // Cache for 5 minutes: KPIs don't need to be real-time to the second.
        $cacheKey = "dashboard.kpis.{$year}.{$month}";
        $kpis = Cache::remember($cacheKey, 300, function () use (
            $invoiceStatuses, $now, $year, $month, $prevMonth
        ) {
            // [PERF-01] All invoice KPIs in ONE query via CASE expressions.
            $ivKpi = DB::table('invoices')
                ->whereIn('status', $invoiceStatuses)
                ->selectRaw("
                    SUM(CASE WHEN DATE(issued_at) = ? THEN total_ttc ELSE 0 END)                                        AS rev_jour,
                    SUM(CASE WHEN DATE(issued_at) = ? THEN total_ttc ELSE 0 END)                                        AS rev_prev_jour,
                    SUM(CASE WHEN YEAR(issued_at)=? AND MONTH(issued_at)=? THEN total_ttc ELSE 0 END)                   AS rev_mois,
                    SUM(CASE WHEN YEAR(issued_at)=? AND MONTH(issued_at)=? THEN total_ttc ELSE 0 END)                   AS rev_prev_mois,
                    SUM(CASE WHEN YEAR(issued_at)=?                        THEN total_ttc ELSE 0 END)                   AS rev_annee,
                    COUNT(CASE WHEN YEAR(issued_at)=? AND MONTH(issued_at)=? THEN 1 END)                                AS nb_mois
                ", [
                    $now->toDateString(),
                    $now->copy()->subDay()->toDateString(),
                    $year, $month,
                    $prevMonth->year, $prevMonth->month,
                    $year,
                    $year, $month,
                ])
                ->first();

            // [PERF-02] Overdue invoices — separate because different status set + due_at filter.
            $overdueKpi = DB::table('invoices')
                ->whereIn('status', ['emise', 'envoyee', 'partiellement_payee', 'en_retard'])
                ->where('due_at', '<', now())
                ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(remaining_amount), 0) as montant')
                ->first();

            // [PERF-03] Both payment KPIs in ONE query.
            $encKpi = DB::table('client_payments')
                ->where('status', 'confirme')
                ->selectRaw("
                    SUM(CASE WHEN YEAR(payment_date)=? AND MONTH(payment_date)=? THEN amount ELSE 0 END) AS enc_mois,
                    SUM(CASE WHEN YEAR(payment_date)=? AND MONTH(payment_date)=? THEN amount ELSE 0 END) AS enc_prev_mois
                ", [$year, $month, $prevMonth->year, $prevMonth->month])
                ->first();

            // [PERF-04] Four count KPIs in ONE subquery block.
            $miscKpi = DB::selectOne("
                SELECT
                    (SELECT COUNT(*)                    FROM product_stocks WHERE quantity <= 0)                                           AS rupture_stock,
                    (SELECT COALESCE(SUM(current_balance),0) FROM cash_accounts WHERE is_active = 1)                                      AS solde_tresorerie,
                    (SELECT COUNT(*)                    FROM clients          WHERE is_active = 1 AND deleted_at IS NULL)                  AS nb_clients,
                    (SELECT COUNT(*)                    FROM orders           WHERE status IN ('confirme','en_preparation','partiellement_livre') AND deleted_at IS NULL) AS nb_commandes
            ");

            return compact('ivKpi', 'overdueKpi', 'encKpi', 'miscKpi');
        });

        ['ivKpi' => $ivKpi, 'overdueKpi' => $overdueKpi, 'encKpi' => $encKpi, 'miscKpi' => $miscKpi] = $kpis;

        // Unpack KPIs into the variable names the view expects
        $revenueJour     = (int) $ivKpi->rev_jour;
        $revenuePrevJour = (int) $ivKpi->rev_prev_jour;
        $revenueMois     = (int) $ivKpi->rev_mois;
        $prevRevenue     = (int) $ivKpi->rev_prev_mois;
        $revenueAnnee    = (int) $ivKpi->rev_annee;
        $nbFacturesMois  = (int) $ivKpi->nb_mois;

        $facturesEnRetard = (int) $overdueKpi->cnt;
        $montantEnRetard  = (int) $overdueKpi->montant;

        $encaissementsMois = (int) $encKpi->enc_mois;
        $prevEncaissements = (int) $encKpi->enc_prev_mois;

        $ruptureStock       = (int) $miscKpi->rupture_stock;
        $soldeTresorerie    = (int) $miscKpi->solde_tresorerie;
        $nbClients          = (int) $miscKpi->nb_clients;
        $nbCommandesEnCours = (int) $miscKpi->nb_commandes;

        $trendJour          = $this->trend($revenueJour, $revenuePrevJour);
        $trendRevenue       = $this->trend($revenueMois, $prevRevenue);
        $trendEncaissements = $this->trend($encaissementsMois, $prevEncaissements);

        // ── Charts: 6 months (one query each — already efficient) ─────────────

        $sixMonthsAgo = $now->copy()->subMonths(5)->startOfMonth();

        $ymExprIssued  = "substr(issued_at, 1, 7)";
        $ymExprPayment = "substr(payment_date, 1, 7)";

        $caByMonth = Invoice::whereIn('status', $invoiceStatuses)
            ->where('issued_at', '>=', $sixMonthsAgo)
            ->selectRaw("{$ymExprIssued} as ym, SUM(total_ttc) as total")
            ->groupByRaw("{$ymExprIssued}")
            ->pluck('total', 'ym')
            ->mapWithKeys(fn($v, $k) => [$k => (int) $v]);

        $encByMonth = ClientPayment::whereIn('status', ['confirme'])
            ->where('payment_date', '>=', $sixMonthsAgo)
            ->selectRaw("{$ymExprPayment} as ym, SUM(amount) as total")
            ->groupByRaw("{$ymExprPayment}")
            ->pluck('total', 'ym')
            ->mapWithKeys(fn($v, $k) => [$k => (int) $v]);

        $decByMonth = SupplierPayment::whereIn('status', ['confirme'])
            ->where('payment_date', '>=', $sixMonthsAgo)
            ->selectRaw("{$ymExprPayment} as ym, SUM(amount) as total")
            ->groupByRaw("{$ymExprPayment}")
            ->pluck('total', 'ym')
            ->mapWithKeys(fn($v, $k) => [$k => (int) $v]);

        $caParMois = collect();
        $encVsDec  = collect();
        for ($i = 5; $i >= 0; $i--) {
            $d     = $now->copy()->subMonths($i);
            $key   = $d->format('Y-m');
            $label = ucfirst($d->locale('fr')->isoFormat('MMM YY'));
            $caParMois->push(['month' => $label, 'amount' => $caByMonth[$key] ?? 0]);
            $encVsDec->push(['month' => $label, 'enc' => $encByMonth[$key] ?? 0, 'dec' => $decByMonth[$key] ?? 0]);
        }

        // ── Chart: 30 derniers jours ──────────────────────────────────────────

        $thirtyDaysAgo = $now->copy()->subDays(29)->startOfDay();

        $caByDay = Invoice::whereIn('status', $invoiceStatuses)
            ->where('issued_at', '>=', $thirtyDaysAgo)
            ->selectRaw('DATE(issued_at) as day, SUM(total_ttc) as total')
            ->groupByRaw('DATE(issued_at)')
            ->pluck('total', 'day')
            ->mapWithKeys(fn($v, $k) => [$k => (int) $v]);

        $encByDay = ClientPayment::whereIn('status', ['confirme'])
            ->where('payment_date', '>=', $thirtyDaysAgo)
            ->selectRaw('DATE(payment_date) as day, SUM(amount) as total')
            ->groupByRaw('DATE(payment_date)')
            ->pluck('total', 'day')
            ->mapWithKeys(fn($v, $k) => [$k => (int) $v]);

        $ca30Days = collect(); $ca30Labels = collect();
        $ca7Days  = collect(); $ca7Labels  = collect();
        $caDaily  = collect(); $encDaily   = collect();

        for ($i = 29; $i >= 0; $i--) {
            $d   = $now->copy()->subDays($i);
            $key = $d->toDateString();
            $val = $caByDay[$key] ?? 0;
            $ca30Days->push($val);
            $ca30Labels->push($d->format('d/m'));
            if ($i <= 13) { $caDaily->push($val); $encDaily->push($encByDay[$key] ?? 0); }
            if ($i <= 6)  { $ca7Days->push($val); $ca7Labels->push($d->locale('fr')->isoFormat('ddd D')); }
        }

        // ── Tables (already efficient — limit + select + with) ────────────────

        // groupBy + with() Eloquent : charger les clients en 1 requête whereIn séparée
        $topClientsRaw = Invoice::whereIn('status', $invoiceStatuses)
            ->whereYear('issued_at', $year)->whereMonth('issued_at', $month)
            ->select('client_id', DB::raw('SUM(total_ttc) as total'))
            ->groupBy('client_id')
            ->orderByDesc('total')->limit(5)->get();

        $clientIds  = $topClientsRaw->pluck('client_id')->filter();
        $clientsMap = \App\Models\Client::whereIn('id', $clientIds)->get(['id', 'name'])->keyBy('id');
        $topClients = $topClientsRaw->map(function ($row) use ($clientsMap) {
            $row->client = $clientsMap->get($row->client_id);
            return $row;
        });

        $paymentsByMethod = ClientPayment::whereIn('status', ['confirme'])
            ->whereYear('payment_date', $year)->whereMonth('payment_date', $month)
            ->select('payment_method_id', DB::raw('SUM(amount) as total'))
            ->groupBy('payment_method_id')->with('paymentMethod:id,name')->get();

        $cashAccounts = CashAccount::where('is_active', true)
            ->orderBy('name')->get(['id', 'name', 'type', 'current_balance']);

        $facturesAEncaisser = Invoice::whereIn('status', ['emise', 'envoyee', 'partiellement_payee', 'en_retard'])
            ->with('client:id,name')->orderBy('due_at')->limit(6)
            ->get(['id', 'number', 'client_id', 'total_ttc', 'remaining_amount', 'due_at', 'status']);

        $derniersEncaissements = ClientPayment::with('client:id,name', 'paymentMethod:id,name')
            ->whereIn('status', ['confirme'])->orderByDesc('payment_date')->limit(6)
            ->get(['id', 'number', 'client_id', 'amount', 'payment_date', 'payment_method_id', 'status', 'reference']);

        $dernieresCommandes = Order::with('client:id,name')
            ->orderByDesc('created_at')->limit(6)
            ->get(['id', 'number', 'client_id', 'total_ttc', 'status', 'issued_at']);

        $recentActivity = AuditLog::orderByDesc('created_at')->limit(10)
            ->get(['user_name', 'action', 'model_type', 'model_id', 'created_at']);

        // [SAGE parité] Dernières factures client (table dashboard).
        $dernieresFactures = Invoice::with('client:id,name')
            ->orderByDesc('issued_at')->orderByDesc('id')->limit(6)
            ->get(['id', 'number', 'client_id', 'issued_at', 'subtotal_ht', 'total_ttc', 'currency_code', 'status']);

        // [SAGE parité] Alertes stock : suivi des seuils — articles sous seuil
        // en premier (rouge), puis les plus proches de leur seuil (surveillance).
        $alertesStock = \App\Models\Product::query()
            ->where('is_stockable', true)
            ->where(fn ($q) => $q->whereNotNull('reorder_point')->orWhereNotNull('stock_min'))
            ->withSum('productStocks as stock_dispo', 'quantity')
            ->with('unit:id,abbreviation')
            ->get(['id', 'name', 'reference', 'code_article', 'reorder_point', 'stock_min', 'unit_id'])
            ->map(function ($p) {
                $p->seuil       = (float) ($p->reorder_point ?? $p->stock_min ?? 0);
                $p->sous_seuil  = $p->seuil > 0 && (float) ($p->stock_dispo ?? 0) <= $p->seuil;
                return $p;
            })
            ->filter(fn ($p) => $p->seuil > 0)
            ->sortBy(fn ($p) => [$p->sous_seuil ? 0 : 1, $p->seuil > 0 ? (float) ($p->stock_dispo ?? 0) / $p->seuil : 0])
            ->take(6)->values();

        $topProduits = InvoiceItem::query()
            ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->join('products', 'invoice_items.product_id', '=', 'products.id')
            ->whereIn('invoices.status', $invoiceStatuses)
            ->whereYear('invoices.issued_at', $year)->whereMonth('invoices.issued_at', $month)
            ->whereNotNull('invoice_items.product_id')
            ->select('products.id', 'products.name',
                DB::raw('SUM(invoice_items.quantity) as qty'),
                DB::raw('SUM(invoice_items.line_total_ht) as ca_ht'),
                DB::raw('SUM(invoice_items.line_total_ht - (invoice_items.quantity * products.purchase_price)) as marge')
            )
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('ca_ht')->limit(5)->get();

        return view('dashboard', compact(
            'revenueJour', 'revenuePrevJour', 'revenueMois', 'revenueAnnee',
            'nbFacturesMois', 'nbClients', 'nbCommandesEnCours',
            'encaissementsMois',
            'facturesEnRetard', 'montantEnRetard',
            'ruptureStock', 'soldeTresorerie',
            'trendRevenue', 'trendEncaissements', 'trendJour',
            'caParMois', 'encVsDec',
            'ca30Days', 'ca30Labels', 'ca7Days', 'ca7Labels',
            'caDaily', 'encDaily',
            'topClients', 'topProduits', 'paymentsByMethod',
            'cashAccounts', 'facturesAEncaisser',
            'derniersEncaissements', 'dernieresCommandes',
            'recentActivity',
            'pendingCount', 'mesValidations', 'dernieresFactures', 'alertesStock',
        ));
    }

    /**
     * Endpoint JSON léger — actualisation automatique toutes les 60s.
     * Retourne uniquement les KPIs volatils (pas les charts, pas les listes).
     * Cache 60s : réduit la charge serveur même sous polling intensif.
     */
    public function kpisJson(): \Illuminate\Http\JsonResponse
    {
        // [SEC §15] KPI financiers réservés aux porteurs de reports.view —
        // même via le endpoint JSON du polling.
        abort_unless(auth()->user()->can('reports.view'), 403);

        $now       = now();
        $month     = $now->month;
        $year      = $now->year;
        $companyId = currentCompany()->id; // [SEC] Isolation multi-tenant (DB::table bypass le HasCompanyScope)

        $data = Cache::remember("dashboard.kpis.live.{$companyId}.{$year}.{$month}", 60, function () use ($now, $year, $month, $companyId) {
            $invoiceStatuses = ['emise', 'envoyee', 'partiellement_payee', 'payee', 'en_retard'];
            $prevMonth       = $now->copy()->subMonth();

            $ivKpi = DB::table('invoices')
                ->where('company_id', $companyId)
                ->whereIn('status', $invoiceStatuses)
                ->selectRaw("
                    SUM(CASE WHEN DATE(issued_at) = ? THEN total_ttc ELSE 0 END)                                   AS rev_jour,
                    SUM(CASE WHEN DATE(issued_at) = ? THEN total_ttc ELSE 0 END)                                   AS rev_prev_jour,
                    SUM(CASE WHEN YEAR(issued_at)=? AND MONTH(issued_at)=? THEN total_ttc ELSE 0 END)              AS rev_mois,
                    SUM(CASE WHEN YEAR(issued_at)=? AND MONTH(issued_at)=? THEN total_ttc ELSE 0 END)              AS rev_prev_mois
                ", [
                    $now->toDateString(),
                    $now->copy()->subDay()->toDateString(),
                    $year, $month,
                    $prevMonth->year, $prevMonth->month,
                ])->first();

            $overdueKpi = DB::table('invoices')
                ->where('company_id', $companyId)
                ->whereIn('status', ['emise', 'envoyee', 'partiellement_payee', 'en_retard'])
                ->where('due_at', '<', now())
                ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(remaining_amount), 0) as montant')
                ->first();

            $encKpi = DB::table('client_payments')
                ->where('company_id', $companyId)
                ->where('status', 'confirme')
                ->whereYear('payment_date', $year)
                ->whereMonth('payment_date', $month)
                ->selectRaw('COALESCE(SUM(amount), 0) AS enc_mois')
                ->first();

            $miscKpi = DB::selectOne("
                SELECT
                    (SELECT COUNT(*) FROM product_stocks ps JOIN warehouses w ON w.id = ps.warehouse_id
                        WHERE w.company_id = ? AND ps.quantity <= 0)                                            AS rupture_stock,
                    (SELECT COALESCE(SUM(current_balance),0) FROM cash_accounts WHERE company_id = ? AND is_active=1) AS solde_tresorerie,
                    (SELECT COUNT(*) FROM orders WHERE company_id = ? AND status IN ('confirme','en_preparation','partiellement_livre') AND deleted_at IS NULL) AS nb_commandes
            ", [$companyId, $companyId, $companyId]);

            $revJour     = (int) $ivKpi->rev_jour;
            $prevJour    = (int) $ivKpi->rev_prev_jour;
            $revMois     = (int) $ivKpi->rev_mois;
            $prevMois    = (int) $ivKpi->rev_prev_mois;

            return [
                'rev_jour'          => $revJour,
                'rev_mois'          => $revMois,
                'enc_mois'          => (int) $encKpi->enc_mois,
                'factures_retard'   => (int) $overdueKpi->cnt,
                'montant_retard'    => (int) $overdueKpi->montant,
                'rupture_stock'     => (int) $miscKpi->rupture_stock,
                'solde_tresorerie'  => (int) $miscKpi->solde_tresorerie,
                'nb_commandes'      => (int) $miscKpi->nb_commandes,
                'trend_jour'        => $this->trend($revJour, $prevJour),
                'trend_mois'        => $this->trend($revMois, $prevMois),
                'refreshed_at'      => $now->format('H:i:s'),
            ];
        });

        return response()->json($data);
    }

    private function trend(float $current, float $previous): array
    {
        if ($previous <= 0) {
            return ['value' => null, 'direction' => 'neutral'];
        }
        $pct = round((($current - $previous) / $previous) * 100, 1);
        return ['value' => abs($pct), 'direction' => $pct >= 0 ? 'up' : 'down'];
    }
}
