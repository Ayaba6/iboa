<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** [X3 §10] Valeur d'un attribut dynamique pour un article. */
class ProductAttributeValue extends Model
{
    protected $fillable = ['product_id', 'category_attribute_id', 'value'];

    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function attribute(): BelongsTo { return $this->belongsTo(CategoryAttribute::class, 'category_attribute_id'); }
}
