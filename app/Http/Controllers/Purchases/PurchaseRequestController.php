<?php

namespace App\Http\Controllers\Purchases;

use App\Http\Controllers\Controller;
use App\Http\Requests\Purchase\StorePurchaseRequestRequest;
use App\Http\Requests\Purchase\UpdatePurchaseRequestRequest;
use App\Models\Product;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Models\Unit;
use App\Services\PurchaseRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PurchaseRequestController extends Controller
{
    public function __construct(private PurchaseRequestService $service) {}

    public function index(Request $request): View
    {
        $filters  = $request->only(['status', 'department', 'search']);
        $requests = $this->service->search($filters, 15);

        $summary = [
            'total'    => PurchaseRequest::count(),
            'pending'  => PurchaseRequest::where('status', 'soumis')->count(),
            'approved' => PurchaseRequest::where('status', 'approuve')->count(),
            'draft'    => PurchaseRequest::where('status', 'brouillon')->count(),
        ];

        return view('achats.demandes-achat.index', compact('requests', 'filters', 'summary'));
    }

    public function create(): View
    {
        $products   = Product::active()->orderBy('name')->get(['id', 'name', 'reference', 'purchase_price']);
        $units      = Unit::orderBy('name')->get(['id', 'name', 'abbreviation']);
        $warehouses = \App\Models\Warehouse::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']);

        return view('achats.demandes-achat.create', compact('products', 'units', 'warehouses'));
    }

    public function store(StorePurchaseRequestRequest $request): RedirectResponse
    {
        $data = $request->validated();
        unset($data['documents']);
        $pr = $this->service->create($data);
        $this->uploadDocuments($pr, $request);

        return redirect()
            ->route('achats.demandes-achat.show', $pr)
            ->with('success', 'Demande d\'achat ' . $pr->number . ' créée.');
    }

    /** Enregistre les pièces jointes de la demande d'achat. */
    private function uploadDocuments(PurchaseRequest $pr, \Illuminate\Http\Request $request): void
    {
        foreach ((array) $request->file('documents', []) as $file) {
            $path = $file->store('attachments/purchase_request/'.$pr->id, 'local');
            $pr->attachments()->create([
                'disk'        => 'local',
                'path'        => $path,
                'filename'    => $file->getClientOriginalName(),
                'mime_type'   => $file->getMimeType(),
                'size'        => $file->getSize(),
                'uploaded_by' => \Illuminate\Support\Facades\Auth::id(),
            ]);
        }
    }

    public function show(PurchaseRequest $demandesAchat): View
    {
        $pr        = $this->service->repository->findWithDetails($demandesAchat->id);
        $suppliers = Supplier::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('achats.demandes-achat.show', compact('pr', 'suppliers'));
    }

    public function edit(PurchaseRequest $demandesAchat): View|\Illuminate\Http\RedirectResponse
    {
        if (! $demandesAchat->isEditable()) {
            return back()->with('error', 'Cette demande ne peut plus être modifiée.');
        }

        $pr       = $this->service->repository->findWithDetails($demandesAchat->id);
        $pr->load('attachments');
        $products   = Product::active()->orderBy('name')->get(['id', 'name', 'reference', 'purchase_price']);
        $units      = Unit::orderBy('name')->get(['id', 'name', 'abbreviation']);
        $warehouses = \App\Models\Warehouse::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']);

        return view('achats.demandes-achat.edit', compact('pr', 'products', 'units', 'warehouses'));
    }

    public function update(UpdatePurchaseRequestRequest $request, PurchaseRequest $demandesAchat): RedirectResponse
    {
        try {
            $data = $request->validated();
            unset($data['documents']);
            $this->service->update($demandesAchat, $data);
            $this->uploadDocuments($demandesAchat, $request);
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('achats.demandes-achat.show', $demandesAchat)
            ->with('success', 'Demande ' . $demandesAchat->number . ' mise à jour.');
    }

    public function destroy(PurchaseRequest $demandesAchat): RedirectResponse
    {
        try {
            $this->service->delete($demandesAchat);

            return redirect()
                ->route('achats.demandes-achat.index')
                ->with('success', 'Demande d\'achat supprimée.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Submit the request for approval (brouillon → soumis).
     */
    public function submit(PurchaseRequest $demandesAchat): RedirectResponse
    {
        try {
            $this->service->submit($demandesAchat);

            return redirect()
                ->route('achats.demandes-achat.show', $demandesAchat)
                ->with('success', 'Demande soumise pour approbation.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Approve the request (soumis → approuve).
     */
    public function approve(PurchaseRequest $demandesAchat): RedirectResponse
    {
        try {
            $this->service->approve($demandesAchat);

            return redirect()
                ->route('achats.demandes-achat.show', $demandesAchat)
                ->with('success', 'Demande approuvée.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Convert an approved request into a purchase order.
     */
    public function convert(Request $request, PurchaseRequest $demandesAchat): RedirectResponse
    {
        $request->validate(['supplier_id' => 'required|exists:suppliers,id']);

        try {
            $po = $this->service->convertToPurchaseOrder($demandesAchat, (int) $request->supplier_id);

            return redirect()
                ->route('achats.commandes.show', $po)
                ->with('success', 'Commande ' . $po->number . ' créée depuis la demande ' . $demandesAchat->number . '.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Reject the request (soumis → rejete).
     */
    public function reject(Request $request, PurchaseRequest $demandesAchat): RedirectResponse
    {
        $request->validate(['reason' => 'required|string|max:500']);

        try {
            $this->service->reject($demandesAchat, $request->input('reason'));

            return redirect()
                ->route('achats.demandes-achat.show', $demandesAchat)
                ->with('success', 'Demande rejetée.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
