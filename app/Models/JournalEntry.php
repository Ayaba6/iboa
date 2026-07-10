<?php

namespace App\Models;

use App\Models\Traits\HasAttachments;
use App\Models\Traits\HasCompanyScope;
use App\Models\Traits\HasCreator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalEntry extends Model
{
    use HasFactory, SoftDeletes, HasCreator, HasCompanyScope, HasAttachments;

    protected $table = 'journal_entries';

    protected $fillable = [
        'company_id',
        'journal_type_id',
        'fiscal_year_id',
        'number',
        'entry_date',
        'value_date',
        'reference',
        'partner_name',
        'description',
        'status',
        'is_simulation',
        'total_debit',
        'total_credit',
        'created_by',
        'validated_by',
        'validated_at',
        // [COMPTA-FIX-03] Reversal tracking — set on the original when contre-passée;
        // the reversal entry stores the inverse pointer in reverses_entry_id.
        'reversed_by_entry_id',
        'reverses_entry_id',
    ];

    protected $casts = [
        'entry_date'   => 'date',
        'is_simulation' => 'boolean',
        'value_date'   => 'date',
        'validated_at' => 'datetime',
        'total_debit'  => 'integer',
        'total_credit' => 'integer',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function journalType(): BelongsTo
    {
        return $this->belongsTo(JournalType::class);
    }

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class)->orderBy('sort_order');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    // [COMPTA-FIX-03] Reversal links — bidirectional.
    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversed_by_entry_id');
    }

    public function reverses(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_entry_id');
    }

    public function isReversed(): bool
    {
        return $this->reversed_by_entry_id !== null;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function isBalanced(): bool
    {
        return $this->total_debit === $this->total_credit;
    }

    public function isEditable(): bool
    {
        return $this->status === 'brouillon';
    }

    public function statusLabel(): string
    {
        return match($this->status) {
            'brouillon' => 'Brouillon',
            'valide'    => 'Validé',
            'cloture'   => 'Clôturé',
            default     => $this->status,
        };
    }

    public function statusColor(): string
    {
        return match($this->status) {
            'brouillon' => 'gray',
            'valide'    => 'green',
            'cloture'   => 'indigo',
            default     => 'gray',
        };
    }
}
