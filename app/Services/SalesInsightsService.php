<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * [VENTES-PRO] Indicateurs avancés du module Ventes — équivalent du dashboard Odoo/Sage.
 *
 *   - KPIs synthétiques : CA mois, encours clients, DSO, taux conversion devis
 *   - Top clients / top articles vendus
 *   - Échéances clients à venir / en retard
 *   - Pipeline devis (par statut)
 *   - Évolution mensuelle 12 mois (CA HT + #factures)
 */
class SalesInsightsService
{
    /**
     * KPIs synthétiques pour le tableau de bord.
     *
     * Optimisé : une seule requête agrégée par domaine via DB::selectRaw.
     */
    public function dashboardKpis(): array
    {
        $companyId      = currentCompany()->id;
        $today          = now();
        $todayStr       = $today->toDateString();
        $startOfMonth   = $today->copy()->startOfMonth();
        $endOfMonth     = $today->copy()->endOfMonth();
        $startPrevMonth = $today->copy()->subMonth()->startOfMonth();
        $endPrevMonth   = $today->copy()->subMonth()->endOfMonth();
        $startOfYear    = $today->copy()->startOfYear();
        $startPrevYear  = $today->copy()->subYear()->startOfYear();
        $endPrevYear    = $today->copy()->subYear()->endOfYear();

        // ── Agrégats factures en une requête ────────────────────────────────
        $invoiceAgg = DB::table('invoices')
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->selectRaw("
                -- CA mois courant
                SUM(CASE WHEN status NOT IN ('brouillon','annulee') AND type != 'avoir'
                         AND issued_at BETWEEN ? AND ? THEN subtotal_ht ELSE 0 END) AS ca_month,
                -- CA mois précédent
                SUM(CASE WHEN status NOT IN ('brouillon','annulee') AND type != 'avoir'
                         AND issued_at BETWEEN ? AND ? THEN subtotal_ht ELSE 0 END) AS ca_prev_month,
                -- CA année en cours
                SUM(CASE WHEN status NOT IN ('brouillon','annulee') AND type != 'avoir'
                         AND issued_at >= ? THEN subtotal_ht ELSE 0 END) AS ca_year,
                -- CA année précédente (même périmètre)
                SUM(CASE WHEN status NOT IN ('brouillon','annulee') AND type != 'avoir'
                         AND issued_at BETWEEN ? AND ? THEN subtotal_ht ELSE 0 END) AS ca_prev_year,
                -- CA 90 derniers jours (pour DSO)
                SUM(CASE WHEN status NOT IN ('brouillon','annulee') AND type != 'avoir'
                         AND issued_at >= ? THEN subtotal_ht ELSE 0 END) AS ca_90d,
                -- Encours (non payées)
                SUM(CASE WHEN status IN ('emise','envoyee','partiellement_payee','en_retard')
                         THEN remaining_amount ELSE 0 END) AS outstanding,
                -- Retards — montant
                SUM(CASE WHEN status IN ('emise','envoyee','partiellement_payee','en_retard')
                         AND due_at < ? AND remaining_amount > 0 THEN remaining_amount ELSE 0 END) AS overdue_amount,
                -- Retards — nombre
                SUM(CASE WHEN status IN ('emise','envoyee','partiellement_payee','en_retard')
                         AND due_at < ? AND remaining_amount > 0 THEN 1 ELSE 0 END) AS overdue_count,
                -- Brouillons à valider
                SUM(CASE WHEN status = 'brouillon' THEN 1 ELSE 0 END) AS draft_invoices,
                -- Panier moyen mois courant
                AVG(CASE WHEN status NOT IN ('brouillon','annulee') AND type != 'avoir'
                         AND issued_at BETWEEN ? AND ? THEN total_ttc ELSE NULL END) AS avg_basket_month
            ",
                // bindings dans l'ordre des ?
                [
                    $startOfMonth,   $endOfMonth,
                    $startPrevMonth, $endPrevMonth,
                    $startOfYear,
                    $startPrevYear,  $endPrevYear,
                    now()->subDays(90)->startOfDay()->toDateTimeString(),
                    $todayStr, $todayStr,
                    $startOfMonth,   $endOfMonth,
                ]
            )
            ->first();

        $caMonth      = (int) ($invoiceAgg->ca_month      ?? 0);
        $caPrevMonth  = (int) ($invoiceAgg->ca_prev_month ?? 0);
        $caYear       = (int) ($invoiceAgg->ca_year       ?? 0);
        $caPrevYear   = (int) ($invoiceAgg->ca_prev_year  ?? 0);
        $ca90         = (int) ($invoiceAgg->ca_90d        ?? 0);
        $outstanding  = (int) ($invoiceAgg->outstanding   ?? 0);
        $overdue      = (int) ($invoiceAgg->overdue_amount ?? 0);
        $overdueCount = (int) ($invoiceAgg->overdue_count ?? 0);
        $draftInvoices = (int) ($invoiceAgg->draft_invoices ?? 0);
        $avgBasket    = (int) ($invoiceAgg->avg_basket_month ?? 0);

        $variation      = $caPrevMonth > 0
            ? round(($caMonth - $caPrevMonth) / $caPrevMonth * 100, 1)
            : null;
        $yearVariation  = $caPrevYear > 0
            ? round(($caYear - $caPrevYear) / $caPrevYear * 100, 1)
            : null;
        $dso = $ca90 > 0 ? (int) round($outstanding / $ca90 * 90) : 0;

        // ── Devis : taux de conversion ───────────────────────────────────────
        $quoteAgg = DB::table('quotes')
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->selectRaw("
                SUM(CASE WHEN status NOT IN ('brouillon','annule') THEN 1 ELSE 0 END) AS sent,
                SUM(CASE WHEN status IN ('accepte','converti') THEN 1 ELSE 0 END) AS accepted
            ")
            ->first();

        $quotesSent     = (int) ($quoteAgg->sent     ?? 0);
        $quotesAccepted = (int) ($quoteAgg->accepted ?? 0);
        $conversionRate = $quotesSent > 0
            ? round($quotesAccepted / $quotesSent * 100, 1)
            : 0.0;

        // ── Commandes en cours ───────────────────────────────────────────────
        $ordersInProgress = (int) DB::table('orders')
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->whereIn('status', ['confirme', 'en_preparation', 'partiellement_livre'])
            ->count();

        // ── Nouveaux clients ce mois ─────────────────────────────────────────
        $newClientsMonth = (int) DB::table('clients')
            ->whereNull('deleted_at')
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->count();

        return [
            'ca_month'           => $caMonth,
            'ca_prev_month'      => $caPrevMonth,
            'ca_variation_pct'   => $variation,
            'ca_year'            => $caYear,
            'ca_prev_year'       => $caPrevYear,
            'ca_year_variation'  => $yearVariation,
            'outstanding'        => $outstanding,
            'overdue_amount'     => $overdue,
            'overdue_count'      => $overdueCount,
            'dso_days'           => $dso,
            'avg_basket_month'   => $avgBasket,
            'quotes_sent'        => $quotesSent,
            'quotes_accepted'    => $quotesAccepted,
            'conversion_rate'    => $conversionRate,
            'orders_in_progress' => $ordersInProgress,
            'draft_invoices'     => $draftInvoices,
            'new_clients_month'  => $newClientsMonth,
        ];
    }

    /**
     * Échéances clients à venir (30 jours) + en retard.
     */
    public function upcomingDueInvoices(int $days = 30): Collection
    {
        $today = now()->toDateString();
        $limit = now()->addDays($days)->toDateString();

        return DB::table('invoices as i')
            ->join('clients as c', 'c.id', '=', 'i.client_id')
            ->where('i.company_id', currentCompany()->id)
            ->whereNull('i.deleted_at')
            ->whereIn('i.status', ['emise', 'envoyee', 'partiellement_payee', 'en_retard'])
            ->where('i.remaining_amount', '>', 0)
            ->where(function ($q) use ($limit) {
                $q->whereNull('i.due_at')->orWhereDate('i.due_at', '<=', $limit);
            })
            ->select('i.id', 'i.number', 'i.status', 'i.issued_at', 'i.due_at',
                'i.total_ttc', 'i.paid_amount', 'i.remaining_amount',
                'c.name as client_name',
                DB::raw("CASE WHEN i.due_at < '{$today}' THEN 1 ELSE 0 END as is_overdue"),
                DB::raw("DATEDIFF(i.due_at, NOW()) as days_to_due"))
            ->orderBy('i.due_at')
            ->limit(20)
            ->get();
    }

    /**
     * Top clients par CA sur les 12 derniers mois.
     */
    public function topClients(int $limit = 5): Collection
    {
        $from = now()->subMonths(12)->startOfMonth();
        return DB::table('invoices as i')
            ->join('clients as c', 'c.id', '=', 'i.client_id')
            ->where('i.company_id', currentCompany()->id)
            ->whereNull('i.deleted_at')
            ->whereNotIn('i.status', ['brouillon', 'annulee'])
            ->where('i.type', '!=', 'avoir')
            ->where('i.issued_at', '>=', $from)
            ->select('c.id', 'c.name',
                DB::raw('COUNT(DISTINCT i.id) as invoices_count'),
                DB::raw('SUM(i.subtotal_ht) as total_ht'),
                DB::raw('SUM(i.remaining_amount) as outstanding'))
            ->groupBy('c.id', 'c.name')
            ->orderByDesc('total_ht')
            ->limit($limit)
            ->get();
    }

    /**
     * Top articles vendus (quantité + CA) sur les 12 derniers mois.
     */
    public function topProducts(int $limit = 5): Collection
    {
        $from = now()->subMonths(12)->startOfMonth();
        return DB::table('invoice_items as it')
            ->join('invoices as i', 'i.id', '=', 'it.invoice_id')
            ->join('products as p', 'p.id', '=', 'it.product_id')
            ->where('i.company_id', currentCompany()->id)
            ->whereNull('i.deleted_at')
            ->whereNotIn('i.status', ['brouillon', 'annulee'])
            ->where('i.type', '!=', 'avoir')
            ->where('i.issued_at', '>=', $from)
            ->select('p.id', 'p.reference', 'p.name',
                DB::raw('SUM(it.quantity) as qty_sold'),
                DB::raw('SUM(it.line_total_ht) as total_ht'))
            ->groupBy('p.id', 'p.reference', 'p.name')
            ->orderByDesc('total_ht')
            ->limit($limit)
            ->get();
    }

    /**
     * CA HT par mois sur les 12 derniers mois (pour graphique évolution).
     */
    public function monthlyEvolution(int $months = 12): Collection
    {
        $from = now()->subMonths($months - 1)->startOfMonth();
        return DB::table('invoices')
            ->where('company_id', currentCompany()->id)
            ->whereNull('deleted_at')
            ->whereNotIn('status', ['brouillon', 'annulee'])
            ->where('type', '!=', 'avoir')
            ->where('issued_at', '>=', $from)
            ->select(
                DB::raw('DATE_FORMAT(issued_at, "%Y-%m") as month'),
                DB::raw('SUM(subtotal_ht) as total_ht'),
                DB::raw('COUNT(*) as invoices_count'))
            ->groupBy('month')
            ->orderBy('month')
            ->get();
    }

    /**
     * Pipeline devis par statut (équivalent funnel Odoo).
     */
    public function quotesPipeline(): array
    {
        $rows = DB::table('quotes')
            ->where('company_id', currentCompany()->id)
            ->whereNull('deleted_at')
            ->select('status', DB::raw('COUNT(*) as count'), DB::raw('SUM(total_ttc) as total'))
            ->groupBy('status')
            ->get();

        $labels = [
            'brouillon' => 'Brouillon',
            'envoye'    => 'Envoyé',
            'accepte'   => 'Accepté',
            'converti'  => 'Converti en cmd',
            'refuse'    => 'Refusé',
            'expire'    => 'Expiré',
            'annule'    => 'Annulé',
        ];

        $result = [];
        foreach ($labels as $k => $label) {
            $row = $rows->firstWhere('status', $k);
            $result[$k] = [
                'label' => $label,
                'count' => $row?->count ?? 0,
                'total' => (int) ($row?->total ?? 0),
            ];
        }
        return $result;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // [VENTES X3] Widgets tableau de bord Sage X3 : répartition, statuts, échéances,
    // activités, alertes, marge. Toutes scopées société, défensives (0 si vide).
    // ─────────────────────────────────────────────────────────────────────────

    /** CA HT par famille d'articles (12 mois), trié décroissant. */
    public function caByFamily(int $months = 12): Collection
    {
        return DB::table('invoice_items as ii')
            ->join('invoices as i', 'i.id', '=', 'ii.invoice_id')
            ->leftJoin('products as p', 'p.id', '=', 'ii.product_id')
            ->leftJoin('product_families as f', 'f.id', '=', 'p.family_id')
            ->where('i.company_id', currentCompany()->id)
            ->whereNotIn('i.status', ['brouillon', 'annulee'])
            ->where('i.issued_at', '>=', Carbon::now()->subMonths($months)->startOfMonth())
            ->selectRaw('COALESCE(f.name, "Autres") as famille, SUM(ii.line_total_ht) as ca')
            ->groupBy('famille')->orderByDesc('ca')->get();
    }

    /** Commandes par statut (label + count) — pour le donut. */
    public function ordersByStatus(): Collection
    {
        $labels = [
            'brouillon' => 'Brouillon', 'soumis' => 'À confirmer', 'confirme' => 'Confirmées',
            'en_preparation' => 'En préparation', 'partiellement_livre' => 'Partiellement livrées',
            'livre' => 'Livrées', 'facture' => 'Facturées', 'annule' => 'Annulées',
        ];
        $rows = DB::table('orders')->where('company_id', currentCompany()->id)
            ->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status');
        $out = collect();
        foreach ($labels as $k => $lab) {
            if (($rows[$k] ?? 0) > 0) $out->push(['label' => $lab, 'count' => (int) $rows[$k]]);
        }
        return $out;
    }

    /** Commandes à livrer réparties par échéance. */
    public function deliveriesByBucket(): array
    {
        $orders = DB::table('orders')->where('company_id', currentCompany()->id)
            ->whereIn('status', ['confirme', 'en_preparation', 'partiellement_livre'])
            ->pluck('delivery_date');
        $b = ['en_retard' => 0, 'aujourd_hui' => 0, 'cette_semaine' => 0, 'semaine_prochaine' => 0, 'plus_tard' => 0];
        $today = Carbon::today();
        foreach ($orders as $dd) {
            if (! $dd) { $b['plus_tard']++; continue; }
            $d = Carbon::parse($dd);
            if ($d->lt($today)) $b['en_retard']++;
            elseif ($d->isSameDay($today)) $b['aujourd_hui']++;
            elseif ($d->lte($today->copy()->endOfWeek())) $b['cette_semaine']++;
            elseif ($d->lte($today->copy()->addWeek()->endOfWeek())) $b['semaine_prochaine']++;
            else $b['plus_tard']++;
        }
        return $b;
    }

    /** Compteurs d'activités commerciales (CRM) sur N jours. */
    public function salesActivities(int $days = 30): array
    {
        $co = currentCompany()->id;
        $rows = DB::table('crm_activities')->where('company_id', $co)
            ->where('created_at', '>=', Carbon::now()->subDays($days))
            ->selectRaw('type, COUNT(*) c')->groupBy('type')->pluck('c', 'type');

        return [
            'visites' => (int) ($rows['visite'] ?? 0),
            'appels'  => (int) ($rows['appel'] ?? 0),
            'emails'  => (int) ($rows['email'] ?? 0),
            'rdv'     => (int) ($rows['rendez_vous'] ?? $rows['reunion'] ?? 0),
            'taches'  => (int) DB::table('crm_activities')->where('company_id', $co)->where('is_done', false)->count(),
        ];
    }

    /** Alertes commerciales : commandes en retard, factures impayées, stock faible. */
    public function salesAlerts(): array
    {
        $co = currentCompany()->id;
        return [
            'orders_late' => (int) DB::table('orders')->where('company_id', $co)
                ->whereIn('status', ['confirme', 'en_preparation', 'partiellement_livre'])
                ->whereNotNull('delivery_date')->whereDate('delivery_date', '<', Carbon::today())->count(),
            'invoices_unpaid' => (int) DB::table('invoices')->where('company_id', $co)
                ->whereNotIn('status', ['brouillon', 'annulee', 'payee'])->count(),
            'low_stock' => (int) DB::table('product_stocks')
                ->join('products', 'products.id', '=', 'product_stocks.product_id')
                ->join('warehouses', 'warehouses.id', '=', 'product_stocks.warehouse_id')
                ->where('warehouses.company_id', $co)
                ->where('products.stock_min', '>', 0)
                ->whereColumn('product_stocks.quantity', '<=', 'products.stock_min')->count(),
        ];
    }

    /** Valeur des commandes de l'année (TTC), hors brouillon/annulé. */
    public function ordersValueYear(): float
    {
        return (float) DB::table('orders')->where('company_id', currentCompany()->id)
            ->whereNotIn('status', ['brouillon', 'annule'])
            ->whereYear('created_at', now()->year)->sum('total_ttc');
    }

    /** Évolution CA HT sur N mois avec comparaison année précédente (barres). */
    public function caMonthlyComparison(int $months = 12): array
    {
        $from     = now()->subMonths($months - 1)->startOfMonth();
        $prevFrom = (clone $from)->subYear();
        $q = fn ($start, $end = null) => DB::table('invoices')->where('company_id', currentCompany()->id)->whereNull('deleted_at')
            ->whereNotIn('status', ['brouillon', 'annulee'])->where('type', '!=', 'avoir')
            ->where('issued_at', '>=', $start)->when($end, fn ($x) => $x->where('issued_at', '<', $end))
            ->selectRaw('DATE_FORMAT(issued_at, "%Y-%m") m, SUM(subtotal_ht) ht')->groupBy('m')->pluck('ht', 'm');
        $cur  = $q($from);
        $prev = $q($prevFrom, $from);

        $labels = $current = $previous = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $d = now()->startOfMonth()->subMonths($i);
            $labels[]   = $d->translatedFormat('M');
            $current[]  = (int) ($cur[$d->format('Y-m')] ?? 0);
            $previous[] = (int) ($prev[$d->copy()->subYear()->format('Y-m')] ?? 0);
        }
        return ['labels' => $labels, 'current' => $current, 'previous' => $previous];
    }

    /** Marge brute annuelle (CA HT − coût de revient des lignes facturées). */
    public function grossMargin(): array
    {
        $r = DB::table('invoice_items as ii')->join('invoices as i', 'i.id', '=', 'ii.invoice_id')
            ->where('i.company_id', currentCompany()->id)->whereNotIn('i.status', ['brouillon', 'annulee'])
            ->where('i.issued_at', '>=', Carbon::now()->startOfYear())
            ->selectRaw('SUM(ii.line_total_ht) ca, SUM(ii.unit_cost * ii.quantity) cost')->first();
        $ca = (float) ($r->ca ?? 0);
        $marge = $ca - (float) ($r->cost ?? 0);
        return ['ca' => $ca, 'marge' => $marge, 'taux' => $ca > 0 ? round($marge / $ca * 100, 2) : 0];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // [VEN Marge] Ventilation de la marge brute par commercial et par site/dépôt.
    // CA HT − coût de revient (unit_cost × quantité) des lignes facturées.
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Marge brute par commercial (sales_rep_id) sur N mois.
     * Retour : lignes {rep_id, rep_name, invoices, ca, cost, marge, taux}, triées par marge décroissante.
     */
    public function marginBySalesRep(int $months = 12): Collection
    {
        $from = Carbon::now()->subMonths($months)->startOfMonth();

        return DB::table('invoice_items as ii')
            ->join('invoices as i', 'i.id', '=', 'ii.invoice_id')
            ->leftJoin('users as u', 'u.id', '=', 'i.sales_rep_id')
            ->where('i.company_id', currentCompany()->id)
            ->whereNotIn('i.status', ['brouillon', 'annulee'])
            ->where('i.type', '!=', 'avoir')
            ->where('i.issued_at', '>=', $from)
            ->selectRaw('
                i.sales_rep_id                          as rep_id,
                COALESCE(u.name, "— Non affecté")        as rep_name,
                COUNT(DISTINCT i.id)                     as invoices,
                SUM(ii.line_total_ht)                    as ca,
                SUM(ii.unit_cost * ii.quantity)          as cost
            ')
            ->groupBy('i.sales_rep_id', 'u.name')
            ->get()
            ->map(fn ($r) => $this->decorateMarginRow($r))
            ->sortByDesc('marge')
            ->values();
    }

    /**
     * Marge brute par site / dépôt (warehouse_id) sur N mois.
     * Retour : lignes {site_id, site_name, invoices, ca, cost, marge, taux}, triées par marge décroissante.
     */
    public function marginBySite(int $months = 12): Collection
    {
        $from = Carbon::now()->subMonths($months)->startOfMonth();

        return DB::table('invoice_items as ii')
            ->join('invoices as i', 'i.id', '=', 'ii.invoice_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 'i.warehouse_id')
            ->where('i.company_id', currentCompany()->id)
            ->whereNotIn('i.status', ['brouillon', 'annulee'])
            ->where('i.type', '!=', 'avoir')
            ->where('i.issued_at', '>=', $from)
            ->selectRaw('
                i.warehouse_id                          as site_id,
                COALESCE(w.name, "— Non affecté")        as site_name,
                COUNT(DISTINCT i.id)                     as invoices,
                SUM(ii.line_total_ht)                    as ca,
                SUM(ii.unit_cost * ii.quantity)          as cost
            ')
            ->groupBy('i.warehouse_id', 'w.name')
            ->get()
            ->map(fn ($r) => $this->decorateMarginRow($r))
            ->sortByDesc('marge')
            ->values();
    }

    /** Ajoute marge + taux à une ligne d'agrégat CA/coût. */
    private function decorateMarginRow(object $r): array
    {
        $ca   = (float) ($r->ca ?? 0);
        $cost = (float) ($r->cost ?? 0);
        $marge = $ca - $cost;

        return array_merge((array) $r, [
            'ca'    => $ca,
            'cost'  => $cost,
            'marge' => $marge,
            'taux'  => $ca > 0 ? round($marge / $ca * 100, 1) : 0.0,
        ]);
    }
}
