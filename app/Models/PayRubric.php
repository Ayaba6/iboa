<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Traits\HasCompanyScope;

/**
 * [RH-PRO] Rubrique de paie paramétrable — inspiré Sage Paie.
 *
 * Représente une ligne du bulletin : gain, retenue, cotisation patronale ou information.
 * Supporte 4 modes de calcul : fixe, taux (base × %), formule, ou saisie manuelle.
 */
class PayRubric extends Model
{
    use HasCompanyScope;

    protected $fillable = [
        'company_id', 'plan_id', 'code', 'libelle', 'description',
        'type', 'categorie', 'sens', 'calc_type',
        'base_ref', 'rate', 'fixed_amount', 'formula',
        'plafond', 'arrondi', 'account_code',
        'is_taxable', 'is_cnss_base', 'is_iuts_base', 'is_in_brut',
        'is_in_net', 'is_employer_charged',
        'display_order', 'show_on_bulletin', 'is_active',
        'valid_from', 'valid_until', 'notes', 'created_by',
    ];

    protected $casts = [
        'rate'               => 'float',
        'fixed_amount'       => 'integer',
        'plafond'            => 'integer',
        'is_taxable'         => 'boolean',
        'is_cnss_base'       => 'boolean',
        'is_iuts_base'       => 'boolean',
        'is_in_brut'         => 'boolean',
        'is_in_net'          => 'boolean',
        'is_employer_charged'=> 'boolean',
        'show_on_bulletin'   => 'boolean',
        'is_active'          => 'boolean',
        'display_order'      => 'integer',
        'valid_from'         => 'date',
        'valid_until'        => 'date',
    ];

    // ─── Relations ────────────────────────────────────────────────────────────

    public function company(): BelongsTo    { return $this->belongsTo(Company::class); }
    public function plan(): BelongsTo       { return $this->belongsTo(PayrollPlan::class, 'plan_id'); }
    public function createdBy(): BelongsTo  { return $this->belongsTo(User::class, 'created_by'); }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($q)       { return $q->where('is_active', true); }
    public function scopeGains($q)        { return $q->where('type', 'gain'); }
    public function scopeRetenues($q)     { return $q->where('type', 'retenue'); }
    public function scopeOrdered($q)      { return $q->orderBy('display_order')->orderBy('code'); }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            'gain'           => 'Gain',
            'retenue'        => 'Retenue',
            'cotisation_pat' => 'Cotisation patronale',
            'information'    => 'Information',
            default          => $this->type,
        };
    }

    public function getTypeColorAttribute(): string
    {
        return match ($this->type) {
            'gain'           => 'emerald',
            'retenue'        => 'red',
            'cotisation_pat' => 'blue',
            'information'    => 'gray',
            default          => 'gray',
        };
    }

    public function getCalcTypeLabelAttribute(): string
    {
        return match ($this->calc_type) {
            'fixe'    => 'Montant fixe',
            'taux'    => 'Taux %',
            'formule' => 'Formule',
            'manuel'  => 'Saisie manuelle',
            default   => $this->calc_type,
        };
    }

    /**
     * Calcule le montant de la rubrique à partir du contexte de paie.
     *
     * @param array $context  [salaire_base, salaire_brut, cnss_base, imposable, ...]
     */
    public function compute(array $context): int
    {
        return match ($this->calc_type) {
            'fixe'    => (int) ($this->fixed_amount ?? 0),
            'taux'    => $this->computeTaux($context),
            'formule' => $this->computeFormule($context),
            default   => 0, // manuel : valeur fournie par PayrollVariable
        };
    }

    private function computeTaux(array $context): int
    {
        $base = match ($this->base_ref) {
            'salaire_base'  => $context['salaire_base']  ?? 0,
            'salaire_brut'  => $context['salaire_brut']  ?? 0,
            'cnss_base'     => $context['cnss_base']     ?? 0,
            'imposable'     => $context['imposable']     ?? 0,
            default         => $context[$this->base_ref] ?? 0,
        };
        return (int) round((float) $base * ($this->rate ?? 0) / 100);
    }

    private function computeFormule(array $context): int
    {
        if (! $this->formula) {
            return 0;
        }
        try {
            // Extract context variables into local scope
            extract($context);
            $result = eval('return ' . $this->formula . ';');
            return (int) round((float) $result);
        } catch (\Throwable) {
            return 0;
        }
    }
}
