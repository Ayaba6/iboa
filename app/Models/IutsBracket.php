<?php

namespace App\Models;

use App\Models\Traits\HasCompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

/**
 * Tranche du barème IUTS/ITS — table structurée.
 *
 * Remplace progressivement le JSON stocké dans payroll_settings.iuts_brackets.
 * Le calcul reste backward-compatible : si aucune ligne active n'existe,
 * PayrollSetting::computeIuts() sert de fallback.
 */
class IutsBracket extends Model
{
    use HasCompanyScope;

    protected $fillable = [
        'company_id', 'pays', 'country_code', 'impot',
        'tranche_min', 'tranche_max', 'taux', 'montant_fixe', 'abattement',
        'nb_parts_min', 'nb_parts_max', 'ordre',
        'valid_from', 'valid_until', 'is_active', 'created_by',
    ];

    protected $casts = [
        'tranche_min'  => 'integer',
        'tranche_max'  => 'integer',
        'taux'         => 'float',
        'montant_fixe' => 'integer',
        'abattement'   => 'float',
        'nb_parts_min' => 'integer',
        'nb_parts_max' => 'integer',
        'ordre'        => 'integer',
        'is_active'    => 'boolean',
        'valid_from'   => 'date',
        'valid_until'  => 'date',
    ];

    // ─── Relations ────────────────────────────────────────────────────────────

    public function company(): BelongsTo   { return $this->belongsTo(Company::class); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($q)       { return $q->where('is_active', true); }
    public function scopeOrdered($q)      { return $q->orderBy('ordre')->orderBy('tranche_min'); }
    public function scopeValidAt($q, string $date)
    {
        return $q->where('is_active', true)
                 ->where(fn($s) => $s->whereNull('valid_from')->orWhere('valid_from', '<=', $date))
                 ->where(fn($s) => $s->whereNull('valid_until')->orWhere('valid_until', '>=', $date));
    }

    // ─── Calcul IUTS ─────────────────────────────────────────────────────────

    /**
     * Calcule l'IUTS à partir du revenu imposable et du nombre de parts.
     * Méthode du quotient familial (BF IUTS).
     *
     * @param int   $companyId
     * @param int   $imposable  Salaire imposable mensuel
     * @param float $parts      Nombre de parts fiscales
     */
    public static function computeIuts(int $companyId, int $imposable, float $parts): int
    {
        if ($imposable <= 0 || $parts <= 0) return 0;

        $brackets = Cache::remember("iuts_brackets_{$companyId}", 600, function () use ($companyId) {
            return self::where('company_id', $companyId)
                ->validAt(now()->toDateString())
                ->ordered()
                ->get();
        });

        if ($brackets->isEmpty()) {
            // Fallback vers le JSON de PayrollSetting
            $setting = PayrollSetting::forCompany($companyId);
            return $setting->computeIuts($imposable, $parts);
        }

        $quotient = $imposable / $parts;
        $tax      = 0.0;

        foreach ($brackets as $bracket) {
            if ($quotient <= $bracket->tranche_min) break;
            $tranche  = min($quotient, $bracket->tranche_max) - $bracket->tranche_min;
            $taxBase  = $tranche * (1 - $bracket->abattement / 100);
            $tax     += $taxBase * $bracket->taux / 100 + $bracket->montant_fixe;
            if ($quotient <= $bracket->tranche_max) break;
        }

        return (int) round($tax * $parts);
    }

    public static function clearCache(int $companyId): void
    {
        Cache::forget("iuts_brackets_{$companyId}");
    }
}
