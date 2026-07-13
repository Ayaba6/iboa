<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\JobPosition;
use Illuminate\Http\Request;

/**
 * [RH-01] Référentiel des postes / grades / emplois.
 */
class JobPositionController extends Controller
{
    public function index(Request $request)
    {
        $positions = JobPosition::with('department')
            ->withCount('employees')
            ->when($request->input('q'), fn ($x, $q) => $x->where(fn ($w) => $w
                ->where('name', 'like', "%{$q}%")->orWhere('code', 'like', "%{$q}%")
                ->orWhere('grade', 'like', "%{$q}%")))
            ->when($request->filled('department_id'), fn ($x) => $x->where('department_id', $request->input('department_id')))
            ->when($request->filled('status'), fn ($x) => $x->where('is_active', $request->input('status') === 'active'))
            ->orderBy('name')
            ->paginate(20)->withQueryString();

        $departments = Department::orderBy('name')->get(['id', 'name']);

        return view('rh.postes.index', compact('positions', 'departments'));
    }

    public function create()
    {
        return view('rh.postes.form', [
            'position'    => new JobPosition(['is_active' => true]),
            'departments' => Department::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        JobPosition::create($this->validated($request));

        return redirect()->route('rh.postes.index')->with('success', 'Poste créé.');
    }

    public function show(JobPosition $poste)
    {
        $poste->load('department', 'employees.jobPosition');

        return view('rh.postes.show', ['position' => $poste]);
    }

    public function edit(JobPosition $poste)
    {
        return view('rh.postes.form', [
            'position'    => $poste,
            'departments' => Department::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, JobPosition $poste)
    {
        $poste->update($this->validated($request, $poste->id));

        return redirect()->route('rh.postes.show', $poste)->with('success', 'Poste mis à jour.');
    }

    public function destroy(JobPosition $poste)
    {
        if ($poste->employees()->exists()) {
            return back()->with('error', 'Poste occupé par des salariés — désactivez-le plutôt que de le supprimer.');
        }
        $poste->delete();

        return redirect()->route('rh.postes.index')->with('success', 'Poste supprimé.');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $unique = 'unique:job_positions,code,' . ($ignoreId ?? 'NULL') . ',id,company_id,' . currentCompany()->id;

        $data = $request->validate([
            'code'             => ['required', 'string', 'max:40', $unique],
            'name'             => ['required', 'string', 'max:150'],
            'department_id'    => ['nullable', 'exists:departments,id'],
            'grade'            => ['nullable', 'string', 'max:60'],
            'category'         => ['nullable', 'string', 'max:60'],
            'cost_center'      => ['nullable', 'string', 'max:40'],
            'headcount_target' => ['nullable', 'integer', 'min:0'],
            'salary_min'       => ['nullable', 'numeric', 'min:0'],
            'salary_max'       => ['nullable', 'numeric', 'min:0', 'gte:salary_min'],
            'description'      => ['nullable', 'string', 'max:1000'],
            'missions'         => ['nullable', 'string', 'max:2000'],
            'is_active'        => ['boolean'],
        ]);

        $data['code']      = strtoupper($data['code']);
        $data['is_active'] = $request->boolean('is_active', true);
        $data['company_id'] = currentCompany()->id;

        return $data;
    }
}
