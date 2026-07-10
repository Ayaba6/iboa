<?php

namespace App\Modules\Quality\Models;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Product;
use App\Models\Reception;
use App\Models\Traits\HasCompanyScope;
use App\Models\Traits\HasCreator;
use App\Modules\Production\Models\ProductionOrder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** [QUALITÉ] Contrôle qualité transverse (réception / en-cours / produit fini). */
class QualityInspection extends Model
{
    use HasFactory, HasCreator, HasCompanyScope;

    protected $fillable = [
        'company_id', 'type', 'reception_id', 'production_order_id', 'product_id', 'controller_id',
        'reference', 'inspected_at', 'status', 'quantity_checked', 'quantity_rejected', 'notes', 'created_by',
        // [Maquette Contrôle qualité]
        'lot_number', 'atelier', 'production_line_id', 'work_center_id',
        'quantity_unit', 'sampling_method', 'norm_reference', 'inspection_stage',
    ];
    protected $casts = ['inspected_at' => 'datetime', 'quantity_checked' => 'decimal:2', 'quantity_rejected' => 'decimal:2'];

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function reception(): BelongsTo { return $this->belongsTo(Reception::class); }
    public function productionOrder(): BelongsTo { return $this->belongsTo(ProductionOrder::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function controller(): BelongsTo { return $this->belongsTo(Employee::class, 'controller_id'); }
    public function nonConformities(): HasMany { return $this->hasMany(NonConformity::class); }
    public function characteristics(): HasMany { return $this->hasMany(QualityInspectionCharacteristic::class)->orderBy('sort_order'); }
    public function productionLine(): BelongsTo { return $this->belongsTo(\App\Modules\Production\Models\ProductionLine::class, 'production_line_id'); }
    public function workCenter(): BelongsTo { return $this->belongsTo(\App\Modules\Production\Models\WorkCenter::class, 'work_center_id'); }

    public function typeLabel(): string { return match($this->type){ 'reception'=>'Réception','en_cours'=>'En cours','produit_fini'=>'Produit fini',default=>$this->type }; }
    public function statusLabel(): string { return match($this->status){ 'conforme'=>'Conforme','non_conforme'=>'Non conforme','partiel'=>'Partiel',default=>$this->status }; }

    protected static function newFactory() { return \Database\Factories\QualityInspectionFactory::new(); }
}
