<?php

namespace App\Http\Controllers\Purchases;

use App\Http\Controllers\Controller;
use App\Http\Requests\Purchase\StoreSupplierInvoiceRequest;
use App\Http\Requests\Purchase\UpdateSupplierInvoiceRequest;
use App\Models\CashAccount;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Services\SupplierInvoiceService;
use Illuminate\Http\Request;

class SupplierInvoiceController extends Controller
{
    public function __construct(private SupplierInvoiceService $service) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', SupplierInvoice::class);
        $filters  = $request->only(['supplier_id', 'status', 'overdue', 'search']);
        $invoices = $this->service->search($filters, 15);
        $suppliers = Supplier::active()->orderBy('name')->get(['id', 'name']);

        // ── Totaux agrégés sur l'ensemble des filtres ──
        $totalsQuery = SupplierInvoice::query()
            ->when(!empty($filters['supplier_id']), fn($q) => $q->where('supplier_id', $filters['supplier_id']))
            ->when(!empty($filters['status']),       fn($q) => $q->where('status', $filters['status']))
            ->when(!empty($filters['overdue']),      fn($q) => $q->where('due_at', '<', now()->toDateString())
                ->whereNotIn('status', ['payee', 'annulee']))
            ->when(!empty($filters['search']),       fn($q) => $q->where(fn($sq) =>
                $sq->where('number', 'like', '%'.$filters['search'].'%')
                    ->orWhereHas('supplier', fn($s) => $s->where('name', 'like', '%'.$filters['search'].'%'))
            ));

        $summary = [
            'total_ttc'       => (int) $totalsQuery->sum('total_ttc'),
            'total_remaining' => (int) (clone $totalsQuery)->sum('remaining_amount'),
            'count_overdue'   => (int) (clone $totalsQuery)->where('due_at', '<', now()->toDateString())
                                    ->whereNotIn('status', ['payee', 'annulee'])->count(),
            'count_paid'      => (int) (clone $totalsQuery)->where('status', 'payee')->count(),
        ];

