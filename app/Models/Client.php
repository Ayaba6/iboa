<?php

namespace App\Models;

use App\Models\Traits\HasAttachments;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\TaxRate;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use App\Models\CreditNote;
use App\Models\SalesRep;

class Client extends Model
{
    use HasFactory, SoftDeletes, HasAttachments;

    protected $table = 'clients';

    // Types (CDC §client — segmentation commerciale)
    const TYPE_ENTREPRISE   = 'entreprise';
    const TYPE_PARTICULIER  = 'particulier';
    const TYPE_DISTRIBUTEUR = 'distributeur';
    const TYPE_MINIER       = 'minier';

    // Modes de règlement
    const PAYMENT_CASH   = 'cash';
    const PAYMENT_CREDIT = 'credit';

    protected $fillable = [
        'site_id',
        'code',
        'type',
        'name',
        'trade_name',
        'civility',
        'phone',
        'phone2',
        'mobile',
        'email',
        'website',
        'boite_postale',
        'address',
        'address_line2',
        'postal_code',
        'city',
        'quartier',
        'region',
        'gps_lat',
        'gps_lng',
        'country',
        'ifu',
        'numero_contribuable',
        'rccm',
        'tax_regime',
        'tax_division',
        'category',
        'groupe_client',
        'secteur_activite',
        'currency',
        'language',
        'assigned_to',
        'created_by',
        'tax_rate_id',
        // Exonération TVA
        'is_tax_exempt',
        'tax_exemption_reason',
        'tax_exemption_number',
        'soumis_tva',
        // [Précompte BIC] inclusion/exclusion au précompte BIC + motif d'exemption
        'soumis_bic',
        'bic_exemption_reason',
        // Statuts
        'is_livrable',
        'is_facturable',
        'blocage_commande',
        // Paramètres commerciaux
        'credit_limit',
        'encours_autorise',
        'compte_collectif',
        'canal',
        'zone_commerciale',
        'famille_tarifaire',
        'payment_mode',
        'payment_days',
        'payment_terms',
        'default_discount',
        // Livraison
        'depot_livraison_id',
        'mode_livraison',
        'transporteur',
        'delai_livraison',
        'adresse_livraison_defaut',
        // Comptabilité / Finance
        'compte_tiers',
        'condition_paiement',
        'echeance',
        'banque',
        'rib_iban',
        'numero_compte',
        'swift',
        'balance',
        'is_active',
        'is_blocked',
        'blocked_reason',
        'notes',
        'sales_rep_id',
        // [Parité Sage X3] Juridique / fiscal
        'forme_juridique',
        'regime_imposition',
        'no_agrement',
        // [Parité Sage X3] Risque crédit
        'code_risque',
        'garantie_montant',
        'nature_garantie',
        'assurance_credit',
        'rrr_montant',
        'rrr_taux',
        'reference_cadastrale',
        // [Parité Sage X3] Tiers comptables
        'client_facture_id',
        'client_payeur_id',
        'client_groupe_id',
        'client_risque_id',
        'factor_id',
    ];

    protected $casts = [
        'credit_limit'     => 'integer',
        'encours_autorise' => 'decimal:2',
        'default_discount' => 'decimal:2',
        'balance'          => 'integer',
        'is_active'        => 'boolean',
        'is_tax_exempt'    => 'boolean',
        'soumis_tva'       => 'boolean',
        'soumis_bic'       => 'boolean',
        'is_livrable'      => 'boolean',
        'is_facturable'    => 'boolean',
        'blocage_commande' => 'boolean',
        'gps_lat'          => 'decimal:6',
        'gps_lng'          => 'decimal:6',
        'delai_livraison'  => 'integer',
        'garantie_montant' => 'decimal:2',
        'rrr_montant'      => 'decimal:2',
        'rrr_taux'         => 'decimal:2',
    ];

