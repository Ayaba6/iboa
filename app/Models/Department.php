<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Traits\HasCompanyScope;

class Department extends Model
{
    use HasCompanyScope;

    protected $fillable = ['company_id', 'name', 'code', 'description', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function employees(): HasMany { return $this->hasMany(Employee::class); }

    public function scopeActive($q) { return $q->where('is_active', true); }
}
