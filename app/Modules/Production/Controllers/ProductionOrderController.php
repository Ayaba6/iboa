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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductionOrderController extends Controller
{
    public function __construct(private ProductionService $service)
    {
        $this->middleware('permission:production.view')->only(['index', 'show']);
        $this->middleware('permission:production.create')->only(['create', 'store', 'edit', 'update']);
        $this->middleware('permission:production.delete')->only(['destroy']);
        $this->middleware('permission:production.launch')->only(['launch', 'start']);
        $this->middleware('permission:production.validate')->only(['finish']);
        $this->middleware('permission:production.cancel')->only(['cancel']);
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
        $orders = ProductionOrder::with(['client', 'product', 'productionLine'])
            ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->input('client_id'), fn ($q, $v) => $q->where('client_id', $v))
            ->when($request->input('q'), fn ($q, $v) => $q->where('number', 'like', "%$v%"))
            ->orderByDesc('id')->paginate(25)->withQueryString();

        $stats = [
            'brouillon' => ProductionOrder::where('status', 'brouillon')->count(),
            'en_cours'  => ProductionOrder::whereIn('status', ['lance', 'en_cours'])->count(),
            'termine'   => ProductionOrder::where('status', 'termine')->count(),
            'metres'    => (float) \App\Modules\Production\Models\ProductionOrderLine::whereHas(
                            'productionOrder', fn ($q) => $q->where('status', 'termine')
                        )->sum('total_meters'),
        ];

        $clients = Client::orderBy('name')->get(['id', 'name', 'trade_name']);

        return view('production.orders.index', compact('orders', 'stats', 'clients'));
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

        return view('production.orders.form', $this->formData($order));
    }

    public function store(Request $request): RedirectResponse
    {
        [$data, $lines] = $this->validateData($request);
        $order = $this->service->create($data, $lines);
        $this->uploadDocuments($order, $request);

        return redirect()->route('production.orders.show', $order)->with('success', 'Ordre de fabrication créé : ' . $order->number);
    }

    /** Enregistre les pièces jointes (documents) de l'OF. */
    private function uploadDocuments(ProductionOrder $order, Request $request): void
    {
        foreach ((array) $request->file('documents', []) as $file) {
            $path = $file->store('attachments/productionorder/'.$order->id, 'local');
            $order->attachments()->create([
                'disk'        => 'local',
                'path'        => $path,
                'filename'    => $file->getClientOriginalName(),
                'mime_type'   => $file->getMimeType(),
                'size'        => $file->getSize(),
                'uploaded_by' => \Illuminate\Support\Facades\Auth::id(),
            ]);
        }
    }

    public function show(ProductionOrder $order): View
    {
        $order->load([
            'client', 'order', 'product', 'billOfMaterial', 'productionLine.machine', 'responsible', 'lines.unit',
            'consumptions.coil', 'outputs.product', 'outputs.warehouse', 'wastes.machine', 'wastes.operator',
            'cost', 'qualityControls.controller', 'reservations.product', 'timeLogs.employee',
            'operations.workCenter', 'operations.operator', 'batches',
        ]);

        $consumedWeight = (float) $order->consumptions->sum('weight_consumed');
        $wasteWeight    = (float) $order->wastes->sum('weight');

        // [Cohérence KPI] Coût matière = bobines (consumptions) + composants BOM
        // sortis du stock à la déclaration (même formule que ProductionCostService).
        // Sans ça un OF consommant uniquement des composants affiche « 0 F ».
        $outputIds = $order->outputs->pluck('id');
        $componentMoves = $outputIds->isNotEmpty()
            ? \App\Models\StockMovement::with('product:id,name,unit_id', 'product.unit:id,name,abbreviation')
                ->where('type', 'sortie')
                ->where('reference_type', \App\Modules\Production\Models\ProductionOutput::class)
                ->whereIn('reference_id', $outputIds)
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
            'consumed_cost'   => (float) $order->consumptions->sum('cost') + $componentCost,
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

    public function edit(ProductionOrder $order): View
    {
        abort_unless($order->isEditable() || $order->isEditableViaModification(), 403, 'OF non modifiable.');
        $order->load('lines');

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
            'products'  => Product::orderBy('name')->get(['id', 'name', 'reference']),
            'boms'      => BillOfMaterial::where('is_active', true)->orderBy('name')->get(['id', 'name', 'product_id']),
            'lines'     => ProductionLine::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'units'     => Unit::where('is_active', true)->orderBy('name')->get(['id', 'name', 'abbreviation']),
            'users'     => User::orderBy('name')->get(['id', 'name']),
            'salesOrders' => Order::orderByDesc('id')->limit(200)->get(['id', 'number']),
            'warehouses' => \App\Models\Warehouse::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'machines'  => \App\Modules\Production\Models\ProductionMachine::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
        ];
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
        ]);

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
