<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Traits\HasCompanyScope;

/**
 * Profil de paie — définit un ensemble de rubriques actives avec surcharges éventuelles,
 * applicable à une catégorie d'employés (cadre, non-cadre, dirigeant…).
 *
 * Un profil hérite les rubriques d'un plan, mais peut :
 *   - désactiver certaines rubriques
 *   - surcharger le taux ou le montant fixe d'une rubrique
 */
class PayrollProfile extends Model
{
    use HasCompanyScope;

    protected $fillable = [
        'company_id', 'plan_id', 'code', 'libelle', 'description',
        'categorie', 'valid_from', 'valid_until',
        'is_active', 'is_default', 'notes',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'valid_from'  => 'date',
        'valid_until' => 'date',
        'is_active'   => 'boolean',
        'is_default'  => 'boolean',
    ];

    // ─── Relations ────────────────────────────────────────────────────────────

    public function company(): BelongsTo  { return $this->belongsTo(Company::class); }
    public function plan(): BelongsTo     { return $this->belongsTo(PayrollPlan::class, 'plan_id'); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function updatedBy(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }

    /** Rubriques liées au profil via la table pivot enrichie */
    public function rubrics(): BelongsToMany
    {
        return $this->belongsToMany(PayRubric::class, 'payroll_profile_rubrics', 'profile_id', 'rubric_id')
                    ->withPivot([
                        'is_active',
                        'override_calc_type',
                        'override_fixed_amount',
                        'override_rate',
                        'override_formula',
                        'notes',
                    ])
                    ->withTimestamps()
                    ->orderBy('display_order')
                    ->orderBy('code');
    }

    /** Contrats utilisant ce profil */
    public function contracts(): HasMany
    {
        return $this->hasMany(EmployeeContract::class, 'payroll_profile_id');
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($q)      { return $q->where('is_active', true); }
    public function scopeForCompany($q, int $companyId) { return $q->where('company_id', $companyId); }

    // ─── Accesseurs ───────────────────────────────────────────────────────────

    public function getCategorieLabelAttribute(): string
    {
        return match ($this->categorie) {
            'cadre'     => 'Cadre',
            'non_cadre' => 'Non-cadre',
            'dirigeant' => 'Dirigeant',
            'interim'   => 'Intérimaire',
            'stagiaire' => 'Stagiaire',
            default     => 'Autre',
        };
    }

    public function getCategorieColorAttribute(): string
    {
        return match ($this->categorie) {
            'cadre'     => 'indigo',
            'non_cadre' => 'blue',
            'dirigeant' => 'violet',
            'interim'   => 'amber',
            'stagiaire' => 'emerald',
            default     => 'gray',
        };
    }

    // ─── Méthodes métier ──────────────────────────────────────────────────────

    /**
     * Hérite toutes les rubriques actives du plan associé.
     * Les rubriques déjà présentes dans le profil ne sont pas modifiées.
     * Retourne le nombre de rubriques nouvellement ajoutées.
     */
    public function inheritFromPlan(): int
    {
        if (! $this->plan_id) {
            return 0;
        }

        $planRubrics = PayRubric::where('plan_id', $this->plan_id)
                                ->where('is_active', true)
                                ->pluck('id');

        $existingIds = $this->rubrics()->pluck('pay_rubrics.id');
        $toAdd = $planRubrics->diff($existingIds);

        foreach ($toAdd as $rubricId) {
            $this->rubrics()->attach($rubricId, ['is_active' => true]);
        }

        return $toAdd->count();
    }

    /**
     * Retourne les rubriques effectives à utiliser pour le calcul de paie.
     * Priorité : surcharge profil > valeur rubrique plan.
     */
    public function effectiveRubrics(): \Illuminate\Support\Collection
    {
        return $this->rubrics()
                    ->wherePivot('is_active', true)
                    ->get()
                    ->map(function (PayRubric $rubric) {
                        // Appliquer les surcharges du profil si définies
                        if ($rubric->pivot->override_calc_type) {
                            $rubric->calc_type = $rubric->pivot->override_calc_type;
                        }
                        if ($rubric->pivot->override_fixed_amount !== null) {
                            $rubric->fixed_amount = $rubric->pivot->override_fixed_amount;
                        }
                        if ($rubric->pivot->override_rate !== null) {
                            $rubric->rate = $rubric->pivot->override_rate;
                        }
                        if ($rubric->pivot->override_formula) {
                            $rubric->formula = $rubric->pivot->override_formula;
                        }
                        return $rubric;
                    });
    }
}
