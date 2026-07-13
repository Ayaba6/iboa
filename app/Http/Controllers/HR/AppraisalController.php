<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Appraisal;
use App\Models\Employee;
use App\Services\AppraisalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * [RH-11] Évaluations & performance.
 */
class AppraisalController extends Controller
{
    public function __construct(private AppraisalService $service) {}

    public function index(Request $request)
    {
        $appraisals = Appraisal::with('employee')
            ->when($request->filled('employee_id'), fn ($q) => $q->where('employee_id', $request->input('employee_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('year'), fn ($q) => $q->where('period_year', $request->input('year')))
            ->orderByDesc('period_year')->orderByDesc('id')
            ->paginate(25)->withQueryString();

        $employees = Employee::orderBy('last_name')->get(['id', 'first_name', 'last_name']);

        return view('rh.evaluations.index', compact('appraisals', 'employees'));
    }

    public function create()
    {
        return view('rh.evaluations.form', [
            'employees' => Employee::orderBy('last_name')->get(['id', 'first_name', 'last_name']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id'    => ['required', 'exists:employees,id'],
            'campaign'       => ['required', 'string', 'max:120'],
            'period_year'    => ['required', 'integer', 'min:2020', 'max:2100'],
            'evaluator_name' => ['nullable', 'string', 'max:120'],
            'objectives'     => ['nullable', 'string', 'max:2000'],
            'criteria'       => ['array'],
            'criteria.*.label'  => ['nullable', 'string', 'max:150'],
            'criteria.*.weight' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $appraisal = DB::transaction(function () use ($request, $data) {
            $appraisal = Appraisal::create([
                'company_id'     => currentCompany()->id,
                'employee_id'    => $data['employee_id'],
                'campaign'       => $data['campaign'],
                'period_year'    => $data['period_year'],
                'evaluator_name' => $data['evaluator_name'] ?? null,
                'objectives'     => $data['objectives'] ?? null,
                'status'         => 'auto_evaluation',
                'created_by'     => auth()->id(),
            ]);
            foreach (collect($request->input('criteria', []))->filter(fn ($r) => filled($r['label'] ?? null))->values() as $i => $r) {
                $appraisal->criteria()->create([
                    'sort_order' => $i + 1,
                    'label'      => $r['label'],
                    'weight'     => (int) ($r['weight'] ?? 1) ?: 1,
                ]);
            }

            return $appraisal;
        });

        return redirect()->route('rh.evaluations.show', $appraisal)->with('success', 'Évaluation créée — renseignez les notes.');
    }

    public function show(Appraisal $evaluation)
    {
        $evaluation->load(['employee', 'criteria']);

        return view('rh.evaluations.show', ['appraisal' => $evaluation]);
    }

    public function update(Request $request, Appraisal $evaluation)
    {
        abort_if($evaluation->status === 'finalisee', 403, 'Évaluation finalisée.');

        $data = $request->validate([
            'evaluator_name' => ['nullable', 'string', 'max:120'],
            'action_plan'    => ['nullable', 'string', 'max:2000'],
            'bonus_amount'   => ['nullable', 'numeric', 'min:0'],
            'comments'       => ['nullable', 'string', 'max:2000'],
            'criteria'       => ['array'],
            'criteria.*.id'             => ['required', 'integer'],
            'criteria.*.self_rating'    => ['nullable', 'numeric', 'min:0', 'max:5'],
            'criteria.*.manager_rating' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'criteria.*.comment'        => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($evaluation, $data, $request) {
            $evaluation->update([
                'evaluator_name' => $data['evaluator_name'] ?? $evaluation->evaluator_name,
                'action_plan'    => $data['action_plan'] ?? null,
                'bonus_amount'   => $data['bonus_amount'] ?? null,
                'comments'       => $data['comments'] ?? null,
                'status'         => 'evaluation_manager',
            ]);
            foreach ($request->input('criteria', []) as $row) {
                $evaluation->criteria()->where('id', $row['id'])->update([
                    'self_rating'    => ($row['self_rating'] ?? '') === '' ? null : $row['self_rating'],
                    'manager_rating' => ($row['manager_rating'] ?? '') === '' ? null : $row['manager_rating'],
                    'comment'        => $row['comment'] ?? null,
                ]);
            }
            $this->service->recompute($evaluation->fresh());
        });

        return redirect()->route('rh.evaluations.show', $evaluation)->with('success', 'Notes enregistrées.');
    }

    public function finalize(Appraisal $evaluation)
    {
        abort_if($evaluation->status === 'finalisee', 403);
        $this->service->finalize($evaluation);

        return back()->with('success', 'Évaluation finalisée — note '.number_format((float) $evaluation->fresh()->overall_score, 2, ',', ' ').'/5 ('.$evaluation->fresh()->ratingLabel().').');
    }
}
