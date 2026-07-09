<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\DeliveryNote;
use App\Services\CommercialWorkflowService;
use App\Services\DeliveryNoteService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class DeliveryNoteController extends Controller
{
    public function __construct(
        private DeliveryNoteService         $service,
        private CommercialWorkflowService   $workflow,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', DeliveryNote::class);
        $filters       = $request->only(['client_id', 'status', 'order_id', 'search']);
        $deliveryNotes = $this->service->search($filters, 15);

        $company = currentCompany();
        $totalsQuery = DeliveryNote::where('company_id', $company->id)
            ->when(!empty($filters['client_id']), fn($q) => $q->where('client_id', $filters['client_id']))
            ->when(!empty($filters['status']),    fn($q) => $q->where('status', $filters['status']))
            ->when(!empty($filters['search']),    fn($q) => $q->where('number', 'like', '%'.$filters['search'].'%'));

        $summary = [
            'total'           => (int) $totalsQuery->count(),
            'count_draft'     => (int) (clone $totalsQuery)->where('status', 'brouillon')->count(),
            'count_validated' => (int) (clone $totalsQuery)->where('status', 'valide')->count(),
            'count_invoiced'  => (int) (clone $totalsQuery)->where('status', 'facture')->count(),
        ];

        return view('ventes.bons-livraison.index', compact('deliveryNotes', 'filters', 'summary'));
    }

    public function show(DeliveryNote $bonsLivraison)
    {
        $this->authorize('view', $bonsLivraison);
        $deliveryNote = $this->service->repository->findWithDetails($bonsLivraison->id);

        return view('ventes.bons-livraison.show', compact('deliveryNote'));
    }

    /**
     * GET ventes/bons-livraison/{deliveryNote}/edit
     * Show a form to adjust item quantities before validating (partial delivery).
     * Only allowed while the BL is still in 'brouillon' status.
     */
    public function edit(DeliveryNote $bonsLivraison)
    {
        $this->authorize('update', $bonsLivraison);
        if ($bonsLivraison->status !== 'brouillon') {
            return back()->with('error', 'Seuls les bons de livraison en brouillon peuvent être modifiés.');
        }

        $deliveryNote = $this->service->repository->findWithDetails($bonsLivraison->id);
        $deliveryNote->load('attachments');

        return view('ventes.bons-livraison.edit', compact('deliveryNote'));
    }

    /**
     * PUT ventes/bons-livraison/{deliveryNote}
     * Save adjusted quantities for partial delivery.
     */
    public function update(Request $request, DeliveryNote $bonsLivraison)
    {
        $this->authorize('update', $bonsLivraison);
        if ($bonsLivraison->status !== 'brouillon') {
            return back()->with('error', 'Seuls les bons de livraison en brouillon peuvent être modifiés.');
        }

        $request->validate([
            'items'                => ['required', 'array'],
            'items.*.id'           => ['required', 'integer', 'exists:delivery_note_items,id'],
            'items.*.quantity'     => ['required', 'numeric', 'min:0'],
            // [SAGE parité] transport
            'delivery_address'     => ['nullable', 'string', 'max:500'],
            'delivery_contact'     => ['nullable', 'string', 'max:120'],
            'carrier'              => ['nullable', 'string', 'max:120'],
            'shipping_mode'        => ['nullable', 'string', 'max:40'],
            'incoterm'             => ['nullable', 'string', 'max:10'],
            'tracking_number'      => ['nullable', 'string', 'max:120'],
            'weight_kg'            => ['nullable', 'numeric', 'min:0'],
            'packages_count'       => ['nullable', 'integer', 'min:0'],
            'expected_delivery_at' => ['nullable', 'date'],
            'notes'                => ['nullable', 'string', 'max:2000'],
            'documents'            => ['nullable', 'array'],
            'documents.*'          => ['file', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx', 'max:5120'],
        ], [
            'items.required'            => 'Aucune ligne à mettre à jour.',
            'items.*.id.required'       => 'Identifiant de ligne manquant.',
            'items.*.id.exists'         => 'Une ligne référencée est invalide.',
            'items.*.quantity.required' => 'La quantité est obligatoire pour chaque ligne.',
            'items.*.quantity.numeric'  => 'La quantité doit être un nombre valide.',
            'items.*.quantity.min'      => 'La quantité ne peut pas être négative.',
        ]);

        \DB::transaction(function () use ($request, $bonsLivraison) {
            $totalQty = 0;
            foreach ($request->input('items') as $row) {
                $item = $bonsLivraison->items()->find($row['id']);
                if (!$item) continue;

                $maxQty = $this->remainingQty($item);
                $qty    = min((float) $row['quantity'], $maxQty);
                $item->update(['quantity' => $qty]);
                $totalQty += $qty;
            }
            $bonsLivraison->update([
                'total_quantity'       => $totalQty,
                'delivery_address'     => $request->input('delivery_address'),
                'delivery_contact'     => $request->input('delivery_contact'),
                'carrier'              => $request->input('carrier'),
                'shipping_mode'        => $request->input('shipping_mode'),
                'incoterm'             => $request->input('incoterm'),
                'tracking_number'      => $request->input('tracking_number'),
                'weight_kg'            => $request->input('weight_kg'),
                'packages_count'       => $request->input('packages_count'),
                'expected_delivery_at' => $request->input('expected_delivery_at'),
                'notes'                => $request->input('notes'),
            ]);
        });

        $this->uploadDocuments($bonsLivraison, $request);

        return redirect()
            ->route('ventes.bons-livraison.show', $bonsLivraison)
            ->with('success', 'Bon de livraison mis à jour.');
    }

    /** Enregistre les pièces jointes du bon de livraison. */
    private function uploadDocuments(DeliveryNote $deliveryNote, Request $request): void
    {
        foreach ((array) $request->file('documents', []) as $file) {
            $path = $file->store('attachments/delivery_note/'.$deliveryNote->id, 'local');
            $deliveryNote->attachments()->create([
                'disk'        => 'local',
                'path'        => $path,
                'filename'    => $file->getClientOriginalName(),
                'mime_type'   => $file->getMimeType(),
                'size'        => $file->getSize(),
                'uploaded_by' => \Illuminate\Support\Facades\Auth::id(),
            ]);
        }
    }

    /**
     * Return the max deliverable qty for a BL item (order item remaining − already delivered).
     */
    private function remainingQty(\App\Models\DeliveryNoteItem $item): float
    {
        if (!$item->order_item_id) {
            return (float) $item->quantity; // no order link → no cap
        }
        $orderItem = \App\Models\OrderItem::find($item->order_item_id);
        if (!$orderItem) {
            return (float) $item->quantity;
        }
        return max(0.0, (float) $orderItem->quantity - (float) $orderItem->delivered_quantity);
    }

    /**
     * POST ventes/bons-livraison/{deliveryNote}/invoice — create invoice from BL.
     */
    public function createInvoice(DeliveryNote $bonsLivraison)
    {
        $this->authorize('update', $bonsLivraison);
        try {
            $invoice = $this->service->createInvoice($bonsLivraison);
            return redirect()
                ->route('ventes.factures.show', $invoice)
                ->with('success', 'Facture ' . $invoice->number . ' créée depuis le bon de livraison ' . $bonsLivraison->number . '.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * POST ventes/bons-livraison/{deliveryNote}/cancel — cancel the delivery note.
     */
    public function cancel(DeliveryNote $bonsLivraison)
    {
        $this->authorize('update', $bonsLivraison);
        try {
            $this->service->cancel($bonsLivraison);
            return back()->with('success', 'Bon de livraison ' . $bonsLivraison->number . ' annulé.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * POST ventes/bons-livraison/{deliveryNote}/validate — validate the delivery note
     * and decrement stock.
     */
    public function validateNote(DeliveryNote $bonsLivraison)
    {
        $this->authorize('validate', $bonsLivraison);
        try {
            $dn = $this->service->validate($bonsLivraison);
            return redirect()
                ->route('ventes.bons-livraison.show', $dn)
                ->with('success', 'Bon de livraison ' . $dn->number . ' validé.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    // ── Workflow de validation interne ────────────────────────────────────────

    public function submit(Request $request, DeliveryNote $bonsLivraison)
    {
        $request->validate(['motif' => ['nullable', 'string', 'max:500']]);
        try {
            $this->workflow->submit($bonsLivraison, $request->motif);
            return back()->with('success', "BL {$bonsLivraison->number} soumis à validation.");
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function validateInternal(Request $request, DeliveryNote $bonsLivraison)
    {
        $request->validate(['motif' => ['nullable', 'string', 'max:500']]);
        try {
            $this->workflow->validateDeliveryNote($bonsLivraison, $request->motif);
            return back()->with('success', "BL {$bonsLivraison->number} validé.");
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Ex. « Stock insuffisant » remonté par StockService lors de la sortie.
            // Sans ce catch, la ValidationException échappe au catch RuntimeException
            // → rollback silencieux (statut inchangé) sans message clair à l'écran.
            return back()->withErrors($e->errors());
        } catch (\RuntimeException $e) {
            // Ex. garde-fou production (« quantité produite insuffisante »). Affiché
            // AUSSI dans la bannière d'erreurs (visible) en plus du toast.
            return back()->withErrors(['livraison' => $e->getMessage()])->with('error', $e->getMessage());
        }
    }

    public function rejectInternal(Request $request, DeliveryNote $bonsLivraison)
    {
        $request->validate(['motif' => ['required', 'string', 'min:5', 'max:500']],
            ['motif.required' => 'Le motif est obligatoire.']);
        try {
            $this->workflow->reject($bonsLivraison, $request->motif);
            return back()->with('success', "BL {$bonsLivraison->number} refusé — retour en brouillon.");
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function cancelInternal(Request $request, DeliveryNote $bonsLivraison)
    {
        $request->validate(['motif' => ['required', 'string', 'min:5', 'max:500']],
            ['motif.required' => "Le motif est obligatoire."]);
        try {
            $this->workflow->cancel($bonsLivraison, $request->motif);
            return back()->with('success', "BL {$bonsLivraison->number} annulé.");
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * GET ventes/bons-livraison/{deliveryNote}/pdf — download or stream PDF.
     * Add ?preview=1 to open in browser instead of downloading.
     */
    public function pdf(DeliveryNote $bonsLivraison, Request $request)
    {
        $this->authorize('view', $bonsLivraison);
        try {
            $deliveryNote = $this->service->repository->findWithDetails($bonsLivraison->id);
            $viewPath     = $this->service->generatePdfPath($deliveryNote);
            $settings     = currentCompany()?->documentSetting;

            $pdf = Pdf::loadView($viewPath, compact('deliveryNote', 'settings'))
                ->setPaper(strtolower($settings?->page_size ?? 'a4'), $settings?->orientation ?? 'portrait');

            $filename = 'BL_' . str_replace(['/', '\\', ' '], '-', $deliveryNote->number) . '.pdf';

            return $request->boolean('preview')
                ? $pdf->stream($filename)
                : $pdf->download($filename);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('PDF BL error', ['id' => $bonsLivraison->id, 'error' => $e->getMessage()]);
            return back()->with('error', 'Impossible de générer le PDF : ' . $e->getMessage());
        }
    }
}
