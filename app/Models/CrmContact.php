<?php

namespace App\Models;

use App\Models\Traits\HasCompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmContact extends Model
{
    use SoftDeletes, HasCompanyScope, \App\Models\Concerns\BustsCrmKpiCache;

    protected $fillable = [
        'company_id', 'user_id', 'client_id',
        'type', 'name', 'company_name', 'job_title',
        'email', 'phone', 'mobile', 'website',
        'address', 'city', 'country',
        'source', 'score', 'status',
        'sector', 'notes', 'tags',
    ];

    protected $casts = [
        'score' => 'integer',
        'tags'  => 'array',
    ];

    // ─── Types ────────────────────────────────────────────────────────────────

    const TYPES = [
        'prospect'   => 'Prospect',
        'contact'    => 'Contact',
        'partenaire' => 'Partenaire',
    ];

    const STATUSES = [
        'new'         => 'Nouveau',
        'contacted'   => 'Contacté',
        'qualified'   => 'Qualifié',
        'unqualified' => 'Non qualifié',
        'converted'   => 'Converti',
        'lost'        => 'Perdu',
    ];

    const SOURCES = [
        'direct'   => 'Direct',
        'referral' => 'Recommandation',
        'web'      => 'Web / SEO',
        'social'   => 'Réseaux sociaux',
        'event'    => 'Événement',
        'other'    => 'Autre',
    ];

    // ─── Relations ────────────────────────────────────────────────────────────

    public function company(): BelongsTo     { return $this->belongsTo(Company::class); }
    public function user(): BelongsTo        { return $this->belongsTo(User::class); }
    public function client(): BelongsTo      { return $this->belongsTo(Client::class); }
    public function opportunities(): HasMany { return $this->hasMany(CrmOpportunity::class); }
    public function activities(): HasMany    { return $this->hasMany(CrmActivity::class); }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeForCompany($q, int $id) { return $q->where('company_id', $id); }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function typeLabel(): string   { return self::TYPES[$this->type]   ?? $this->type; }
    public function statusLabel(): string { return self::STATUSES[$this->status] ?? $this->status; }
    public function sourceLabel(): string { return self::SOURCES[$this->source]  ?? $this->source; }

    public function statusColor(): string
    {
        return match($this->status) {
            'new'         => 'blue',
            'contacted'   => 'blue',
            'qualified'   => 'emerald',
            'unqualified' => 'gray',
            'converted'   => 'green',
            'lost'        => 'red',
            default       => 'gray',
        };
    }

    public function typeColor(): string
    {
        return match($this->type) {
            'prospect'   => 'amber',
            'contact'    => 'blue',
            'partenaire' => 'teal',
            default      => 'gray',
        };
    }

    /** Initiales pour l'avatar */
    public function initials(): string
    {
        $parts = explode(' ', $this->name, 2);
        return strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
    }
}
