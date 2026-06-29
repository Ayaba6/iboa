<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Traits\HasCompanyScope;

/**
 * Cotisation sociale paramétrable — CNSS, assurance, retraite, mutuelle.
 * Remplace les colonnes figées de payroll_settings pour les cotisations.
 */
class SocialContribution extends Model
{
    use HasCompanyScope;

    protected $fillable = [
        'company_id', 'code', 'libelle', 'organisme',
        'taux_salarie', 'taux_employeur',
        'base_cotisable', 'plafond', 'base_ref',
        'account_salarie', 'account_employeur',
        'valid_from', 'valid_until', 'is_active',
        'notes', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'taux_salarie'   => 'float',
        'taux_employeur' => 'float',
        'plafond'        => 'integer',
        'is_active'      => 'boolean',
        'valid_from'     => 'date',
        'valid_until'    => 'date',
    ];

    // ─── Relations ────────────────────────────────────────────────────────────

    public function company(): BelongsTo   { return $this->belongsTo(Company::class); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function updatedBy(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($q) { return $q->where('is_active', true); }
    public function scopeValidAt($q, string $date)
    {
        return $q->where('is_active', true)
                 ->where(fn($s) => $s->whereNull('valid_from')->orWhere('valid_from', '<=', $date))
                 ->where(fn($s) => $s->whereNull('valid_until')->orWhere('valid_until', '>=', $date));
    }

    // ─── Calcul ───────────────────────────────────────────────────────────────

    /**
     * Calcule la cotisation salarié pour un salaire donné.
     */
    public function computeSalarie(int $salaireBrut): int
    {
        $base = $this->getBase($salaireBrut);
        return (int) round($base * $this->taux_salarie / 100);
    }

    /**
     * Calcule la cotisation patronale pour un salaire donné.
     */
    public function computeEmployeur(int $salaireBrut): int
    {
        $base = $this->getBase($salaireBrut);
        return (int) round($base * $this->taux_employeur / 100);
    }

    private function getBase(int $salaireBrut): int
    {
        return match ($this->base_cotisable) {
            'plafonne' => min($salaireBrut, $this->plafond ?? $salaireBrut),
            default    => $salaireBrut,
        };
    }

    // ─── Labels ───────────────────────────────────────────────────────────────

    public function getOrganismeLabelAttribute(): string
    {
        return match ($this->organisme) {
            'cnss'      => 'CNSS',
            'assurance' => 'Assurance',
            'retraite'  => 'Retraite',
            'mutuelle'  => 'Mutuelle',
            default     => 'Autre',
        };
    }

    public function getBaseCotisableLabelAttribute(): string
    {
        return match ($this->base_cotisable) {
            'salaire_brut'  => 'Salaire brut',
            'salaire_base'  => 'Salaire de base',
            'plafonne'      => 'Plafonné à ' . number_format($this->plafond ?? 0, 0, ',', ' ') . ' FCFA',
            'custom'        => 'Personnalisé (' . $this->base_ref . ')',
            default         => $this->base_cotisable,
        };
    }
}
