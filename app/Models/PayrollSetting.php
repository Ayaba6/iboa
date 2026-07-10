<?php

namespace App\Models;

use App\Models\Traits\HasCompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

/**
 * [RH-PRO] Paramétrage de la paie.
 * Singleton par company_id — un seul enregistrement par entreprise.
 *
 * RÈGLE : aucun taux, plafond ou barème n'est codé en dur dans les services.
 * Tous les paramètres sont lus ici. Si un paramètre requis est null,
 * assertComplete() lève une RuntimeException explicite.
 *
 * Valeurs de référence Burkina Faso (2024) — saisies en DB, non codées :
 *   CNSS salarié   : 5,5 %  (plafonné 800 000 FCFA/mois depuis 2023)
 *   CNSS patronal  : 16,0 %
 *   IUTS abattement: 20 %   (frais professionnels — CGI Art. 130)
 *   Effort de paix : 1 %    (sur net)
 *   HS 25 %        : majoration 25 %
 *   HS 50 %        : majoration 50 %
 *   HS nuit        : majoration 75 %
 *   Ancienneté     : 2 %/an, plafonné à 25 % (Code du Travail Art. 109)
 *   Quotient familial : célibataire=1 / marié=2 / veuf=1,5 / +0,5/enfant / max=5
 */
class PayrollSetting extends Model
{
    use HasCompanyScope;

    protected $fillable = [
        'company_id',
        'cnss_employee_rate', 'cnss_employer_rate', 'cnss_ceiling', 'cnss_at_rate',
        'smig',
        'work_days_month', 'work_hours_day', 'leave_days_year',
        'hs_rate_25', 'hs_rate_50', 'hs_rate_nuit',
        'anc_rate_per_year', 'anc_rate_max_pct',         // [NO-HARDCODE] ancienneté
        'nb_parts_max', 'parts_per_child',
        'parts_base_single', 'parts_base_married', 'parts_base_widowed',
        'iuts_brackets',
        'iuts_abattement_rate',
        'effort_paix_enabled', 'effort_paix_rate',
        'bulletin_prefix', 'currency_code', 'country_code',
        'cnss_affiliation', 'phone', 'address_bulletin',
        'notes', 'updated_by',
    ];

    protected $casts = [
        'cnss_employee_rate'  => 'float',
        'cnss_employer_rate'  => 'float',
        'cnss_ceiling'        => 'integer',
        'cnss_at_rate'        => 'float',
        'smig'                => 'integer',
        'work_days_month'     => 'integer',
        'work_hours_day'      => 'integer',
        'leave_days_year'     => 'integer',
        'hs_rate_25'          => 'float',
        'hs_rate_50'          => 'float',
        'hs_rate_nuit'        => 'float',
        'anc_rate_per_year'   => 'float',                // [NO-HARDCODE]
        'anc_rate_max_pct'    => 'float',                // [NO-HARDCODE]
        'nb_parts_max'        => 'integer',
        'parts_per_child'     => 'float',
        'parts_base_single'   => 'float',
        'parts_base_married'  => 'float',
        'parts_base_widowed'  => 'float',
        'iuts_brackets'        => 'array',
        'iuts_abattement_rate' => 'float',
        'effort_paix_enabled'  => 'boolean',
        'effort_paix_rate'     => 'float',
    ];

    // ─── Relations ────────────────────────────────────────────────────────────

