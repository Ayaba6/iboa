<?php

namespace App\Http\Controllers\Sales;

use App\Exports\InvoicesExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sale\StoreInvoiceRequest;
use App\Http\Requests\Sale\UpdateInvoiceRequest;
use App\Http\Traits\ManagesEditLock;
use App\Jobs\SendInvoiceEmailJob;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\TaxRate;
use App\Services\CommercialWorkflowService;
use App\Services\InvoiceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class InvoiceController extends Controller
{
    use \App\Http\Controllers\Concerns\UploadsDocuments;

    use ManagesEditLock;

    public function __construct(
        private InvoiceService              $service,
        private CommercialWorkflowService   $workflow,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Invoice::class);

        $filters  = $request->only(['client_id', 'status', 'type', 'overdue', 'search', 'date_from', 'date_to']);
        $invoices = $this->service->search($filters, 15);

        if ($request->boolean('export')) {
            return Excel::download(
                new InvoicesExport($filters),
                'factures-' . now()->format('Y-m-d') . '.xlsx'
            );
        }

        $clients = Client::active()->orderBy('name')->get(['id', 'name', 'trade_name']);

        // ── Totaux agrégés sur l'ensemble des filtres (pas seulement la page courante) ──
        $company = currentCompany();
        $totalsQuery = Invoice::where('company_id', $company->id)
            ->when(!empty($filters['client_id']), fn($q) => $q->where('client_id', $filters['client_id']))
            ->when(!empty($filters['status']),    fn($q) => $q->where('status', $filters['status']))
            ->when(!empty($filters['type']),      fn($q) => $q->where('type', $filters['type']))
            ->when(!empty($filters['date_from']), fn($q) => $q->whereDate('issued_at', '>=', $filters['date_from']))
            ->when(!empty($filters['date_to']),   fn($q) => $q->whereDate('issued_at', '<=', $filters['date_to']))
            ->when(!empty($filters['overdue']),   fn($q) => $q->where('due_at', '<', now()->toDateString())
                ->whereNotIn('status', ['payee', 'annulee']))
            ->when(!empty($filters['search']),    fn($q) => $q->where(fn($sq) =>
                $sq->where('number', 'like', '%'.$filters['search'].'%')
                    ->orWhereHas('client', fn($c) => $c->where('name', 'like', '%'.$filters['search'].'%'))
            ));

        $summary = [
            'total_ttc'       => (int) $totalsQuery->sum('total_ttc'),
            'total_ht'        => (int) (clone $totalsQuery)->sum('subtotal_ht'),
            'total_remaining' => (int) (clone $totalsQuery)->sum('remaining_amount'),
            'count_overdue'   => (int) (clone $totalsQuery)->where('due_at', '<', now()->toDateString())
                                    ->whereNotIn('status', ['payee', 'annulee'])->count(),
            'count_paid'      => (int) (clone $totalsQuery)->where('status', 'payee')->count(),
        ];

        return view('ventes.factures.index', compact('invoices', 'filters', 'clients', 'summary'));
    }

    public function create(Request $request)
    {
        $this->authorize('create', Invoice::class);

        $clients = Client::active()
            ->with(['taxRates' => fn($q) => $q->where('type', 'retenue')])
            ->orderBy('name')
            ->get(['id', 'name', 'trade_name', 'is_tax_exempt']);
        $products       = Product::active()->sellable()->with(['taxRate:id,rate', 'family:id,name'])->withSum('productStocks as stock_qty', 'quantity')->orderBy('name')->get(['id', 'name', 'reference', 'barcode', 'sale_price', 'tax_rate_id', 'is_stockable', 'family_id']);
        $selectedClient = $request->query('client_id');

        // Map { clientId: [{name, short_name, rate}, ...] } pour le calcul JS des retenues
        $clientWithholding = $clients->mapWithKeys(fn($c) => [
            $c->id => $c->taxRates->map(fn($t) => [
                'name'       => $t->name,
                'short_name' => $t->short_name,
                'rate'       => (float) $t->rate,
            ])->values(),
        ]);

        // Map { clientId: bool } — indique au JS si le client est exonéré de TVA
        $clientExemptions = $clients->pluck('is_tax_exempt', 'id');

        // Taux TVA de type 'tva' pour le sélecteur (exclut les retenues)
        $taxRatesVente = TaxRate::where('type', 'tva')->where('is_active', true)->orderBy('rate')->get(['id', 'name', 'rate', 'is_default']);

        return view('ventes.factures.create', compact('clients', 'products', 'selectedClient', 'clientWithholding', 'clientExemptions', 'taxRatesVente') + $this->maquetteFormData());
    }

    public function store(StoreInvoiceRequest $request)
    {
        $this->authorize('create', Invoice::class);

        $data = $request->validated();
        unset($data['documents']);
        $invoice = $this->service->create($data);
        $this->uploadDocuments($invoice, $request);

        return redirect()
            ->route('ventes.factures.show', $invoice)
            ->with('success', 'Facture ' . $invoice->number . ' créée avec succès.');
    }

    /** [Maquette Facture] Lignes d'une commande (JSON) pour « Ajouter depuis commande ». */
    public function orderItems(Request $request)
    {
        $order = \App\Models\Order::with('items.product.taxRate')->findOrFail($request->integer('order_id'));

        return response()->json($order->items->map(fn ($it) => [
            'product_id'       => $it->product_id,
            'description'      => $it->description ?: $it->product?->name,
            'quantity'         => (float) $it->quantity,
            'unit_price'       => (float) $it->unit_price,
            'discount_percent' => (float) ($it->discount_percent ?? 0),
            'tax_rate_value'   => (float) ($it->tax_rate_value ?? $it->product?->taxRate?->rate ?? 0),
        ])->values());
    }

    /** [Maquette Facture] Lignes d'un bon de livraison (JSON) pour « Ajouter depuis BL ». */
    public function deliveryNoteItems(Request $request)
    {
        $dn = \App\Models\DeliveryNote::with('items.product.taxRate')->findOrFail($request->integer('delivery_note_id'));

        return response()->json($dn->items->map(fn ($it) => [
            'product_id'       => $it->product_id,
            'description'      => $it->description ?: $it->product?->name,
            'quantity'         => (float) $it->quantity,
            'unit_price'       => (float) ($it->unit_price ?? $it->product?->sale_price ?? 0),
            'discount_percent' => 0,
            'tax_rate_value'   => (float) ($it->product?->taxRate?->rate ?? 0),
        ])->values());
    }

    /** [Maquette Facture de vente] Données complémentaires du formulaire. */
    private function maquetteFormData(): array
    {
        return [
            'contacts'      => \App\Models\ClientContact::orderBy('last_name')->get(['id', 'client_id', 'civility', 'first_name', 'last_name']),
            'warehouses'    => \App\Models\Warehouse::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'salesReps'     => \App\Models\User::orderBy('name')->get(['id', 'name']),
            'orders'        => \App\Models\Order::orderByDesc('id')->limit(100)->get(['id', 'reference', 'number', 'issued_at']),
            'deliveryNotes' => \App\Models\DeliveryNote::orderByDesc('id')->limit(100)->get(['id', 'number']),
        ];
    }

    public function show(Invoice $facture)
    {
        $this->authorize('view', $facture);

        $invoice = $this->service->repository->findWithDetails($facture->id);

        // [UX-4] Audit log de cette facture — 20 dernières opérations
        $audits = \App\Models\AuditLog::where('model_type', \App\Models\Invoice::class)
            ->where('model_id', $invoice->id)
            ->latest('created_at')
            ->limit(20)
            ->get();

        return view('ventes.factures.show', compact('invoice', 'audits'));
    }

    public function edit(Invoice $facture)
    {
        $this->authorize('update', $facture);

        if (!in_array($facture->status, ['brouillon'])) {
            return back()->with('error', 'Seules les factures en brouillon peuvent être modifiées.');
        }

        // [CONCURRENCE] Acquisition du verrou d'édition
        $lock = $this->acquireLockOr($facture, 'ventes.factures.show', $facture);
        if ($lock instanceof \Illuminate\Http\RedirectResponse) return $lock;

        $invoice  = $this->service->repository->findWithDetails($facture->id);
        $invoice->load('attachments');
        $clients  = Client::active()
            ->with(['taxRates' => fn($q) => $q->where('type', 'retenue')])
            ->orderBy('name')
            ->get(['id', 'name', 'trade_name', 'is_tax_exempt']);
        $products = Product::active()->sellable()->with(['taxRate:id,rate', 'family:id,name'])->withSum('productStocks as stock_qty', 'quantity')->orderBy('name')->get(['id', 'name', 'reference', 'barcode', 'sale_price', 'tax_rate_id', 'is_stockable', 'family_id']);

        $clientWithholding = $clients->mapWithKeys(fn($c) => [
            $c->id => $c->taxRates->map(fn($t) => [
                'name'       => $t->name,
                'short_name' => $t->short_name,
                'rate'       => (float) $t->rate,
            ])->values(),
        ]);
        $clientExemptions  = $clients->pluck('is_tax_exempt', 'id');
        $taxRatesVente     = TaxRate::where('type', 'tva')->where('is_active', true)->orderBy('rate')->get(['id', 'name', 'rate', 'is_default']);

        $editLock = $lock; // déjà le verrou actif pour ce user
        return view('ventes.factures.edit', compact('invoice', 'clients', 'products', 'clientWithholding', 'clientExemptions', 'taxRatesVente', 'editLock') + $this->maquetteFormData());
    }

    public function update(UpdateInvoiceRequest $request, Invoice $facture)
    {
        $this->authorize('update', $facture);

        try {
            $data = $request->validated();
            unset($data['documents']);
            $this->service->update($facture, $data);
            $this->uploadDocuments($facture, $request);
            $this->releaseLock($facture); // [CONCURRENCE] Libère le verrou après sauvegarde
            return redirect()
                ->route('ventes.factures.show', $facture)
                ->with('success', 'Facture mise à jour.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function destroy(Invoice $facture)
    {
        $this->authorize('delete', $facture);

        try {
            $this->service->delete($facture);
            return redirect()
                ->route('ventes.factures.index')
                ->with('success', 'Facture supprimée.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * POST ventes/factures/{invoice}/validate — validate the invoice.
     */
    public function validateInvoice(Invoice $facture)
    {
        $this->authorize('validate', $facture);

        try {
            $invoice = $this->service->validate($facture);
            return redirect()
                ->route('ventes.factures.show', $invoice)
                ->with('success', 'Facture ' . $invoice->number . ' validée.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * POST ventes/factures/{invoice}/cancel — annulation avec contre-passation comptable.
     * Seulement possible pour factures émises sans paiement encaissé.
     */
    /**
     * POST ventes/factures/{invoice}/convert-proforma — convertit une proforma en facture standard.
     * Crée une nouvelle facture standard avec déclenchement compta + stock.
     * La proforma originelle passe à 'annulee' avec lien parent_invoice_id.
     */
    public function convertProforma(Invoice $facture)
    {
        $this->authorize('validate', $facture);

        try {
            $newInvoice = $this->service->convertProforma($facture);
            return redirect()
                ->route('ventes.factures.show', $newInvoice)
                ->with('success', 'Proforma ' . $facture->number . ' convertie en facture ' . $newInvoice->number . '.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function cancelInvoice(Invoice $facture, Request $request)
    {
        $this->authorize('delete', $facture);  // l'annulation est aussi sensible que la suppression

        $data = $request->validate([
            'reason' => 'required|string|min:5|max:500',
        ], [
            'reason.required' => 'Le motif de l\'annulation est obligatoire pour l\'audit comptable.',
            'reason.min'      => 'Le motif doit faire au moins 5 caractères.',
        ]);

        try {
            $invoice = $this->service->cancel($facture, $data['reason']);
            return redirect()
                ->route('ventes.factures.show', $invoice)
                ->with('success', 'Facture ' . $invoice->number . ' annulée (contre-passation comptable effectuée).');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * GET ventes/factures/export-pdf — export the filtered invoice list as PDF.
     */
    public function exportPdf(Request $request): mixed
    {
        $this->authorize('viewAny', Invoice::class);

        $filters = array_filter(
            $request->only(['client_id', 'status', 'type', 'overdue', 'search', 'date_from', 'date_to']),
            fn($v) => $v !== '' && $v !== null
        );

        $company = currentCompany();

        $invoices = Invoice::with(['client'])
            ->when(!empty($filters['client_id']), fn($q) => $q->where('client_id', $filters['client_id']))
            ->when(!empty($filters['status']),    fn($q) => $q->where('status',    $filters['status']))
            ->when(!empty($filters['overdue']),   fn($q) => $q->where('due_at', '<', now())
                ->whereNotIn('status', ['payee', 'annulee']))
            ->when(!empty($filters['search']),    fn($q) => $q->where(fn($sq) =>
                $sq->where('number', 'like', '%'.$filters['search'].'%')
                   ->orWhereHas('client', fn($c) => $c->where('name', 'like', '%'.$filters['search'].'%'))
            ))
            ->when(!empty($filters['date_from']), fn($q) => $q->whereDate('issued_at', '>=', $filters['date_from']))
            ->when(!empty($filters['date_to']),   fn($q) => $q->whereDate('issued_at', '<=', $filters['date_to']))
            ->orderByDesc('issued_at')
            ->orderByDesc('number')
            ->get();

        $totalHt        = $invoices->sum('subtotal_ht');
        $totalTtc       = $invoices->sum('total_ttc');
        $totalTva       = $invoices->sum('total_tax');
        $totalRemaining = $invoices->sum('remaining_amount');
        $countOverdue   = $invoices->filter(fn($i) =>
            $i->due_at && $i->due_at->isPast() && !in_array($i->status, ['payee', 'annulee'])
        )->count();

        $statusLabels = [
            'brouillon'           => 'Brouillon',
            'emise'               => 'Émise',
            'envoyee'             => 'Envoyée',
            'partiellement_payee' => 'Part. payée',
            'payee'               => 'Payée',
            'en_retard'           => 'En retard',
            'annulee'             => 'Annulée',
        ];

        $pdf = Pdf::loadView('ventes.factures.pdf.index', compact(
            'company', 'invoices', 'filters',
            'totalHt', 'totalTtc', 'totalTva', 'totalRemaining', 'countOverdue',
            'statusLabels'
        ))->setPaper('a4', 'landscape');

        return $pdf->download('factures_' . now()->format('Ymd_His') . '.pdf');
    }

    /**
     * GET ventes/factures/{invoice}/pdf — download or stream PDF.
     * Add ?preview=1 to open in browser instead of downloading.
     */
    public function pdf(Invoice $facture, Request $request)
    {
        $this->authorize('view', $facture);

        try {
            $invoice  = $this->service->repository->findWithDetails($facture->id);
            $viewPath = $this->service->generatePdfPath($invoice);
            $settings = currentCompany()?->documentSetting;

            $pdf = Pdf::loadView($viewPath, compact('invoice', 'settings'))
                ->setPaper(strtolower($settings?->page_size ?? 'a4'), $settings?->orientation ?? 'portrait');

            $filename = 'Facture_' . str_replace(['/', '\\', ' '], '-', $invoice->number) . '.pdf';

            // [Phase 2.8] Archiver l'exemplaire émis (une seule fois) : dès qu'une
            // facture n'est plus en brouillon, le PDF réellement produit est figé
            // + empreinté. La régénération ultérieure ne réécrit jamais l'original.
            if (! in_array($invoice->status, ['brouillon', 'annulee'], true)) {
                app(\App\Services\DocumentArchiveService::class)->archive($invoice, $pdf->output(), $invoice->number);
            }

            return $request->boolean('preview')
                ? $pdf->stream($filename)
                : $pdf->download($filename);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('PDF facture error', ['id' => $facture->id, 'error' => $e->getMessage()]);
            return back()->with('error', 'Impossible de générer le PDF : ' . $e->getMessage());
        }
    }

    // ── Workflow de validation interne ────────────────────────────────────────

    public function submit(Request $request, Invoice $facture)
    {
        $request->validate(['motif' => ['nullable', 'string', 'max:500']]);
        try {
            $this->workflow->submit($facture, $request->motif);
            return back()->with('success', "Facture {$facture->number} soumise à validation.");
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function validateInternal(Request $request, Invoice $facture)
    {
        $request->validate(['motif' => ['nullable', 'string', 'max:500']]);
        try {
            $this->workflow->validateInvoice($facture, $request->motif);
            return back()->with('success', "Facture {$facture->number} validée.");
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (\RuntimeException $e) {
            return back()->withErrors(['facture' => $e->getMessage()])->with('error', $e->getMessage());
        }
    }

    public function rejectInternal(Request $request, Invoice $facture)
    {
        $request->validate(['motif' => ['required', 'string', 'min:5', 'max:500']],
            ['motif.required' => 'Le motif est obligatoire.']);
        try {
            $this->workflow->reject($facture, $request->motif);
            return back()->with('success', "Facture {$facture->number} refusée — retour en brouillon.");
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function cancelInternal(Request $request, Invoice $facture)
    {
        $request->validate(['motif' => ['required', 'string', 'min:5', 'max:500']],
            ['motif.required' => "Le motif est obligatoire."]);
        try {
            $this->workflow->cancel($facture, $request->motif);
            return back()->with('success', "Facture {$facture->number} annulée.");
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * POST ventes/factures/{invoice}/send-email — send invoice by email.
     */
    public function sendEmail(Invoice $facture)
    {
        $this->authorize('send', $facture);

        $invoice = $this->service->repository->findWithDetails($facture->id);
        $email   = $invoice->client?->email;

        if (!$email) {
            return back()->with('error', 'Ce client n\'a pas d\'adresse email renseignée.');
        }

        // [PERF-C1] Dispatch asynchronously — the job handles send + status update + logging.
        SendInvoiceEmailJob::dispatch(
            $invoice,
            $email,
            $invoice->client?->displayName() ?? $email,
        );

        return back()->with('success', 'Email programmé pour ' . $email . '. Il sera envoyé sous quelques instants.');
    }
}