        return view('achats.factures-fournisseurs.index', compact('invoices', 'filters', 'suppliers', 'summary'));
    }

    public function create()
    {
        $this->authorize('create', SupplierInvoice::class);
        $suppliers = Supplier::active()->orderBy('name')->get(['id', 'name']);
        $products  = Product::active()->orderBy('name')->get(['id', 'name', 'reference', 'purchase_price']);

        return view('achats.factures-fournisseurs.create', compact('suppliers', 'products') + $this->maquetteFormData());
    }

    /** [Maquette Facture fournisseur] Données complémentaires du formulaire. */
    private function maquetteFormData(): array
    {
        return [
            'supplierContacts' => \App\Models\SupplierContact::orderBy('last_name')->get(['id', 'supplier_id', 'civility', 'first_name', 'last_name']),
            'buyers'           => \App\Models\User::orderBy('name')->get(['id', 'name']),
            'purchaseOrders'   => \App\Models\PurchaseOrder::orderByDesc('id')->limit(100)->get(['id', 'number']),
            'receptions'       => \App\Models\Reception::orderByDesc('id')->limit(100)->get(['id', 'number']),
        ];
    }

    /** [Maquette Facture fournisseur] Lignes d'un BC (JSON) pour « Ajouter depuis BC ». */
    public function poItems(\Illuminate\Http\Request $request)
    {
        $po = \App\Models\PurchaseOrder::with('items.product')->findOrFail($request->integer('purchase_order_id'));

        return response()->json($po->items->map(fn ($it) => [
            'product_id'       => $it->product_id,
            'description'      => $it->description ?: $it->product?->name,
            'quantity'         => (float) $it->quantity,
            'unit_price'       => (float) $it->unit_price,
            'discount_percent' => (float) ($it->discount_percent ?? 0),
            'tax_rate_value'   => (float) ($it->tax_rate_value ?? 18),
        ])->values());
    }

    public function store(StoreSupplierInvoiceRequest $request)
    {
        $this->authorize('create', SupplierInvoice::class);
        $data = $request->validated();
        unset($data['documents']);
        $invoice = $this->service->create($data);
        $this->uploadDocuments($invoice, $request);

        return redirect()
            ->route('achats.factures-fournisseurs.show', $invoice)
            ->with('success', 'Facture fournisseur ' . $invoice->number . ' créée avec succès.');
    }

    /** Enregistre les pièces jointes de la facture fournisseur. */
    private function uploadDocuments(SupplierInvoice $invoice, Request $request): void
    {
        foreach ((array) $request->file('documents', []) as $file) {
            $path = $file->store('attachments/supplier_invoice/'.$invoice->id, 'local');
            $invoice->attachments()->create([
                'disk'        => 'local',
                'path'        => $path,
                'filename'    => $file->getClientOriginalName(),
                'mime_type'   => $file->getMimeType(),
                'size'        => $file->getSize(),
                'uploaded_by' => \Illuminate\Support\Facades\Auth::id(),
            ]);
        }
    }

    public function show(SupplierInvoice $facturesFournisseur)
    {
        $this->authorize('view', $facturesFournisseur);
        $invoice        = $this->service->repository->findWithDetails($facturesFournisseur->id);
        $paymentMethods = PaymentMethod::where('is_active', true)->orderBy('sort_order')->get(['id', 'name']);
        $cashAccounts   = CashAccount::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('achats.factures-fournisseurs.show', compact('invoice', 'paymentMethods', 'cashAccounts'));
    }

    public function edit(SupplierInvoice $facturesFournisseur)
    {
        $this->authorize('update', $facturesFournisseur);
        $invoice   = $this->service->repository->findWithDetails($facturesFournisseur->id);
        $invoice->load('attachments');
        $suppliers = Supplier::active()->orderBy('name')->get(['id', 'name']);
        $products  = Product::active()->orderBy('name')->get(['id', 'name', 'reference', 'purchase_price']);

        return view('achats.factures-fournisseurs.edit', compact('invoice', 'suppliers', 'products') + $this->maquetteFormData());
    }

    public function update(UpdateSupplierInvoiceRequest $request, SupplierInvoice $facturesFournisseur)
    {
        $this->authorize('update', $facturesFournisseur);
        $data = $request->validated();
        unset($data['documents']);
        $this->service->update($facturesFournisseur, $data);
        $this->uploadDocuments($facturesFournisseur, $request);

        return redirect()
            ->route('achats.factures-fournisseurs.show', $facturesFournisseur)
            ->with('success', 'Facture fournisseur mise à jour.');
    }

    public function destroy(SupplierInvoice $facturesFournisseur)
    {
        $this->authorize('delete', $facturesFournisseur);
        try {
            $this->service->delete($facturesFournisseur);
            return redirect()
                ->route('achats.factures-fournisseurs.index')
                ->with('success', 'Facture fournisseur supprimée.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * POST achats/factures-fournisseurs/{facturesFournisseur}/payment
     */
    public function recordPayment(Request $request, SupplierInvoice $facturesFournisseur)
    {
        $this->authorize('update', $facturesFournisseur);
        $request->validate([
            'amount'            => 'required|numeric|min:1',
            'payment_date'      => 'required|date',
            'payment_method_id' => 'nullable|exists:payment_methods,id',
            'cash_account_id'   => 'nullable|exists:cash_accounts,id',
            'reference'         => 'nullable|string|max:100',
            'notes'             => 'nullable|string|max:500',
        ]);

        try {
            $this->service->recordPayment($facturesFournisseur, $request->only([
                'amount', 'payment_date', 'payment_method_id', 'cash_account_id', 'reference', 'notes',
            ]));

            return redirect()
                ->route('achats.factures-fournisseurs.show', $facturesFournisseur)
                ->with('success', 'Paiement enregistré avec succès.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * POST achats/factures-fournisseurs/{supplierInvoice}/validate
     */
    public function validateInvoice(SupplierInvoice $facturesFournisseur)
    {
        $this->authorize('validate', $facturesFournisseur);
        try {
            $this->service->validate($facturesFournisseur);
            return redirect()
                ->route('achats.factures-fournisseurs.show', $facturesFournisseur)
                ->with('success', 'Facture fournisseur validée.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
