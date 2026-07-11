<?php

namespace App\Http\Controllers\Purchases;

use App\Http\Controllers\Controller;
use App\Http\Requests\Purchase\StorePurchaseOrderRequest;
use App\Http\Requests\Purchase\UpdatePurchaseOrderRequest;
use App\Http\Traits\ManagesEditLock;
use App\Models\Company;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\PurchaseOrderService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class PurchaseOrderController extends Controller
{
    use \App\Http\Controllers\Concerns\UploadsDocuments;

    use ManagesEditLock;

    public function __construct(private PurchaseOrderService $service) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', PurchaseOrder::class);
        $filters = $request->only(['supplier_id', 'status', 'search']);
        $purchaseOrders = $this->service->search($filters, 15);
        $suppliers = Supplier::active()->orderBy('name')->get(['id', 'name']);

        // ── Totaux agrégés sur l'ensemble des filtres ──
        $company = currentCompany();
        $totalsQuery = PurchaseOrder::where('company_id', $company->id)
            ->when(!empty($filters['supplier_id']), fn($q) => $q->where('supplier_id', $filters['supplier_id']))
            ->when(!empty($filters['status']),      fn($q) => $q->where('status', $filters['status']))
            ->when(!empty($filters['search']),      fn($q) => $q->where(fn($sq) =>
                $sq->where('number', 'like', '%'.$filters['search'].'%')
                    ->orWhereHas('supplier', fn($s) => $s->where('name', 'like', '%'.$filters['search'].'%'))
            ));

        $summary = [
            'total_ttc'       => (int) $totalsQuery->sum('total_ttc'),
            'total_ht'        => (int) (clone $totalsQuery)->sum('subtotal_ht'),
            'count_confirmed' => (int) (clone $totalsQuery)->whereIn('status', ['confirme', 'envoye', 'partiellement_recu'])->count(),
            'count_received'  => (int) (clone $totalsQuery)->where('status', 'recu')->count(),
        ];

        return view('achats.commandes.index', compact('purchaseOrders', 'filters', 'suppliers', 'summary'));
    }

    public function create()
    {
        $this->authorize('create', PurchaseOrder::class);
        $suppliers = Supplier::active()->orderBy('name')->get(['id', 'name']);
        $products  = Product::active()->orderBy('name')->get(['id', 'name', 'reference', 'purchase_price']);
        $warehouses = \App\Models\Warehouse::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name', 'can_purchase']);
        $currencies = ['XOF', 'XAF', 'EUR', 'USD'];

        return view('achats.commandes.create', compact('suppliers', 'products', 'warehouses', 'currencies') + $this->maquetteFormData());
    }

    /** [Maquette Commande fournisseur] Données complémentaires du formulaire. */
    private function maquetteFormData(): array
    {
        return [
            'supplierContacts' => \App\Models\SupplierContact::orderBy('last_name')->get(['id', 'supplier_id', 'civility', 'first_name', 'last_name']),
            'buyers'           => \App\Models\User::orderBy('name')->get(['id', 'name']),
            'purchaseRequests' => \App\Models\PurchaseRequest::where('status', 'approuvee')->orderByDesc('id')->limit(100)->get(['id', 'number']),
        ];
    }

    /** [Maquette Commande fournisseur] Lignes d'une DA approuvée (JSON) pour « Ajouter depuis DA ». */
    public function prItems(\Illuminate\Http\Request $request)
    {
        $pr = \App\Models\PurchaseRequest::with('items.product')->findOrFail($request->integer('purchase_request_id'));

        return response()->json($pr->items->map(fn ($it) => [
            'product_id'  => $it->product_id,
            'description' => $it->description ?: $it->product?->name,
            'quantity'    => (float) $it->quantity,
            'unit_price'  => (float) ($it->estimated_price ?? $it->product?->purchase_price ?? 0),
        ])->values());
    }

    public function store(StorePurchaseOrderRequest $request)
    {
        $this->authorize('create', PurchaseOrder::class);
        $data = $request->validated();
        unset($data['documents']);

        $po = $this->service->create($data);
        $this->uploadDocuments($po, $request);

        return redirect()
            ->route('achats.commandes.show', $po)
            ->with('success', 'Commande achat ' . $po->number . ' créée avec succès.');
    }

    public function show(PurchaseOrder $commande)
    {
        $this->authorize('view', $commande);
        $purchaseOrder = $this->service->repository->findWithDetails($commande->id);

        return view('achats.commandes.show', compact('purchaseOrder'));
    }

    public function edit(PurchaseOrder $commande)
    {
        $this->authorize('update', $commande);

        // [CONCURRENCE] Acquisition du verrou d'édition
        $lock = $this->acquireLockOr($commande, 'achats.commandes.show', $commande);
        if ($lock instanceof \Illuminate\Http\RedirectResponse) return $lock;

        $purchaseOrder = $this->service->repository->findWithDetails($commande->id);
        $purchaseOrder->load('attachments');
        $suppliers     = Supplier::active()->orderBy('name')->get(['id', 'name']);
        $products      = Product::active()->orderBy('name')->get(['id', 'name', 'reference', 'purchase_price']);
        $warehouses    = \App\Models\Warehouse::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name', 'can_purchase']);
        $currencies    = ['XOF', 'XAF', 'EUR', 'USD'];
        $editLock      = $lock;

        return view('achats.commandes.edit', compact('purchaseOrder', 'suppliers', 'products', 'editLock', 'warehouses', 'currencies') + $this->maquetteFormData());
    }

    public function update(UpdatePurchaseOrderRequest $request, PurchaseOrder $commande)
    {
        $this->authorize('update', $commande);
        try {
            $data = $request->validated();
            unset($data['documents']);
            $this->service->update($commande, $data);
            $this->uploadDocuments($commande, $request);
            $this->releaseLock($commande); // [CONCURRENCE] Libère le verrou
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('achats.commandes.show', $commande)
            ->with('success', 'Commande achat mise à jour.');
    }

    public function destroy(PurchaseOrder $commande)
    {
        $this->authorize('delete', $commande);
        try {
            $this->service->delete($commande);
            return redirect()
                ->route('achats.commandes.index')
                ->with('success', 'Commande achat supprimée.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * [ACHATS-PRO] Duplique un bon de commande en brouillon.
     */
    public function duplicate(PurchaseOrder $commande)
    {
        $this->authorize('create', PurchaseOrder::class);
        $new = $this->service->duplicate($commande);
        return redirect()
            ->route('achats.commandes.show', $new)
            ->with('success', "Bon de commande dupliqué : {$new->number}. Statut : brouillon.");
    }

    /**
     * GET achats/commandes/{purchaseOrder}/pdf — download or stream PDF.
     */
    public function pdf(PurchaseOrder $commande, Request $request)
    {
        $this->authorize('view', $commande);
        try {
            $purchaseOrder = $this->service->repository->findWithDetails($commande->id);
            $settings      = currentCompany()?->documentSetting;

            $pdf = Pdf::loadView('achats.pdf.purchase-order', compact('purchaseOrder', 'settings'))
                ->setPaper(strtolower($settings?->page_size ?? 'a4'), $settings?->orientation ?? 'portrait');

            $filename = 'BC_' . str_replace(['/', '\\', ' '], '-', $purchaseOrder->number) . '.pdf';

            return $request->boolean('preview')
                ? $pdf->stream($filename)
                : $pdf->download($filename);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('PDF BC error', ['id' => $commande->id, 'error' => $e->getMessage()]);
            return back()->with('error', 'Impossible de générer le PDF : ' . $e->getMessage());
        }
    }

    /**
     * POST achats/commandes/{commande}/confirm
     * Confirm a draft purchase order (brouillon → confirme).
     */
    public function confirm(PurchaseOrder $commande)
    {
        $this->authorize('validate', $commande);
        try {
            // [ACHATS-PRO-APPROVAL] Bloque la confirmation logistique si l'approbation est requise et non donnée.
            app(\App\Services\PoApprovalService::class)->assertApprovedForConfirm($commande);

            $this->service->confirm($commande);
            return redirect()
                ->route('achats.commandes.show', $commande)
                ->with('success', 'Commande ' . $commande->number . ' confirmée.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * POST achats/commandes/{commande}/reception
     * Create a reception from all PO items.
     */
    public function createReception(PurchaseOrder $commande)
    {
        $this->authorize('update', $commande);
        try {
            $reception = $this->service->createReception($commande);
            return redirect()
                ->route('achats.receptions.show', $reception)
                ->with('success', 'Réception ' . $reception->number . ' créée. Veuillez confirmer les quantités reçues.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * POST achats/commandes/{commande}/facture
     * Create a supplier invoice from this PO.
     */
    public function createSupplierInvoice(PurchaseOrder $commande)
    {
        $this->authorize('update', $commande);
        try {
            $invoice = $this->service->createSupplierInvoice($commande);
            return redirect()
                ->route('achats.factures-fournisseurs.show', $invoice)
                ->with('success', 'Facture fournisseur ' . $invoice->number . ' créée avec succès.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