    public function company(): BelongsTo   { return $this->belongsTo(Company::class); }
    public function updatedBy(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Récupère ou crée les paramètres de paie pour une entreprise.
     *
     * Les valeurs de firstOrCreate sont des valeurs d'INITIALISATION (Burkina Faso 2024).
     * Elles sont immédiatement écrites en DB et modifiables via l'UI RH → Paramètres de paie.
     * Elles ne constituent pas un "codage en dur" car elles sont overridables à tout moment.
     */
    public static function forCompany(int $companyId): self
    {
        return Cache::remember("payroll_settings_{$companyId}", 600, function () use ($companyId) {
            return self::firstOrCreate(
                ['company_id' => $companyId],
                [
                    // ── CNSS (BF 2024) ──────────────────────────────────────
                    'cnss_employee_rate'  => 5.5,
                    'cnss_employer_rate'  => 16.0,
                    'cnss_ceiling'        => 800_000,   // plafond BF 2023+
                    // ── Temps de travail ────────────────────────────────────
                    'work_days_month'     => 26,
                    'work_hours_day'      => 8,
                    'leave_days_year'     => 30,
                    // ── Heures supplémentaires ──────────────────────────────
                    'hs_rate_25'          => 25.0,
                    'hs_rate_50'          => 50.0,
                    'hs_rate_nuit'        => 75.0,
                    // ── Ancienneté (BF Code du Travail Art. 109) ────────────
                    'anc_rate_per_year'   => 2.0,
                    'anc_rate_max_pct'    => 25.0,
                    // ── IUTS ────────────────────────────────────────────────
                    'iuts_abattement_rate'=> 20.0,
                    'iuts_brackets'       => self::defaultIutsBrackets(),
                    // ── Effort de paix ──────────────────────────────────────
                    'effort_paix_enabled' => true,
                    'effort_paix_rate'    => 1.0,
                    // ── Quotient familial ────────────────────────────────────
                    'nb_parts_max'        => 5,
                    'parts_per_child'     => 0.5,
                    'parts_base_single'   => 1.0,
                    'parts_base_married'  => 2.0,
                    'parts_base_widowed'  => 1.5,
                    // ── Divers ───────────────────────────────────────────────
                    'smig'                => 45_000,
                    'currency_code'       => 'XOF',
                    'country_code'        => 'BF',
                ]
            );
        });
    }

    /**
     * Vérifie que tous les paramètres requis pour le calcul de paie sont renseignés.
     * Lève une RuntimeException explicite si un paramètre est null.
     *
     * Appeler depuis PayrollService::loadSettings() pour bloquer le calcul
     * dès le départ et non au milieu d'un bulletin.
     */
    public function assertComplete(): void
    {
        $required = [
            'cnss_employee_rate'  => 'Taux CNSS salarié (%)',
            'cnss_employer_rate'  => 'Taux CNSS patronal (%)',
            'cnss_ceiling'        => 'Plafond CNSS (FCFA)',
            'work_days_month'     => 'Jours ouvrables/mois',
            'work_hours_day'      => 'Heures/jour',
            'hs_rate_25'          => 'Majoration HS 25 % (%)',
            'hs_rate_50'          => 'Majoration HS 50 % (%)',
            'hs_rate_nuit'        => 'Majoration HS nuit (%)',
            'anc_rate_per_year'   => 'Taux ancienneté / an (%)',
            'anc_rate_max_pct'    => 'Plafond ancienneté (%)',
            'iuts_abattement_rate'=> 'Abattement IUTS (%)',
            'effort_paix_rate'    => 'Taux effort de paix (%)',
            'nb_parts_max'        => 'Nombre de parts maximum',
            'parts_per_child'     => 'Parts par enfant',
            'parts_base_single'   => 'Parts célibataire',
            'parts_base_married'  => 'Parts marié(e)',
            'parts_base_widowed'  => 'Parts veuf/veuve',
        ];

        $missing = [];
        foreach ($required as $field => $label) {
            if (is_null($this->$field)) {
                $missing[] = $label;
            }
        }

        // Vérifier séparément le barème IUTS (tableau, pas scalar)
        if (empty($this->iuts_brackets)) {
            $missing[] = 'Barème IUTS (tranches)';
        }

        if (!empty($missing)) {
            throw new \RuntimeException(
                'Paramètres de paie manquants pour l\'entreprise #' . $this->company_id
                . ' — configurez-les dans RH → Paramètres de paie : '
                . implode(', ', $missing) . '.'
            );
        }
    }

    /**
     * Invalide le cache après modification.
     */
    public static function clearCache(int $companyId): void
    {
        Cache::forget("payroll_settings_{$companyId}");
    }

    /**
     * Barème IUTS par défaut — Burkina Faso 2024.
     * Utilisé UNIQUEMENT lors du firstOrCreate (initialisation).
     * Ces valeurs sont immédiatement écrites en DB et modifiables via l'UI.
     * Format : [[plafond_tranche, taux_pct], ...]
     */
    public static function defaultIutsBrackets(): array
    {
        return [
            [25_000,          0],
            [40_000,         12],
            [60_000,         17],
            [80_000,         22],
            [120_000,        27],
            [9_999_999_999,  33],
        ];
    }

    /**
     * Calcule l'IUTS par la méthode du quotient familial.
     * Lit le barème depuis iuts_brackets (DB), jamais depuis des constantes PHP.
     */
    public function computeIuts(int $imposable, float $parts): int
    {
        if ($imposable <= 0 || $parts <= 0) {
            return 0;
        }

        $brackets = $this->iuts_brackets;
        if (empty($brackets)) {
            throw new \RuntimeException(
                'Barème IUTS non configuré pour l\'entreprise #' . $this->company_id
                . '. Configurez-le dans RH → Paramètres de paie → Barème IUTS.'
            );
        }

        $quotient = $imposable / $parts;
        $tax      = 0.0;
        $prev     = 0;

        foreach ($brackets as [$limit, $rate]) {
            if ($quotient <= $prev) {
                break;
            }
            $tranche = min($quotient, $limit) - $prev;
            $tax    += $tranche * $rate / 100;
            $prev    = $limit;
            if ($quotient <= $limit) {
                break;
            }
        }

        return (int) round($tax * $parts);
    }

    /**
     * Calcule le nombre de parts fiscales selon situation familiale.
     * Lit les parts depuis la DB — aucun fallback numérique.
     */
    public function computeNbParts(string $familyStatus, int $nbChildren): float
    {
        $parts = match ($familyStatus) {
            'marie' => (float) $this->parts_base_married,
            'veuf'  => (float) $this->parts_base_widowed,
            default => (float) $this->parts_base_single,
        };
        $parts += $nbChildren * (float) $this->parts_per_child;
        return min($parts, (float) $this->nb_parts_max);
    }
}
