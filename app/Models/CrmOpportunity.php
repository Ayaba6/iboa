<?php

namespace App\Models;

use App\Models\Traits\HasCompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmOpportunity extends Model
{
    use SoftDeletes, HasCompanyScope;

    protected $fillable = [
        'company_id', 'crm_contact_id', 'user_id',
        'title', 'amount', 'probability', 'expected_close',
        'stage', 'lost_reason', 'product_service', 'notes', 'sort_order',
    ];

    protected $casts = [
        'amount'         => 'float',
        'probability'    => 'integer',
        'sort_order'     => 'integer',
        'expected_close' => 'date',
    ];

    // ─── Stages ───────────────────────────────────────────────────────────────

    const STAGES = [
        'prospection'   => ['label' => 'Prospection',   'color' => 'sky',     'icon' => '🔍', 'prob' => 10],
        'qualification' => ['label' => 'Qualification', 'color' => 'blue',    'icon' => '✅', 'prob' => 25],
        'proposition'   => ['label' => 'Proposition',   'color' => 'violet',  'icon' => '📋', 'prob' => 50],
        'negociation'   => ['label' => 'Négociation',   'color' => 'amber',   'icon' => '🤝', 'prob' => 75],
        'gagne'         => ['label' => 'Gagné',         'color' => 'emerald', 'icon' => '🏆', 'prob' => 100],
        'perdu'         => ['label' => 'Perdu',         'color' => 'red',     'icon' => '❌', 'prob' => 0],
    ];

    // ─── Relations ────────────────────────────────────────────────────────────

    public function company(): BelongsTo    { return $this->belongsTo(Company::class); }
    public function contact(): BelongsTo    { return $this->belongsTo(CrmContact::class, 'crm_contact_id'); }
    public function user(): BelongsTo       { return $this->belongsTo(User::class); }
    public function activities(): HasMany   { return $this->hasMany(CrmActivity::class); }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeForCompany($q, int $id) { return $q->where('company_id', $id); }
    public function scopeActive($q) { return $q->whereNotIn('stage', ['gagne', 'perdu']); }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function stageConfig(): array { return self::STAGES[$this->stage] ?? ['label' => $this->stage, 'color' => 'gray', 'icon' => '•', 'prob' => 0]; }
    public function stageLabel(): string { return $this->stageConfig()['label']; }
    public function stageColor(): string { return $this->stageConfig()['color']; }

    /** Valeur pondérée = montant × probabilité */
    public function weightedAmount(): float
    {
        return $this->amount * ($this->probability / 100);
    }

    public function isWon(): bool  { return $this->stage === 'gagne'; }
    public function isLost(): bool { return $this->stage === 'perdu'; }
    public function isOpen(): bool { return !in_array($this->stage, ['gagne', 'perdu']); }

    public function daysToClose(): ?int
    {
        if (!$this->expected_close) return null;
        return (int) now()->diffInDays($this->expected_close, false);
    }
}
