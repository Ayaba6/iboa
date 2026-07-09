<?php

namespace App\Http\Controllers\Purchases;

use App\Http\Controllers\Controller;
use App\Http\Requests\Purchase\StoreSupplierReturnRequest;
use App\Http\Requests\Purchase\UpdateSupplierReturnRequest;
use App\Models\Company;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierReturn;
use App\Services\SupplierReturnService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierReturnController extends Controller
{
    public function __construct(private SupplierReturnService $service) {}

    public function index(Request $request): View
    {
        $filters   = $request->only(['supplier_id', 'status', 'search']);
        $returns   = $this->service->search($filters, 15);
        $suppliers = Supplier::active()->orderBy('name')->get(['id', 'name']);

        return view('achats.retours-fournisseurs.index', compact('returns', 'filters', 'suppliers'));
    }

    public function create(): View
    {
        $suppliers = Supplier::active()->orderBy('name')->get(['id', 'name']);
        $products  = Product::active()->orderBy('name')->get(['id', 'name', 'reference', 'purchase_price']);

        return view('achats.retours-fournisseurs.create', compact('suppliers', 'products') + $this->maquetteFormData());
    }

    /** [Maquette Retour fournisseur] Données complémentaires du formulaire. */
    private function maquetteFormData(): array
    {
        return [
            'purchaseOrders'   => \App\Models\PurchaseOrder::orderByDesc('id')->limit(100)->get(['id', 'number']),
            'receptions'       => \App\Models\Reception::orderByDesc('id')->limit(100)->get(['id', 'number']),
            'supplierInvoices' => \App\Models\SupplierInvoice::orderByDesc('id')->limit(100)->get(['id', 'number']),
            'warehouses'       => \App\Models\Warehouse::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
        ];
    }

    /** [Maquette Retour fournisseur] Lignes d'une réception (JSON) pour « Ajouter depuis réception ». */
    public function receptionItems(Request $request)
    {
        $reception = \App\Models\Reception::with('items.product')->findOrFail($request->integer('reception_id'));

        return response()->json($reception->items->map(fn ($it) => [
            'product_id'  => $it->product_id,
            'description' => $it->description ?: $it->product?->name,
            'quantity'    => (float) ($it->received_quantity ?: $it->expected_quantity),
            'unit_price'  => (float) ($it->unit_cost ?? $it->product?->purchase_price ?? 0),
        ])->values());
    }

    public function store(StoreSupplierReturnRequest $request): RedirectResponse
    {
        $return = $this->service->create($request->validated());

        return redirect()
            ->route('achats.retours-fournisseurs.show', $return)
            ->with('success', 'Retour fournisseur ' . $return->number . ' créé avec succès.');
    }

    public function show(SupplierReturn $retoursFournisseurs): View
    {
        $return = $this->service->repository->findWithDetails($retoursFournisseurs->id);

        return view('achats.retours-fournisseurs.show', compact('return'));
    }

    public function edit(SupplierReturn $retoursFournisseurs): View|RedirectResponse
    {
        if (! $retoursFournisseurs->isEditable()) {
            return back()->with('error', 'Ce retour ne peut plus être modifié.');
        }

        $return    = $this->service->repository->findWithDetails($retoursFournisseurs->id);
        $suppliers = Supplier::active()->orderBy('name')->get(['id', 'name']);
        $products  = Product::active()->orderBy('name')->get(['id', 'name', 'reference', 'purchase_price']);

        return view('achats.retours-fournisseurs.edit', compact('return', 'suppliers', 'products') + $this->maquetteFormData());
    }

    public function update(UpdateSupplierReturnRequest $request, SupplierReturn $retoursFournisseurs): RedirectResponse
    {
        try {
            $this->service->update($retoursFournisseurs, $request->validated());
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('achats.retours-fournisseurs.show', $retoursFournisseurs)
            ->with('success', 'Retour ' . $retoursFournisseurs->number . ' mis à jour.');
    }

    public function pdf(SupplierReturn $retoursFournisseurs): mixed
    {
        $return  = $this->service->repository->findWithDetails($retoursFournisseurs->id);
        $company = currentCompany();

        $pdf = Pdf::loadView('achats.pdf.supplier-return', compact('return', 'company'))
            ->setPaper('a4', 'portrait');

        return $pdf->download('avoir_' . $return->number . '_' . now()->format('Ymd') . '.pdf');
    }

    public function destroy(SupplierReturn $retoursFournisseurs): RedirectResponse
    {
        try {
            $this->service->delete($retoursFournisseurs);

            return redirect()
                ->route('achats.retours-fournisseurs.index')
                ->with('success', 'Retour fournisseur supprimé.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function validateReturn(SupplierReturn $retoursFournisseurs): RedirectResponse
    {
        try {
            $this->service->validate($retoursFournisseurs);

            return redirect()
                ->route('achats.retours-fournisseurs.show', $retoursFournisseurs)
                ->with('success', 'Retour fournisseur validé. Le stock a été ajusté.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
