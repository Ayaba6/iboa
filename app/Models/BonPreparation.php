<?php

namespace App\Models;

use App\Models\Traits\HasCompanyScope;
use App\Models\Traits\HasCreator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** [CDC §bon-preparation] Document interne autorisant le magasinier à procéder au chargement. */
class BonPreparation extends Model
{
    // [Ventes §17] SoftDeletes ajouté : le modèle n'en portait pas, donc toute
    // suppression était définitive et irrattrapable hors sauvegarde SQL — sur un
    // document qui matérialise l'autorisation de sortie de marchandise.
    use HasFactory, HasCreator, HasCompanyScope, \Illuminate\Database\Eloquent\SoftDeletes;

    protected $table = 'bon_preparations';

    protected $fillable = [
        'company_id', 'order_id', 'fiscal_year_id', 'number', 'payment_mode', 'status',
        'payment_reference', 'payment_amount', 'payment_recorded_by', 'payment_recorded_at', 'client_payment_id',
        'validated_by', 'validated_at', 'loaded_by', 'loaded_at', 'notes', 'created_by',
        'cancelled_at', 'cancelled_by', 'cancellation_reason',
    ];

    protected $casts = [
        'payment_recorded_at' => 'datetime',
        'validated_at'        => 'datetime',
        'loaded_at'           => 'datetime',
        'cancelled_at'        => 'datetime',
        'payment_amount'      => 'integer',
    ];

    /**
     * [Ventes §17] Un bon n'est annulable que tant que la marchandise n'est pas
     * partie. `charge` signifie chargement terminé : à ce stade le camion peut
     * être sur la route et le bon de livraison en préparation. Revenir dessus
     * relève du retour client, pas de l'annulation.
     */
    public function isCancellable(): bool
    {
        return in_array($this->status, ['en_attente', 'en_cours'], true);
    }

    public function cancelledBy(): BelongsTo { return $this->belongsTo(User::class, 'cancelled_by'); }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function company(): BelongsTo    { return $this->belongsTo(Company::class); }
    public function order(): BelongsTo      { return $this->belongsTo(Order::class); }
    public function fiscalYear(): BelongsTo { return $this->belongsTo(FiscalYear::class); }
    public function createdBy(): BelongsTo  { return $this->belongsTo(User::class, 'created_by'); }
    public function validatedBy(): BelongsTo{ return $this->belongsTo(User::class, 'validated_by'); }
    public function loadedBy(): BelongsTo   { return $this->belongsTo(User::class, 'loaded_by'); }
    public function paymentRecordedBy(): BelongsTo { return $this->belongsTo(User::class, 'payment_recorded_by'); }

    // ── Accessors ─────────────────────────────────────────────────────────────

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'en_attente' => 'En attente',
            'en_cours'   => 'En cours de chargement',
            'charge'     => 'Chargé',
            'annule'     => 'Annulé',
            default      => ucfirst($this->status),
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'en_attente' => 'yellow',
            'en_cours'   => 'blue',
            'charge'     => 'green',
            'annule'     => 'red',
            default      => 'gray',
        };
    }

    public function getPaymentModeLabelAttribute(): string
    {
        return $this->payment_mode === 'cash' ? 'Comptant' : 'Ligne de crédit';
    }

    public function isCharge(): bool    { return $this->status === 'charge'; }
    public function isEnAttente(): bool { return $this->status === 'en_attente'; }
}
