<?php

namespace App\Http\Controllers;

use App\Http\Requests\Supplier\StoreSupplierRequest;
use App\Http\Requests\Supplier\UpdateSupplierRequest;
use App\Models\Supplier;
use App\Services\SupplierService;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function __construct(private SupplierService $service) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Supplier::class);
        $filters   = $request->only(['search', 'is_active']);
        $suppliers = $this->service->search($filters, 15);

        $summary = [
            'total'  => Supplier::count(),
            'active' => Supplier::where('is_active', true)->count(),
        ];

        return view('suppliers.index', compact('suppliers', 'filters', 'summary'));
    }

    public function create()
    {
        $this->authorize('create', Supplier::class);
        return view('suppliers.create', $this->formRefs());
    }

    public function store(StoreSupplierRequest $request)
    {
        $this->authorize('create', Supplier::class);
        $supplier = $this->service->create($request->validated());
        $this->uploadDocuments($supplier, $request);

        return redirect()
            ->route('suppliers.show', $supplier)
            ->with('success', 'Fournisseur créé avec succès.');
    }

    /** Données de référence partagées create/edit (fiche SAGE). */
    private function formRefs(): array
    {
        return [
            'taxRates'   => \App\Models\TaxRate::where('is_active', true)->orderByDesc('is_default')->orderBy('rate')->get(),
            'warehouses' => \App\Models\Warehouse::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
        ];
    }

    /** Enregistre les pièces jointes (documents) du fournisseur. */
    private function uploadDocuments(Supplier $supplier, Request $request): void
    {
        foreach ((array) $request->file('documents', []) as $file) {
            $path = $file->store('attachments/supplier/'.$supplier->id, 'local');
            $supplier->attachments()->create([
                'disk'        => 'local',
                'path'        => $path,
                'filename'    => $file->getClientOriginalName(),
                'mime_type'   => $file->getMimeType(),
                'size'        => $file->getSize(),
                'uploaded_by' => \Illuminate\Support\Facades\Auth::id(),
            ]);
        }
    }

    public function show(Supplier $supplier)
    {
        $this->authorize('view', $supplier);
        $supplier = $this->service->repository->findWithDetails($supplier->id);

        return view('suppliers.show', compact('supplier'));
    }

    public function edit(Supplier $supplier)
    {
        $this->authorize('update', $supplier);
        $supplier->load(['contacts', 'addresses', 'attachments']);

        return view('suppliers.edit', array_merge(['supplier' => $supplier], $this->formRefs()));
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier)
    {
        $this->authorize('update', $supplier);
        $this->service->update($supplier, $request->validated());
        $this->uploadDocuments($supplier, $request);

        return redirect()
            ->route('suppliers.show', $supplier)
            ->with('success', 'Fournisseur mis à jour avec succès.');
    }

    public function destroy(Supplier $supplier)
    {
        $this->authorize('delete', $supplier);
        try {
            $this->service->delete($supplier);

            return redirect()
                ->route('suppliers.index')
                ->with('success', 'Fournisseur supprimé avec succès.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
