<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Traits\HasCompanyScope;

/**
 * Plan de paie — conteneur de rubriques par pays/devise/société.
 * Inspiré Sage Paie : "Plan de paie Burkina Faso CDI".
 */
class PayrollPlan extends Model
{
    use HasCompanyScope;

    protected $fillable = [
        'company_id', 'code', 'libelle', 'description',
        'pays', 'country_code', 'devise',
        'valid_from', 'valid_until', 'is_active', 'is_default',
        'notes', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'is_active'   => 'boolean',
        'is_default'  => 'boolean',
        'valid_from'  => 'date',
        'valid_until' => 'date',
    ];

    // ─── Relations ────────────────────────────────────────────────────────────

    public function company(): BelongsTo   { return $this->belongsTo(Company::class); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function updatedBy(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }
    public function rubrics(): HasMany     { return $this->hasMany(PayRubric::class, 'plan_id'); }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($q)               { return $q->where('is_active', true); }
    public function scopeForCompany($q, int $id)  { return $q->where('company_id', $id); }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function getStatusLabelAttribute(): string
    {
        if (! $this->is_active) return 'Inactif';
        if ($this->valid_until && $this->valid_until->isPast()) return 'Expiré';
        return 'Actif';
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status_label) {
            'Actif'   => 'green',
            'Expiré'  => 'amber',
            default   => 'gray',
        };
    }

    /** Duplique le plan avec toutes ses rubriques */
    public function duplicate(string $newCode, string $newLibelle): self
    {
        $clone = $this->replicate(['code', 'libelle', 'is_default']);
        $clone->code    = $newCode;
        $clone->libelle = $newLibelle;
        $clone->is_default = false;
        $clone->save();

        foreach ($this->rubrics as $rubric) {
            $rubricClone = $rubric->replicate();
            $rubricClone->plan_id = $clone->id;
            $rubricClone->save();
        }

        return $clone;
    }
}
