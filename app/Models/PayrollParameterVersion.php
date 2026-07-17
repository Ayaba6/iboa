<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Version réglementaire d'un paramètre fiscal ou social (IUTS, CNSS, SMIG…).
 *
 * Règles d'intégrité (appliquées par PayrollRegulationService) :
 *  - une seule version active couvre une date donnée pour un code/pays ;
 *  - pas de chevauchement de périodes entre versions actives ;
 *  - une version utilisée dans une paie validée ne peut plus être supprimée ;
 *  - toute modification passe par la création d'une nouvelle version ;
 *  - les anciennes versions restent consultables (statut archive).
 */
class PayrollParameterVersion extends Model
{
    public const CODE_IUTS_BAREME       = 'IUTS_BAREME';
    public const CODE_IUTS_REDUCTIONS   = 'IUTS_REDUCTIONS_FAMILLE';
    public const CODE_CNSS_PLAFOND      = 'CNSS_PLAFOND_MENSUEL';
    public const CODE_CNSS_PLAFOND_AN   = 'CNSS_PLAFOND_ANNUEL';
    public const CODE_CNSS_SALARIE      = 'CNSS_TAUX_SALARIE';
    public const CODE_CNSS_PATRONAL     = 'CNSS_TAUX_PATRONAL_VENTILE';
    public const CODE_SMIG              = 'SMIG';

    protected $fillable = [
        'code', 'libelle', 'pays', 'valeur', 'type_valeur',
        'date_debut', 'date_fin', 'statut', 'version',
        'reference_legale', 'commentaire', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'valeur'     => 'array',
        'date_debut' => 'date',
        'date_fin'   => 'date',
        'version'    => 'integer',
    ];

    public function brackets(): HasMany
    {
        return $this->hasMany(PayrollBaremeBracket::class, 'bareme_id')->orderBy('ordre');
    }

    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function updatedBy(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }

    /** Version active d'un code à une date donnée. */
    public static function activeFor(string $code, ?\DateTimeInterface $date = null, string $pays = 'BF'): ?self
    {
        $date = $date ?? now();
        return self::where('code', $code)
            ->where('pays', $pays)
            ->where('statut', 'actif')
            ->whereDate('date_debut', '<=', $date)
            ->where(fn($q) => $q->whereNull('date_fin')->orWhereDate('date_fin', '>=', $date))
            ->orderByDesc('version')
            ->first();
    }
}
