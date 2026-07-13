<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\CareerEvent;
use App\Models\Department;
use App\Models\Employee;
use App\Models\JobPosition;
use App\Services\CareerMovementService;
use Illuminate\Http\Request;

/**
 * [RH-05] Mouvements et carrière des salariés.
 */
class CareerEventController extends Controller
{
    public function __construct(private CareerMovementService $service) {}

    public function index(Request $request)
    {
        $events = CareerEvent::with(['employee', 'toJobPosition', 'toDepartment'])
            ->when($request->filled('employee_id'), fn ($q) => $q->where('employee_id', $request->input('employee_id')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->input('type')))
            ->orderByDesc('effective_date')->orderByDesc('id')
            ->paginate(25)->withQueryString();

        $employees = Employee::orderBy('last_name')->get(['id', 'first_name', 'last_name']);

        return view('rh.carriere.index', compact('events', 'employees'));
    }

    public function create(Request $request)
    {
        return view('rh.carriere.form', [
            'event'       => new CareerEvent(['effective_date' => now()->toDateString()]),
            'employees'   => Employee::orderBy('last_name')->get(['id', 'first_name', 'last_name']),
            'positions'   => JobPosition::orderBy('name')->get(['id', 'name']),
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'selected'    => $request->input('employee_id'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id'        => ['required', 'exists:employees,id'],
            'type'               => ['required', 'in:'.implode(',', array_keys(CareerEvent::TYPES))],
            'effective_date'     => ['required', 'date'],
            'to_job_position_id' => ['nullable', 'exists:job_positions,id'],
            'to_department_id'   => ['nullable', 'exists:departments,id'],
            'to_category'        => ['nullable', 'string', 'max:60'],
            'to_fonction'        => ['nullable', 'string', 'max:120'],
            'grade'              => ['nullable', 'string', 'max:60'],
            'manager_name'       => ['nullable', 'string', 'max:120'],
            'site'               => ['nullable', 'string', 'max:60'],
            'cost_center'        => ['nullable', 'string', 'max:60'],
            'salary'             => ['nullable', 'numeric', 'min:0'],
            'reason'             => ['nullable', 'string', 'max:1000'],
            'notes'              => ['nullable', 'string', 'max:1000'],
        ]);

        $employee = Employee::findOrFail($data['employee_id']);
        $event = $this->service->record($employee, $data);

        $msg = $event->applied
            ? 'Mouvement enregistré et appliqué à la fiche salarié.'
            : 'Mouvement enregistré (prise d\'effet future — non encore appliqué).';

        return redirect()->route('rh.carriere.index', ['employee_id' => $employee->id])->with('success', $msg);
    }
}
