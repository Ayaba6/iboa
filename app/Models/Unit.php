<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    use HasFactory;

    protected $table = 'units';

    protected $fillable = [
        'code',
        'name',
        'name_en',
        'abbreviation',
        'type',
        'dimension',
        'parent_unit_id',
        'conversion_factor',
        'decimal_places',
        'rounding_mode',
        'unit_category',
        'is_default_inventory',
        'is_default_sales',
        'description',
        'internal_notes',
        'is_active',
    ];

    protected $casts = [
        'is_active'            => 'boolean',
        'is_default_inventory' => 'boolean',
        'is_default_sales'     => 'boolean',
        'decimal_places'       => 'integer',
        'conversion_factor'    => 'decimal:6',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    // [Maquette Unité] hiérarchie de conversion
    public function parentUnit(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_unit_id');
    }

    public function childUnits(): HasMany
    {
        return $this->hasMany(self::class, 'parent_unit_id');
    }
}
