<?php

namespace App\Http\Controllers;

use App\Models\CashAccount;
use App\Models\ClientPayment;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Order;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Quote;
use App\Models\DeliveryNote;
use App\Models\StockMovement;
use App\Modules\Production\Models\MachineMaintenance;
use App\Modules\Production\Models\ProductionOrder;
use App\Modules\Production\Models\ProductionOutput;
use App\Modules\Production\Models\ProductionWaste;
use App\Modules\Quality\Models\NonConformity;
use App\Modules\Quality\Models\QualityInspection;
use App\Models\QualityCertificate;
use Illuminate\Support\Facades\DB;

/**
 * §3 CDC — Chaîne de valeur intégrée :
 * Achats → Planification → Production → Qualité → Stocks → Vente → Livraison
 *         → Facturation → Trésorerie → Comptabilité → Business Intelligence
 */
class ValueChainController extends Controller
{
    public function __construct()
    {
        // [SEC §15] Chaîne de valeur = vue direction — aligné sur la route.
        $this->middleware(['auth', 'verified', 'permission:direction.view']);
    }

    public function index()
    {
        $steps = $this->buildChain();

        // Flux financier global (mois courant)
        $fluxMois = $this->financialFlow();

        return view('value-chain.index', compact('steps', 'fluxMois'));
    }