    public function site(): BelongsTo { return $this->belongsTo(Warehouse::class, 'site_id'); }
    public function depotLivraison(): BelongsTo { return $this->belongsTo(Warehouse::class, 'depot_livraison_id'); }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function taxRates(): BelongsToMany
    {
        return $this->belongsToMany(TaxRate::class, 'client_tax_rates');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(ClientContact::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(ClientAddress::class);
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(ClientInteraction::class);
    }

    public function assignedCommercial(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function salesRep(): BelongsTo
    {
        return $this->belongsTo(SalesRep::class);
    }

    // [Parité Sage X3] Tiers comptables (self-références nullable)
    public function clientFacture(): BelongsTo { return $this->belongsTo(self::class, 'client_facture_id'); }
    public function clientPayeur(): BelongsTo  { return $this->belongsTo(self::class, 'client_payeur_id'); }
    public function clientGroupe(): BelongsTo  { return $this->belongsTo(self::class, 'client_groupe_id'); }
    public function clientRisque(): BelongsTo  { return $this->belongsTo(self::class, 'client_risque_id'); }
    public function factor(): BelongsTo        { return $this->belongsTo(self::class, 'factor_id'); }

    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** [VEN Crédit] Historique des décisions de crédit. */
    public function creditDecisions(): HasMany
    {
        return $this->hasMany(CreditDecision::class)->orderByDesc('created_at')->orderByDesc('id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ClientPayment::class);
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class);
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeClients(Builder $query): Builder
    {
        return $query->whereIn('type', [self::TYPE_ENTREPRISE, self::TYPE_PARTICULIER, self::TYPE_DISTRIBUTEUR, self::TYPE_MINIER]);
    }

    public function isCash(): bool
    {
        return $this->payment_mode === self::PAYMENT_CASH;
    }

    public function isCredit(): bool
    {
        return $this->payment_mode === self::PAYMENT_CREDIT || !$this->payment_mode;
    }

    public static function typeLabel(string $type): string
    {
        return match ($type) {
            'entreprise'   => 'Entreprise',
            'particulier'  => 'Particulier',
            'distributeur' => 'Distributeur',
            'minier'       => 'Minier',
            default        => ucfirst($type),
        };
    }

    // -------------------------------------------------------------------------
    // Methods
    // -------------------------------------------------------------------------

    public function displayName(): string
    {
        return $this->trade_name ?? $this->name;
    }

    /**
     * Retourne true si le client est exonéré de TVA.
     * Utilisé par les services pour forcer tax_rate_value = 0 côté serveur.
     */
    public function isTaxExempt(): bool
    {
        return (bool) $this->is_tax_exempt;
    }

    /** Vérifier si l'encours dépasse le plafond de crédit. */
    public function isOverCreditLimit(): bool
    {
        if (!$this->credit_limit || $this->credit_limit <= 0) return false;
        return $this->balance > $this->credit_limit;
    }

    /** Montant disponible avant d'atteindre le plafond. */
    public function getAvailableCreditAttribute(): int
    {
        if (!$this->credit_limit || $this->credit_limit <= 0) return PHP_INT_MAX;
        return max(0, (int)$this->credit_limit - (int)$this->balance);
    }

    /** Taux d'utilisation du crédit en %. */
    public function getCreditUsagePercentAttribute(): float
    {
        if (!$this->credit_limit || $this->credit_limit <= 0) return 0;
        return round(($this->balance / $this->credit_limit) * 100, 1);
    }

    /**
     * Recalculate and persist the client's outstanding balance.
     * Balance = open invoice amounts − available credit-note credits.
     * Call this after any event that modifies invoice amounts, statuses, or credit notes.
     */
    public function recalculateBalance(): void
    {
        $outstanding = $this->invoices()
            ->whereIn('status', ['emise', 'envoyee', 'partiellement_payee', 'en_retard'])
            ->sum('remaining_amount');

        // [FIX-SOLDES-01] Subtract any credit-note credit still available to apply.
        // A validated avoir reduces what the client effectively owes.
        $availableCredit = $this->creditNotes()
            ->where('status', 'valide')
            ->sum('remaining_credit');

        $this->update(['balance' => max(0, (int) ($outstanding - $availableCredit))]);
    }
}
