<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Traits\HasCompanyScope;

class LeaveType extends Model
{
    use HasCompanyScope;

    protected $fillable = [
        'company_id','name','code','days_per_year',
        'is_paid','deduct_from_salary','color','is_active',
    ];

    protected $casts = [
        'days_per_year'       => 'float',
        'is_paid'             => 'boolean',
        'deduct_from_salary'  => 'boolean',
        'is_active'           => 'boolean',
    ];

    public function company(): BelongsTo   { return $this->belongsTo(Company::class); }
    public function requests(): HasMany    { return $this->hasMany(LeaveRequest::class); }
    public function balances(): HasMany    { return $this->hasMany(LeaveBalance::class); }
}
