<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sale\StoreOrderRequest;
use App\Http\Requests\Sale\UpdateOrderRequest;
use App\Models\Client;
use App\Models\Company;
use App\Models\Order;
use App\Models\Product;
use App\Models\TaxRate;
use App\Services\BonPreparationService;
use App\Services\CommercialWorkflowService;
use App\Services\OrderService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    use \App\Http\Controllers\Concerns\UploadsDocuments;

    public function __construct(
        private OrderService                $service,
        private CommercialWorkflowService   $workflow,
        private BonPreparationService       $bonPrepService,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Order::class);
        $filters = $request->only(['client_id', 'status', 'search']);
        $orders  = $this->service->search($filters, 15);

        // ── Totaux agrégés sur l'ensemble des filtres ──
        $company = currentCompany();
        $totalsQuery = Order::where('company_id', $company->id)
            ->when(!empty($filters['client_id']), fn($q) => $q->where('client_id', $filters['client_id']))
            ->when(!empty($filters['status']),    fn($q) => $q->where('status', $filters['status']))
            ->when(!empty($filters['search']),    fn($q) => $q->where(fn($sq) =>
                $sq->where('number', 'like', '%'.$filters['search'].'%')
                    ->orWhereHas('client', fn($c) => $c->where('name', 'like', '%'.$filters['search'].'%'))
            ));

        $summary = [
            'total_ttc'         => (int) $totalsQuery->sum('total_ttc'),
            'total_ht'          => (int) (clone $totalsQuery)->sum('subtotal_ht'),
            'count_confirmed'   => (int) (clone $totalsQuery)->whereIn('status', ['confirme', 'en_preparation', 'partiellement_livre'])->count(),
            'count_delivered'   => (int) (clone $totalsQuery)->where('status', 'livre')->count(),
            'count_invoiced'    => (int) (clone $totalsQuery)->where('status', 'facture')->count(),
        ];

        return view('ventes.commandes.index', compact('orders', 'filters', 'summary'));
    }

    public function create(Request $request)
    {
        $this->authorize('create', Order::class);
        $clients          = Client::active()->orderBy('name')->get(['id', 'name', 'trade_name', 'is_tax_exempt']);
        $products         = Product::active()->sellable()->with(['taxRate:id,rate', 'family:id,name'])->withSum('productStocks as stock_qty', 'quantity')->orderBy('name')->get(['id', 'name', 'reference', 'barcode', 'sale_price', 'tax_rate_id', 'is_stockable', 'family_id']);
        $selectedClient   = $request->query('client_id');
        $clientExemptions = $clients->pluck('is_tax_exempt', 'id');
        $taxRatesVente    = TaxRate::where('type', 'tva')->where('is_active', true)->orderBy('rate')->get(['id', 'name', 'rate', 'is_default']);
        $warehouses       = \App\Models\Warehouse::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name', 'can_sale']);
        $currencies       = ['XOF', 'XAF', 'EUR', 'USD'];

        return view('ventes.commandes.create', compact('clients', 'products', 'selectedClient', 'clientExemptions', 'taxRatesVente', 'warehouses', 'currencies') + $this->maquetteFormData());
    }

    /** [Maquette Commande client] Données complémentaires du formulaire. */
    private function maquetteFormData(): array
    {
        return [
            'contacts'  => \App\Models\ClientContact::orderBy('last_name')->get(['id', 'client_id', 'civility', 'first_name', 'last_name']),
            'salesReps' => \App\Models\User::orderBy('name')->get(['id', 'name']),
            'quotes'    => \App\Models\Quote::orderByDesc('id')->limit(100)->get(['id', 'number', 'issued_at']),
        ];
    }

    /** [Maquette Commande client] Lignes d'un devis (JSON) pour « Ajouter depuis devis ». */
    public function quoteItems(Request $request)
    {
        $quote = \App\Models\Quote::with('items.product.taxRate')->findOrFail($request->integer('quote_id'));

        return response()->json($quote->items->map(fn ($it) => [
            'product_id'       => $it->product_id,
            'description'      => $it->description ?: $it->product?->name,
            'quantity'         => (float) $it->quantity,
            'unit_price'       => (float) $it->unit_price,
            'discount_percent' => (float) ($it->discount_percent ?? 0),
            'tax_rate_value'   => (float) ($it->tax_rate_value ?? $it->product?->taxRate?->rate ?? 0),
        ])->values());
    }

    public function store(StoreOrderRequest $request)
    {
        $this->authorize('create', Order::class);
        $data = $request->validated();
        unset($data['documents']);

        $order = $this->service->create($data);
        $this->uploadDocuments($order, $request);

        return redirect()
            ->route('ventes.commandes.show', $order)
            ->with('success', 'Commande ' . $order->number . ' créée avec succès.');
    }

    public function show(Order $commande)
    {
        $this->authorize('view', $commande);
        $order = $this->service->repository->findWithDetails($commande->id);

        $salesProd = app(\App\Modules\Production\Services\SalesProductionService::class);
        $productionSummary = $salesProd->summary($order);
        $stockAnalysis     = $salesProd->stockAnalysis($order);

        return view('ventes.commandes.show', compact('order', 'productionSummary', 'stockAnalysis'));
    }

    public function edit(Order $commande)
    {
        $this->authorize('update', $commande);
        $order            = $this->service->repository->findWithDetails($commande->id);
        $clients          = Client::active()->orderBy('name')->get(['id', 'name', 'trade_name', 'is_tax_exempt']);
        $products         = Product::active()->sellable()->with(['taxRate:id,rate', 'family:id,name'])->withSum('productStocks as stock_qty', 'quantity')->orderBy('name')->get(['id', 'name', 'reference', 'barcode', 'sale_price', 'tax_rate_id', 'is_stockable', 'family_id']);
        $clientExemptions = $clients->pluck('is_tax_exempt', 'id');
        $taxRatesVente    = TaxRate::where('type', 'tva')->where('is_active', true)->orderBy('rate')->get(['id', 'name', 'rate', 'is_default']);
        $warehouses       = \App\Models\Warehouse::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name', 'can_sale']);
        $currencies       = ['XOF', 'XAF', 'EUR', 'USD'];
        $order->load('attachments');

        return view('ventes.commandes.edit', compact('order', 'clients', 'products', 'clientExemptions', 'taxRatesVente', 'warehouses', 'currencies') + $this->maquetteFormData());
    }

    public function update(UpdateOrderRequest $request, Order $commande)
    {
        $this->authorize('update', $commande);
        $data = $request->validated();
        unset($data['documents']);

        $this->service->update($commande, $data);
        $this->uploadDocuments($commande, $request);

        return redirect()
            ->route('ventes.commandes.show', $commande)
            ->with('success', 'Commande mise à jour.');
    }

    public function destroy(Order $commande)
    {
        $this->authorize('delete', $commande);
        try {
            $this->service->delete($commande);
            return redirect()
                ->route('ventes.commandes.index')
                ->with('success', 'Commande supprimée.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /** POST ventes/commandes/{order}/confirm — confirm the order. */
    public function confirm(Order $commande)
    {
        $this->authorize('validate', $commande);
        try {
            $this->service->confirm($commande);
            return back()->with('success', 'Commande ' . $commande->number . ' confirmée.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /** POST ventes/commandes/{order}/cancel — cancel the order. */
    public function cancel(Order $commande)
    {
        $this->authorize('delete', $commande);
        try {
            $this->service->cancel($commande);
            return back()->with('success', 'Commande ' . $commande->number . ' annulée.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * POST ventes/commandes/{order}/invoice — create invoice from order.
     */
    public function createInvoice(Order $commande)
    {
        $this->authorize('update', $commande);
        try {
            $invoice = $this->service->createInvoice($commande);
            return redirect()
                ->route('ventes.factures.show', $invoice)
                ->with('success', 'Facture ' . $invoice->number . ' créée depuis la commande.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * POST ventes/commandes/{order}/delivery-note — create delivery note from order.
     */
    public function createDeliveryNote(Order $commande)
    {
        $this->authorize('update', $commande);

        // [CDC §13.7] Préparation → contrôle chargement → BL : tant que le bon
        // de préparation n'est pas « chargé », la livraison est verrouillée.
        if (! $commande->isReadyForDelivery()) {
            $bp = $commande->activeBonPreparation();
            return back()->with('error',
                "Bon de préparation {$bp->number} ({$bp->status_label}) : le chargement doit être terminé avant de créer le bon de livraison (§13.7)."
            );
        }

        try {
            $dn = $this->service->createDeliveryNote($commande);
            return redirect()
                ->route('ventes.bons-livraison.show', $dn)
                ->with('success', 'Bon de livraison ' . $dn->number . ' créé depuis la commande.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    // ── Workflow de validation interne ────────────────────────────────────────

    public function submit(Request $request, Order $commande)
    {
        $request->validate(['motif' => ['nullable', 'string', 'max:500']]);
        try {
            $this->workflow->submit($commande, $request->motif);
            return back()->with('success', "Commande {$commande->number} soumise à validation.");
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function validateInternal(Request $request, Order $commande)
    {
        $request->validate(['motif' => ['nullable', 'string', 'max:500']]);
        try {
            $this->workflow->validateOrder($commande, $request->motif);
            return back()->with('success', "Commande {$commande->number} validée.");
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (\RuntimeException $e) {
            return back()->withErrors(['commande' => $e->getMessage()])->with('error', $e->getMessage());
        }
    }

    public function rejectInternal(Request $request, Order $commande)
    {
        $request->validate(['motif' => ['required', 'string', 'min:5', 'max:500']],
            ['motif.required' => 'Le motif est obligatoire.']);
        try {
            $this->workflow->reject($commande, $request->motif);
            return back()->with('success', "Commande {$commande->number} refusée — retour en brouillon.");
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function cancelInternal(Request $request, Order $commande)
    {
        $request->validate(['motif' => ['required', 'string', 'min:5', 'max:500']],
            ['motif.required' => "Le motif d'annulation est obligatoire."]);
        try {
            $this->workflow->cancel($commande, $request->motif);
            return back()->with('success', "Commande {$commande->number} annulée.");
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * [CDC §réouverture] POST ventes/commandes/{order}/reopen
     * Réouvre une commande annulée — réservé aux responsables hiérarchiques.
     */
    public function reopen(Request $request, Order $commande)
    {
        $this->authorize('reopen', $commande);
        if ($commande->status !== 'annule') {
            return back()->with('error', 'Seules les commandes annulées peuvent être réouvertes.');
        }
        $commande->update(['status' => 'brouillon', 'rejection_reason' => null]);
        return back()->with('success', "Commande {$commande->number} réouverte — de retour en brouillon.");
    }

    /**
     * [CDC §cash] POST ventes/commandes/{order}/register-payment
     * Caissier enregistre le paiement comptant → crée le bon de préparation.
     */
    public function registerPayment(Request $request, Order $commande)
    {
        $request->validate([
            'payment_amount'    => ['required', 'integer', 'min:1'],
            'payment_reference' => ['nullable', 'string', 'max:100'],
        ]);

        $client = $commande->client ?? \App\Models\Client::find($commande->client_id);
        if (!$client || !$client->isCash()) {
            return back()->with('error', 'Cette action est réservée aux commandes de clients au comptant.');
        }
        if (!in_array($commande->status, ['confirme', 'en_preparation'])) {
            return back()->with('error', 'La commande doit être confirmée avant d\'enregistrer un paiement.');
        }
        if ($commande->hasBonPreparation()) {
            return back()->with('error', 'Un bon de préparation existe déjà pour cette commande.');
        }

        try {
            $bp = $this->bonPrepService->createForCashOrder(
                $commande,
                (int) $request->payment_amount,
                $request->payment_reference,
            );
            return redirect()
                ->route('ventes.bons-preparation.show', $bp)
                ->with('success', "Paiement enregistré — bon de préparation {$bp->number} créé.");
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
