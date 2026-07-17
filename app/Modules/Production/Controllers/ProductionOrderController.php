<?php

namespace App\Modules\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Production\Models\BillOfMaterial;
use App\Models\Client;
use App\Modules\Production\Models\Coil;
use App\Models\Order;
use App\Models\Product;
use App\Modules\Production\Models\ProductionLine;
use App\Modules\Production\Models\ProductionOrder;
use App\Models\Unit;
use App\Models\User;
use App\Modules\Production\Services\ProductionService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductionOrderController extends Controller
{
    use \App\Http\Controllers\Concerns\UploadsDocuments;

    public function __construct(private ProductionService $service)
    {
        $this->middleware('permission:production.view')->only(['index', 'show']);
        $this->middleware('permission:production.create')->only(['create', 'store', 'edit', 'update']);
        $this->middleware('permission:production.delete')->only(['destroy']);
        $this->middleware('permission:production.launch')->only(['launch', 'start']);
        $this->middleware('permission:production.validate')->only(['finish']);
        $this->middleware('permission:production.cancel')->only(['cancel']);
        $this->middleware('permission:production.launch')->only(['suspend', 'resume']);
        $this->middleware('permission:production.approve_financial')->only(['authorizeFinance']);
        $this->middleware('permission:production.modify_launched')->only(['requestModification']);
        $this->middleware('permission:production.submit_validation')->only(['submitForValidation']);
        $this->middleware('permission:production.validate_chef')->only(['validateChefAtelier']);
        $this->middleware('permission:production.validate_responsable')->only(['validateResponsable']);
        $this->middleware('permission:production.modification.avis_chef')->only(['modificationChefAvis']);
        $this->middleware('permission:production.modification.avis_commercial')->only(['modificationCommercialAvis']);
        $this->middleware('permission:production.modification.avis_finance')->only(['modificationFinanceAvis']);
        $this->middleware('permission:production.modification.avis_dg')->only(['modificationDgApprove']);
        // Rejet possible à n'importe quelle étape — n'importe lequel des 4 acteurs.
        $this->middleware('permission:production.modification.avis_chef|production.modification.avis_commercial|production.modification.avis_finance|production.modification.avis_dg')
            ->only(['modificationReject']);
    }

    public function index(Request $request): View
    {
        // [X3] Vues rapides : en_retard / a_lancer / clotures
        $vue = $request->input('vue');

        $orders = ProductionOrder::with(['client:id,name,trade_name', 'product:id,name,reference', 'productionLine:id,name', 'order:id,number', 'responsible:id,name'])
            // [FIX MÉTRAGE] Mètres réellement produits (déclarations), pas les lignes
            // planifiées — vides pour les OF MTO générés depuis une commande.
            ->withSum('outputs as total_meters', 'total_meters')
            ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->input('client_id'), fn ($q, $v) => $q->where('client_id', $v))
            ->when($request->input('product_id'), fn ($q, $v) => $q->where('product_id', $v))
            ->when($request->input('production_line_id'), fn ($q, $v) => $q->where('production_line_id', $v))
            ->when($request->input('responsible_id'), fn ($q, $v) => $q->where('responsible_id', $v))
            ->when($request->input('priorite'), fn ($q, $v) => $q->where('priorite', $v))
            ->when($request->input('origin'), fn ($q, $v) => $q->where('origin', $v))
            ->when($request->input('commande'), fn ($q, $v) => $q->whereHas('order', fn ($s) => $s->where('number', 'like', "%$v%")))
            ->when($request->input('from'), fn ($q, $v) => $q->whereDate('date_fabrication_prevue', '>=', $v))
            ->when($request->input('to'), fn ($q, $v) => $q->whereDate('date_fabrication_prevue', '<=', $v))
            ->when($request->input('q'), fn ($q, $v) => $q->where('number', 'like', "%$v%"))
            ->when($vue === 'en_retard', fn ($q) => $q->enRetard())
            ->when($vue === 'a_lancer', fn ($q) => $q->aLancer())
            ->when($vue === 'clotures', fn ($q) => $q->whereIn('status', ['termine', 'annule']))
            ->orderByDesc('id')->paginate(25)->withQueryString();

        $stats = [
            'brouillon' => ProductionOrder::where('status', 'brouillon')->count(),
            'en_cours'  => ProductionOrder::whereIn('status', ['lance', 'en_cours'])->count(),
            'en_retard' => ProductionOrder::enRetard()->count(),
            'termine'   => ProductionOrder::where('status', 'termine')->count(),
            // [FIX KPI] Mètres réellement produits = déclarations (outputs), pas les
            // lignes planifiées — vides pour les OF MTO générés automatiquement.
            'metres'    => (float) \App\Modules\Production\Models\ProductionOutput::whereHas(
                            'productionOrder', fn ($q) => $q->where('status', 'termine')
                        )->sum('total_meters'),
        ];

        $clients      = Client::orderBy('name')->get(['id', 'name', 'trade_name']);
        $produits     = Product::where('is_manufacturable', true)->orderBy('name')->get(['id', 'name', 'reference']);
        $lignes       = ProductionLine::orderBy('name')->get(['id', 'name']);
        $responsables = User::whereIn('id', ProductionOrder::whereNotNull('responsible_id')->distinct()->pluck('responsible_id'))
            ->orderBy('name')->get(['id', 'name']);

        return view('production.orders.index', compact('orders', 'stats', 'clients', 'produits', 'lignes', 'responsables'));
    }

    public function create(Request $request): View
    {
        $order = new ProductionOrder();

        // Pré-remplissage depuis une commande de vente
        if ($srcId = $request->input('order_id')) {
            $src = Order::with('items')->find($srcId);
            if ($src) {
                $first                     = $src->items->first();
                $order->client_id          = $src->client_id;
                $order->order_id           = $src->id;
                $order->product_id         = $first?->product_id;
                $order->quantity_requested = (float) $src->items->sum('quantity');
            }
        }

        // [X3] Pré-remplit les dépôts / ligne / responsable par défaut (transparence : l'utilisateur
        // voit les valeurs qui seront enregistrées, modifiables avant validation).
        $this->service->fillDefaultsForForm($order);

        return view('production.orders.form', $this->formData($order));
    }

    public function store(Request $request): RedirectResponse
    {
        [$data, $lines] = $this->validateData($request);
        $order = $this->service->create($data, $lines);
        $this->uploadDocuments($order, $request);

        // [Audit création OF] « Enregistrer + soumettre » : envoie directement
        // l'OF dans le circuit de validation (brouillon → attente Chef Atelier).
        if ($request->boolean('save_and_submit')) {
            $this->service->submitForValidation($order->fresh());

            return redirect()->route('production.orders.show', $order)
                ->with('success', 'OF ' . $order->number . ' créé et soumis à la validation du Chef Atelier.');
        }

        return redirect()->route('production.orders.show', $order)->with('success', 'Ordre de fabrication créé : ' . $order->number);
    }

    public function show(ProductionOrder $order): View
    {
        $order->load([
            'client', 'order', 'product', 'billOfMaterial', 'productionLine.machine', 'responsible', 'lines.unit',
            'consumptions.coil.supplier', 'outputs.product', 'outputs.warehouse', 'wastes.machine', 'wastes.operator',
            'cost', 'qualityControls.controller', 'reservations.product', 'timeLogs.employee',
            'operations.workCenter', 'operations.operator', 'batches',
        ]);

        $consumedWeight = (float) $order->consumptions->whereNull('reversed_at')->sum('weight_consumed');
        $wasteWeight    = (float) $order->wastes->sum('weight');

        // [Cohérence KPI] Coût matière = bobines (consumptions) + composants BOM
        // sortis du stock à la déclaration (même formule que ProductionCostService).
        // Sans ça un OF consommant uniquement des composants affiche « 0 F ».
        // [FIX A1] Anti double comptage : les sorties backflush du MÊME produit
        // qu'une bobine consommée sont exclues du coût (le réel bobine prime).
        $coilProductIds = $order->consumptions->pluck('coil.product_id')->filter()->unique();
        $outputIds = $order->outputs->pluck('id');
        $componentMoves = $outputIds->isNotEmpty()
            ? \App\Models\StockMovement::with('product:id,name,unit_id', 'product.unit:id,name,abbreviation')
                ->where('type', 'sortie')
                ->where('reference_type', \App\Modules\Production\Models\ProductionOutput::class)
                ->whereIn('reference_id', $outputIds)
                ->when($coilProductIds->isNotEmpty(), fn ($q) => $q->whereNotIn('product_id', $coilProductIds))
                ->orderBy('id')->get()
            : collect();
        $componentCost = (float) $componentMoves->sum('total_cost');
        $componentQty  = (float) $componentMoves->sum('quantity');

        // Unité d'affichage des composants : celle de l'article si homogène, sinon « u ».
        $componentUnits = $componentMoves
            ->map(fn ($m) => $m->product?->unit?->abbreviation ?: $m->product?->unit?->name)
            ->filter()->unique()->values();
        $componentUnit = $componentUnits->count() === 1 ? $componentUnits->first() : 'u';

        // Rendement matière : bobines → (consommé − chutes) / consommé ; sinon
        // composants BOM → besoin théorique (BOM × qté produite) / consommé réel.
        $yield = $consumedWeight > 0
            ? round((($consumedWeight - $wasteWeight) / $consumedWeight) * 100, 1)
            : null;
        if ($yield === null && $componentQty > 0) {
            $order->loadMissing('billOfMaterial.lines');
            $theoretical = (float) ($order->billOfMaterial?->lines->sum('quantity_per_meter') ?? 0)
                * (float) $order->quantity_produced;
            $yield = $theoretical > 0 ? round(($theoretical / $componentQty) * 100, 1) : null;
        }

        $metrics = [
            'consumed_weight' => $consumedWeight,
            'consumed_cost'   => (float) $order->consumptions->whereNull('reversed_at')->sum('cost') + $componentCost,
            'component_qty'   => $componentQty,
            'component_unit'  => $componentUnit,
            'output_meters'   => (float) $order->outputs->sum('total_meters'),
            'output_qty'      => (float) $order->outputs->sum('quantity'),
            'waste_weight'    => $wasteWeight,
            'waste_value'     => (float) $order->wastes->sum('value'),
            'yield'           => $yield,
        ];

        // Données pour les formulaires d'exécution (OF en cours)
        $coils      = $order->isInProgress() ? Coil::where('status', '!=', 'epuisee')->orderBy('reference')->get() : collect();
        $machines   = $order->isInProgress() ? \App\Modules\Production\Models\ProductionMachine::where('is_active', true)->orderBy('name')->get() : collect();
        $employees  = in_array($order->status, ['lance', 'en_cours', 'termine'], true) ? \App\Models\Employee::orderBy('last_name')->get() : collect();
        $warehouses = $order->isInProgress() ? \App\Models\Warehouse::orderByDesc('is_default')->orderBy('name')->get() : collect();

        $workflow = app(\App\Modules\Production\Services\ProductionWorkflowService::class)->steps($order);
        $opProgress = app(\App\Modules\Production\Services\RoutingService::class)->progress($order);

        // [CDC §3] Rupture matière avant lancement → bloque le bouton « Lancer l'OF »
        // et propose une dérogation aux valideurs.
        $materialShortages = in_array($order->status, ['brouillon', 'matiere_allouee'], true)
            ? $this->service->materialShortages($order)
            : [];

        return view('production.orders.show', compact('order', 'metrics', 'componentMoves', 'coils', 'machines', 'employees', 'warehouses', 'workflow', 'opProgress', 'materialShortages'));
    }

    /**
     * Fiche OF téléchargeable en PDF (DomPDF).
     * Reprend les données clés de la fiche : entête, nomenclature, consommations
     * matière, déclarations PF, contrôle qualité et coût de revient.
     */
    public function pdf(ProductionOrder $order)
    {
        $order->load([
            'client', 'order', 'product.unit', 'product.articleAvarie.unit', 'product.articleChute.unit',
            'billOfMaterial.lines.product.unit', 'productionLine.machine', 'responsible', 'depotProduitFini',
            'lines.unit', 'consumptions.coil.product.unit',
            'outputs.product.unit', 'outputs.warehouse', 'wastes', 'cost', 'qualityControls.controller',
        ]);

        $consumedWeight = (float) $order->consumptions->whereNull('reversed_at')->sum('weight_consumed');
        $wasteWeight    = (float) $order->wastes->sum('weight');
        $metrics = [
            'consumed_weight' => $consumedWeight,
            'consumed_cost'   => (float) $order->consumptions->whereNull('reversed_at')->sum('cost'),
            'output_qty'      => (float) $order->outputs->sum('quantity'),
            'output_meters'   => (float) $order->outputs->sum('total_meters'),
            'waste_weight'    => $wasteWeight,
            'yield'           => $consumedWeight > 0
                ? round((($consumedWeight - $wasteWeight) / $consumedWeight) * 100, 1)
                : null,
        ];

        $pdf = Pdf::loadView('production.orders.pdf', [
            'order'   => $order,
            'metrics' => $metrics,
            'company' => currentCompany(),
        ])->setPaper('a4');

        return $pdf->download('OF_' . ($order->number ?? $order->id) . '.pdf');
    }

    public function edit(ProductionOrder $order): View
    {
        abort_unless($order->isEditable() || $order->isEditableViaModification(), 403, 'OF non modifiable.');
        // [X3] Onglets Allocation matière / Coûts / Traçabilité (lecture)
        $order->load([
            'lines',
            'reservations.product:id,name,reference', 'reservations.warehouse:id,code,name',
            'consumptions.coil:id,reference,lot_number,remaining_weight',
            'cost',
            'outputs' => fn ($q) => $q->latest('produced_at'),
            'batches',
            'timeLogs',
            'createdBy:id,name',
        ]);

        return view('production.orders.form', $this->formData($order));
    }

    public function update(Request $request, ProductionOrder $order): RedirectResponse
    {
        [$data, $lines] = $this->validateData($request);
        $this->service->update($order, $data, $lines);
        $this->uploadDocuments($order, $request);

        return redirect()->route('production.orders.show', $order)->with('success', 'OF mis à jour.');
    }

    public function destroy(ProductionOrder $order): RedirectResponse
    {
        if ($order->status !== 'brouillon') {
            return back()->with('error', 'Seul un OF en brouillon peut être supprimé.');
        }
        $order->delete();

        return redirect()->route('production.orders.index')->with('success', 'OF supprimé.');
    }

    public function launch(Request $request, ProductionOrder $order): RedirectResponse
    {
        // [CDC §3] Rupture matière = lancement bloqué. Dérogation possible via
        // « Lancer malgré rupture » — réservée aux valideurs (production.validate).
        $force     = $request->boolean('bypass_material') && $request->user()->can('production.validate');
        $shortages = $this->service->materialShortages($order);

        $this->service->launch($order, $force);

        $resp = back()->with('success', 'OF lancé.');
        if ($shortages && $force) {
            $msg = collect($shortages)->map(fn ($s) => sprintf(
                '%s (besoin %s / dispo %s)',
                $s['product'], number_format($s['need'], 0, ',', ' '), number_format($s['available'], 0, ',', ' ')
            ))->implode(' · ');
            $resp->with('warning', 'OF lancé en dérogation malgré rupture matière : ' . $msg);
        }

        return $resp;
    }

    public function allocateMaterial(ProductionOrder $order): RedirectResponse
    {
        $this->service->allocateMaterial($order);

        return back()->with('success', 'Matière allouée — OF prêt à lancer.');
    }

    public function start(ProductionOrder $order): RedirectResponse
    {
        $this->service->start($order);

        return back()->with('success', 'Production démarrée.');
    }

    public function partial(ProductionOrder $order): RedirectResponse
    {
        $this->service->markPartiallyDone($order);

        return back()->with('success', 'OF marqué terminé partiellement.');
    }

    public function finish(Request $request, ProductionOrder $order): RedirectResponse
    {
        // [Cohérence demande/production] Clôture avec écart produit < demandé →
        // réservée aux valideurs après confirmation explicite (confirm_shortfall).
        $force = $request->boolean('confirm_shortfall') && $request->user()->can('production.validate');
        $this->service->finish($order, $force);

        return back()->with('success', 'OF terminé.');
    }

    public function cancel(Request $request, ProductionOrder $order): RedirectResponse
    {
        $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        $this->service->cancel($order, $request->input('reason'));

        return back()->with('success', 'OF annulé.');
    }

    /** [X3] Suspension d'un OF lancé — bloque déclarations/consommations jusqu'à reprise. */
    public function suspend(Request $request, ProductionOrder $order): RedirectResponse
    {
        abort_unless($order->isSuspendable(), 422, 'Seul un OF lancé ou en cours peut être suspendu.');
        $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        $order->update([
            'suspended_from' => $order->status,
            'suspended_at'   => now(),
            'status'         => 'suspendu',
            'notes'          => trim(($order->notes ? $order->notes . "\n" : '')
                . '[Suspension ' . now()->format('d/m/Y H:i') . '] ' . ($request->input('reason') ?: 'sans motif')),
        ]);

        return back()->with('success', 'OF suspendu — reprise possible à tout moment.');
    }

    /** [X3] Reprise d'un OF suspendu — restaure le statut d'origine. */
    public function resume(ProductionOrder $order): RedirectResponse
    {
        abort_unless($order->status === 'suspendu', 422, 'Cet OF n\'est pas suspendu.');

        $order->update([
            'status'         => $order->suspended_from ?: 'lance',
            'suspended_from' => null,
            'suspended_at'   => null,
        ]);

        return back()->with('success', 'OF repris (' . $order->statusLabel() . ').');
    }

    /**
     * [§13.2 CDC] Validation financière DAF/DG avant lancement OF.
     * Client comptant < 100% | acompte < 70% | crédit → autorisation obligatoire.
     */
    public function authorizeFinance(Request $request, ProductionOrder $order): RedirectResponse
    {
        $request->validate([
            'financial_notes' => ['nullable', 'string', 'max:500'],
            'bypass'          => ['nullable', 'boolean'],
        ]);

        abort_if(
            in_array($order->financial_authorization, ['approved', 'bypassed'], true),
            422,
            'OF déjà autorisé financièrement.'
        );

        $order->update([
            'financial_authorization' => $request->boolean('bypass') ? 'bypassed' : 'approved',
            'financial_authorized_at' => now(),
            'financial_authorized_by' => auth()->id(),
            'financial_notes'         => $request->input('financial_notes') ?? 'Autorisation manuelle DAF/DG.',
        ]);

        return back()->with('success', 'Autorisation financière accordée. L\'OF peut être lancé.');
    }

    // ─── §13.3 CDC : validation 2-niveaux ────────────────────────────────────

    /** brouillon | matiere_allouee → attente_chef (soumission pour validation). */
    public function submitForValidation(ProductionOrder $order): RedirectResponse
    {
        $this->service->submitForValidation($order);

        return back()->with('success', 'OF soumis pour validation Chef Atelier.');
    }

    /** attente_chef → attente_responsable (validation Chef Atelier). */
    public function validateChefAtelier(ProductionOrder $order): RedirectResponse
    {
        $this->service->validateByChef($order);

        return back()->with('success', 'OF validé par le Chef Atelier — en attente Responsable Production.');
    }

    /** attente_responsable → matiere_allouee (validation Responsable Production, prêt à lancer). */
    public function validateResponsable(ProductionOrder $order): RedirectResponse
    {
        $this->service->validateByResponsable($order);

        return back()->with('success', 'OF validé par le Responsable Production — prêt à lancer.');
    }

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * [§13.10 CDC] Demande de modification d'un OF lancé — ouvre le circuit
     * 4 étapes : Chef Production → Commercial → Finance → DG.
     */
    public function requestModification(Request $request, ProductionOrder $order): RedirectResponse
    {
        $data = $request->validate(['modification_reason' => ['required', 'string', 'max:500']]);

        try {
            $this->service->requestModification($order, $data['modification_reason']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return back()->with('info', 'Demande de modification ouverte — avis Chef Production requis (étape 1/4).');
    }

    /** Étape 1/4 — avis Chef Production. */
    public function modificationChefAvis(Request $request, ProductionOrder $order): RedirectResponse
    {
        $data = $request->validate(['comment' => ['nullable', 'string', 'max:500']]);
        try {
            $this->service->giveModificationChefAvis($order, $data['comment'] ?? null);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }
        return back()->with('success', 'Avis Chef Production enregistré — avis Commercial requis (étape 2/4).');
    }

    /** Étape 2/4 — avis Commercial. */
    public function modificationCommercialAvis(Request $request, ProductionOrder $order): RedirectResponse
    {
        $data = $request->validate(['comment' => ['nullable', 'string', 'max:500']]);
        try {
            $this->service->giveModificationCommercialAvis($order, $data['comment'] ?? null);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }
        return back()->with('success', 'Avis Commercial enregistré — avis Finance requis (étape 3/4).');
    }

    /** Étape 3/4 — avis Finance. */
    public function modificationFinanceAvis(Request $request, ProductionOrder $order): RedirectResponse
    {
        $data = $request->validate(['comment' => ['nullable', 'string', 'max:500']]);
        try {
            $this->service->giveModificationFinanceAvis($order, $data['comment'] ?? null);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }
        return back()->with('success', 'Avis Finance enregistré — validation DG requise (étape 4/4).');
    }

    /** Étape 4/4 — validation finale DG. Débloque l'édition de l'OF. */
    public function modificationDgApprove(Request $request, ProductionOrder $order): RedirectResponse
    {
        $data = $request->validate(['comment' => ['nullable', 'string', 'max:500']]);
        try {
            $this->service->approveModificationByDg($order, $data['comment'] ?? null);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }
        return back()->with('success', 'Modification approuvée par le DG. L\'OF peut être modifié.');
    }

    /** Rejet à n'importe quelle étape en_attente. */
    public function modificationReject(Request $request, ProductionOrder $order): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);
        try {
            $this->service->rejectModification($order, $data['reason']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }
        return back()->with('success', 'Demande de modification rejetée.');
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function formData(ProductionOrder $order): array
    {
        return [
            'order'     => $order,
            'clients'   => Client::orderBy('name')->get(['id', 'name', 'trade_name']),
            // [FIX] « Article à lancer » = produit fini fabricable uniquement.
            // On inclut aussi l'article de l'OF en édition s'il ne l'est plus (sécurité).
            'products'  => Product::where('is_manufacturable', true)
                ->when($order->product_id, fn ($q) => $q->orWhere('id', $order->product_id))
                ->orderBy('name')->get(['id', 'name', 'reference']),
            'boms'      => BillOfMaterial::where('is_active', true)->orderBy('name')->get(['id', 'name', 'product_id']),
            'lines'     => ProductionLine::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'units'     => Unit::where('is_active', true)->orderBy('name')->get(['id', 'name', 'abbreviation']),
            'users'     => User::orderBy('name')->get(['id', 'name']),
            'salesOrders' => Order::orderByDesc('id')->limit(200)->get(['id', 'number']),
            'warehouses' => \App\Models\Warehouse::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'machines'  => \App\Modules\Production\Models\ProductionMachine::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'bomData'   => $this->bomData(),
            'byproducts' => $this->byproductsData(),
            // [X3] Panneau sélecteur gauche : OF récents
            'selectorOrders' => ProductionOrder::orderByDesc('id')->limit(30)->get(['id', 'number', 'status', 'site_production']),
        ];
    }

    /**
     * [X3 « Articles lancés »] Sous-produits (avarié / chute) déclarés en même temps
     * que l'article fabriqué. Map produit → { ref, name, avarie, chute } pour le
     * tableau réactif « Articles lancés » du formulaire.
     */
    private function byproductsData(): array
    {
        return Product::whereNotNull('article_avarie_id')->orWhereNotNull('article_chute_id')
            ->with(['articleAvarie:id,reference,name', 'articleChute:id,reference,name'])
            ->get(['id', 'reference', 'name', 'article_avarie_id', 'article_chute_id'])
            ->mapWithKeys(fn ($p) => [$p->id => [
                'ref'    => $p->reference,
                'name'   => $p->name,
                'avarie' => $p->articleAvarie ? ['ref' => $p->articleAvarie->reference, 'name' => $p->articleAvarie->name] : null,
                'chute'  => $p->articleChute ? ['ref' => $p->articleChute->reference, 'name' => $p->articleChute->name] : null,
            ]])->all();
    }

    /**
     * [Audit création OF] Payload JSON des nomenclatures actives : composants
     * (coef, unité, stock disponible, CMP) + gamme (opérations, temps standard).
     * Alimente les onglets Composants/Opérations et le prévisionnel LIVE du
     * formulaire — lecture seule, les flux réels restent au lancement/déclaration.
     */
    private function bomData(): array
    {
        $boms = BillOfMaterial::with(['lines.product.unit', 'routing.operations.workCenter'])
            ->where('is_active', true)->get();

        $productIds = $boms->flatMap(fn ($b) => $b->lines->pluck('product_id'))->filter()->unique();
        $stocks = \App\Models\ProductStock::whereIn('product_id', $productIds)
            ->selectRaw('product_id, SUM(quantity - reserved_quantity) as dispo, MAX(avg_cost) as cost')
            ->groupBy('product_id')->get()->keyBy('product_id');

        return $boms->mapWithKeys(fn ($b) => [$b->id => [
            'machine_time'   => (float) ($b->machine_time_per_unit ?? 0),
            'labor_per_unit' => (float) ($b->labor_per_unit ?? 0),
            'components'     => $b->lines->map(fn ($l) => [
                'name'  => $l->product?->name ?? 'Composant #' . $l->product_id,
                'unit'  => $l->product?->unit?->abbreviation ?? $l->product?->unit?->name ?? 'u',
                'coef'  => (float) $l->quantity_per_meter,
                'stock' => (float) ($stocks[$l->product_id]->dispo ?? 0),
                'cost'  => (float) ($stocks[$l->product_id]->cost ?? 0),
            ])->values()->all(),
            'routing' => $b->routing ? [
                'name' => $b->routing->name,
                'ops'  => $b->routing->operations->map(fn ($op) => [
                    'seq'    => $op->sequence,
                    'name'   => $op->name,
                    'center' => $op->workCenter?->name ?? '—',
                    'setup'  => (float) ($op->setup_minutes ?? 0),
                    'run'    => (float) ($op->run_minutes_per_unit ?? 0),
                    // Coût horaire du poste — repli MO du prévisionnel quand la
                    // BOM n'a pas de labor_per_unit (même cascade que le coût réel).
                    'rate'   => (float) ($op->workCenter?->cost_per_hour ?? 0),
                ])->values()->all(),
            ] : null,
        ]])->all();
    }

    /** @return array{0: array, 1: array} */
    private function validateData(Request $request): array
    {
        $validated = $request->validate([
            'client_id'          => ['nullable', 'integer', 'exists:clients,id'],
            'order_id'           => ['nullable', 'integer', 'exists:orders,id'],
            'product_id'         => ['nullable', 'integer', 'exists:products,id', new \App\Rules\ProductFlux('fabrique')],
            'bill_of_material_id'=> ['nullable', 'integer', 'exists:bills_of_materials,id'],
            'production_line_id' => ['nullable', 'integer', 'exists:production_lines,id'],
            'responsible_id'     => ['nullable', 'integer', 'exists:users,id'],
            'sheet_type'         => ['nullable', 'string', 'max:60'],
            'thickness'          => ['nullable', 'numeric', 'min:0'],
            'color'              => ['nullable', 'string', 'max:60'],
            'length'             => ['nullable', 'numeric', 'min:0'],
            'usable_width'       => ['nullable', 'numeric', 'min:0'],
            'quantity_requested' => ['nullable', 'numeric', 'min:0'],
            'notes'              => ['nullable', 'string', 'max:2000'],
            // [SAGE parité] en-tête + paramètres
            'site_planification'  => ['nullable', 'string', 'max:20'],
            'site_production'     => ['nullable', 'string', 'max:20'],
            'numero_optimisation' => ['nullable', 'string', 'max:30'],
            'prepa_fabrication'   => ['nullable', 'string', 'max:60'],
            'reference_of'        => ['nullable', 'string', 'max:60'],
            'designation'         => ['nullable', 'string', 'max:200'],
            'mode_lancement'      => ['nullable', 'string', 'max:30'],
            'priorite'            => ['nullable', 'string', 'max:20'],
            'date_fabrication_prevue' => ['nullable', 'date'],
            'date_lancement'      => ['nullable', 'date'],
            'heure_lancement'     => ['nullable', 'string', 'max:8'],
            'observation'         => ['nullable', 'string', 'max:500'],
            'rendement_standard'  => ['nullable', 'numeric', 'min:0', 'max:9.9999'],
            'taux_perte'          => ['nullable', 'numeric', 'min:0', 'max:9.9999'],
            'depot_produit_fini_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'depot_rebut_id'      => ['nullable', 'integer', 'exists:warehouses,id'],
            'controle_qualite_obligatoire' => ['nullable', 'boolean'],
            'lines'              => ['nullable', 'array'],
            'lines.*.length'     => ['nullable', 'numeric', 'min:0'],
            'lines.*.quantity'   => ['nullable', 'numeric', 'min:0'],
            'lines.*.unit_id'    => ['nullable', 'integer', 'exists:units,id'],
            'lines.*.label'      => ['nullable', 'string', 'max:120'],
            'documents'          => ['nullable', 'array'],
            'documents.*'        => ['file', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx', 'max:5120'],
            // [Audit création OF] entête étendue
            'of_type'            => ['nullable', 'in:standard,reprise,retouche,speciale_client'],
            'origin'             => ['nullable', 'in:manuel,commande_client,stock_minimum,mrp'],
            'atelier'            => ['nullable', 'string', 'max:60'],
            'bom_version'        => ['nullable', 'string', 'max:20'],
            'routing_version'    => ['nullable', 'string', 'max:20'],
            'depot_matiere_id'   => ['nullable', 'integer', 'exists:warehouses,id'],
            'depot_qualite_id'   => ['nullable', 'integer', 'exists:warehouses,id'],
            'responsable_atelier_id' => ['nullable', 'integer', 'exists:users,id'],
            'operateur_prevu_id' => ['nullable', 'integer', 'exists:users,id'],
            'date_debut_prevue'  => ['nullable', 'date'],
            'date_fin_prevue'    => ['nullable', 'date', 'after_or_equal:date_debut_prevue'],
            'heure_debut_prevue' => ['nullable', 'string', 'max:8'],
            'heure_fin_prevue'   => ['nullable', 'string', 'max:8'],
            'temps_reglage'      => ['nullable', 'numeric', 'min:0'],
            'equipe_prevue'      => ['nullable', 'string', 'max:60'],
            'nb_operateurs'      => ['nullable', 'integer', 'min:0', 'max:500'],
            'autoriser_cloture_partielle' => ['nullable', 'boolean'],
            'autoriser_depassement_qte'   => ['nullable', 'boolean'],
            // Caractéristiques tôle bac étendues
            'profil'             => ['nullable', 'string', 'max:40'],
            'largeur_totale'     => ['nullable', 'numeric', 'min:0'],
            'longueur_standard'  => ['nullable', 'numeric', 'min:0'],
            'unite_production'   => ['nullable', 'in:ML,M2,PIECE'],
            'poids_par_metre'    => ['nullable', 'numeric', 'min:0'],
            'poids_theorique'    => ['nullable', 'numeric', 'min:0'],
            'couleur_ral'        => ['nullable', 'string', 'max:20'],
            'revetement'         => ['nullable', 'string', 'max:60'],
            'tolerance_longueur' => ['nullable', 'numeric', 'min:0'],
            'tolerance_epaisseur'=> ['nullable', 'numeric', 'min:0'],
        ], [
            'product_id.required' => 'Aucun article à lancer — sélectionnez le produit fini à fabriquer.',
        ]);

        // [Audit création OF — contrôles bloquants]
        // 1. Un OF sans article à lancer est inutilisable (aucune entrée stock PF possible).
        if (empty($validated['product_id'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'product_id' => 'Aucun article à lancer — sélectionnez le produit fini à fabriquer.',
            ]);
        }
        // 2. Quantité totale à produire > 0 (champ direct OU somme des coupes).
        $totalQty = (float) ($validated['quantity_requested'] ?? 0)
            + collect($validated['lines'] ?? [])->sum(fn ($l) => (float) ($l['quantity'] ?? 0));
        if ($totalQty <= 0) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'quantity_requested' => 'Quantité demandée égale à 0 — saisissez une quantité ou des lignes de coupe.',
            ]);
        }

        // [FIX null-vs-défaut] quantity_requested vide = 0 (colonne NOT NULL) — un null
        // explicite provoquait « Column cannot be null » (500). recomputeQuantities()
        // la recalcule ensuite depuis les lignes si elles existent.
        if (array_key_exists('quantity_requested', $validated) && $validated['quantity_requested'] === null) {
            $validated['quantity_requested'] = 0;
        }

        // [FIX cohérence OF] La nomenclature sélectionnée doit appartenir à l'article
        // lancé — sinon l'allocation matière consommerait les composants d'une AUTRE
        // nomenclature (mauvaise matière pour le produit fabriqué).
        if (!empty($validated['bill_of_material_id']) && !empty($validated['product_id'])) {
            $bomProductId = \App\Modules\Production\Models\BillOfMaterial::whereKey($validated['bill_of_material_id'])
                ->value('product_id');
            if ($bomProductId !== null && (int) $bomProductId !== (int) $validated['product_id']) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'bill_of_material_id' => 'La nomenclature sélectionnée appartient à un autre article — choisissez une nomenclature de l\'article à lancer.',
                ]);
            }
        }

        $lines = $validated['lines'] ?? [];
        unset($validated['lines'], $validated['documents']);

        return [$validated, $lines];
    }
}
