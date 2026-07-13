<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\ExpenseReport;
use App\Services\ExpenseReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * [RH-09] Notes de frais.
 */
class ExpenseReportController extends Controller
{
    public function __construct(private ExpenseReportService $service) {}

    public function index(Request $request)
    {
        $reports = ExpenseReport::with('employee')
            ->when($request->filled('employee_id'), fn ($q) => $q->where('employee_id', $request->input('employee_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->orderByDesc('report_date')->orderByDesc('id')
            ->paginate(25)->withQueryString();

        $employees = Employee::orderBy('last_name')->get(['id', 'first_name', 'last_name']);
        $stats = [
            'a_approuver' => ExpenseReport::where('status', 'soumise')->count(),
            'a_rembourser' => (float) ExpenseReport::where('status', 'approuvee')->sum('total_amount'),
        ];

        return view('rh.frais.index', compact('reports', 'employees', 'stats'));
    }

    public function create()
    {
        return view('rh.frais.form', [
            'report'    => new ExpenseReport(['report_date' => now()->toDateString(), 'status' => 'brouillon']),
            'employees' => Employee::orderBy('last_name')->get(['id', 'first_name', 'last_name']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $report = DB::transaction(function () use ($request, $data) {
            $report = ExpenseReport::create($data + [
                'company_id' => currentCompany()->id,
                'status'     => 'brouillon',
                'created_by' => auth()->id(),
            ]);
            $this->syncLines($report, $request);
            $this->service->refreshTotal($report);

            return $report;
        });

        return redirect()->route('rh.frais.show', $report)->with('success', 'Note de frais créée.');
    }

    public function show(ExpenseReport $frais)
    {
        $frais->load(['employee', 'lines']);

        return view('rh.frais.show', ['report' => $frais]);
    }

    public function edit(ExpenseReport $frais)
    {
        abort_unless($frais->isEditable(), 403, 'Note de frais non modifiable.');
        $frais->load('lines');

        return view('rh.frais.form', [
            'report'    => $frais,
            'employees' => Employee::orderBy('last_name')->get(['id', 'first_name', 'last_name']),
        ]);
    }

    public function update(Request $request, ExpenseReport $frais)
    {
        abort_unless($frais->isEditable(), 403);
        DB::transaction(function () use ($request, $frais) {
            $frais->update($this->validated($request));
            $frais->lines()->delete();
            $this->syncLines($frais, $request);
            $this->service->refreshTotal($frais);
        });

        return redirect()->route('rh.frais.show', $frais)->with('success', 'Note de frais mise à jour.');
    }

    public function submit(ExpenseReport $frais)
    {
        abort_unless($frais->isEditable(), 403);
        $this->service->submit($frais);

        return back()->with('success', 'Note de frais soumise pour approbation.');
    }

    public function approve(ExpenseReport $frais)
    {
        abort_unless($frais->status === 'soumise', 403);
        $this->service->approve($frais, auth()->id());

        return back()->with('success', 'Note de frais approuvée.');
    }

    public function reject(Request $request, ExpenseReport $frais)
    {
        abort_unless($frais->status === 'soumise', 403);
        $this->service->reject($frais, $request->input('reject_reason'));

        return back()->with('success', 'Note de frais rejetée.');
    }

    public function reimburse(Request $request, ExpenseReport $frais)
    {
        abort_unless($frais->status === 'approuvee', 403);
        $this->service->markReimbursed($frais, $request->input('payment_method'));

        return back()->with('success', 'Note de frais marquée remboursée.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'title'       => ['required', 'string', 'max:150'],
            'report_date' => ['nullable', 'date'],
            'notes'       => ['nullable', 'string', 'max:1000'],
        ]);
    }

    private function syncLines(ExpenseReport $report, Request $request): void
    {
        $rows = collect($request->input('lines', []))
            ->filter(fn ($r) => (float) ($r['amount'] ?? 0) > 0)->values();

        foreach ($rows as $i => $r) {
            $report->lines()->create([
                'sort_order'   => $i + 1,
                'expense_date' => ($r['expense_date'] ?? '') ?: null,
                'category'     => $r['category'] ?? 'autre',
                'description'  => $r['description'] ?? null,
                'amount'       => (float) ($r['amount'] ?? 0),
                'tax_amount'   => (float) ($r['tax_amount'] ?? 0),
                'has_receipt'  => ! empty($r['has_receipt']),
            ]);
        }
    }
}
