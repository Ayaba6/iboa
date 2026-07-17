<?php

namespace App\Models;

use App\Models\Traits\HasCompanyScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Immobilisation (actif fixe) — SYSCOHADA.
 *
 * Règle : les taux/durées sont toujours stockés sur l'actif, jamais codés en dur.
 * Les dotations postées (is_posted = true) sont figées et ne peuvent être recalculées.
 */
class FixedAsset extends Model
{
    use HasFactory, SoftDeletes, HasCompanyScope;

    protected $table = 'fixed_assets';

    protected $fillable = [
        'company_id', 'code', 'name', 'description', 'category',
        'acquisition_date', 'commissioning_date',
        'acquisition_cost', 'residual_value',
        'useful_life_years', 'depreciation_method',
        'asset_account', 'depr_account', 'charge_account',
        'status', 'cession_date', 'cession_value',
        'vendor', 'invoice_ref', 'notes', 'created_by',
        // [Maquette X3]
        'famille', 'asset_type', 'serial_number', 'accessory_cost',
        'periodicity', 'prorata_temporis', 'degressive_rate',
        'service_code', 'responsable', 'utilisateur', 'localisation',
        'batiment', 'bureau', 'centre_analytique', 'projet', 'code_activite',
    ];

    protected $casts = [
        'acquisition_date'   => 'date',
        'commissioning_date' => 'date',
        'cession_date'       => 'date',
        'acquisition_cost'   => 'integer',
        'residual_value'     => 'integer',
        'cession_value'      => 'integer',
        'useful_life_years'  => 'integer',
    ];

    // ── Labels ──────────────────────────────────────────────────────────────────

    public static array $categoryLabels = [
        'materiel_informatique' => 'Matériel informatique',
        'vehicule'              => 'Véhicule',
        'mobilier_bureau'       => 'Mobilier & bureau',
        'materiel_industriel'   => 'Matériel industriel',
        'batiment'              => 'Bâtiment',
        'terrain'               => 'Terrain',
        'logiciel'              => 'Logiciel',
        'autre'                 => 'Autre',
    ];

    public static array $methodLabels = [
        'lineaire'  => 'Linéaire',
        'degressif' => 'Dégressif',
    ];

    public static array $statusLabels = [
        'en_service'    => 'En service',
        'cede'          => 'Cédé',
        'mis_au_rebut'  => 'Mis au rebut',
    ];

    /**
     * Comptes GL par défaut selon la catégorie.
     * Format : ['asset_account', 'depr_account', 'charge_account', 'useful_life_years']
     */
    public static array $categoryDefaults = [
        'materiel_informatique' => ['2454', '28454', '6813', 3],
        'vehicule'              => ['2445', '28445', '6813', 5],
        'mobilier_bureau'       => ['2440', '28440', '6813', 10],
        'materiel_industriel'   => ['2430', '28430', '6813', 10],
        'batiment'              => ['2310', '28310', '6813', 20],
        'terrain'               => ['2210', '',       '',     0],  // non amortissable
        'logiciel'              => ['2120', '28120', '6811', 3],
        'autre'                 => ['2480', '28480', '6813', 5],
    ];

    // ── Computed attributes ──────────────────────────────────────────────────────

    public function getCategoryLabelAttribute(): string
    {
        return self::$categoryLabels[$this->category] ?? $this->category;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::$statusLabels[$this->status] ?? $this->status;
    }

    public function isDepreciable(): bool
    {
        return $this->category !== 'terrain' && $this->useful_life_years > 0;
    }

    /** Valeur brute = coût d'acquisition + frais accessoires (transport, installation…) */
    public function getGrossValueAttribute(): int
    {
        return (int) $this->acquisition_cost + (int) ($this->accessory_cost ?? 0);
    }

    /** Valeur nette comptable actuelle (valeur brute − cumul amortissements postés) */
    public function getNetBookValueAttribute(): int
    {
        $cumulated = $this->depreciations()->where('is_posted', true)->sum('depreciation_amount');
        return max(0, $this->gross_value - $cumulated);
    }

    /** Cumul des amortissements postés */
    public function getCumulatedDepreciationAttribute(): int
    {
        return (int) $this->depreciations()->where('is_posted', true)->sum('depreciation_amount');
    }

    /** Taux d'amortissement annuel (linéaire) en % */
    public function getAnnualRateAttribute(): float
    {
        if (! $this->useful_life_years) return 0;
        return round(100 / $this->useful_life_years, 4);
    }

    // ── Relations ────────────────────────────────────────────────────────────────

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function depreciations(): HasMany
    {
        return $this->hasMany(FixedAssetDepreciation::class)->orderBy('fiscal_year');
    }
}
