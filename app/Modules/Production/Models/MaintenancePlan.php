<?php

namespace App\Modules\Production\Models;

use App\Models\Company;
use App\Models\Traits\HasCompanyScope;
use App\Models\Traits\HasCreator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * [CDC §13.8/§14] Plan de maintenance préventive — périodicité fixe (en jours)
 * par machine. Génère automatiquement une MachineMaintenance (type=preventive)
 * quand next_due_at est atteinte.
 */
class MaintenancePlan extends Model
{
    use HasFactory, HasCreator, HasCompanyScope;

    protected $fillable = [
        'company_id', 'machine_id', 'name', 'frequency_days', 'instructions',
        'last_generated_at', 'next_due_at', 'is_active', 'created_by',
    ];

    protected $casts = [
        'last_generated_at' => 'date',
        'next_due_at'       => 'date',
        'is_active'         => 'boolean',
        'frequency_days'    => 'integer',
    ];

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function machine(): BelongsTo { return $this->belongsTo(ProductionMachine::class, 'machine_id'); }
    public function interventions(): HasMany { return $this->hasMany(MachineMaintenance::class, 'maintenance_plan_id'); }

    public function isDue(): bool
    {
        return $this->is_active && $this->next_due_at !== null && $this->next_due_at->lte(now()->toDateString());
    }

    protected static function newFactory()
    {
        return \Database\Factories\MaintenancePlanFactory::new();
    }
}
