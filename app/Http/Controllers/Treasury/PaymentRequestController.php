<?php

namespace App\Http\Controllers\Treasury;

use App\Http\Controllers\Controller;
use App\Models\CashAccount;
use App\Models\PaymentMethod;
use App\Models\PaymentRequest;
use App\Models\Supplier;
use App\Services\PaymentRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentRequestController extends Controller
{
    public function __construct(private PaymentRequestService $service) {}

    public function index(Request $request): View
    {
        $requests = PaymentRequest::with(['supplier', 'requestedBy', 'validatedBy'])
            ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        $stats = [
            'soumises'        => PaymentRequest::where('status', 'soumis')->count(),
            'a_payer_count'   => PaymentRequest::where('status', 'valide')->count(),
            'a_payer_montant' => (int) PaymentRequest::where('status', 'valide')->sum('amount'),
            'payees'          => PaymentRequest::where('status', 'paye')->count(),
        ];

        return view('tresorerie.demandes.index', compact('requests', 'stats'));
    }

    public function create(): View
    {
        $suppliers      = Supplier::orderBy('name')->get(['id', 'name']);
        $paymentMethods = PaymentMethod::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('tresorerie.demandes.create', compact('suppliers', 'paymentMethods'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        // [PARITÉ SAGE X3] Net à payer = montant demandé + frais divers (informatif,
        // recalculé serveur ; le workflow reste basé sur `amount`).
        $data['net_amount'] = (int) $data['amount'] + max(0, (int) ($data['misc_fees'] ?? 0));
        unset($data['documents']);

        $pr = $this->service->create($data);

        // Pièces justificatives (post-création, additif).
        foreach ((array) $request->file('documents', []) as $file) {
            $path = $file->store('attachments/payment_request/'.$pr->id, 'local');
            $pr->attachments()->create([
                'disk' => 'local', 'path' => $path,
                'filename' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(), 'size' => $file->getSize(),
                'uploaded_by' => \Illuminate\Support\Facades\Auth::id(),
            ]);
        }

        if ($request->boolean('save_and_new')) {
            return redirect()->route('tresorerie.demandes.create')
                ->with('success', "Demande {$pr->number} créée. Nouvelle saisie.");
        }

        return redirect()->route('tresorerie.demandes.show', $pr)
            ->with('success', "Demande {$pr->number} créée (brouillon).");
    }

    public function show(PaymentRequest $demande): View
    {
        $demande->load(['supplier', 'paymentMethod', 'supplierInvoice', 'supplierPayment', 'requestedBy', 'validatedBy']);
        $cashAccounts = CashAccount::where('is_active', true)->orderBy('name')->get(['id', 'name', 'type']);
        $paymentMethods = PaymentMethod::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        return view('tresorerie.demandes.show', compact('demande', 'cashAccounts', 'paymentMethods'));
    }

    public function submit(PaymentRequest $demande): RedirectResponse
    {
        return $this->run(fn () => $this->service->submit($demande), $demande, 'soumise');
    }

    public function approve(PaymentRequest $demande): RedirectResponse
    {
        return $this->run(fn () => $this->service->approve($demande), $demande, 'validée');
    }

    public function reject(Request $request, PaymentRequest $demande): RedirectResponse
    {
        $data = $request->validate([
            'motif' => ['required', 'string', 'min:5', 'max:500'],
        ], ['motif.required' => 'Le motif de rejet est obligatoire.']);

        return $this->run(fn () => $this->service->reject($demande, $data['motif']), $demande, 'rejetée');
    }

    public function pay(Request $request, PaymentRequest $demande): RedirectResponse
    {
        $data = $request->validate([
            'cash_account_id'   => ['required', 'integer', 'exists:cash_accounts,id'],
            'payment_method_id' => ['nullable', 'integer', 'exists:payment_methods,id'],
            'payment_date'      => ['required', 'date'],
        ]);

        if (! $demande->supplier_id) {
            return back()->with('error', "Cette demande n'est liée à aucun fournisseur enregistré — paiement manuel requis.");
        }

        try {
            $this->service->pay($demande, $data);
            return back()->with('success', "Demande {$demande->number} payée (décaissement généré).");
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function run(\Closure $action, PaymentRequest $demande, string $verb): RedirectResponse
    {
        try {
            $action();
            return back()->with('success', "Demande {$demande->number} {$verb}.");
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'supplier_id'         => ['nullable', 'integer', 'exists:suppliers,id'],
            'supplier_invoice_id' => ['nullable', 'integer', 'exists:supplier_invoices,id'],
            'payment_method_id'   => ['nullable', 'integer', 'exists:payment_methods,id'],
            'object'              => ['required', 'string', 'max:255'],
            'beneficiary'         => ['nullable', 'string', 'max:150'],
            'amount'              => ['required', 'integer', 'min:1'],
            'due_date'            => ['nullable', 'date'],
            'priority'            => ['required', 'in:basse,normale,haute,urgente'],
            'notes'               => ['nullable', 'string', 'max:1000'],
            // [PARITÉ SAGE X3] Champs descriptifs
            'site'                => ['nullable', 'string', 'max:40'],
            'service'             => ['nullable', 'string', 'max:60'],
            'request_type'        => ['nullable', 'string', 'max:30'],
            'internal_reference'  => ['nullable', 'string', 'max:100'],
            'amount_ht'           => ['nullable', 'integer', 'min:0'],
            'tax_amount'          => ['nullable', 'integer', 'min:0'],
            'misc_fees'           => ['nullable', 'integer', 'min:0'],
            'bank_account'        => ['nullable', 'string', 'max:50'],
            'cost_center'         => ['nullable', 'string', 'max:30'],
            'analytic_section'    => ['nullable', 'string', 'max:30'],
            'documents'           => ['nullable', 'array'],
            'documents.*'         => ['file', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx', 'max:5120'],
        ]);
    }
}
