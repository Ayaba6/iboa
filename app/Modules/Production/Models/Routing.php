<?php

namespace App\Modules\Production\Models;

use App\Models\Company;
use App\Models\Product;
use App\Models\Traits\HasAttachments;
use App\Models\Traits\HasCompanyScope;
use App\Models\Traits\HasCreator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** [PRODUCTION] Gamme opératoire (séquence d'opérations d'une nomenclature). */
class Routing extends Model
{
    use HasFactory, SoftDeletes, HasCreator, HasCompanyScope, HasAttachments;

    protected $fillable = [
        'company_id', 'bill_of_material_id', 'product_id', 'code', 'name', 'is_active', 'notes', 'created_by',
        // [SAGE parité]
        'site', 'alternative', 'version_majeure', 'version_mineure', 'date_reference', 'statut',
        'date_debut_validite', 'date_fin_validite', 'unite_temps', 'quantite_base', 'uo',
        'rendement_standard', 'controle_qualite',
        // [Maquette Gamme] type, suivi, dépôt, unité + propriétés d'exécution
        'type_gamme', 'methode_suivi', 'unite_gestion_id', 'depot_production_id', 'version_active',
        'allow_time_overrun', 'tolerance_rendement', 'gestion_rebuts', 'auto_transfer', 'block_on_control_fail',
    ];
    protected $casts = [
        'is_active' => 'boolean',
        'date_reference' => 'date', 'date_debut_validite' => 'date', 'date_fin_validite' => 'date',
        'quantite_base' => 'decimal:3', 'rendement_standard' => 'decimal:2', 'controle_qualite' => 'boolean',
        'version_active' => 'boolean', 'allow_time_overrun' => 'boolean', 'gestion_rebuts' => 'boolean',
        'auto_transfer' => 'boolean', 'block_on_control_fail' => 'boolean',
        'tolerance_rendement' => 'decimal:2',
    ];

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function billOfMaterial(): BelongsTo { return $this->belongsTo(BillOfMaterial::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function operations(): HasMany { return $this->hasMany(RoutingOperation::class)->orderBy('sequence'); }
    public function uniteGestion(): BelongsTo { return $this->belongsTo(\App\Models\Unit::class, 'unite_gestion_id'); }
    public function depotProduction(): BelongsTo { return $this->belongsTo(\App\Models\Warehouse::class, 'depot_production_id'); }

    protected static function newFactory() { return \Database\Factories\RoutingFactory::new(); }
}
