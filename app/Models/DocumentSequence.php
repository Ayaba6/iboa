<?php

namespace App\Models;

use App\Models\Traits\HasCompanyScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentSequence extends Model
{
    use HasFactory, HasCompanyScope;

    protected $table = 'document_sequences';

    protected $fillable = [
        'company_id',
        'fiscal_year_id',
        'document_type',
        'prefix',
        'suffix',
        'padding',
        'include_year',
        'include_month',
        'year_format',
        'year_separator',
        'last_number',
        'numbering_mode',
        'is_locked',
        'last_modified_by',
        'last_modified_reason',
    ];

    protected $casts = [
        'include_year'  => 'boolean',
        'include_month' => 'boolean',
        'is_locked'    => 'boolean',
        'padding'      => 'integer',
        'last_number'  => 'integer',
    ];

    /**
     * Champs qui définissent le FORMAT (vs. compteur).
     * Modifier l'un de ces champs sur une séquence déjà utilisée
     * peut casser la cohérence des références — d'où vérification + audit.
     */
    public const FORMAT_FIELDS = [
        'prefix', 'suffix', 'padding',
        'include_year', 'include_month', 'year_format', 'year_separator',
    ];

    /**
     * Audit automatique à la création (firstOrCreate inclus).
     */
    protected static function booted(): void
    {
        static::created(function (DocumentSequence $seq) {
            try {
                DocumentSequenceAudit::create([
                    'document_sequence_id' => $seq->id,
                    'user_id'              => \Illuminate\Support\Facades\Auth::id(),
                    'action'               => 'create',
                    'before'               => null,
                    'after'                => $seq->only(array_merge(self::FORMAT_FIELDS, ['last_number', 'numbering_mode'])),
                    'reason'               => 'Création initiale (auto)',
                    'ip_address'           => \Illuminate\Support\Facades\Request::ip(),
                    'user_agent'           => substr((string) \Illuminate\Support\Facades\Request::userAgent(), 0, 500),
                ]);
            } catch (\Throwable $e) {
                // Ne JAMAIS bloquer la création de la séquence si l'audit échoue.
                report($e);
            }
        });
    }

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    /**
     * The company this sequence belongs to.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * The fiscal year this sequence is scoped to.
     */
    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function lastModifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_modified_by');
    }

    public function audits(): HasMany
    {
        return $this->hasMany(DocumentSequenceAudit::class)->latest();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Generate the next formatted document number and increment last_number.
     *
     * Example with prefix="FA", suffix="", padding=5, last_number=0 → "FA00001"
     */
    public function nextNumber(): string
    {
        $this->increment('last_number');
        $this->refresh();

        $padded = str_pad((string) $this->last_number, (int) $this->padding, '0', STR_PAD_LEFT);

        return ($this->prefix ?? '') . $padded . ($this->suffix ?? '');
    }
}
