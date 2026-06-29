<?php

namespace App\Models;

use App\Models\Traits\HasCompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

/**
 * Constante de paie historisée.
 *
 * Remplace les colonnes figées de payroll_settings pour les valeurs
 * qui changent dans le temps (SMIG, taux CNSS, heures mensuelles…).
 *
 * Plusieurs lignes peuvent exister pour le même code — la valeur
 * active est celle dont valid_from <= aujourd'hui <= valid_until.
 */
class PayrollConstant extends Model
{
    use HasCompanyScope;

    protected $fillable = [
        'company_id', 'code', 'libelle', 'description',
        'value_type', 'value_raw', 'unit',
        'valid_from', 'valid_until', 'is_active',
        'groupe', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'valid_from' => 'date',
        'valid_until'=> 'date',
    ];

    // ─── Relations ────────────────────────────────────────────────────────────

    public function company(): BelongsTo   { return $this->belongsTo(Company::class); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function updatedBy(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($q)       { return $q->where('is_active', true); }
    public function scopeByCode($q, string $code) { return $q->where('code', $code); }
    public function scopeByGroupe($q, string $g)  { return $q->where('groupe', $g); }

    /** Scope : valide à une date donnée */
    public function scopeValidAt($q, string $date)
    {
        return $q->where('is_active', true)
                 ->where(fn($s) => $s->whereNull('valid_from')->orWhere('valid_from', '<=', $date))
                 ->where(fn($s) => $s->whereNull('valid_until')->orWhere('valid_until', '>=', $date));
    }

    // ─── Helpers — valeur castée ──────────────────────────────────────────────

    public function getValueAttribute(): mixed
    {
        return match ($this->value_type) {
            'montant' => (int)   $this->value_raw,
            'taux'    => (float) $this->value_raw,
            'nombre'  => (float) $this->value_raw,
            'booleen' => (bool)  $this->value_raw,
            default   => (string)$this->value_raw,
        };
    }

    public function getValueFormattedAttribute(): string
    {
        $v = $this->value;
        return match ($this->value_type) {
            'montant' => number_format((int)$v, 0, ',', ' ') . ' ' . ($this->unit ?? 'FCFA'),
            'taux'    => $v . ' ' . ($this->unit ?? '%'),
            'nombre'  => $v . ' ' . ($this->unit ?? ''),
            'booleen' => $v ? 'Oui' : 'Non',
            default   => (string)$v,
        };
    }

    // ─── Récupération de la constante active ──────────────────────────────────

    /**
     * Retourne la valeur active d'un code de constante pour une entreprise.
     * Utilise le cache 10 min pour éviter des requêtes répétées.
     */
    public static function getValue(int $companyId, string $code, mixed $default = null): mixed
    {
        $cacheKey = "payroll_const_{$companyId}_{$code}";

        $const = Cache::remember($cacheKey, 600, function () use ($companyId, $code) {
            return self::where('company_id', $companyId)
                ->where('code', $code)
                ->validAt(now()->toDateString())
                ->orderByDesc('valid_from')
                ->first();
        });

        return $const ? $const->value : $default;
    }

    public static function clearCache(int $companyId, string $code): void
    {
        Cache::forget("payroll_const_{$companyId}_{$code}");
    }

    // ─── Labels ───────────────────────────────────────────────────────────────

    public function getValueTypeLabelAttribute(): string
    {
        return match ($this->value_type) {
            'montant' => 'Montant',
            'taux'    => 'Taux (%)',
            'nombre'  => 'Nombre',
            'texte'   => 'Texte',
            'booleen' => 'Booléen',
            default   => $this->value_type,
        };
    }

    public function getGroupeLabelAttribute(): string
    {
        return match ($this->groupe) {
            'cnss'       => 'CNSS',
            'iuts'       => 'IUTS / Fiscal',
            'heures'     => 'Heures & Jours',
            'conges'     => 'Congés',
            'smig'       => 'SMIG',
            'anciennete' => 'Ancienneté',
            'fiscal'     => 'Fiscal',
            default      => 'Autre',
        };
    }
}
