<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Traits\HasCompanyScope;

class CrmActivity extends Model
{
    use HasCompanyScope;

    protected $fillable = [
        'company_id', 'user_id', 'crm_contact_id', 'crm_opportunity_id',
        'type', 'subject', 'description',
        'due_at', 'done_at', 'priority', 'is_done', 'duration_minutes',
    ];

    protected $casts = [
        'due_at'           => 'datetime',
        'done_at'          => 'datetime',
        'is_done'          => 'boolean',
        'duration_minutes' => 'integer',
    ];

    // ─── Types & helpers ──────────────────────────────────────────────────────

    const TYPES = [
        'appel' => ['label' => 'Appel',      'icon' => '📞', 'color' => 'blue'],
        'email' => ['label' => 'Email',       'icon' => '✉️', 'color' => 'indigo'],
        'rdv'   => ['label' => 'RDV',         'icon' => '📅', 'color' => 'emerald'],
        'note'  => ['label' => 'Note',        'icon' => '📝', 'color' => 'amber'],
        'tache' => ['label' => 'Tâche',       'icon' => '✅', 'color' => 'purple'],
    ];

    const PRIORITIES = [
        'low'    => ['label' => 'Faible',  'color' => 'gray'],
        'normal' => ['label' => 'Normale', 'color' => 'blue'],
        'high'   => ['label' => 'Haute',   'color' => 'red'],
    ];

    // ─── Relations ────────────────────────────────────────────────────────────

    public function company(): BelongsTo     { return $this->belongsTo(Company::class); }
    public function user(): BelongsTo        { return $this->belongsTo(User::class); }
    public function contact(): BelongsTo     { return $this->belongsTo(CrmContact::class, 'crm_contact_id'); }
    public function opportunity(): BelongsTo { return $this->belongsTo(CrmOpportunity::class, 'crm_opportunity_id'); }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeForCompany($q, int $id) { return $q->where('company_id', $id); }
    public function scopePending($q) { return $q->where('is_done', false); }
    public function scopeOverdue($q) { return $q->where('is_done', false)->where('due_at', '<', now()); }
    public function scopeDueToday($q) { return $q->where('is_done', false)->whereDate('due_at', today()); }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function typeConfig(): array    { return self::TYPES[$this->type]         ?? ['label' => $this->type,     'icon' => '•', 'color' => 'gray']; }
    public function typeLabel(): string    { return $this->typeConfig()['label']; }
    public function typeIcon(): string     { return $this->typeConfig()['icon']; }
    public function typeColor(): string    { return $this->typeConfig()['color']; }
    public function priorityLabel(): string { return self::PRIORITIES[$this->priority]['label'] ?? $this->priority; }
    public function priorityColor(): string { return self::PRIORITIES[$this->priority]['color'] ?? 'gray'; }

    public function isOverdue(): bool
    {
        return !$this->is_done && $this->due_at && $this->due_at->isPast();
    }
}
