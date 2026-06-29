<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Traits\HasCompanyScope;

/**
 * Règle de numérotation des bulletins de paie.
 * Format exemple : BUL-2026-05-0001
 */
class PayrollNumbering extends Model
{
    use HasCompanyScope;

    protected $fillable = [
        'company_id', 'code', 'libelle',
        'prefix', 'separator', 'year_format', 'month_format', 'seq_length',
        'reset_on', 'is_active', 'is_default', 'notes',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'seq_length' => 'integer',
        'is_active'  => 'boolean',
        'is_default' => 'boolean',
    ];

    // ─── Relations ────────────────────────────────────────────────────────────

    public function company(): BelongsTo    { return $this->belongsTo(Company::class); }
    public function createdBy(): BelongsTo  { return $this->belongsTo(User::class, 'created_by'); }
    public function sequences(): HasMany    { return $this->hasMany(PayrollNumberingSequence::class, 'numbering_id'); }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Génère un aperçu du format basé sur une date donnée.
     * N'incrémente PAS la séquence — pour affichage uniquement.
     */
    public function preview(\Carbon\Carbon $date, int $seq = 1): string
    {
        return $this->buildNumber($date, $seq);
    }

    /**
     * Construit la clé de période (ex: '2026', '2026-05', 'global').
     */
    public function periodKey(\Carbon\Carbon $date): string
    {
        return match ($this->reset_on) {
            'year'  => $date->format('Y'),
            'month' => $date->format('Y-m'),
            default => 'global',
        };
    }

    /**
     * Assemble le numéro formaté à partir d'une date et d'un numéro de séquence.
     */
    public function buildNumber(\Carbon\Carbon $date, int $seq): string
    {
        $parts = [$this->prefix];
        $sep   = $this->separator;

        if ($this->year_format !== 'none') {
            $parts[] = $date->format($this->year_format === 'YY' ? 'y' : 'Y');
        }
        if ($this->month_format !== 'none') {
            $parts[] = $date->format($this->month_format === 'M' ? 'n' : 'm');
        }

        $parts[] = str_pad($seq, $this->seq_length, '0', STR_PAD_LEFT);

        return implode($sep, $parts);
    }

    public function getFormatExampleAttribute(): string
    {
        return $this->preview(now(), 1) . ' … ' . $this->preview(now(), 99);
    }
}
