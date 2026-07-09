<?php

namespace App\Http\Controllers\Stock;

use App\Exports\InventorySessionExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\SaveInventoryCountRequest;
use App\Http\Requests\Stock\StoreInventoryRequest;
use App\Models\InventorySession;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class InventoryController extends Controller
{
    public function __construct(private InventoryService $inventoryService) {}

    /**
     * List all inventory sessions.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', InventorySession::class);
        $filters  = $request->only(['warehouse_id', 'status']);
        $sessions = $this->inventoryService->list($filters);
        $warehouses = Warehouse::active()->orderBy('name')->get(['id', 'name']);

        return view('stocks.inventaires.index', compact('sessions', 'warehouses', 'filters'));
    }

    /**
     * Form to create a new inventory session.
     */
    public function create(): View
    {
        $this->authorize('create', InventorySession::class);
        $warehouses = Warehouse::active()->orderBy('name')->get(['id', 'name', 'code']);

        return view('stocks.inventaires.create', compact('warehouses'));
    }

    /**
     * Store a new inventory session.
     */
    public function store(StoreInventoryRequest $request): RedirectResponse
    {
        $this->authorize('create', InventorySession::class);
        try {
            $data = $request->validated();
            // [PARITÉ SAGE X3] Cases à cocher explicites (0/1)
            foreach (['freeze_stock','include_lots','include_locations'] as $b) {
                $data[$b] = $request->boolean($b);
            }
            unset($data['documents']);
            $session = $this->inventoryService->create($data);

            foreach ((array) $request->file('documents', []) as $file) {
                $path = $file->store('attachments/inventory/'.$session->id, 'local');
                $session->attachments()->create([
                    'disk' => 'local', 'path' => $path, 'filename' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(), 'size' => $file->getSize(),
                    'uploaded_by' => \Illuminate\Support\Facades\Auth::id(),
                ]);
            }

            if ($request->boolean('save_and_new')) {
                return redirect()->route('stocks.inventaires.create')
                    ->with('success', "Inventaire {$session->number} créé. Nouvelle saisie.");
            }

            return redirect()
                ->route('stocks.inventaires.show', $session)
                ->with('success', 'Inventaire créé avec succès. Vous pouvez maintenant saisir les quantités comptées.');
        } catch (\Throwable $e) {
            return back()
                ->withInput()
                ->with('error', 'Erreur lors de la création : ' . $e->getMessage());
        }
    }

    /**
     * Show an inventory session with its items for counting.
     */
    public function show(InventorySession $inventorySession): View
    {
        $this->authorize('view', $inventorySession);
        $session = $this->inventoryService->repository->findWithDetails($inventorySession->id);

        return view('stocks.inventaires.show', compact('session'));
    }

    /**
     * Save counted quantities for inventory items.
     */
    public function saveCount(SaveInventoryCountRequest $request, InventorySession $inventorySession): RedirectResponse
    {
        $this->authorize('update', $inventorySession);
        if (!$inventorySession->isEditable()) {
            return back()->with('error', 'Cet inventaire n\'est plus modifiable.');
        }

        try {
            $this->inventoryService->saveCount($inventorySession, $request->input('items', []));

            return back()->with('success', 'Comptage enregistré avec succès.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Erreur lors de l\'enregistrement : ' . $e->getMessage());
        }
    }

    /**
     * Export inventory session to Excel (.xlsx).
     */
    public function exportExcel(InventorySession $inventorySession): BinaryFileResponse
    {
        $this->authorize('view', $inventorySession);
        $session  = $this->inventoryService->repository->findWithDetails($inventorySession->id);
        $filename = 'inventaire-' . ($session->number ?? $session->id) . '.xlsx';

        return Excel::download(new InventorySessionExport($session), $filename);
    }

    /**
     * Export inventory session to PDF.
     */
    public function exportPdf(InventorySession $inventorySession): \Illuminate\Http\Response
    {
        $this->authorize('view', $inventorySession);
        $session = $this->inventoryService->repository->findWithDetails($inventorySession->id);
        $filename = 'inventaire-' . ($session->number ?? $session->id) . '.pdf';

        $pdf = Pdf::loadView('stocks.inventaires.pdf', compact('session'))
            ->setPaper('a4', 'landscape');

        return $pdf->download($filename);
    }

    /**
     * Validate an inventory session (apply stock adjustments).
     */
    public function validateInventory(InventorySession $inventorySession): RedirectResponse
    {
        $this->authorize('validate', $inventorySession);

        try {
            $this->inventoryService->validate($inventorySession);

            return redirect()
                ->route('stocks.inventaires.index')
                ->with('success', 'Inventaire validé. Le stock a été mis à jour.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            return back()->with('error', 'Erreur lors de la validation : ' . $e->getMessage());
        }
    }
}
