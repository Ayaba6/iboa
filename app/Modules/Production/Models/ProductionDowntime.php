<?php

namespace App\Modules\Production\Models;

use App\Models\Traits\HasCompanyScope;
use App\Models\Traits\HasCreator;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * [PRO Temps d'arrêt] Arrêt de production tracé (hors intervention de maintenance).
 */
class ProductionDowntime extends Model
{
    use HasFactory, HasCreator, HasCompanyScope;

    protected $fillable = [
        'company_id', 'production_order_id', 'machine_id', 'work_center_id',
        'category', 'reason', 'description', 'started_at', 'ended_at',
        'duration_minutes', 'declared_by',
    ];

    protected $casts = [
        'started_at'       => 'datetime',
        'ended_at'         => 'datetime',
        'duration_minutes' => 'integer',
    ];

    public const CATEGORIES = [
        'planifie'     => 'Planifié',
        'non_planifie' => 'Non planifié',
    ];

    public const REASONS = [
        'panne'            => 'Panne',
        'changement_outil' => 'Changement d\'outil',
        'rupture_matiere'  => 'Rupture matière',
        'reglage'          => 'Réglage',
        'attente'          => 'Attente (personnel / instruction)',
        'nettoyage'        => 'Nettoyage',
        'autre'            => 'Autre',
    ];

    public function company(): BelongsTo { return $this->belongsTo(\App\Models\Company::class); }
    public function productionOrder(): BelongsTo { return $this->belongsTo(ProductionOrder::class); }
    public function machine(): BelongsTo { return $this->belongsTo(ProductionMachine::class, 'machine_id'); }
    public function workCenter(): BelongsTo { return $this->belongsTo(WorkCenter::class, 'work_center_id'); }
    public function declaredBy(): BelongsTo { return $this->belongsTo(User::class, 'declared_by'); }

    public function categoryLabel(): string { return self::CATEGORIES[$this->category] ?? $this->category; }
    public function reasonLabel(): string { return self::REASONS[$this->reason] ?? $this->reason; }

    public function isOngoing(): bool { return $this->ended_at === null; }

    /** Durée effective (minutes) : colonne si clôturé, sinon écoulé depuis le début. */
    public function effectiveMinutes(): int
    {
        if ($this->duration_minutes !== null) {
            return $this->duration_minutes;
        }
        return (int) abs($this->started_at->diffInMinutes(now()));
    }
}
