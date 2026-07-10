<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class Company extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'companies';

    /**
     * [PERF-PHASE3] Récupère l'entreprise courante avec mise en cache (1h).
     * Eager-load des relations souvent utilisées : currentFiscalYear, defaultCurrency.
     * Le cache est invalidé automatiquement à toute modification via le hook booted().
     */
    public static function current(): ?self
    {
        return Cache::remember('company:current', 3600, function () {
            return static::with(['currentFiscalYear', 'defaultCurrency'])->first();
        });
    }

    /**
     * Variante non-cachée pour les opérations qui ne peuvent pas accepter
     * une vue legerement obsolète (rare — réserver à l'édition).
     */
    public static function currentFresh(): ?self
    {
        Cache::forget('company:current');
        return static::current();
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('company:current'));
        static::deleted(fn () => Cache::forget('company:current'));
    }

    protected $fillable = [
        'name',
        'trade_name',
        'slogan',
        'logo',
        'address',
        'city',
        'region',
        'country',
        'postal_code',
        'phone',
        'phone2',
        'fax',
        'email',
        'website',
        'legal_form',
        'rccm',
        'ifu',
        'nif',
        'is_vat_subject',
        'vat_number',
        'share_capital',
        'share_capital_currency',
        'default_currency_id',
        'current_fiscal_year_id',
        'validation_mode',
        'allow_self_validation',
        // [Maquette Paramétrage société]
        'company_code', 'sigle', 'cnss_number', 'main_activity', 'language', 'timezone',
        'opened_at', 'status', 'notes', 'district', 'po_box', 'main_contact', 'accounting_email',
        'fiscal_regime', 'vat_mode', 'default_vat_rate', 'tax_center', 'withholding_regime', 'taxpayer_type',
        'stamp_path', 'signature_path', 'pdf_header_path', 'pdf_footer_path',
        'multi_sites', 'vat_management', 'validation_workflow', 'electronic_signature',
        'auto_pdf_print', 'email_notifications', 'secondary_currency', 'maintenance_mode',
    ];

    protected $casts = [
        // [Maquette Paramétrage société]
        'opened_at'            => 'date',
        'default_vat_rate'     => 'decimal:2',
        'multi_sites'          => 'boolean',
        'vat_management'       => 'boolean',
        'validation_workflow'  => 'boolean',
        'electronic_signature' => 'boolean',
        'auto_pdf_print'       => 'boolean',
        'email_notifications'  => 'boolean',
        'secondary_currency'   => 'boolean',
        'maintenance_mode'     => 'boolean',
        'is_vat_subject'       => 'boolean',
        'allow_self_validation' => 'boolean',
    ];

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    /**
     * Bank accounts belonging to this company.
     */
    public function bankAccounts(): HasMany
    {
        return $this->hasMany(CompanyBankAccount::class);
    }

    /**
     * Document layout/print settings for this company.
     */
    public function documentSetting(): HasOne
    {
        return $this->hasOne(DocumentSetting::class);
    }

    /**
     * Warehouses belonging to this company.
     */
    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }

    /**
     * Document numbering sequences for this company.
     */
    public function documentSequences(): HasMany
    {
        return $this->hasMany(DocumentSequence::class);
    }

    /**
     * The default currency of this company.
     */
    public function defaultCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'default_currency_id');
    }

    /**
     * The currently active fiscal year.
     */
    public function currentFiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class, 'current_fiscal_year_id');
    }

    /**
     * Users linked to this company.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    // -------------------------------------------------------------------------
    // Accessors
    // -------------------------------------------------------------------------

    /**
     * [P3.D] Retourne le logo encodé en base64 (data URI) pour DomPDF.
     * DomPDF ne peut pas résoudre les URLs HTTP — on lit le fichier depuis le
     * disque et on l'encode en base64 pour l'intégrer directement dans le HTML.
     *
     * @return string|null  "data:image/png;base64,..." ou null si aucun logo.
     */
    public function getLogoBase64Attribute(): ?string
    {
        if (empty($this->logo)) {
            return null;
        }

        $path = storage_path('app/public/' . ltrim($this->logo, '/'));

        if (! file_exists($path) || ! is_readable($path)) {
            return null;
        }

        $content = @file_get_contents($path);
        if ($content === false || $content === '') {
            return null;
        }

        $mime = mime_content_type($path) ?: 'image/png';

        return 'data:' . $mime . ';base64,' . base64_encode($content);
    }
}
