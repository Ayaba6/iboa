<?php

namespace App\Modules\Production\Models;

use App\Models\Company;
use App\Models\Product;
use App\Models\Traits\HasCompanyScope;
use App\Models\Traits\HasCreator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** [PRODUCTION] Lot de fabrication (traçabilité). */
class ProductionBatch extends Model
{
    use HasFactory, HasCreator, HasCompanyScope;

    protected $fillable = ['company_id', 'production_order_id', 'product_id', 'batch_number', 'quantity', 'status', 'produced_at', 'created_by'];
    protected $casts = ['quantity' => 'decimal:2', 'produced_at' => 'date'];

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function productionOrder(): BelongsTo { return $this->belongsTo(ProductionOrder::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }

    public function statusLabel(): string { return match($this->status) { 'cloture' => 'Clôturé', 'conforme' => 'Conforme', default => 'En cours' }; }

    protected static function newFactory() { return \Database\Factories\ProductionBatchFactory::new(); }
}
