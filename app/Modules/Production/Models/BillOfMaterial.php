<?php
namespace App\Modules\Production\Models;
use App\Models\Company;
use App\Models\Product;

use App\Models\Traits\HasCompanyScope;
use App\Models\Traits\HasCreator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** [PRODUCTION] Nomenclature / recette d'un type de tôle bac. */
class BillOfMaterial extends Model
{
    use HasFactory, SoftDeletes, HasCreator, HasCompanyScope;

    protected $table = 'bills_of_materials';
    protected $fillable = [
        'company_id','product_id','scrap_product_id','defect_product_id','name','sheet_type','thickness','coil_width','usable_width',
        'standard_waste_rate','consumption_per_meter','machine_time_per_unit','labor_per_unit','packaging_per_unit',
        'std_material_cost','std_labor_cost','std_machine_cost','std_energy_cost','std_maintenance_cost','std_packaging_cost','std_overhead_cost',
        'is_active','notes','created_by',
        // [SAGE parité] en-tête article composé
        'site','alternative','date_reference','version_majeure','version_mineure','unite_gestion_id',
        'quantite_base','statut','date_debut_validite','date_fin_validite','rendement_standard','controle_qualite',
        // [Maquette Nomenclature] code, type, dépôt production, valorisation, priorité + propriétés
        'code','type_nomenclature','depot_production_id','valuation_method','priorite','version_active',
        'multi_niveaux','allow_sub_bom','lot_management','serial_tracking','lock_modification',
    ];
    protected $casts = [
        'thickness'=>'decimal:2','coil_width'=>'decimal:1','usable_width'=>'decimal:1','standard_waste_rate'=>'decimal:2',
        'consumption_per_meter'=>'decimal:4','machine_time_per_unit'=>'decimal:2','labor_per_unit'=>'decimal:2','packaging_per_unit'=>'decimal:2','is_active'=>'boolean',
        'date_reference'=>'date','date_debut_validite'=>'date','date_fin_validite'=>'date',
        'quantite_base'=>'decimal:3','rendement_standard'=>'decimal:2','controle_qualite'=>'boolean','version_active'=>'boolean','multi_niveaux'=>'boolean','allow_sub_bom'=>'boolean','lot_management'=>'boolean','serial_tracking'=>'boolean','lock_modification'=>'boolean',
    ];

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function depotProduction(): BelongsTo { return $this->belongsTo(\App\Models\Warehouse::class, 'depot_production_id'); }
    public function uniteGestion(): BelongsTo { return $this->belongsTo(\App\Models\Unit::class, 'unite_gestion_id'); }
    public function scrapProduct(): BelongsTo { return $this->belongsTo(Product::class, 'scrap_product_id'); }
    public function defectProduct(): BelongsTo { return $this->belongsTo(Product::class, 'defect_product_id'); }
    public function lines(): HasMany { return $this->hasMany(BomLine::class); }
    public function routing(): \Illuminate\Database\Eloquent\Relations\HasOne { return $this->hasOne(Routing::class)->where('is_active', true); }

    protected static function newFactory()
    {
        return \Database\Factories\BillOfMaterialFactory::new();
    }
}
