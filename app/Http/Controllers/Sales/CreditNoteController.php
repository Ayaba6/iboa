<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sale\StoreCreditNoteRequest;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Services\CommercialWorkflowService;
use App\Services\CreditNoteService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class CreditNoteController extends Controller
{
    public function __construct(
        private CreditNoteService           $service,
        private CommercialWorkflowService   $workflow,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', CreditNote::class);
        $filters    = $request->only(['search', 'status', 'client_id']);
        $creditNotes = $this->service->repository->search($filters, 15);

        // ── Totaux agrégés ──
        $company = currentCompany();
        $totalsQuery = CreditNote::where('company_id', $company->id)
            ->when(!empty($filters['client_id']), fn($q) => $q->where('client_id', $filters['client_id']))
            ->when(!empty($filters['status']),    fn($q) => $q->where('status', $filters['status']))
            ->when(!empty($filters['search']),    fn($q) => $q->where(fn($sq) =>
                $sq->where('number', 'like', '%'.$filters['search'].'%')
                    ->orWhereHas('client', fn($c) => $c->where('name', 'like', '%'.$filters['search'].'%'))
            ));

        $summary = [
            'total_ttc'        => (int) $totalsQuery->sum('total_ttc'),
            'remaining_credit' => (int) (clone $totalsQuery)->sum('remaining_credit'),
            'count_used'       => (int) (clone $totalsQuery)->where('status', 'utilise')->count(),
            'count_pending'    => (int) (clone $totalsQuery)->whereIn('status', ['emis', 'partiel'])->count(),
        ];

        return view('ventes.avoirs.index', compact('creditNotes', 'filters', 'summary'));
    }

    /**
     * GET ventes/avoirs/create?invoice_id=X
     * Pre-loads invoice items to prefill form.
     */
    public function create(Request $request)
    {
        $this->authorize('create', CreditNote::class);
        $invoiceId = $request->query('invoice_id');
        $invoice   = $invoiceId ? Invoice::with('items.product', 'client')->findOrFail($invoiceId) : null;

        return view('ventes.avoirs.create', compact('invoice'));
    }

    public function store(StoreCreditNoteRequest $request)
    {
        $this->authorize('create', CreditNote::class);
        $invoice    = Invoice::findOrFail($request->invoice_id);
        $creditNote = $this->service->createFromInvoice($invoice, $request->validated());

        return redirect()
            ->route('ventes.avoirs.show', $creditNote)
            ->with('success', 'Avoir ' . $creditNote->number . ' créé avec succès.');
    }

    public function show(CreditNote $avoir)
    {
        $this->authorize('view', $avoir);
        $creditNote = $this->service->repository->findWithDetails($avoir->id);
        return view('ventes.avoirs.show', compact('creditNote'));
    }

    public function validateNote(CreditNote $avoir)
    {
        $this->authorize('validate', $avoir);
        try {
            $creditNote = $this->service->validate($avoir);
            return redirect()
                ->route('ventes.avoirs.show', $creditNote)
                ->with('success', 'Avoir ' . $creditNote->number . ' validé.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function applyToInvoice(CreditNote $avoir)
    {
        $this->authorize('update', $avoir);
        try {
            $this->service->applyToInvoice($avoir);
            return back()->with('success', 'Avoir appliqué à la facture avec succès.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function destroy(CreditNote $avoir)
    {
        $this->authorize('delete', $avoir);
        try {
            $this->service->delete($avoir);
            return redirect()
                ->route('ventes.avoirs.index')
                ->with('success', 'Avoir supprimé.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    // ── Workflow de validation interne ────────────────────────────────────────

    public function submit(Request $request, CreditNote $avoir)
    {
        $request->validate(['motif' => ['nullable', 'string', 'max:500']]);
        try {
            $this->workflow->submit($avoir, $request->motif);
            return back()->with('success', "Avoir {$avoir->number} soumis à validation.");
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function validateInternal(Request $request, CreditNote $avoir)
    {
        $request->validate(['motif' => ['nullable', 'string', 'max:500']]);
        try {
            $this->workflow->validateCreditNote($avoir, $request->motif);
            return back()->with('success', "Avoir {$avoir->number} validé.");
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (\RuntimeException $e) {
            return back()->withErrors(['avoir' => $e->getMessage()])->with('error', $e->getMessage());
        }
    }

    public function rejectInternal(Request $request, CreditNote $avoir)
    {
        $request->validate(['motif' => ['required', 'string', 'min:5', 'max:500']],
            ['motif.required' => 'Le motif est obligatoire.']);
        try {
            $this->workflow->reject($avoir, $request->motif);
            return back()->with('success', "Avoir {$avoir->number} refusé — retour en brouillon.");
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function cancelInternal(Request $request, CreditNote $avoir)
    {
        $request->validate(['motif' => ['required', 'string', 'min:5', 'max:500']],
            ['motif.required' => "Le motif est obligatoire."]);
        try {
            $this->workflow->cancel($avoir, $request->motif);
            return back()->with('success', "Avoir {$avoir->number} annulé.");
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function pdf(CreditNote $avoir, Request $request)
    {
        $this->authorize('view', $avoir);
        $creditNote = $this->service->repository->findWithDetails($avoir->id);
        $settings   = currentCompany()?->documentSetting;

        $pdf = Pdf::loadView('ventes.pdf.credit-note', compact('creditNote', 'settings'))
            ->setPaper(strtolower($settings?->page_size ?? 'a4'), $settings?->orientation ?? 'portrait');

        $filename = 'Avoir_' . str_replace(['/', '\\', ' '], '-', $creditNote->number) . '.pdf';

        return $request->boolean('preview')
            ? $pdf->stream($filename)
            : $pdf->download($filename);
    }
}
