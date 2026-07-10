<?php

namespace App\Http\Controllers\Treasury;

use App\Helpers\NumberHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Treasury\StoreSupplierPaymentRequest;
use App\Models\CashAccount;
use App\Models\Company;
use App\Models\PaymentMethod;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Repositories\SupplierPaymentRepository;
use App\Services\SupplierPaymentService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierPaymentController extends Controller
{
    public function __construct(
        protected SupplierPaymentRepository $repository,
        protected SupplierPaymentService    $service,
    ) {
        $this->middleware('can:payments.view')->only(['index', 'show']);
        $this->middleware('can:payments.create')->except(['index', 'show', 'approve', 'reject']);
        $this->middleware('can:treasury.validate')->only(['approve', 'reject']);
    }

    /**
     * List all supplier payments with optional filters.
     */
    public function index(Request $request): View
    {
        $filters = $request->only(['search', 'supplier_id', 'payment_method_id', 'date_from', 'date_to']);
        $filters = array_filter($filters, fn ($v) => $v !== '' && $v !== null);

        $payments       = $this->repository->search($filters, 15);
        $suppliers      = Supplier::active()->orderBy('name')->get(['id', 'name']);
        $paymentMethods = PaymentMethod::where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'type']);

        // ── Totaux agrégés sur l'ensemble des filtres ──
        $totalsQuery = SupplierPayment::query()
            ->when(!empty($filters['supplier_id']),      fn($q) => $q->where('supplier_id', $filters['supplier_id']))
            ->when(!empty($filters['payment_method_id']),fn($q) => $q->where('payment_method_id', $filters['payment_method_id']))
            ->when(!empty($filters['date_from']),         fn($q) => $q->whereDate('payment_date', '>=', $filters['date_from']))
            ->when(!empty($filters['date_to']),           fn($q) => $q->whereDate('payment_date', '<=', $filters['date_to']))
            ->when(!empty($filters['search']),            fn($q) => $q->where(fn($sq) =>
                $sq->where('reference', 'like', '%'.$filters['search'].'%')
                    ->orWhereHas('supplier', fn($s) => $s->where('name', 'like', '%'.$filters['search'].'%'))
            ));

        $summary = [
            'total_amount' => (int) $totalsQuery->sum('amount'),
            'count'        => (int) (clone $totalsQuery)->count(),
            'this_month'   => (int) (clone $totalsQuery)->whereMonth('payment_date', now()->month)->whereYear('payment_date', now()->year)->sum('amount'),
        ];

        return view('tresorerie.decaissements.index', compact('payments', 'filters', 'suppliers', 'paymentMethods', 'summary'));
    }

    /**
     * Show the payment creation form.
     */
    public function create(Request $request)
    {
        $suppliers      = Supplier::active()->orderBy('name')->get(['id', 'name']);
        $paymentMethods = PaymentMethod::where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'type', 'requires_reference', 'is_mobile_money']);
        $cashAccounts   = CashAccount::where('is_active', true)->orderBy('name')->get(['id', 'name', 'type', 'current_balance']);
        $selectedSupplier = $request->query('supplier_id');

        // [PENDING-PAYMENT-GUARD-UI] Bloque la création si le fournisseur a déjà
        // des décaissements non imputés en attente.
        if ($selectedSupplier) {
            $pending = $this->checkPendingUnallocated((int) $selectedSupplier);
            if ($pending) {
                return redirect()
                    ->route('tresorerie.decaissements.show', $pending['payment_id'])
                    ->with('error', $pending['message']);
            }
        }

        return view('tresorerie.decaissements.create', compact('suppliers', 'paymentMethods', 'cashAccounts', 'selectedSupplier'));
    }

    private function checkPendingUnallocated(int $supplierId): ?array
    {
        $pending = \App\Models\SupplierPayment::where('supplier_id', $supplierId)
            ->where('unallocated_amount', '>', 0)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get(['id', 'number', 'amount', 'unallocated_amount']);

        if ($pending->isEmpty()) return null;

        $supplier = Supplier::find($supplierId);
        $totalPending = $pending->sum('unallocated_amount');
        $count        = $pending->count();
        $first        = $pending->first();

        return [
            'payment_id' => $first->id,
            'message'    => sprintf(
                "🔒 Création de décaissement bloquée pour %s : %d décaissement(s) non imputé(s) en attente "
                . "totalisant %s FCFA (le plus ancien : %s). "
                . "Imputez d'abord ce(s) décaissement(s) sur ses factures à payer avant d'en saisir un nouveau.",
                $supplier?->name ?? 'ce fournisseur',
                $count,
                number_format($totalPending, 0, ',', ' '),
                $first->number
            ),
        ];
    }

    /**
     * Store a new supplier payment.
     */
    public function store(StoreSupplierPaymentRequest $request): RedirectResponse
    {
        try {
            $payment = $this->service->create($request->validated());
        } catch (\RuntimeException $e) {
            // [GUARD-UX] Anti-doublon / facture verrouillée : message clair au lieu de 500.
            return back()->withInput()->with('error', $e->getMessage());
        }

        // [PARITÉ SAGE X3] Pièces jointes du décaissement (post-création, additif).
        foreach ((array) $request->file('documents', []) as $file) {
            $path = $file->store('attachments/supplier_payment/'.$payment->id, 'local');
            $payment->attachments()->create([
                'disk'        => 'local',
                'path'        => $path,
                'filename'    => $file->getClientOriginalName(),
                'mime_type'   => $file->getMimeType(),
                'size'        => $file->getSize(),
                'uploaded_by' => \Illuminate\Support\Facades\Auth::id(),
            ]);
        }

        // [SAGE X3] « Enregistrer et créer » : rebascule sur un formulaire vierge.
        if ($request->boolean('save_and_new')) {
            return redirect()
                ->route('tresorerie.decaissements.create')
                ->with('success', 'Décaissement '.$payment->number.' enregistré. Nouvelle saisie.');
        }

        return redirect()
            ->route('tresorerie.decaissements.show', $payment)
            ->with('success', 'Décaissement enregistré avec succès.');
    }

    /**
     * Generate and download the supplier payment receipt as PDF.
     */
    public function recu(SupplierPayment $decaissement): mixed
    {
        $payment       = $this->repository->findWithDetails($decaissement->id);
        $company       = currentCompany();
        $amountInWords = NumberHelper::toWords((int) $payment->amount);

        $pdf = Pdf::loadView('tresorerie.decaissements.pdf.receipt', compact('payment', 'company', 'amountInWords'))
            ->setPaper([0, 0, 419.53, 595.28]); // A5 portrait

        return $pdf->download('recu_dec_' . $payment->number . '_' . now()->format('Ymd') . '.pdf');
    }

    /**
     * Show a single supplier payment.
     */
    public function show(SupplierPayment $decaissement): View
    {
        $payment = $this->repository->findWithDetails($decaissement->id);

        return view('tresorerie.decaissements.show', compact('payment'));
    }

    /**
     * Annule un décaissement (contre-passation comptable + restauration facture).
     * Le motif est obligatoire pour traçabilité comptable.
     */
    public function cancel(Request $request, SupplierPayment $decaissement): RedirectResponse
    {
        $this->authorize('viewAny', SupplierPayment::class);  // permission gérée par groupe route

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'reason.required' => 'Le motif d\'annulation est obligatoire (traçabilité comptable).',
            'reason.min'      => 'Le motif doit faire au moins 5 caractères.',
        ]);

        try {
            $this->service->cancel($decaissement, $data['reason']);
            return redirect()
                ->route('tresorerie.decaissements.show', $decaissement)
                ->with('success', 'Décaissement ' . $decaissement->number . ' annulé. Contre-passation comptable et restauration de la facture effectuées.');
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * [TRESO-WORKFLOW] Valide un décaissement en attente (selon seuil).
     */
    public function approve(SupplierPayment $decaissement): RedirectResponse
    {
        try {
            $this->service->approve($decaissement);
            return back()->with('success', "Décaissement {$decaissement->number} validé et comptabilisé.");
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * [TRESO-WORKFLOW] Rejette un décaissement en attente (motif obligatoire).
     */
    public function reject(Request $request, SupplierPayment $decaissement): RedirectResponse
    {
        $data = $request->validate([
            'motif' => ['required', 'string', 'min:5', 'max:500'],
        ], ['motif.required' => 'Le motif de rejet est obligatoire.']);

        try {
            $this->service->reject($decaissement, $data['motif']);
            return back()->with('success', "Décaissement {$decaissement->number} rejeté.");
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * AJAX: return unpaid invoices for a given supplier_id.
     */
    public function getInvoices(Request $request): JsonResponse
    {
        $supplierId = (int) $request->query('supplier_id');

        if (!$supplierId) {
            return response()->json([]);
        }

        $invoices = $this->service->getSupplierUnpaidInvoices($supplierId);

        return response()->json($invoices->map(fn ($inv) => [
            'id'                      => $inv->id,
            'number'                  => $inv->number,
            'supplier_invoice_number' => $inv->supplier_invoice_number,
            'received_at'             => $inv->received_at?->format('d/m/Y'),
            'due_at'                  => $inv->due_at?->format('d/m/Y'),
            'total_ttc'               => $inv->total_ttc,
            'remaining_amount'        => $inv->remaining_amount,
            'status'                  => $inv->status,
        ]));
    }
}
