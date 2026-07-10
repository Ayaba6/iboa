<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Traits\HasCompanyScope;

/**
 * Ligne du plan d'amortissement.
 *
 * is_posted = true → dotation passée en écriture GL, ligne figée.
 * Le montant d'une ligne postée ne peut pas être recalculé.
 */
class FixedAssetDepreciation extends Model
{
    use HasCompanyScope;

    protected $table = 'fixed_asset_depreciations';

    protected $fillable = [
        'fixed_asset_id', 'company_id', 'fiscal_year',
        'depreciation_amount', 'cumulated_depreciation', 'net_book_value',
        'journal_entry_id', 'is_posted',
    ];

    protected $casts = [
        'depreciation_amount'    => 'integer',
        'cumulated_depreciation' => 'integer',
        'net_book_value'         => 'integer',
        'is_posted'              => 'boolean',
        'fiscal_year'            => 'integer',
    ];

    public function fixedAsset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
