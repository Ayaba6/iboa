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

        // [ACHATS Réceptions] Règles métier centralisées dans le service
        // transactionnel — le contrôleur ne fait que valider, déléguer, répondre.
        $movementsCreated = 0;
        $linesSkipped     = 0;
        try {
            [$movementsCreated, $linesSkipped] = app(\App\Services\PurchaseReceptionService::class)
                ->validate($reception, (int) $request->warehouse_id, (array) $request->items);
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

    /**
     * [Audit annulations] Annulation technique d'une réception validée par
     * erreur — gardes et inversions dans PurchaseOrderService::cancelReception.
     */
    public function cancelReception(Request $request, Reception $reception): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'reason.required' => 'Le motif d\'annulation est obligatoire.',
            'reason.min'      => 'Le motif doit faire au moins 5 caractères.',
        ]);

        try {
            app(\App\Services\PurchaseOrderService::class)->cancelReception($reception, $data['reason']);

            return redirect()
                ->route('achats.receptions.show', $reception)
                ->with('success', 'Réception ' . $reception->number . ' annulée — stock contre-passé, bobines retirées, commande fournisseur réouverte.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
