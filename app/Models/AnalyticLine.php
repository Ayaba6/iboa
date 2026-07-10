<?php

namespace App\Models;

use App\Models\Traits\HasCompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * [§12 CDC] Ligne de comptabilité analytique.
 * Ventilation des charges/produits par centre de coûts et catégorie.
 * Catégories : matière, main_oeuvre, energie, maintenance, emballage, overhead, autre.
 */
class AnalyticLine extends Model
{
    use HasCompanyScope;

    protected $table = 'analytic_lines';

    protected $fillable = [
        'company_id', 'cost_center_id', 'journal_entry_line_id',
        'ref_type', 'ref_id', 'date', 'label', 'category', 'amount', 'currency', 'created_by',
    ];

    protected $casts = [
        'date'   => 'date',
        'amount' => 'decimal:2',
    ];

    public static array $categoryLabels = [
        'matiere'      => 'Matières premières',
        'main_oeuvre'  => "Main-d'œuvre",
        'energie'      => 'Énergie',
        'maintenance'  => 'Maintenance',
        'emballage'    => 'Emballage',
        'overhead'     => 'Frais généraux',
        'autre'        => 'Autre',
    ];

    public function costCenter(): BelongsTo      { return $this->belongsTo(CostCenter::class); }
    public function journalEntryLine(): BelongsTo { return $this->belongsTo(JournalEntryLine::class); }
    public function createdBy(): BelongsTo        { return $this->belongsTo(User::class, 'created_by'); }

    public function categoryLabel(): string
    {
        return self::$categoryLabels[$this->category] ?? ucfirst($this->category);
    }

    /**
     * Crée une ligne analytique liée à une écriture GL.
     */
    public static function fromJournalLine(
        JournalEntryLine $jLine,
        CostCenter $center,
        string $category = 'autre',
        ?string $label = null
    ): self {
        return static::create([
            'company_id'           => $jLine->company_id ?? $center->company_id,
            'cost_center_id'       => $center->id,
            'journal_entry_line_id'=> $jLine->id,
            'ref_type'             => $jLine->journal_entry_id ? \App\Models\JournalEntry::class : null,
            'ref_id'               => $jLine->journal_entry_id,
            'date'                 => $jLine->entry?->date ?? now()->toDateString(),
            'label'                => $label ?? $jLine->label,
            'category'             => $category,
            'amount'               => $jLine->debit - $jLine->credit,
            'currency'             => 'XOF',
            'created_by'           => auth()->id(),
        ]);
    }
}