    private function buildChain(): array
    {
        $today = now()->startOfMonth();
        $end   = now()->endOfMonth();

        // ── 1. ACHATS ─────────────────────────────────────────────────────────
        $daEnAttente   = PurchaseRequest::whereIn('status', ['soumise', 'en_attente'])->count();
        $bcEnCours     = PurchaseOrder::whereIn('status', ['confirme', 'en_cours'])->count();
        $receptionAttente = PurchaseOrder::where('status', 'confirme')
            ->whereDoesntHave('receptions')->count();

        // ── 2. PLANIFICATION ──────────────────────────────────────────────────
        $ofPlanifies   = ProductionOrder::whereIn('status', ['brouillon', 'valide'])->count();
        $ofLances      = ProductionOrder::whereIn('status', ['lance', 'en_cours'])->count();
        $ofRetard      = ProductionOrder::whereIn('status', ['lance', 'en_cours'])
            ->whereHas('order', fn ($q) => $q->whereNotNull('delivery_date')->whereDate('delivery_date', '<', today()))
            ->count();

        // ── 3. PRODUCTION ─────────────────────────────────────────────────────
        $ofTermine     = ProductionOrder::where('status', 'termine')->whereBetween('finished_at', [$today, $end])->count();
        $metersMonth   = (float) ProductionOutput::whereBetween('produced_at', [$today, $end])->sum('total_meters');
        $rebuts        = (float) ProductionWaste::whereHas('productionOrder', fn ($q) => $q->whereBetween('updated_at', [$today, $end]))->sum('weight');

        // ── 4. QUALITÉ ────────────────────────────────────────────────────────
        $ncOuvertes    = NonConformity::whereNotIn('status', ['cloturee', 'resolue'])->count();
        $certifMois    = QualityCertificate::whereBetween('date_certificat', [$today, $end])->count();
        $certifNC      = QualityCertificate::whereBetween('date_certificat', [$today, $end])->where('resultat', 'non_conforme')->count();

        // ── 5. STOCKS ─────────────────────────────────────────────────────────
        $ruptures      = Product::where('stock_min', '>', 0)
            ->whereRaw('(SELECT COALESCE(SUM(quantity - reserved_quantity), 0) FROM product_stocks WHERE product_stocks.product_id = products.id) < products.stock_min')
            ->count();
        $mvtsMois      = StockMovement::whereBetween('occurred_at', [$today, $end])->count();
        $valeurStock   = (int) DB::table('product_stocks')
            ->join('products', 'products.id', '=', 'product_stocks.product_id')
            ->sum(DB::raw('product_stocks.quantity * products.purchase_price'));

        // ── 6. VENTES ─────────────────────────────────────────────────────────
        $devisEnCours  = Quote::whereIn('status', ['brouillon', 'emis', 'envoye'])->count();
        $cmdEnCours    = Order::whereIn('status', ['confirme', 'en_preparation', 'en_cours'])->count();
        $caMois        = (int) Invoice::whereBetween('issued_at', [$today, $end])->where('status', '!=', 'annulee')->sum('total_ttc');

        // ── 7. LIVRAISON ──────────────────────────────────────────────────────
        $blAPreparer   = Order::where('status', 'confirme')
            ->whereDoesntHave('deliveryNotes', fn ($q) => $q->whereNotIn('status', ['annule']))
            ->count();
        $blEnCours     = DeliveryNote::whereIn('status', ['brouillon', 'en_preparation', 'valide'])->count();
        $blLivres      = DeliveryNote::where('status', 'livre')->whereBetween('updated_at', [$today, $end])->count();

        // ── 8. FACTURATION ────────────────────────────────────────────────────
        $facturaBrouil = Invoice::where('status', 'brouillon')->count();
        $factEnRetard  = Invoice::where('status', 'emise')->whereDate('due_at', '<', today())->count();
        $montantRetard = (int) Invoice::where('status', 'emise')->whereDate('due_at', '<', today())->sum('remaining_amount');

        // ── 9. TRÉSORERIE ─────────────────────────────────────────────────────
        $encaissements = (int) ClientPayment::where('status', 'confirme')->whereBetween('payment_date', [$today, $end])->sum('amount');
        $soldeTresorerie = (int) CashAccount::sum('current_balance');

        // ── 10. COMPTABILITÉ ──────────────────────────────────────────────────
        $ecrituresBrouil = JournalEntry::where('status', 'brouillon')->count();
        $ecrituresMois   = JournalEntry::whereBetween('entry_date', [$today, $end])->count();

        return [
            [
                'id'       => 'achats',
                'label'    => 'Achats',
                'icon'     => 'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z',
                'color'    => 'orange',
                'route'    => route('achats.dashboard'),
                'kpis'     => [
                    ['label' => 'DA en attente', 'value' => $daEnAttente, 'alert' => $daEnAttente > 5],
                    ['label' => 'BC en cours',   'value' => $bcEnCours,   'alert' => false],
                    ['label' => 'À réceptionner','value' => $receptionAttente, 'alert' => $receptionAttente > 0],
                ],
            ],
            [
                'id'       => 'planification',
                'label'    => 'Planification',
                'icon'     => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
                'color'    => 'violet',
                'route'    => route('production.planning'),
                'kpis'     => [
                    ['label' => 'OF planifiés',  'value' => $ofPlanifies,  'alert' => false],
                    ['label' => 'OF lancés',      'value' => $ofLances,     'alert' => false],
                    ['label' => 'OF en retard',   'value' => $ofRetard,     'alert' => $ofRetard > 0],
                ],
            ],
            [
                'id'       => 'production',
                'label'    => 'Production',
                'icon'     => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z',
                'color'    => 'sky',
                'route'    => route('production.orders.index'),
                'kpis'     => [
                    ['label' => 'OF terminés/mois', 'value' => $ofTermine,              'alert' => false],
                    ['label' => 'Mètres produits',   'value' => number_format($metersMonth, 0, ',', ' ').' m', 'alert' => false],
                    ['label' => 'Rebuts (kg)',        'value' => number_format($rebuts, 0, ',', ' '),           'alert' => $rebuts > 500],
                ],
            ],
            [
                'id'       => 'qualite',
                'label'    => 'Qualité',
                'icon'     => 'M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z',
                'color'    => 'lime',
                'route'    => route('qualite.inspections.index'),
                'kpis'     => [
                    ['label' => 'NC ouvertes',      'value' => $ncOuvertes, 'alert' => $ncOuvertes > 0],
                    ['label' => 'Certificats/mois', 'value' => $certifMois, 'alert' => false],
                    ['label' => 'Certificats NC',   'value' => $certifNC,   'alert' => $certifNC > 0],
                ],
            ],
            [
                'id'       => 'stocks',
                'label'    => 'Stocks',
                'icon'     => 'M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4',
                'color'    => 'teal',
                'route'    => route('stocks.dashboard'),
                'kpis'     => [
                    ['label' => 'Ruptures stock',  'value' => $ruptures,  'alert' => $ruptures > 0],
                    ['label' => 'Mvts du mois',    'value' => $mvtsMois,  'alert' => false],
                    ['label' => 'Valeur stock (F)', 'value' => number_format($valeurStock, 0, ',', ' '), 'alert' => false],
                ],
            ],
            [
                'id'       => 'ventes',
                'label'    => 'Ventes',
                'icon'     => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
                'color'    => 'green',
                'route'    => route('ventes.dashboard'),
                'kpis'     => [
                    ['label' => 'Devis en cours',   'value' => $devisEnCours, 'alert' => false],
                    ['label' => 'Commandes actives', 'value' => $cmdEnCours,  'alert' => false],
                    ['label' => 'CA du mois (F)',    'value' => number_format($caMois, 0, ',', ' '), 'alert' => false],
                ],
            ],
            [
                'id'       => 'livraison',
                'label'    => 'Livraison',
                'icon'     => 'M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0',
                'color'    => 'cyan',
                'route'    => route('ventes.bons-livraison.index'),
                'kpis'     => [
                    ['label' => 'BL à préparer',   'value' => $blAPreparer, 'alert' => $blAPreparer > 5],
                    ['label' => 'BL en cours',      'value' => $blEnCours,   'alert' => false],
                    ['label' => 'BL livrés/mois',   'value' => $blLivres,    'alert' => false],
                ],
            ],
            [
                'id'       => 'facturation',
                'label'    => 'Facturation',
                'icon'     => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
                'color'    => 'indigo',
                'route'    => route('ventes.factures.index'),
                'kpis'     => [
                    ['label' => 'Brouillons',       'value' => $facturaBrouil, 'alert' => $facturaBrouil > 0],
                    ['label' => 'En retard',         'value' => $factEnRetard,  'alert' => $factEnRetard > 0],
                    ['label' => 'Montant retard (F)','value' => number_format($montantRetard, 0, ',', ' '), 'alert' => $montantRetard > 0],
                ],
            ],
            [
                'id'       => 'tresorerie',
                'label'    => 'Trésorerie',
                'icon'     => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
                'color'    => 'amber',
                'route'    => route('tresorerie.dashboard'),
                'kpis'     => [
                    ['label' => 'Encaissements/mois', 'value' => number_format($encaissements, 0, ',', ' ').' F', 'alert' => false],
                    ['label' => 'Solde trésorerie (F)','value' => number_format($soldeTresorerie, 0, ',', ' '), 'alert' => $soldeTresorerie < 0],
                ],
            ],
            [
                'id'       => 'comptabilite',
                'label'    => 'Comptabilité',
                'icon'     => 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z',
                'color'    => 'rose',
                'route'    => route('comptabilite.dashboard'),
                'kpis'     => [
                    ['label' => 'Écritures brouillon', 'value' => $ecrituresBrouil, 'alert' => $ecrituresBrouil > 0],
                    ['label' => 'Écritures du mois',   'value' => $ecrituresMois,   'alert' => false],
                ],
            ],
            [
                'id'       => 'bi',
                'label'    => 'Business Intelligence',
                'icon'     => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
                'color'    => 'purple',
                'route'    => route('direction.dashboard'),
                'kpis'     => [
                    ['label' => 'CA mois (F)',       'value' => number_format($caMois, 0, ',', ' '), 'alert' => false],
                    ['label' => 'Position tréso (F)','value' => number_format($soldeTresorerie, 0, ',', ' '), 'alert' => $soldeTresorerie < 0],
                ],
            ],
        ];
    }

    private function financialFlow(): array
    {
        $today = now()->startOfMonth();
        $end   = now()->endOfMonth();

        return [
            'ca_mois'       => (int) Invoice::whereBetween('issued_at', [$today, $end])->where('status', '!=', 'annulee')->sum('total_ttc'),
            'encaisse'      => (int) ClientPayment::where('status', 'confirme')->whereBetween('payment_date', [$today, $end])->sum('amount'),
            'achats_mois'   => (int) \App\Models\SupplierInvoice::whereBetween('received_at', [$today, $end])->where('status', '!=', 'annulee')->sum('total_ttc'),
            'solde'         => (int) CashAccount::sum('current_balance'),
            'bc_en_cours_val' => (int) \App\Models\PurchaseOrder::whereIn('status', ['confirme', 'en_cours'])->sum('total_ttc'),
            'cmd_non_fact'  => (int) Order::where('status', 'confirme')->sum('total_ttc'),
        ];
    }
}
