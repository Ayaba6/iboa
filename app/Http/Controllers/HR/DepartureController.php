<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeDeparture;
use App\Services\DepartureService;
use Illuminate\Http\Request;

/**
 * [RH-13] Départs & solde de tout compte.
 */
class DepartureController extends Controller
{
    public function __construct(private DepartureService $service) {}

    public function index(Request $request)
    {
        $departures = EmployeeDeparture::with('employee')
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->input('type')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->orderByDesc('effective_date')->orderByDesc('id')
            ->paginate(25)->withQueryString();

        return view('rh.departs.index', compact('departures'));
    }

    public function create()
    {
        return view('rh.departs.form', [
            'departure' => new EmployeeDeparture(['effective_date' => now()->toDateString(), 'status' => 'declare']),
            'employees' => Employee::where('status', '!=', 'sorti')->orderBy('last_name')->get(['id', 'first_name', 'last_name']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['company_id'] = currentCompany()->id;
        $data['created_by'] = auth()->id();
        $departure = EmployeeDeparture::create($data);
        $this->service->refreshTotal($departure);

        return redirect()->route('rh.departs.show', $departure)->with('success', 'Départ déclaré.');
    }

    public function show(EmployeeDeparture $depart)
    {
        $depart->load('employee');

        return view('rh.departs.show', ['departure' => $depart]);
    }

    public function update(Request $request, EmployeeDeparture $depart)
    {
        abort_if($depart->status === 'cloture', 403, 'Départ clôturé.');
        $depart->update($this->validated($request));
        $this->service->refreshTotal($depart);

        return back()->with('success', 'Départ mis à jour.');
    }

    public function finalize(EmployeeDeparture $depart)
    {
        abort_if($depart->status === 'cloture', 403);
        $this->service->finalize($depart);

        return back()->with('success', 'Départ clôturé — STC '.number_format((float) $depart->fresh()->total_stc, 0, ',', ' ').' F. Salarié marqué sorti.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'employee_id'          => ['required', 'exists:employees,id'],
            'type'                 => ['required', 'in:'.implode(',', array_keys(EmployeeDeparture::TYPES))],
            'notice_start'         => ['nullable', 'date'],
            'notice_days'          => ['nullable', 'integer', 'min:0'],
            'effective_date'       => ['required', 'date'],
            'reason'               => ['nullable', 'string', 'max:1000'],
            'severance_amount'     => ['nullable', 'numeric', 'min:0'],
            'notice_amount'        => ['nullable', 'numeric', 'min:0'],
            'leave_balance_days'   => ['nullable', 'numeric', 'min:0'],
            'leave_balance_amount' => ['nullable', 'numeric', 'min:0'],
            'other_amount'         => ['nullable', 'numeric', 'min:0'],
            'equipment_returned'   => ['boolean'],
            'documents_issued'     => ['boolean'],
            'notes'                => ['nullable', 'string', 'max:1000'],
        ]);
        $data['equipment_returned'] = $request->boolean('equipment_returned');
        $data['documents_issued']   = $request->boolean('documents_issued');
        foreach (['severance_amount', 'notice_amount', 'leave_balance_days', 'leave_balance_amount', 'other_amount'] as $f) {
            $data[$f] = $data[$f] ?? 0;
        }

        return $data;
    }
}
