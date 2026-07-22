<?php

namespace App\Http\Controllers\Purchases;

use App\Http\Controllers\Controller;
use App\Models\Reception;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReceptionController extends Controller
{
    public function __construct(private StockService $stockService) {}

    /**
     * List all receptions with filters.
     */
    public function index(Request $request): View
    {
        $query = Reception::with(['supplier', 'purchaseOrder', 'createdBy'])
            ->latest('received_at');

        if ($s = $request->search) {
            $query->where(function ($q) use ($s) {
                $q->where('number', 'like', "%{$s}%")
                  ->orWhereHas('supplier', fn($q2) => $q2->where('name', 'like', "%{$s}%"));
            });
        }

        if ($status = $request->status) {
            $query->where('status', $status);
        }

        if ($supplierId = $request->supplier_id) {
            $query->where('supplier_id', $supplierId);
        }

        $receptions = $query->paginate(15)->withQueryString();
        $filters    = $request->only(['search', 'status', 'supplier_id']);
        $suppliers  = Supplier::active()->orderBy('name')->get(['id', 'name']);

        $summary = [
            'total'     => Reception::count(),
            'pending'   => Reception::where('status', 'brouillon')->count(),
            'validated' => Reception::where('status', 'valide')->count(),
            'partial'   => Reception::where('status', 'partielle')->count(),
        ];

        return view('achats.receptions.index', compact('receptions', 'filters', 'suppliers', 'summary'));
    }

    /**
     * Show a single reception with its items and stock impact.
     */
    public function show(Reception $reception): View
    {
        $reception->load([
            'supplier',
            'purchaseOrder',
            'items.product',
            'items.unit',
            'createdBy',
            'validatedBy',
            'attachments',
        ]);

        $warehouses = Warehouse::active()->orderBy('name')->get(['id', 'name']);

        return view('achats.receptions.show', compact('reception', 'warehouses'));
    }

    /**
     * Validate a reception: update quantities on PO items and create stock movements.
     */
    public function validateReception(Request $request, Reception $reception): RedirectResponse
    {
        $request->validate([
            'warehouse_id'                  => ['required', 'exists:warehouses,id', new \App\Rules\WarehouseAllows('can_purchase')],
            'items'                         => ['required', 'array'],
            'items.*.received_quantity'     => ['required', 'numeric', 'min:0'],
            'items.*.lot_number'            => ['nullable', 'string', 'max:100'],
            'items.*.expiry_date'           => ['nullable', 'date'],
            'documents'                     => ['nullable', 'array'],
            'documents.*'                   => ['file', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx', 'max:5120'],
        ], [
            'warehouse_id.required' => 'Veuillez sélectionner un entrepôt de destination.',
            'warehouse_id.exists'   => 'L\'entrepôt sélectionné est invalide.',
            'items.required'        => 'Aucun article trouvé.',
            'items.*.received_quantity.required' => 'La quantité reçue est obligatoire pour chaque ligne.',
            'items.*.received_quantity.min'      => 'La quantité reçue ne peut pas être négative.',
        ]);

        // Compteurs pour message final transparent à l'utilisateur
        $movementsCreated = 0;
        $linesSkipped     = 0;

        try {
            DB::transaction(function () use ($request, $reception, &$movementsCreated, &$linesSkipped) {
                // Re-fetch under lock to prevent concurrent double-validation (TOCTOU).
                $reception = Reception::lockForUpdate()->findOrFail($reception->id);
                if ($reception->status !== 'brouillon') {
                    throw new \RuntimeException('Seules les réceptions en brouillon peuvent être validées.');
                }

                $warehouseId = $request->warehouse_id;

                // Passe 1 — persister les quantités reçues + synchroniser les lignes BC.
                foreach ($request->items as $itemId => $itemData) {
                    $item = $reception->items()->find($itemId);
                    if (!$item) {
                        continue;
                    }

                    $receivedQty = (float) ($itemData['received_quantity'] ?? 0);

                    $item->update([
                        'received_quantity' => $receivedQty,
                        'lot_number'        => $itemData['lot_number']   ?? null,
                        'expiry_date'       => $itemData['expiry_date']  ?? null,
                    ]);

                    // Update received_quantity on the linked PO item
                    if ($item->purchase_order_item_id) {
                        $poItem = $item->purchaseOrderItem;
                        if ($poItem) {
                            $totalReceived = $poItem->received_quantity + $receivedQty;
                            $poItem->update(['received_quantity' => min($totalReceived, $poItem->quantity)]);
                        }
                    }
                }

                // Passe 2 — [Sync ERP] entrées stock depuis les quantités PERSISTÉES,
                // journalisées + idempotentes + relançables (sync_logs).
                $reception->update(['warehouse_id' => $warehouseId]);
                app(\App\Services\Sync\SyncOrchestrator::class)->run(
                    sourceModule: 'achats',
                    targetModule: 'stock',
                    eventName: 'reception.validated',
                    action: 'create_stock_entries',
                    source: $reception,
                    callback: function () use ($reception, &$movementsCreated, &$linesSkipped) {
                        [$movementsCreated, $linesSkipped] =
                            app(\App\Services\Sync\Handlers\ReplayReceptionStockSync::class)($reception->fresh('items'));
                    },
                    payload: ['warehouse_id' => $warehouseId],
                    handlerClass: \App\Services\Sync\Handlers\ReplayReceptionStockSync::class,
                );

                // Mark reception as validated
                $reception->update([
                    'status'       => 'valide',
                    'validated_by' => Auth::id(),
                    'validated_at' => now(),
                ]);

                // [Gap inter-modules — recette] Génération AUTOMATIQUE des bobines
                // et lots pour les articles à suivi bobine/lot : l'action manuelle
                // « Générer les bobines » restait facile à rater et bloquait
                // ensuite la consommation matière en production. Le service filtre
                // lui-même les articles éligibles et ne double PAS l'entrée de
                // stock (traçabilité pure sur ce chemin).
                try {
                    app(\App\Modules\Production\Services\CoilReceptionService::class)
                        ->createFromReception($reception->fresh('items.product.itemCategory'), onlyTracked: true);
                } catch (\Illuminate\Validation\ValidationException) {
                    // Déjà générées ou rien d'éligible — silencieux.
                }

                // Update PO status
                $po = $reception->purchaseOrder;
                if ($po) {
                    $po->load('items');
                    $allReceived = $po->items->every(
                        fn($i) => (float) $i->received_quantity >= (float) $i->quantity
                    );
                    $po->update(['status' => $allReceived ? 'recu' : 'partiellement_recu']);
                }

                // [Sync ERP] event domaine apres commit — point d'extension decouple
                DB::afterCommit(fn () => event(new \App\Events\ReceptionValidated($reception)));
            });
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        // [SAGE parité] Pièces jointes (BL fournisseur signé, photos, certificats)
        foreach ((array) $request->file('documents', []) as $file) {
            $path = $file->store('attachments/reception/'.$reception->id, 'local');
            $reception->attachments()->create([
                'disk'        => 'local',
                'path'        => $path,
                'filename'    => $file->getClientOriginalName(),
                'mime_type'   => $file->getMimeType(),
                'size'        => $file->getSize(),
                'uploaded_by' => Auth::id(),
            ]);
        }

        // [CDC §13.4] Réception validée par le magasin → contrôle qualité requis
        // avant entrée stock définitive (pesée, dimensions, conformité matière).
        if ($movementsCreated > 0) {
            \App\Notifications\ValidationStepNotification::sendToRoles(
                ['responsable_qualite'],
                title: 'Contrôle qualité requis',
                message: "Réception {$reception->number} validée — contrôle qualité de la matière requis.",
                url: route('achats.receptions.show', $reception),
                modelType: 'Reception',
                modelId: $reception->id,
                type: 'reception_validated',
                icon: 'magnifying-glass',
                color: 'blue',
            );
        }

        // Message transparent : nombre de mouvements créés + warning si des lignes ont été ignorées
        if ($movementsCreated === 0 && $linesSkipped === 0) {
            $msg = 'Réception validée (aucune ligne avec quantité reçue).';
        } elseif ($movementsCreated === 0 && $linesSkipped > 0) {
            $msg = "Réception validée — ⚠ aucun mouvement de stock créé : les {$linesSkipped} ligne(s) sont sans produit catalogué (description libre / service).";
        } elseif ($linesSkipped > 0) {
            $msg = "Réception validée : {$movementsCreated} mouvement(s) de stock enregistré(s), {$linesSkipped} ligne(s) ignorée(s) (sans produit catalogué).";
        } else {
            $msg = "Réception validée : {$movementsCreated} mouvement(s) de stock enregistré(s).";
        }

        return redirect()
            ->route('achats.receptions.show', $reception)
            ->with($linesSkipped > 0 ? 'warning' : 'success', $msg);
    }
}
