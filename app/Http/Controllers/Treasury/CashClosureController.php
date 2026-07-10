<?php

namespace App\Http\Controllers\Treasury;

use App\Http\Controllers\Controller;
use App\Models\CashAccount;
use App\Models\CashClosure;
use App\Services\CashClosureService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CashClosureController extends Controller
{
    public function __construct(private CashClosureService $service) {}

    public function index(Request $request): View
    {
        $filters = $request->only(['cash_account_id', 'status']);

        $closures = CashClosure::with(['cashAccount', 'createdBy', 'validatedBy'])
            ->when($filters['cash_account_id'] ?? null, fn ($q, $v) => $q->where('cash_account_id', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->orderByDesc('closure_date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $stats = [
            'brouillons'    => CashClosure::where('status', 'brouillon')->count(),
            'validees'      => CashClosure::where('status', 'valide')->count(),
            'ecart_manque'  => (int) CashClosure::where('status', 'valide')->where('difference', '<', 0)->sum('difference'),
            'ecart_excedent'=> (int) CashClosure::where('status', 'valide')->where('difference', '>', 0)->sum('difference'),
        ];

        $cashAccounts = CashAccount::where('type', 'caisse')->where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('tresorerie.clotures.index', compact('closures', 'cashAccounts', 'filters', 'stats'));
    }

    public function create(Request $request): View
    {
        // Seules les caisses (classe 57) se clôturent — pas les comptes bancaires.
        $cashAccounts = CashAccount::where('type', 'caisse')->where('is_active', true)
            ->orderBy('name')->get(['id', 'name', 'current_balance']);

        return view('tresorerie.clotures.create', compact('cashAccounts'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'cash_account_id'   => ['required', 'integer', 'exists:cash_accounts,id'],
            'closure_date'      => ['required', 'date'],
            'counted_balance'   => ['required', 'integer', 'min:0'],
            'difference_reason' => ['nullable', 'string', 'max:1000'],
            'notes'             => ['nullable', 'string', 'max:1000'],
            // [Parité SAGE X3] entête + billetage
            'site'            => ['nullable', 'string', 'max:40'],
            'cashier_name'    => ['nullable', 'string', 'max:100'],
            'supervisor_name' => ['nullable', 'string', 'max:100'],
            'currency_code'   => ['nullable', 'string', 'size:3'],
            'denominations'   => ['nullable', 'array'],
            'denominations.*' => ['nullable', 'integer', 'min:0'],
            'documents'       => ['nullable', 'array'],
            'documents.*'     => ['file', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx', 'max:5120'],
        ]);

        // Billetage : ne conserver que les coupures réellement comptées.
        if (isset($data['denominations'])) {
            $data['denominations'] = array_filter($data['denominations'], fn ($q) => (int) $q > 0) ?: null;
        }
        unset($data['documents']);

        try {
            $closure = $this->service->create($data);

            foreach ((array) $request->file('documents', []) as $file) {
                $path = $file->store('attachments/cashclosure/' . $closure->id, 'local');
                $closure->attachments()->create([
                    'disk' => 'local', 'path' => $path,
                    'filename' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(), 'size' => $file->getSize(),
                    'uploaded_by' => auth()->id(),
                ]);
            }

            if ($request->boolean('save_and_new')) {
                return redirect()->route('tresorerie.clotures.create')
                    ->with('success', "Clôture {$closure->number} enregistrée — nouvelle clôture.");
            }

            return redirect()
                ->route('tresorerie.clotures.show', $closure)
                ->with('success', "Clôture {$closure->number} enregistrée (brouillon). Validez-la pour comptabiliser l'écart.");
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show(CashClosure $cloture): View
    {
        $cloture->load(['cashAccount', 'createdBy', 'validatedBy', 'journalEntry']);
        return view('tresorerie.clotures.show', compact('cloture'));
    }

    public function validateClosure(Request $request, CashClosure $cloture): RedirectResponse
    {
        // Motif d'écart saisissable au moment de la validation
        if ($request->filled('difference_reason')) {
            $cloture->update(['difference_reason' => $request->input('difference_reason')]);
        }

        try {
            $this->service->validateClosure($cloture);
            return back()->with('success', "Clôture {$cloture->number} validée.");
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
