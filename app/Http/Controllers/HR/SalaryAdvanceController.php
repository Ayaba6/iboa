<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Employee;
use App\Models\SalaryAdvance;
use Illuminate\Http\Request;

class SalaryAdvanceController extends Controller
{
    public function index(Request $request)
    {
        $company  = currentCompany();
        $filters  = $request->only(['employee_id', 'status']);

        $query = SalaryAdvance::with(['employee', 'approvedBy', 'recoveredIn'])
            ->where('company_id', $company->id)
            ->orderByDesc('advance_date');

        if ($filters['employee_id'] ?? null) {
            $query->where('employee_id', $filters['employee_id']);
        }
        if ($filters['status'] ?? null) {
            $query->where('status', $filters['status']);
        }

        $advances  = $query->paginate(20)->withQueryString();
        $employees = Employee::where('company_id', $company->id)
            ->active()->orderBy('last_name')->get();

        return view('rh.avances.index', compact('advances', 'employees', 'filters'));
    }

    public function store(Request $request)
    {
        $company = currentCompany();
        $data    = $request->validate([
            'employee_id'  => ['required', 'exists:employees,id'],
            'amount'       => ['required', 'integer', 'min:1000'],
            'advance_date' => ['required', 'date'],
            'reason'       => ['nullable', 'string', 'max:255'],
            'notes'        => ['nullable', 'string'],
        ]);

        SalaryAdvance::create(array_merge($data, [
            'company_id' => $company->id,
            'status'     => 'en_attente',
            'created_by' => auth()->id(),
        ]));

        return back()->with('success', 'Avance enregistrée. Elle sera déduite après approbation.');
    }

    public function approve(SalaryAdvance $advance)
    {
        if ($advance->status !== 'en_attente') {
            return back()->with('error', 'Cette avance ne peut plus être approuvée.');
        }

        // [SEC-PHASE2 §6] Un salarié ne valide jamais sa propre avance
        try {
            app(\App\Services\MakerCheckerService::class)->assertNotBeneficiary(
                $advance->employee?->user_id ? (int) $advance->employee->user_id : null,
                "l'avance de {$advance->employee?->full_name}",
                $advance
            );
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $advance->update([
            'status'      => 'approuve',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);
        return back()->with('success', 'Avance approuvée. Elle sera déduite lors du prochain calcul de paie.');
    }

    public function cancel(SalaryAdvance $advance)
    {
        if (!in_array($advance->status, ['en_attente', 'approuve'])) {
            return back()->with('error', 'Cette avance ne peut plus être annulée.');
        }
        $advance->update(['status' => 'annule']);
        return back()->with('success', 'Avance annulée.');
    }
}
