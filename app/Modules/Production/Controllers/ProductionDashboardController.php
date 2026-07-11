<?php

namespace App\Modules\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Models\BonPreparation;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockMovement;
use App\Modules\Production\Models\Coil;
use App\Modules\Production\Models\MachineMaintenance;
use App\Modules\Production\Models\ProductionConsumption;
use App\Modules\Production\Models\ProductionCost;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Models\ProductionOutput;
use App\Modules\Production\Models\ProductionTimeLog;
use App\Modules\Production\Models\ProductionWaste;
use App\Modules\Quality\Models\QualityInspection;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProductionDashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:production.view');
    }

    public function index(Request $request): View
    {
        $from = $request->input('from', now()->startOfMonth()->format('Y-m-d'));
        $to   = $request->input('to', now()->format('Y-m-d'));
        $f    = Carbon::parse($from)->startOfDay();
        $t    = Carbon::parse($to)->endOfDay();

        $kpis = [
            'of_total'      => ProductionOrder::count(),
            'of_en_cours'   => ProductionOrder::whereIn('status', ['lance', 'en_cours'])->count(),
            'of_termine'    => ProductionOrder::where('status', 'termine')->whereBetween('finished_at', [$f, $t])->count(),
            'meters'        => (float) ProductionOutput::whereBetween('produced_at', [$f, $t])->sum('total_meters'),
            'material_cost' => (float) ProductionConsumption::whereBetween('consumed_at', [$f, $t])->sum('cost'),
            'waste_weight'  => (float) ProductionWaste::whereHas('productionOrder', fn ($q) => $q->whereBetween('updated_at', [$f, $t]))->sum('weight'),
            'coils_stock'   => (float) Coil::where('status', '!=', 'epuisee')->sum('remaining_weight'),
        ];

        // [§10] KPIs complémentaires
        $kpis['of_en_retard'] = ProductionOrder::whereIn('status', ['lance', 'en_cours'])
            ->whereHas('order', fn ($q) => $q->whereNotNull('delivery_date')->whereDate('delivery_date', '<', today()))
            ->count();
        $kpis['mp_critiques'] = Product::whereHas('family', fn ($q) => $q->whereIn('code', ['MP', 'BPRE', 'BGAL']))
            ->where('stock_min', '>', 0)
            ->whereRaw('(SELECT COALESCE(SUM(quantity - reserved_quantity), 0) FROM product_stocks WHERE product_stocks.product_id = products.id) < products.stock_min')
            ->count();
        $kpis['pf_disponibles'] = (float) ProductStock::whereHas('product.family', fn ($q) => $q->where('code', 'PF'))
            ->sum(DB::raw('quantity - reserved_quantity'));
        $kpis['meters_today'] = (float) ProductionOutput::whereDate('produced_at', today())->sum('total_meters');
        $kpis['avaries'] = (float) StockMovement::where('type', 'entree')
            ->whereHas('product.family', fn ($q) => $q->where('code', 'AVAR'))
            ->whereBetween('occurred_at', [$f, $t])->sum('quantity');
        $kpis['ventes_jour'] = (int) Invoice::whereDate('issued_at', today())
            ->where('status', '!=', 'annulee')->sum('total_ttc');

        // [X3 §4] KPIs complémentaires — parc machines, bobines, qualité, coûts
        $kpis['of_a_lancer'] = ProductionOrder::aLancer()->count();
        $kpis['tonnage'] = round(((float) ProductionOutput::whereBetween('produced_at', [$f, $t])
            ->join('production_orders as po2', 'po2.id', '=', 'production_outputs.production_order_id')
            ->selectRaw('COALESCE(SUM(production_outputs.total_meters * COALESCE(po2.poids_par_metre, 0)), 0) kg')
            ->value('kg')) / 1000, 2);
        $kpis['coils_dispo']     = Coil::where('status', 'disponible')->count();
        $kpis['coils_reservees'] = Coil::whereIn('status', ['reservee', 'en_production'])->count();
        $kpis['machines_dispo']  = \App\Modules\Production\Models\ProductionMachine::where('is_active', true)
            ->whereNotIn('status', ['en_panne', 'maintenance', 'indisponible', 'arretee'])->count();
        $kpis['machines_panne']  = \App\Modules\Production\Models\ProductionMachine::whereIn('status', ['en_panne', 'maintenance', 'indisponible', 'arretee'])->count();
        $kpis['nc_ouvertes']     = \App\Modules\Quality\Models\NonConformity::whereNotIn('status', ['cloturee', 'annulee', 'cloture'])->count();
        $kpis['cq_attente']      = QualityInspection::whereNotIn('status', ['conforme', 'non_conforme'])->count();
        $costPeriode             = ProductionCost::whereBetween('created_at', [$f, $t]);
        $kpis['cout_mo']         = (float) (clone $costPeriode)->sum('labor_cost');
        $kpis['cout_machine']    = (float) (clone $costPeriode)->sum('machine_cost');
        $kpis['marge_estimee']   = (float) (clone $costPeriode)->sum('margin');

        // Stock disponible par dépôt
        $stockParDepot = ProductStock::join('warehouses', 'warehouses.id', '=', 'product_stocks.warehouse_id')
            ->selectRaw('warehouses.name n, warehouses.type t, SUM(product_stocks.quantity - product_stocks.reserved_quantity) dispo')
            ->groupBy('warehouses.id', 'warehouses.name', 'warehouses.type')
            ->orderBy('warehouses.name')->get();

        // Rendement matière moyen sur la période (OF terminés)
        $consumed = (float) ProductionConsumption::whereBetween('consumed_at', [$f, $t])->sum('weight_consumed');
        $waste    = $kpis['waste_weight'];
        $kpis['yield'] = $consumed > 0 ? max(0, min(100, round((($consumed - $waste) / $consumed) * 100, 1))) : null;

        // Production par jour (mètres)
        $daily = ProductionOutput::whereBetween('produced_at', [$f, $t])
            ->selectRaw('DATE(produced_at) as d, SUM(total_meters) as m')
            ->groupByRaw('DATE(produced_at)')->orderByRaw('DATE(produced_at)')->get();
        $chartDaily = [
            'labels' => $daily->map(fn ($r) => Carbon::parse($r->d)->format('d/m'))->all(),
            'data'   => $daily->map(fn ($r) => round((float) $r->m, 2))->all(),
        ];

        // OF par statut
        $byStatus = ProductionOrder::selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status');

        // Top clients par mètres produits
        $topClients = ProductionOutput::whereBetween('produced_at', [$f, $t])
            ->join('production_orders', 'production_orders.id', '=', 'production_outputs.production_order_id')
            ->leftJoin('clients', 'clients.id', '=', 'production_orders.client_id')
            ->selectRaw('COALESCE(clients.name, "—") as client, SUM(production_outputs.total_meters) as m')
            ->groupByRaw('clients.name')->orderByDesc('m')->limit(8)->get();

        // Coût de revient moyen récent
        $avgCost = (float) ProductionCost::avg('cost_per_meter');

        // ── TRS — Taux de Rendement Synthétique (§16 CDC Production) ─────────
        // TRS = Disponibilité × Performance × Qualité
        // Disponibilité = (Temps théorique - Arrêts) / Temps théorique
        // Performance   = Mètres réels / Mètres théoriques (si défini)
        // Qualité       = (Mètres produits - Rebuts métriques) / Mètres produits
        $trs = $this->computeTrs($f, $t, $kpis);

        // ── Coût standard vs réel (§11 CDC) ──────────────────────────────────
        $coutComparaison = $this->computeCostComparison($f, $t);

        // [CDC §tôles-bac] Commandes tôles bac autorisées vs en attente d'autorisation.
        // Tôles bac = articles de famille BPRE ou BGAL.
        // Une commande est autorisée si : client cash + BP créé, OU client crédit + BP créé.
        $toBacAutorisees = Order::whereIn('status', ['confirme', 'en_preparation'])
            ->whereHas('items.product.family', fn ($q) => $q->whereIn('code', ['BPRE', 'BGAL']))
            ->whereHas('bonPreparations', fn ($q) => $q->whereIn('status', ['en_attente', 'en_cours', 'charge']))
            ->with(['client:id,name,payment_mode', 'bonPreparations' => fn ($q) => $q->latest()->limit(1)])
            ->orderByDesc('id')->limit(10)->get();

        $toBacEnAttente = Order::whereIn('status', ['confirme', 'en_preparation'])
            ->whereHas('items.product.family', fn ($q) => $q->whereIn('code', ['BPRE', 'BGAL']))
            ->whereDoesntHave('bonPreparations', fn ($q) => $q->whereIn('status', ['en_attente', 'en_cours', 'charge']))
            ->with(['client:id,name,payment_mode'])
            ->orderByDesc('id')->limit(10)->get();

        // ── Écran SAGE : validations, sparkline, panneaux ─────────────────────
        $pendingCount = app(\App\Services\PendingValidationsService::class)->for($request->user())->count();

        $prod7Raw = ProductionOutput::where('produced_at', '>=', today()->subDays(6)->startOfDay())
            ->selectRaw('DATE(produced_at) d, SUM(total_meters) m')
            ->groupByRaw('DATE(produced_at)')->pluck('m', 'd');
        $prod7Days = collect(range(6, 0))
            ->map(fn ($i) => round((float) ($prod7Raw[today()->subDays($i)->format('Y-m-d')] ?? 0), 1))->all();

        $kpis['rebut_pct'] = $consumed > 0 ? min(100, round(($waste / $consumed) * 100, 2)) : null;

        $inspPeriode = QualityInspection::whereBetween('inspected_at', [$f, $t]);
        $inspTotal   = (clone $inspPeriode)->count();
        if ($inspTotal === 0) {
            $inspTotal = QualityInspection::count();
            $inspConf  = QualityInspection::where('status', 'conforme')->count();
        } else {
            $inspConf  = (clone $inspPeriode)->where('status', 'conforme')->count();
        }
        $kpis['conformite'] = $inspTotal > 0 ? round(($inspConf / $inspTotal) * 100, 1) : null;

        $ofEnCours = ProductionOrder::with(['product:id,name,reference', 'productionLine:id,name'])
            ->orderByRaw("CASE status WHEN 'en_cours' THEN 1 WHEN 'lance' THEN 2 WHEN 'planifie' THEN 3 WHEN 'valide' THEN 4 WHEN 'brouillon' THEN 5 WHEN 'termine' THEN 6 ELSE 7 END ASC")
            ->orderByDesc('id')->limit(7)->get();

        $suiviJour = ProductionOutput::with(['productionOrder:id,number', 'product:id,name'])
            ->orderByDesc('produced_at')->orderByDesc('id')->limit(5)->get();
        $suiviUsers = \App\Models\User::whereIn('id', $suiviJour->pluck('created_by')->filter())->pluck('name', 'id');

        // Alertes production
        $alertes = collect();
        MachineMaintenance::with('machine:id,code,name')
            ->whereNotIn('status', ['termine', 'cloture', 'annule'])
            ->orderByDesc('started_at')->limit(2)->get()
            ->each(fn ($m) => $alertes->push([
                'niveau' => 'rouge',
                'titre'  => 'Arrêt machine ' . ($m->machine?->code ?? '—'),
                'detail' => $m->title,
                'heure'  => ($m->started_at ?? $m->planned_at)?->format('H:i'),
            ]));
        Product::whereHas('family', fn ($q) => $q->whereIn('code', ['MP', 'BPRE', 'BGAL']))
            ->where('stock_min', '>', 0)
            ->whereRaw('(SELECT COALESCE(SUM(quantity - reserved_quantity), 0) FROM product_stocks WHERE product_stocks.product_id = products.id) < products.stock_min')
            ->limit(2)->get(['id', 'name', 'stock_min'])
            ->each(fn ($p) => $alertes->push([
                'niveau' => 'orange',
                'titre'  => 'Stock matière faible',
                'detail' => $p->name . ' — seuil : ' . number_format((float) $p->stock_min, 0, ',', ' '),
                'heure'  => null,
            ]));
        ProductionOrder::whereIn('status', ['lance', 'en_cours'])
            ->whereHas('order', fn ($q) => $q->whereNotNull('delivery_date')->whereDate('delivery_date', '<', today()))
            ->limit(2)->get(['id', 'number'])
            ->each(fn ($of) => $alertes->push([
                'niveau' => 'rouge',
                'titre'  => 'Retard OF ' . $of->number,
                'detail' => 'Date de livraison dépassée',
                'heure'  => null,
            ]));
        if (($kpis['rebut_pct'] ?? 0) > 3) {
            $alertes->push([
                'niveau' => 'orange',
                'titre'  => 'Taux de rebut élevé',
                'detail' => $kpis['rebut_pct'] . ' % (seuil 3 %)',
                'heure'  => null,
            ]);
        }

        // Consommation matières (MP + consommables)
        $mpIds = Product::whereHas('family', fn ($q) => $q->whereIn('code', ['MP', 'CONS']))->pluck('id');
        $stockDispoMp = ProductStock::whereIn('product_id', $mpIds)
            ->selectRaw('product_id, SUM(quantity - reserved_quantity) q')->groupBy('product_id')->pluck('q', 'product_id');
        $consoJour = StockMovement::where('type', 'sortie')->whereIn('product_id', $mpIds)
            ->whereDate('occurred_at', today())
            ->selectRaw('product_id, SUM(quantity) q')->groupBy('product_id')->pluck('q', 'product_id');
        $consoMois = StockMovement::where('type', 'sortie')->whereIn('product_id', $mpIds)
            ->whereBetween('occurred_at', [now()->startOfMonth(), now()])
            ->selectRaw('product_id, SUM(quantity) q')->groupBy('product_id')->pluck('q', 'product_id');
        $consoMatieres = Product::with('unit:id,abbreviation')->whereIn('id', $mpIds)
            ->orderBy('name')->limit(4)->get(['id', 'name', 'unit_id'])
            ->map(fn ($p) => [
                'name'  => $p->name,
                'stock' => (float) ($stockDispoMp[$p->id] ?? 0),
                'jour'  => (float) ($consoJour[$p->id] ?? 0),
                'mois'  => (float) ($consoMois[$p->id] ?? 0),
                'unite' => $p->unit?->abbreviation ?? '—',
            ]);

        // Performances machines (disponibilité mois courant)
        $moisDebut     = now()->startOfMonth();
        $joursMois     = max(1, $moisDebut->diffInDays(now()) + 1);
        $downByMachine = MachineMaintenance::whereBetween('started_at', [$moisDebut, now()])
            ->selectRaw('machine_id, SUM(downtime_minutes) dm')->groupBy('machine_id')->pluck('dm', 'machine_id');
        $perfMachines = \App\Modules\Production\Models\ProductionMachine::where('is_active', true)
            ->orderBy('code')->limit(5)->get(['id', 'code', 'name', 'status'])
            ->map(function ($m) use ($downByMachine, $joursMois) {
                $dm    = (float) ($downByMachine[$m->id] ?? 0);
                $dispo = max(0, min(100, round((1 - $dm / ($joursMois * 8 * 60)) * 100, 1)));
                return [
                    'code'   => $m->code,
                    'name'   => $m->name,
                    'dispo'  => $dispo,
                    'arrets' => round($dm / 60, 1),
                    'statut' => $m->status,
                ];
            });

        $controlesQualite = QualityInspection::with(['productionOrder:id,number', 'controller'])
            ->orderByDesc('inspected_at')->limit(5)->get();

        // [X3 §4] Chaîne de production visuelle — comptages par étape du flux
        $chaine = [
            ['label' => 'Commande client',    'count' => Order::whereIn('status', ['confirme', 'en_preparation'])->count(),                     'url' => route('ventes.commandes.index'),        'color' => 'blue'],
            ['label' => 'OF à lancer',        'count' => $kpis['of_a_lancer'],                                                                   'url' => route('production.orders.index', ['vue' => 'a_lancer']), 'color' => 'amber'],
            ['label' => 'Réservation matière','count' => \App\Models\StockReservation::where('status', 'active')->whereNotNull('production_order_id')->count(), 'url' => route('production.orders.index'), 'color' => 'amber'],
            ['label' => 'Production',         'count' => $kpis['of_en_cours'],                                                                   'url' => route('production.orders.index', ['status' => 'en_cours']), 'color' => 'emerald'],
            ['label' => 'Contrôle qualité',   'count' => $kpis['cq_attente'],                                                                    'url' => route('qualite.inspections.index'),     'color' => 'purple'],
            ['label' => 'Stock PF',           'count' => (int) $kpis['pf_disponibles'],                                                          'url' => route('stocks.index'),                  'color' => 'teal'],
            ['label' => 'Livraison',          'count' => \App\Models\DeliveryNote::whereIn('status', ['en_attente_validation', 'brouillon'])->count(), 'url' => route('ventes.bons-livraison.index'), 'color' => 'sky'],
            ['label' => 'Facture',            'count' => Invoice::whereIn('status', ['emise', 'partiellement_payee'])->count(),                  'url' => route('ventes.factures.index'),         'color' => 'indigo'],
            ['label' => 'Encaissement',       'count' => (int) \App\Models\ClientPayment::whereBetween('payment_date', [$f, $t])->count(),      'url' => route('tresorerie.encaissements.index'), 'color' => 'emerald'],
        ];

        return view('production.dashboard', compact(
            'kpis', 'chartDaily', 'byStatus', 'topClients', 'avgCost',
            'stockParDepot', 'from', 'to', 'trs', 'coutComparaison',
            'toBacAutorisees', 'toBacEnAttente',
            'pendingCount', 'prod7Days', 'ofEnCours', 'suiviJour', 'suiviUsers',
            'alertes', 'consoMatieres', 'perfMachines', 'controlesQualite', 'chaine'
        ));
    }

    /**
     * TRS = D × P × Q (§16 CDC — indicateur industrie).
     * Disponibilité = 1 - (arrêts / temps théorique)
     * Performance   = mètres réels / (mètres théoriques selon gamme)
     * Qualité       = mètres bons / mètres produits
     */
    private function computeTrs(Carbon $f, Carbon $t, array $kpis): array
    {
        // Disponibilité : arrêts machine sur la période
        $downtimeMinutes = (float) MachineMaintenance::whereBetween('started_at', [$f, $t])
            ->where('status', 'cloture')
            ->sum('downtime_minutes');

        $nbJours = max(1, $f->diffInDays($t) + 1);
        // Hypothèse : 1 machine × 8h/jour (configurable) = temps théorique en minutes
        $nbMachines = \App\Modules\Production\Models\ProductionMachine::where('is_active', true)->count() ?: 1;
        $theoreticalMinutes = $nbJours * 8 * 60 * $nbMachines;

        $disponibilite = $theoreticalMinutes > 0
            ? max(0, min(100, round((1 - $downtimeMinutes / $theoreticalMinutes) * 100, 1)))
            : 100.0;

        // Performance : mètres réels vs heures ouvrées × cadence théorique
        // On utilise le rendement matière comme proxy de performance
        $metersReal = $kpis['meters'] ?? 0;
        // Cadence standard = mètres théoriques basés sur les OF terminés avec quantité planifiée
        $metersPlanned = (float) ProductionOrder::where('status', 'termine')
            ->whereBetween('finished_at', [$f, $t])
            ->sum('quantity_requested');
        $performance = $metersPlanned > 0
            ? max(0, min(100, round(($metersReal / $metersPlanned) * 100, 1)))
            : ($metersReal > 0 ? 85.0 : 0.0); // fallback estimation si pas de quantité planifiée

        // Qualité : (mètres produits - rebuts en kg / densité) / mètres produits
        $wasteKg   = $kpis['waste_weight'] ?? 0;
        $metersTotal = $metersReal;
        // Estimation rebuts en mètres : 1 m de tôle bac ≈ poids_m = epaisseur × largeur × densité
        // On utilise le ratio rebuts/consommation comme proxy qualité
        $consumed = (float) ProductionConsumption::whereBetween('consumed_at', [$f, $t])->sum('weight_consumed');
        $qualite  = $consumed > 0
            ? max(0, min(100, round((1 - $wasteKg / max(1, $consumed)) * 100, 1)))
            : ($metersTotal > 0 ? 95.0 : 0.0);

        $trsValue = round($disponibilite * $performance * $qualite / 10000, 1);

        return [
            'trs'            => $trsValue,
            'disponibilite'  => $disponibilite,
            'performance'    => $performance,
            'qualite'        => $qualite,
            'downtime_h'     => round($downtimeMinutes / 60, 1),
            'theoretical_h'  => round($theoreticalMinutes / 60, 0),
        ];
    }

    /**
     * Comparaison coût standard vs coût réel par produit (§11 CDC).
     */
    private function computeCostComparison(Carbon $f, Carbon $t): \Illuminate\Support\Collection
    {
        return ProductionCost::with('productionOrder.product')
            ->whereBetween('created_at', [$f, $t])
            ->get()
            ->groupBy('productionOrder.product.name')
            ->map(function ($costs, $productName) {
                $real     = $costs->avg('cost_per_meter') ?? 0;
                $standard = $costs->first()?->productionOrder?->product?->purchase_price ?? 0;
                return [
                    'product'   => $productName ?: 'Produit inconnu',
                    'cout_reel' => round($real, 0),
                    'cout_std'  => round($standard, 0),
                    'ecart'     => round($real - $standard, 0),
                    'ecart_pct' => $standard > 0 ? round((($real - $standard) / $standard) * 100, 1) : null,
                ];
            })->values()->take(10);
    }
}
