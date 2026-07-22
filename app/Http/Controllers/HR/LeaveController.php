<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeaveController extends Controller
{
    // ── Types de congés ────────────────────────────────────────────────────────

    public function indexTypes()
    {
        $company = currentCompany();
        $types   = LeaveType::where('company_id', $company->id)->orderBy('name')->get();
        return view('rh.conges.types', compact('types'));
    }

    public function storeType(Request $request)
    {
        $company = currentCompany();
        $data    = $request->validate([
            'name'              => ['required', 'string', 'max:100'],
            'code'              => ['required', 'string', 'max:20'],
            'days_per_year'     => ['required', 'numeric', 'min:0', 'max:365'],
            'is_paid'           => ['boolean'],
            'deduct_from_salary'=> ['boolean'],
            'color'             => ['nullable', 'string', 'max:20'],
        ]);

        LeaveType::create(array_merge($data, [
            'company_id'        => $company->id,
            'is_paid'           => $request->boolean('is_paid', true),
            'deduct_from_salary'=> $request->boolean('deduct_from_salary', false),
        ]));

        return back()->with('success', 'Type de congé créé.');
    }

    // ── Demandes de congés ─────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $company = currentCompany();
        $filters = $request->only(['employee_id', 'status', 'year']);

        $empIds = Employee::where('company_id', $company->id)->pluck('id');

        $query = LeaveRequest::with(['employee', 'leaveType', 'approvedBy'])
            ->whereIn('employee_id', $empIds)
            ->orderByDesc('start_date');

        if ($filters['employee_id'] ?? null) $query->where('employee_id', $filters['employee_id']);
        if ($filters['status'] ?? null)      $query->where('status', $filters['status']);
        if ($filters['year'] ?? null)        $query->whereYear('start_date', $filters['year']);

        $requests  = $query->paginate(20)->withQueryString();
        $employees = Employee::where('company_id', $company->id)->active()->orderBy('last_name')->get();
        $types     = LeaveType::where('company_id', $company->id)->where('is_active', true)->get();

        return view('rh.conges.index', compact('requests', 'employees', 'types', 'filters'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id'   => ['required', 'exists:employees,id'],
            'leave_type_id' => ['required', 'exists:leave_types,id'],
            'start_date'    => ['required', 'date'],
            'end_date'      => ['required', 'date', 'gte:start_date'],
            'reason'        => ['nullable', 'string'],
        ]);

        // [GARDE chevauchement] Refuser une demande qui recouvre un congé déjà
        // en attente ou approuvé du même employé (double décompte de solde).
        $overlap = LeaveRequest::where('employee_id', $data['employee_id'])
            ->whereIn('status', ['en_attente', 'approuve'])
            ->where('start_date', '<=', $data['end_date'])
            ->where('end_date', '>=', $data['start_date'])
            ->exists();
        if ($overlap) {
            return back()->with('error', 'Une demande de congé en attente ou approuvée chevauche déjà cette période pour cet employé.')->withInput();
        }

        // Calcul jours ouvrés (hors samedi/dimanche)
        $start = \Carbon\Carbon::parse($data['start_date']);
        $end   = \Carbon\Carbon::parse($data['end_date']);
        $days  = 0;
        $curr  = $start->copy();
        while ($curr->lte($end)) {
            if (!$curr->isWeekend()) $days++;
            $curr->addDay();
        }

        LeaveRequest::create(array_merge($data, [
            'days'       => $days,
            'status'     => 'en_attente',
            'created_by' => auth()->id(),
        ]));

        return back()->with('success', "Demande de congé créée ({$days} jour(s) ouvré(s)).");
    }

    public function approve(LeaveRequest $leave)
    {
        if ($leave->status !== 'en_attente') {
            return back()->with('error', 'Cette demande ne peut plus être approuvée.');
        }

        // [GARDE chevauchement] Re-vérifier contre les congés APPROUVÉS (une autre
        // demande chevauchante a pu être approuvée entre-temps).
        $overlap = LeaveRequest::where('employee_id', $leave->employee_id)
            ->where('id', '!=', $leave->id)
            ->where('status', 'approuve')
            ->where('start_date', '<=', $leave->end_date)
            ->where('end_date', '>=', $leave->start_date)
            ->exists();
        if ($overlap) {
            return back()->with('error', 'Un congé approuvé chevauche déjà cette période — approbation refusée.');
        }

        // [FIX-RH-SOLDE] Vérifier le solde disponible avant approbation.
        $year    = $leave->start_date->year;
        $balance = LeaveBalance::firstOrCreate(
            ['employee_id'   => $leave->employee_id,
             'leave_type_id' => $leave->leave_type_id,
             'year'          => $year],
            ['entitled_days' => $leave->leaveType->days_per_year ?? 0]
        );
        $available = max(0, (float) $balance->entitled_days - (float) $balance->taken_days);

        if ($leave->days > $available) {
            return back()->with('error', sprintf(
                'Solde insuffisant pour %s : %g jour(s) demandé(s) mais seulement %g jour(s) disponible(s) sur %g acquis.',
                $leave->employee->full_name,
                $leave->days,
                $available,
                $balance->entitled_days
            ));
        }

        DB::transaction(function () use ($leave, $balance) {
            $leave->update([
                'status'      => 'approuve',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

            // Déduire du solde
            $balance->increment('taken_days', $leave->days);
        });

        return back()->with('success', sprintf(
            'Congé approuvé — %g jour(s) déduit(s) du solde de %s.',
            $leave->days,
            $leave->employee->full_name
        ));
    }

    public function refuse(LeaveRequest $leave, Request $request)
    {
        $request->validate(['notes' => ['nullable', 'string']]);
        if ($leave->status !== 'en_attente') {
            return back()->with('error', 'Cette demande ne peut plus être modifiée.');
        }
        $leave->update([
            'status'      => 'refuse',
            'notes'       => $request->notes,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);
        return back()->with('success', 'Demande refusée.');
    }

    // ── Soldes ────────────────────────────────────────────────────────────────

    public function balances(Request $request)
    {
        $company   = currentCompany();
        $year      = $request->integer('year', now()->year);
        $employees = Employee::with(['leaveBalances' => fn($q) => $q->where('year', $year)->with('leaveType')])
            ->where('company_id', $company->id)
            ->active()
            ->orderBy('last_name')
            ->get();

        $types = LeaveType::where('company_id', $company->id)->where('is_active', true)->get();

        return view('rh.conges.balances', compact('employees', 'types', 'year'));
    }
}
