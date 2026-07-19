<?php

namespace App\Models;

use App\Models\Traits\HasCompanyScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentSetting extends Model
{
    use HasFactory, HasCompanyScope;

    protected $table = 'document_settings';

    protected $fillable = [
        'company_id',
        'primary_color',
        'font_family',
        'page_size',
        'orientation',
        'show_logo',
        'show_watermark',
        'watermark_text',
        'footer_text',
        'terms_conditions',
        'penalty_mentions',
        'signature_name',
        'signature_title',
        'signature_image',
        'stamp_image',
        'quote_settings',
        'invoice_settings',
        'delivery_settings',
        'product_columns',
    ];

    protected $casts = [
        'show_logo'        => 'boolean',
        'show_watermark'   => 'boolean',
        'quote_settings'   => 'array',
        'invoice_settings' => 'array',
        'delivery_settings'=> 'array',
        'product_columns'  => 'array',
    ];

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    /**
     * The company these document settings belong to.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
