<?php

namespace App\Models;

use App\Models\Traits\HasCompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class QualityCertificate extends Model
{
    use HasCompanyScope, SoftDeletes;

    protected $fillable = [
        'company_id', 'number', 'type', 'ref_type', 'ref_id',
        'lot_number', 'fournisseur', 'date_reception', 'date_certificat',
        'poids_reel', 'largeur_mm', 'epaisseur_mm', 'couleur', 'norme',
        'resultat', 'observations', 'controles',
        'controleur_id', 'validateur_id', 'validated_at',
    ];

    protected $casts = [
        'controles'      => 'array',
        'date_reception' => 'date',
        'date_certificat'=> 'date',
        'validated_at'   => 'datetime',
        'poids_reel'     => 'decimal:3',
        'largeur_mm'     => 'decimal:2',
        'epaisseur_mm'   => 'decimal:3',
    ];

    public const TYPES = [
        'reception_bobine'  => 'Réception Bobine',
        'produit_fini'      => 'Produit Fini',
        'matiere_premiere'  => 'Matière Première',
        'autre'             => 'Autre',
    ];

    public const RESULTATS = [
        'conforme'      => ['label' => 'Conforme',       'color' => 'green'],
        'non_conforme'  => ['label' => 'Non Conforme',   'color' => 'red'],
        'sous_reserve'  => ['label' => 'Sous Réserve',   'color' => 'amber'],
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function controleur()
    {
        return $this->belongsTo(User::class, 'controleur_id');
    }

    public function validateur()
    {
        return $this->belongsTo(User::class, 'validateur_id');
    }

    public function ref()
    {
        return $this->morphTo();
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function resultatLabel(): string
    {
        return self::RESULTATS[$this->resultat]['label'] ?? $this->resultat;
    }

    public function resultatColor(): string
    {
        return self::RESULTATS[$this->resultat]['color'] ?? 'gray';
    }

    public function isConforme(): bool
    {
        return $this->resultat === 'conforme';
    }
}
