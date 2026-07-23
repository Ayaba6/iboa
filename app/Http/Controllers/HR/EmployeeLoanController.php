<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Models\EmployeeLoanPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * [RH-PRO] Gestion des prêts salariés.
 * Un prêt est remboursé sur plusieurs mois (≠ avance ponctuelle).
 */
class EmployeeLoanController extends Controller
{
    public function index(Request $request)
    {
        $company = currentCompany();

        $query = EmployeeLoan::with(['employee.department'])
            ->where('company_id', $company->id)
            ->orderByDesc('created_at');

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $loans = $query->paginate(20)->withQueryString();

        $employees = Employee::where('company_id', $company->id)
            ->active()
            ->orderBy('last_name')
            ->get(['id', 'last_name', 'first_name', 'matricule']);

        return view('rh.prets.index', compact('loans', 'employees'));
    }

    public function show(EmployeeLoan $pret)
    {
        $company = currentCompany();
        abort_if($pret->company_id !== $company->id, 403);

        $pret->load(['employee.department', 'payments.createdBy', 'approvedBy', 'createdBy']);

        return view('rh.prets.show', compact('pret'));
    }

    public function store(Request $request)
    {
        $company = currentCompany();

        $validated = $request->validate([
            'employee_id'       => ['required', 'exists:employees,id'],
            'amount'            => ['required', 'integer', 'min:1000'],
            'monthly_deduction' => ['required', 'integer', 'min:1000'],
            'nb_months'         => ['required', 'integer', 'min:1', 'max:60'],
            'start_date'        => ['required', 'date'],
            'reason'            => ['nullable', 'string', 'max:500'],
            'notes'             => ['nullable', 'string', 'max:1000'],
        ]);

        // Vérifier que l'employé appartient à l'entreprise
        abort_if(
            ! Employee::where('id', $validated['employee_id'])
                      ->where('company_id', $company->id)
                      ->exists(),
            403
        );

        DB::transaction(function () use ($validated, $company) {
            $year     = now()->year;
            $count    = EmployeeLoan::where('company_id', $company->id)
                            ->whereYear('created_at', $year)->count() + 1;
            $loanNum  = sprintf('PRET-%d-%03d', $year, $count);

            $nbMonths  = (int) $validated['nb_months'];
            $monthly   = (int) $validated['monthly_deduction'];
            $startDate = \Carbon\Carbon::parse($validated['start_date']);
            $endDate   = $startDate->copy()->addMonths($nbMonths - 1);

            EmployeeLoan::create([
                'company_id'        => $company->id,
                'employee_id'       => $validated['employee_id'],
                'loan_number'       => $loanNum,
                'amount'            => (int) $validated['amount'],
                'monthly_deduction' => $monthly,
                'remaining_balance' => (int) $validated['amount'],
                'nb_months'         => $nbMonths,
                'status'            => 'actif',
                'start_date'        => $startDate->toDateString(),
                'end_date'          => $endDate->toDateString(),
                'reason'            => $validated['reason'] ?? null,
                'notes'             => $validated['notes'] ?? null,
                'created_by'        => Auth::id(),
            ]);
        });

        return redirect()->route('rh.prets.index')
            ->with('success', 'Prêt enregistré avec succès.');
    }

    /** Approuver un prêt */
    public function approve(EmployeeLoan $pret)
    {
        $company = currentCompany();
        abort_if($pret->company_id !== $company->id, 403);

        // [SEC-PHASE2 §6] Un salarié ne valide jamais son propre prêt
        try {
            app(\App\Services\MakerCheckerService::class)->assertNotBeneficiary(
                $pret->employee?->user_id ? (int) $pret->employee->user_id : null,
                "le prêt de {$pret->employee?->full_name}",
                $pret
            );
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $pret->update([
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        return back()->with('success', 'Prêt approuvé.');
    }

    /** Annuler un prêt */
    public function cancel(EmployeeLoan $pret)
    {
        $company = currentCompany();
        abort_if($pret->company_id !== $company->id, 403);

        if ($pret->status !== 'actif') {
            return back()->with('error', 'Ce prêt ne peut pas être annulé.');
        }

        $pret->update(['status' => 'annule']);

        return back()->with('success', 'Prêt annulé.');
    }

    /** Enregistrer un remboursement mensuel */
    public function recordPayment(Request $request, EmployeeLoan $pret)
    {
        $company = currentCompany();
        abort_if($pret->company_id !== $company->id, 403);

        if ($pret->status !== 'actif') {
            return back()->with('error', "Ce prêt n'est pas actif.");
        }

        $validated = $request->validate([
            'amount'       => ['required', 'integer', 'min:1', 'max:' . $pret->remaining_balance],
            'period_month' => ['required', 'integer', 'min:1', 'max:12'],
            'period_year'  => ['required', 'integer', 'min:2000', 'max:2100'],
            'notes'        => ['nullable', 'string', 'max:500'],
        ]);

        // Vérifier qu'un paiement n'existe pas déjà pour ce mois
        $exists = EmployeeLoanPayment::where('employee_loan_id', $pret->id)
            ->where('period_month', $validated['period_month'])
            ->where('period_year',  $validated['period_year'])
            ->exists();

        if ($exists) {
            return back()->with('error', "Un remboursement existe déjà pour {$validated['period_month']}/{$validated['period_year']}.");
        }

        DB::transaction(function () use ($validated, $pret) {
            $amount     = (int) $validated['amount'];
            $balAfter   = max(0, $pret->remaining_balance - $amount);
            $newStatus  = $balAfter <= 0 ? 'rembourse' : 'actif';

            EmployeeLoanPayment::create([
                'employee_loan_id' => $pret->id,
                'period_month'     => $validated['period_month'],
                'period_year'      => $validated['period_year'],
                'amount'           => $amount,
                'balance_after'    => $balAfter,
                'notes'            => $validated['notes'] ?? null,
                'created_by'       => Auth::id(),
            ]);

            $pret->update([
                'remaining_balance' => $balAfter,
                'status'            => $newStatus,
            ]);
        });

        return back()->with('success', 'Remboursement enregistré.');
    }
}
