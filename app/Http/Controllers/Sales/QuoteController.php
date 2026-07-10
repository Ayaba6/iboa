<?php

namespace App\Http\Controllers\Sales;

use App\Exports\Sales\QuoteExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sale\StoreQuoteRequest;
use App\Http\Requests\Sale\UpdateQuoteRequest;
use App\Models\Client;
use App\Models\Company;
use App\Models\Product;
use App\Models\Quote;
use App\Models\TaxRate;
use App\Services\CommercialWorkflowService;
use App\Services\QuoteService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class QuoteController extends Controller
{
    public function __construct(
        private QuoteService                $service,
        private CommercialWorkflowService   $workflow,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Quote::class);

        $filters = $request->only(['client_id', 'status', 'date_from', 'date_to', 'search']);
        $quotes  = $this->service->search($filters, 15);

        // ── Totaux agrégés sur l'ensemble des filtres (pas seulement la page courante) ──
        $company = currentCompany();
        $totalsQuery = Quote::where('company_id', $company->id)
            ->when(!empty($filters['client_id']), fn($q) => $q->where('client_id', $filters['client_id']))
            ->when(!empty($filters['status']),    fn($q) => $q->where('status', $filters['status']))
            ->when(!empty($filters['date_from']), fn($q) => $q->whereDate('issued_at', '>=', $filters['date_from']))
            ->when(!empty($filters['date_to']),   fn($q) => $q->whereDate('issued_at', '<=', $filters['date_to']))
            ->when(!empty($filters['search']),    fn($q) => $q->where(fn($sq) =>
                $sq->where('number', 'like', '%'.$filters['search'].'%')
                    ->orWhereHas('client', fn($c) => $c->where('name', 'like', '%'.$filters['search'].'%'))
            ));

        $summary = [
            'total_ttc'      => (int) $totalsQuery->sum('total_ttc'),
            'total_ht'       => (int) (clone $totalsQuery)->sum('subtotal_ht'),
            'count_accepted' => (int) (clone $totalsQuery)->where('status', 'accepte')->count(),
            'count_pending'  => (int) (clone $totalsQuery)->whereIn('status', ['brouillon', 'envoye'])->count(),
            'count_expired'  => (int) (clone $totalsQuery)->where('status', 'expire')->count(),
            'total_accepted' => (int) (clone $totalsQuery)->where('status', 'accepte')->sum('total_ttc'),
        ];

        return view('ventes.devis.index', compact('quotes', 'filters', 'summary'));
    }

    /**
     * GET ventes/devis/export — export filtered list to Excel.
     */
    public function export(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $this->authorize('viewAny', Quote::class);

        $filters  = $request->only(['client_id', 'status', 'date_from', 'date_to', 'search']);
        $filename = 'devis-' . now()->format('Y-m-d') . '.xlsx';

        return Excel::download(new QuoteExport($filters), $filename);
    }

    public function create(Request $request)
    {
        $this->authorize('create', Quote::class);

        $clients        = Client::active()->orderBy('name')
            ->get(['id', 'name', 'trade_name', 'phone', 'mobile', 'email', 'address', 'city', 'default_discount', 'payment_terms', 'payment_days', 'is_tax_exempt']);
        $products       = Product::active()->sellable()->with(['taxRate:id,rate', 'family:id,name'])->withSum('productStocks as stock_qty', 'quantity')->withSum('productStocks as reserved_qty', 'reserved_quantity')->orderBy('name')->get(['id', 'name', 'reference', 'barcode', 'sale_price', 'tax_rate_id', 'is_stockable', 'family_id']);
        $selectedClient = $request->query('client_id');
        $clientExemptions = $clients->pluck('is_tax_exempt', 'id');
        $taxRatesVente    = TaxRate::where('type', 'tva')->where('is_active', true)->orderBy('rate')->get(['id', 'name', 'rate', 'is_default']);

        return view('ventes.devis.create', compact('clients', 'products', 'selectedClient', 'clientExemptions', 'taxRatesVente') + $this->maquetteFormData());
    }

    /** [Maquette Nouveau devis] Données complémentaires du formulaire. */
    private function maquetteFormData(): array
    {
        return [
            'contacts'   => \App\Models\ClientContact::orderBy('last_name')->get(['id', 'client_id', 'civility', 'first_name', 'last_name']),
            'warehouses' => \App\Models\Warehouse::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'salesReps'  => \App\Models\User::orderBy('name')->get(['id', 'name']),
            'orders'     => \App\Models\Order::orderByDesc('id')->limit(100)->get(['id', 'reference', 'number', 'issued_at']),
        ];
    }

    /** [Maquette Nouveau devis] Lignes d'une commande (JSON) pour « Ajouter depuis commande ». */
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

    public function store(StoreQuoteRequest $request)
    {
        $this->authorize('create', Quote::class);

        $data = $request->validated();
        unset($data['documents']);
        $quote = $this->service->create($data);
        $this->uploadDocuments($quote, $request);

        return redirect()
            ->route('ventes.devis.show', $quote)
            ->with('success', 'Devis ' . $quote->number . ' créé avec succès.');
    }

    /** Enregistre les pièces jointes (documents) du devis. */
    private function uploadDocuments(Quote $quote, Request $request): void
    {
        foreach ((array) $request->file('documents', []) as $file) {
            $path = $file->store('attachments/quote/'.$quote->id, 'local');
            $quote->attachments()->create([
                'disk'        => 'local',
                'path'        => $path,
                'filename'    => $file->getClientOriginalName(),
                'mime_type'   => $file->getMimeType(),
                'size'        => $file->getSize(),
                'uploaded_by' => \Illuminate\Support\Facades\Auth::id(),
            ]);
        }
    }

    public function show(Quote $devis)
    {
        $this->authorize('view', $devis);

        $quote = $this->service->repository->findWithDetails($devis->id);

        return view('ventes.devis.show', compact('quote'));
    }

    public function edit(Quote $devis)
    {
        $quote    = $this->service->repository->findWithDetails($devis->id);
        $quote->load('attachments');
        $clients  = Client::active()->orderBy('name')
            ->get(['id', 'name', 'trade_name', 'phone', 'mobile', 'email', 'address', 'city', 'default_discount', 'payment_terms', 'payment_days', 'is_tax_exempt']);
        $products = Product::active()->sellable()->with(['taxRate:id,rate', 'family:id,name'])->withSum('productStocks as stock_qty', 'quantity')->orderBy('name')->get(['id', 'name', 'reference', 'barcode', 'sale_price', 'tax_rate_id', 'is_stockable', 'family_id']);
        $clientExemptions = $clients->pluck('is_tax_exempt', 'id');
        $taxRatesVente    = TaxRate::where('type', 'tva')->where('is_active', true)->orderBy('rate')->get(['id', 'name', 'rate', 'is_default']);

        return view('ventes.devis.edit', compact('quote', 'clients', 'products', 'clientExemptions', 'taxRatesVente') + $this->maquetteFormData());
    }

    public function update(UpdateQuoteRequest $request, Quote $devis)
    {
        $data = $request->validated();
        unset($data['documents']);
        $this->service->update($devis, $data);
        $this->uploadDocuments($devis, $request);

        return redirect()
            ->route('ventes.devis.show', $devis)
            ->with('success', 'Devis mis à jour.');
    }

    public function destroy(Quote $devis)
    {
        try {
            $this->service->delete($devis);
            return redirect()
                ->route('ventes.devis.index')
                ->with('success', 'Devis supprimé.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * [VENTES-PRO] Duplique un devis (équivalent Odoo Duplicate).
     */
    public function duplicate(Quote $devis)
    {
        $this->authorize('create', Quote::class);
        $new = $this->service->duplicate($devis);
        return redirect()
            ->route('ventes.devis.edit', $new)
            ->with('success', "Devis dupliqué : {$new->number}. Modifiez puis enregistrez.");
    }

    /**
     * POST ventes/devis/{quote}/convert — transformer le devis validé en commande.
     * Requiert : sales.transform — déclenché uniquement sur devis au statut 'valide'.
     */
    public function convert(Quote $devis)
    {
        try {
            $order = $this->service->convertToOrder($devis);
            return redirect()
                ->route('ventes.commandes.show', $order)
                ->with('success', 'Devis ' . $devis->number . ' transformé en commande ' . $order->number . '.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    // ── Workflow de validation interne ────────────────────────────────────────

    /** POST ventes/devis/{devis}/submit — soumet le devis à validation interne. */
    public function submit(Request $request, Quote $devis)
    {
        $request->validate(['motif' => ['nullable', 'string', 'max:500']]);
        try {
            $this->workflow->submit($devis, $request->motif);
            return back()->with('success', "Devis {$devis->number} soumis à validation.");
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /** POST ventes/devis/{devis}/validate-internal — valide le devis en interne. */
    public function validateInternal(Request $request, Quote $devis)
    {
        $request->validate(['motif' => ['nullable', 'string', 'max:500']]);
        try {
            $this->workflow->validateQuote($devis, $request->motif);
            return back()->with('success', "Devis {$devis->number} validé avec succès.");
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (\RuntimeException $e) {
            return back()->withErrors(['devis' => $e->getMessage()])->with('error', $e->getMessage());
        }
    }

    /** POST ventes/devis/{devis}/reject-internal — refuse le devis avec motif. */
    public function rejectInternal(Request $request, Quote $devis)
    {
        $request->validate([
            'motif' => ['required', 'string', 'min:5', 'max:500'],
        ], ['motif.required' => 'Le motif de refus est obligatoire.']);
        try {
            $this->workflow->reject($devis, $request->motif);
            return back()->with('success', "Devis {$devis->number} refusé — retour en brouillon.");
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /** POST ventes/devis/{devis}/cancel-internal — annule le devis. */
    public function cancelInternal(Request $request, Quote $devis)
    {
        $request->validate([
            'motif' => ['required', 'string', 'min:5', 'max:500'],
        ], ['motif.required' => "Le motif d'annulation est obligatoire."]);
        try {
            $this->workflow->cancel($devis, $request->motif);
            return back()->with('success', "Devis {$devis->number} annulé.");
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * GET ventes/devis/{quote}/pdf — download or stream PDF.
     * Add ?preview=1 to open in browser instead of downloading.
     */
    public function pdf(Quote $devis, Request $request)
    {
        try {
            $quote    = $this->service->repository->findWithDetails($devis->id);
            $settings = currentCompany()?->documentSetting;

            $pdf = Pdf::loadView('ventes.pdf.quote', compact('quote', 'settings'))
                ->setPaper(strtolower($settings?->page_size ?? 'a4'), $settings?->orientation ?? 'portrait');

            $filename = 'Devis_' . str_replace(['/', '\\', ' '], '-', $quote->number) . '.pdf';

            return $request->boolean('preview')
                ? $pdf->stream($filename)
                : $pdf->download($filename);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('PDF devis error', ['id' => $devis->id, 'error' => $e->getMessage()]);
            return back()->with('error', 'Impossible de générer le PDF : ' . $e->getMessage());
        }
    }
}
