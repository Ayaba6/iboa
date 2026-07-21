<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** [X3 §10] Attribut dynamique défini par une catégorie d'article. */
class CategoryAttribute extends Model
{
    protected $fillable = ['item_category_id', 'code', 'label', 'type', 'options', 'required', 'sort_order'];

    protected $casts = ['options' => 'array', 'required' => 'boolean'];

    public function category(): BelongsTo { return $this->belongsTo(ItemCategory::class, 'item_category_id'); }
}
