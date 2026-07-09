<?php

namespace App\Http\Controllers\Treasury;

use App\Http\Controllers\Controller;
use App\Models\CashAccount;
use App\Models\CashOperation;
use App\Services\CashOperationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CashOperationController extends Controller
{
    public function __construct(private CashOperationService $service) {}

    public function index(Request $request): View
    {
        $filters = $request->only(['cash_account_id', 'direction', 'from', 'to']);

        $operations = CashOperation::with(['cashAccount', 'createdBy'])
            ->when($filters['cash_account_id'] ?? null, fn ($q, $v) => $q->where('cash_account_id', $v))
            ->when($filters['direction'] ?? null, fn ($q, $v) => $q->where('direction', $v))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->whereDate('operation_date', '>=', $v))
            ->when($filters['to']   ?? null, fn ($q, $v) => $q->whereDate('operation_date', '<=', $v))
            ->orderByDesc('operation_date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $coll          = $operations->getCollection()->where('status', 'valide');
        $totalEntrees  = $coll->where('direction', 'entree')->sum('amount');
        $totalSorties  = $coll->where('direction', 'sortie')->sum('amount');

        $cashAccounts = CashAccount::where('is_active', true)->orderBy('type')->orderBy('name')->get(['id', 'name', 'type']);

        return view('tresorerie.operations.index', compact('operations', 'cashAccounts', 'filters', 'totalEntrees', 'totalSorties'));
    }

    public function create(Request $request): View
    {
        $direction    = $request->input('direction') === 'sortie' ? 'sortie' : 'entree';
        $cashAccounts = CashAccount::where('type', 'caisse')->where('is_active', true)
            ->orderBy('name')->get(['id', 'name', 'current_balance']);

        return view('tresorerie.operations.create', compact('cashAccounts', 'direction'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'cash_account_id' => ['required', 'integer', 'exists:cash_accounts,id'],
            'direction'       => ['required', 'in:entree,sortie'],
            'amount'          => ['required', 'integer', 'min:1'],
            'operation_date'  => ['required', 'date'],
            'category'        => ['nullable', 'string', 'max:100'],
            'label'           => ['nullable', 'string', 'max:255'],
            // [PARITÉ SAGE X3] Champs descriptifs
            'site'               => ['nullable', 'string', 'max:40'],
            'operation_type'     => ['nullable', 'string', 'max:40'],
            'reference'          => ['nullable', 'string', 'max:100'],
            'requester'          => ['nullable', 'string', 'max:100'],
            'cashier_name'       => ['nullable', 'string', 'max:100'],
            'currency_code'      => ['nullable', 'string', 'size:3'],
            'exchange_rate'      => ['nullable', 'numeric', 'min:0'],
            'fees'               => ['nullable', 'integer', 'min:0'],
            'value_date'         => ['nullable', 'date'],
            'general_account'    => ['nullable', 'string', 'max:20'],
            'counterpart_account' => ['nullable', 'string', 'max:20'],
            'cost_center'        => ['nullable', 'string', 'max:30'],
            'analytic_section'   => ['nullable', 'string', 'max:30'],
            'payment_method'     => ['nullable', 'string', 'max:40'],
            'comment'            => ['nullable', 'string', 'max:500'],
            'lines'              => ['nullable', 'array'],
            'documents'          => ['nullable', 'array'],
            'documents.*'        => ['file', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx', 'max:5120'],
        ]);

        try {
            $operation = $this->service->create($data);
            // [PARITÉ SAGE X3] Métadonnées descriptives (post-création — le mouvement
            // et l'écriture comptable sont déjà générés par le service à partir de amount/direction).
            $extras = collect($data)->only([
                'site','operation_type','reference','requester','cashier_name','currency_code',
                'exchange_rate','fees','value_date','general_account','counterpart_account',
                'cost_center','analytic_section','payment_method','comment','lines',
            ])->filter(fn($v) => $v !== null)->all();
            $extras['net_amount'] = (int) $data['amount'] - max(0, (int) ($data['fees'] ?? 0));
            if ($extras) $operation->update($extras);

            foreach ((array) $request->file('documents', []) as $file) {
                $path = $file->store('attachments/cash_operation/'.$operation->id, 'local');
                $operation->attachments()->create([
                    'disk' => 'local', 'path' => $path, 'filename' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(), 'size' => $file->getSize(),
                    'uploaded_by' => \Illuminate\Support\Facades\Auth::id(),
                ]);
            }

            if ($request->boolean('save_and_new')) {
                return redirect()
                    ->route('tresorerie.operations.create', ['direction' => $operation->direction])
                    ->with('success', "Opération {$operation->number} enregistrée. Nouvelle saisie.");
            }
            return redirect()
                ->route('tresorerie.operations.index')
                ->with('success', "Opération {$operation->number} ({$operation->directionLabel()}) enregistrée.");
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function cancel(Request $request, CashOperation $operation): RedirectResponse
    {
        $data = $request->validate([
            'motif' => ['required', 'string', 'min:5', 'max:500'],
        ], ['motif.required' => "Le motif d'annulation est obligatoire."]);

        try {
            $this->service->cancel($operation, $data['motif']);
            return back()->with('success', "Opération {$operation->number} annulée — solde restauré.");
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
