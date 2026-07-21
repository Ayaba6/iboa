<?php

namespace App\Models;

use App\Models\Traits\HasAttachments;
use App\Models\Traits\HasCompanyScope;
use App\Models\Traits\HasCreator;
use App\Traits\HasCommercialWorkflow;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory, SoftDeletes, HasCreator, HasCompanyScope, HasCommercialWorkflow, HasAttachments;

    const DOCUMENT_TYPE = 'order';

    protected $table = 'orders';

    protected $fillable = [
        'company_id',
        'client_id',
        'fiscal_year_id',
        'quote_id',
        'number',
        'reference',
        'status',
        'production_approved',
        'production_approved_at',
        'production_approved_by',
        'production_approval_reason',
        'production_approval_unpaid',
        'production_approval_expires_at',
        'issued_at',
        'expires_at',
        'delivery_date',
        'delivery_warehouse_id',
        'delivery_address',
        'billing_address',
        'currency_code',
        'exchange_rate',
        'subtotal_ht',
        'total_discount',
        'total_tax',
        'total_ttc',
        'global_discount_percent',
        'global_discount_amount',
        'invoiced_amount',
        'notes',
        'terms',
        'footer_note',
        'created_by',
        'validated_by',
        'validated_at',
        'submitted_by',
        'submitted_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        // [Maquette Commande client]
        'contact_id', 'sales_rep_id', 'price_mode', 'net_prices', 'price_list',
        'payment_terms', 'payment_method', 'fiscal_representative', 'fiscal_regime',
        'default_tax_label', 'project_reference',
        'carrier', 'vehicle_number', 'delivery_location', 'incoterm', 'priority', 'total_weight_kg',
    ];

    protected $casts = [
        'net_prices'              => 'boolean',
        'production_approved'     => 'boolean',
        'production_approved_at'  => 'datetime',
        'production_approval_expires_at' => 'date',
        'production_approval_unpaid'     => 'integer',
        'total_weight_kg'         => 'decimal:2',
        'issued_at'               => 'date',
        'expires_at'              => 'date',
        'delivery_date'           => 'date',
        'subtotal_ht'             => 'integer',
        'total_discount'          => 'integer',
        'total_tax'               => 'integer',
        'total_ttc'               => 'integer',
        'global_discount_percent' => 'decimal:2',
        'global_discount_amount'  => 'integer',
        'invoiced_amount'         => 'integer',
        'exchange_rate'           => 'decimal:6',
        'validated_at'            => 'datetime',
        'submitted_at'            => 'datetime',
        'rejected_at'             => 'datetime',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    // [Maquette Commande client]
    public function contact(): BelongsTo { return $this->belongsTo(ClientContact::class, 'contact_id'); }
    public function salesRep(): BelongsTo { return $this->belongsTo(User::class, 'sales_rep_id'); }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function deliveryWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'delivery_warehouse_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('sort_order');
    }

    public function deliveryNotes(): HasMany
    {
        return $this->hasMany(DeliveryNote::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function productionOrders(): HasMany
    {
        return $this->hasMany(\App\Modules\Production\Models\ProductionOrder::class);
    }

    public function bonPreparations(): HasMany
    {
        return $this->hasMany(BonPreparation::class);
    }

    public function productionApprovedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'production_approved_by');
    }

    /** OF actif (non annulé) lié à la commande. */
    public function hasActiveProductionOrder(): bool
    {
        return $this->productionOrders()->where('status', '!=', 'annule')->exists();
    }

    /**
     * [MTO §1.3 — méthode centrale] Encaissements confirmés rattachables à la commande :
     *   1. allocations confirmées sur les factures de la commande ;
     *   2. paiements caisse enregistrés sur les bons de préparation actifs (comptant) ;
     *   3. acomptes libres confirmés du client (non alloués).
     * Utilisée PAR LE TABLEAU d'éligibilité ET par la gate financière de lancement OF
     * (même règle, même source — exigence de recette).
     */
    /**
     * Montant retenu UNIQUEMENT pour l'éligibilité de la commande à la production
     * (tableau coordinateur + gate financière de lancement OF).
     *
     * Le résultat est PLAFONNÉ au TTC de la commande et ne représente pas
     * nécessairement le total comptable ou bancaire réellement encaissé.
     * Ne jamais l'utiliser pour : états d'encaissement, trésorerie, solde client,
     * comptabilité, remboursements, balance âgée, détection de trop-perçus.
     *
     * Pas de mémoïsation : une décision financière doit toujours refléter l'état
     * courant (un paiement ajouté/annulé dans la même exécution est vu au prochain appel).
     */
    public function confirmedReceipts(): int
    {
        // Seuls les paiements CONFIRMÉS comptent (brouillons/annulés/rejetés exclus) ;
        // un BP annulé est exclu (statuts actifs seulement). L'acompte libre affecté
        // ensuite à une facture est un TRANSFERT (unallocated ↓, allocation ↑) — pas
        // de double comptage par construction.
        $invoiceIds = \App\Models\Invoice::where('order_id', $this->id)->pluck('id');
        $viaInvoices = $invoiceIds->isNotEmpty()
            ? (int) \App\Models\ClientPaymentAllocation::whereIn('invoice_id', $invoiceIds)
                ->whereHas('clientPayment', fn ($q) => $q->where('status', 'confirme'))->sum('amount')
            : 0;

        $viaCaisse = (int) $this->bonPreparations()
            ->whereIn('status', ['en_attente', 'en_cours', 'charge'])
            ->sum('payment_amount');

        $acomptesLibres = (int) \App\Models\ClientPayment::where('client_id', $this->client_id)
            ->where('status', 'confirme')->where('is_acompte', true)
            ->sum('unallocated_amount');

        // Plafond au TTC : si le même argent caisse (BP) est ensuite ressaisi en
        // trésorerie et alloué à la facture (aucun lien BP↔ClientPayment n'existe),
        // la somme sur-compterait — le plafond rend ce cumul inoffensif pour
        // l'éligibilité et la gate (comparaisons bornées à 100 % du TTC).
        return min($viaInvoices + $viaCaisse + $acomptesLibres, (int) $this->total_ttc);
    }

    /**
     * [MTO §1.3] Montant minimum requis avant production selon le mode de paiement
     * du client : comptant = 100 % TTC ; acompte = TTC × taux paramétré ;
     * crédit/inconnu = jamais éligible financièrement (chemin approbation/DAF).
     */
    public function requiredBeforeProduction(): ?int
    {
        $mode = $this->client?->payment_mode;
        if ($mode === 'comptant') {
            return (int) $this->total_ttc;
        }
        if ($mode === 'acompte') {
            $rate = (float) (\App\Models\SalesSetting::current()->deposit_required_rate ?? 70);

            return (int) ceil((int) $this->total_ttc * $rate / 100);
        }

        return null; // crédit / non défini : pas de chemin financier direct
    }

    /** [MTO §1.3] Éligibilité financière = encaissements confirmés ≥ minimum requis. */
    public function isFinanciallyEligibleForProduction(): bool
    {
        $required = $this->requiredBeforeProduction();

        return $required !== null && $this->confirmedReceipts() >= $required;
    }

    /** [MTO §1.3] Approbation gérant valide (posée et non expirée). */
    public function hasValidProductionApproval(): bool
    {
        return (bool) $this->production_approved
            && ($this->production_approval_expires_at === null
                || $this->production_approval_expires_at->gte(today()));
    }

    /**
     * [Flux tôle bac §3 / MTO §1.3] Pré-filtre SQL de l'éligibilité : confirmée,
     * ≥ 1 article MTO, sans OF actif, ET (approbation valide OU BP actif).
     * Le volet financier exact (montant encaissé ≥ requis, 3 sources) n'est pas
     * exprimable proprement en SQL : il est appliqué par le contrôleur du tableau
     * via isFinanciallyEligibleForProduction() — même méthode que la gate OF.
     */
    public function scopeEligibleForProduction($query)
    {
        return $query->whereIn('status', ['confirme', 'en_preparation'])
            ->whereHas('items.product', fn ($q) => $q->where('production_mode', 'mto'))
            ->whereDoesntHave('productionOrders', fn ($q) => $q->where('status', '!=', 'annule'))
            ->where(fn ($q) => $q
                ->where(fn ($a) => $a
                    ->where('production_approved', true)
                    ->where(fn ($v) => $v
                        ->whereNull('production_approval_expires_at')
                        ->orWhereDate('production_approval_expires_at', '>=', today())))
                ->orWhereHas('bonPreparations', fn ($b) => $b->whereIn('status', ['en_attente', 'en_cours', 'charge'])));
    }

    /** Retourne true si la commande a un bon de préparation actif (pas annulé). */
    public function hasBonPreparation(): bool
    {
        return $this->bonPreparations()->whereIn('status', ['en_attente', 'en_cours', 'charge'])->exists();
    }

    /** Bon de préparation actif de la commande (en attente, en cours ou chargé). */
    public function activeBonPreparation(): ?BonPreparation
    {
        return $this->bonPreparations()
            ->whereIn('status', ['en_attente', 'en_cours', 'charge'])
            ->latest('id')
            ->first();
    }

    /**
     * [SYNC] Recalcule le montant facturé depuis les factures actives liées —
     * appelé à la validation ET à l'annulation d'une facture.
     */
    public static function resyncInvoicedAmount(?int $orderId): void
    {
        if (! $orderId) {
            return;
        }
        $total = Invoice::where('order_id', $orderId)
            ->whereNotIn('status', ['brouillon', 'annulee'])
            ->sum('total_ttc');
        static::withoutGlobalScopes()->where('id', $orderId)
            ->update(['invoiced_amount' => $total]);
    }

    /**
     * [CDC §13.7] Le BL ne se crée qu'après préparation + contrôle chargement :
     * si un bon de préparation existe, il doit être « chargé ». Sans BP
     * (flux direct hors préparation), la livraison reste possible.
     */
    public function isReadyForDelivery(): bool
    {
        $bp = $this->activeBonPreparation();

        return $bp === null || $bp->isCharge();
    }

    // ── Accessors workflow ────────────────────────────────────────────────────

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'brouillon'             => 'Brouillon',
            'en_attente_validation' => 'En attente de validation',
            'confirme'              => 'Confirmé',
            'en_preparation'        => 'En préparation',
            'partiellement_livre'   => 'Partiellement livré',
            'livre'                 => 'Livré',
            'facture'               => 'Facturé',
            'annule'                => 'Annulé',
            default                 => ucfirst($this->status),
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'brouillon'             => 'gray',
            'en_attente_validation' => 'yellow',
            'confirme'              => 'green',
            'en_preparation'        => 'blue',
            'partiellement_livre'   => 'indigo',
            'livre'                 => 'teal',
            'facture'               => 'purple',
            'annule'                => 'red',
            default                 => 'gray',
        };
    }

    protected function getValidatedStatuses(): array
    {
        return ['confirme', 'en_preparation', 'partiellement_livre', 'livre', 'facture'];
    }
}
