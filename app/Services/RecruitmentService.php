<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\JobCandidate;
use App\Models\Recruitment;
use Illuminate\Support\Facades\DB;

/**
 * [RH-03] Recrutement & onboarding : pipeline candidat + embauche
 * (création automatique de la fiche salarié à partir d'un candidat retenu).
 */
class RecruitmentService
{
    /**
     * Embauche un candidat : crée la fiche Employee, rattache le candidat,
     * marque le besoin « pourvu » si le quota de postes est atteint.
     * Idempotent : ne recrée pas d'employé si le candidat est déjà embauché.
     */
    public function hire(JobCandidate $candidate, array $overrides = []): Employee
    {
        if ($candidate->status === 'embauche' && $candidate->hired_employee_id) {
            return $candidate->hiredEmployee;
        }

        return DB::transaction(function () use ($candidate, $overrides) {
            $recruitment = $candidate->recruitment;

            $employee = Employee::create(array_merge([
                'company_id'      => $candidate->company_id,
                'department_id'   => $recruitment?->department_id,
                'job_position_id' => $recruitment?->job_position_id,
                'matricule'       => $this->nextMatricule($candidate->company_id),
                'first_name'      => $candidate->first_name,
                'last_name'       => $candidate->last_name,
                'email'           => $candidate->email,
                'phone'           => $candidate->phone,
                'job_title'       => $recruitment?->title,
                'hiring_date'     => now()->toDateString(),
                'status'          => 'actif',
            ], $overrides));

            $candidate->update([
                'status'            => 'embauche',
                'hired_employee_id' => $employee->id,
            ]);

            if ($recruitment) {
                $hired = $recruitment->candidates()->where('status', 'embauche')->count();
                if ($hired >= $recruitment->positions_count) {
                    $recruitment->update(['status' => 'pourvu', 'closed_at' => now()->toDateString()]);
                } elseif ($recruitment->status === 'ouvert') {
                    $recruitment->update(['status' => 'en_cours']);
                }
            }

            return $employee;
        });
    }

    /** Génère un matricule séquentiel par société : MAT-0001, MAT-0002… */
    public function nextMatricule(int $companyId): string
    {
        $count = Employee::withoutGlobalScopes()->where('company_id', $companyId)->count();

        return 'MAT-'.str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);
    }
}
