<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use App\Models\Traits\HasCompanyScope;

/**
 * Période de paie mensuelle avec cycle de vie verrouillable.
 *
 * Transitions autorisées :
 *   open → closed    via close()
 *   closed → open    via reopen()
 *   closed → locked  via lock()
 *   locked → open    via unlock()  ← action dangereuse, tracée
 *   locked → archived via archive()
 *
 * Invariant de sécurité : toute tentative d'écriture sur un PayrollItem
 * dont la période est locked ou archived doit passer par
 * PayrollPeriodService::guardAgainstLocked().
 */
class PayrollPeriod extends Model
{
    // ── Constantes de statut ───────────────────────────────────────────────────

    public const STATUS_OPEN     = 'open';
    public const STATUS_CLOSED   = 'closed';
    public const STATUS_LOCKED   = 'locked';
    public const STATUS_ARCHIVED = 'archived';

    public const STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_CLOSED,
        self::STATUS_LOCKED,
        self::STATUS_ARCHIVED,
    ];

    // ── Fillable / Casts ───────────────────────────────────────────────────────

    protected $fillable = [
        'company_id', 'code', 'libelle',
        'period_start', 'period_end',
        'status',
        'closed_at', 'closed_by',
        'locked_at', 'locked_by',
        'unlocked_at', 'unlocked_by', 'unlock_reason',
        'archived_at', 'archived_by',
        'payroll_run_id',
        'notes', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end'   => 'date',
        'closed_at'    => 'datetime',
        'locked_at'    => 'datetime',
        'unlocked_at'  => 'datetime',
        'archived_at'  => 'datetime',
    ];

    // ── Relations ──────────────────────────────────────────────────────────────

    public function company(): BelongsTo   { return $this->belongsTo(Company::class); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function updatedBy(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }
    public function closedBy(): BelongsTo  { return $this->belongsTo(User::class, 'closed_by'); }
    public function lockedBy(): BelongsTo  { return $this->belongsTo(User::class, 'locked_by'); }
    public function unlockedBy(): BelongsTo{ return $this->belongsTo(User::class, 'unlocked_by'); }
    public function archivedBy(): BelongsTo{ return $this->belongsTo(User::class, 'archived_by'); }
    public function payrollRun(): BelongsTo{ return $this->belongsTo(PayrollRun::class); }

    /** Bulletins de paie rattachés à cette période */
    public function items(): HasMany
    {
        return $this->hasMany(PayrollItem::class, 'payroll_period_id');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeOpen(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_OPEN);
    }

    public function scopeClosed(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_CLOSED);
    }

    public function scopeLocked(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_LOCKED);
    }

    public function scopeForCompany(Builder $q, int $companyId): Builder
    {
        return $q->where('company_id', $companyId);
    }

    // ── Predicats ─────────────────────────────────────────────────────────────

    public function isOpen(): bool     { return $this->status === self::STATUS_OPEN; }
    public function isClosed(): bool   { return $this->status === self::STATUS_CLOSED; }
    public function isLocked(): bool   { return $this->status === self::STATUS_LOCKED; }
    public function isArchived(): bool { return $this->status === self::STATUS_ARCHIVED; }

    /**
     * Retourne true si des écritures sont encore possibles sur cette période.
     * Un null de period_id (bulletins sans période) est traité comme "modifiable".
     */
    public function canBeModified(): bool
    {
        return $this->isOpen();
    }

    /**
     * Levée d'exception si la période bloque les modifications.
     *
     * @throws \RuntimeException
     */
    public function guardAgainstWrite(): void
    {
        if ($this->isLocked()) {
            throw new \RuntimeException(
                "La période « {$this->libelle} » est verrouillée. Aucune modification n'est autorisée."
            );
        }
        if ($this->isArchived()) {
            throw new \RuntimeException(
                "La période « {$this->libelle} » est archivée. Aucune modification n'est autorisée."
            );
        }
    }

    // ── Transitions de statut ─────────────────────────────────────────────────

    /**
     * Clôture la période (open → closed).
     * Les bulletins restent modifiables jusqu'au verrouillage.
     */
    public function close(?int $userId = null): void
    {
        if (! $this->isOpen()) {
            throw new \LogicException("Seule une période ouverte peut être clôturée (statut actuel : {$this->status}).");
        }
        $this->update([
            'status'     => self::STATUS_CLOSED,
            'closed_at'  => now(),
            'closed_by'  => $userId ?? Auth::id(),
            'updated_by' => $userId ?? Auth::id(),
        ]);
    }

    /**
     * Réouverture (closed → open).
     * Impossible si des bulletins ont déjà été verrouillés.
     */
    public function reopen(?int $userId = null): void
    {
        if (! $this->isClosed()) {
            throw new \LogicException("Seule une période clôturée peut être réouverte (statut actuel : {$this->status}).");
        }
        $this->update([
            'status'     => self::STATUS_OPEN,
            'closed_at'  => null,
            'closed_by'  => null,
            'updated_by' => $userId ?? Auth::id(),
        ]);
    }

    /**
     * Verrouillage définitif (closed → locked).
     * Bloque toute écriture ultérieure sur les PayrollItems de cette période.
     */
    public function lock(?int $userId = null): void
    {
        // Auto-clôture si encore ouverte
        if ($this->isOpen()) {
            $this->close($userId);
            $this->refresh();
        }

        if (! $this->isClosed()) {
            throw new \LogicException("Impossible de verrouiller (statut actuel : {$this->status}).");
        }

        $this->update([
            'status'     => self::STATUS_LOCKED,
            'locked_at'  => now(),
            'locked_by'  => $userId ?? Auth::id(),
            'updated_by' => $userId ?? Auth::id(),
        ]);
    }

    /**
     * Déverrouillage (locked → open) — action dangereuse, tracée.
     * Une justification est obligatoire.
     */
    public function unlock(string $reason, ?int $userId = null): void
    {
        if (! $this->isLocked()) {
            throw new \LogicException("Seule une période verrouillée peut être déverrouillée (statut actuel : {$this->status}).");
        }
        if (trim($reason) === '') {
            throw new \InvalidArgumentException('Une justification est obligatoire pour déverrouiller une période.');
        }

        $this->update([
            'status'        => self::STATUS_OPEN,
            'unlocked_at'   => now(),
            'unlocked_by'   => $userId ?? Auth::id(),
            'unlock_reason' => $reason,
            'updated_by'    => $userId ?? Auth::id(),
        ]);
    }

    /**
     * Archivage (locked → archived).
     * État terminal — aucune action possible après.
     */
    public function archive(?int $userId = null): void
    {
        if (! $this->isLocked()) {
            throw new \LogicException("Seule une période verrouillée peut être archivée (statut actuel : {$this->status}).");
        }

        $this->update([
            'status'      => self::STATUS_ARCHIVED,
            'archived_at' => now(),
            'archived_by' => $userId ?? Auth::id(),
            'updated_by'  => $userId ?? Auth::id(),
        ]);
    }

    // ── Accesseurs ────────────────────────────────────────────────────────────

    /**
     * Libellé lisible du statut courant.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_OPEN     => 'Ouverte',
            self::STATUS_CLOSED   => 'Clôturée',
            self::STATUS_LOCKED   => 'Verrouillée',
            self::STATUS_ARCHIVED => 'Archivée',
            default               => $this->status,
        };
    }

    /**
     * Couleur Tailwind pour le badge de statut (classes statiques uniquement).
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_OPEN     => 'emerald',
            self::STATUS_CLOSED   => 'amber',
            self::STATUS_LOCKED   => 'red',
            self::STATUS_ARCHIVED => 'gray',
            default               => 'gray',
        };
    }

    // ── Factory ───────────────────────────────────────────────────────────────

    /**
     * Crée ou retourne la période correspondant à un mois donné.
     */
    public static function findOrCreateForMonth(Carbon $date, int $companyId): static
    {
        $code  = $date->format('Y-m');
        $start = $date->copy()->startOfMonth();
        $end   = $date->copy()->endOfMonth();

        return static::firstOrCreate(
            ['company_id' => $companyId, 'code' => $code],
            [
                'libelle'      => ucfirst($date->translatedFormat('F Y')),
                'period_start' => $start,
                'period_end'   => $end,
                'status'       => self::STATUS_OPEN,
                'created_by'   => Auth::id(),
            ]
        );
    }
}
