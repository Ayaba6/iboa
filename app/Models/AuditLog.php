<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    // Table is append-only: no updated_at column
    public $timestamps  = false;
    const CREATED_AT    = 'created_at';

    protected $fillable = [
        'user_id',
        'user_name',
        'action',
        'model_type',
        'model_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'url',
        'prev_hash',
        'row_hash',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    // ─── Relations ──────────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ─── Helpers ────────────────────────────────────────────────────────────────

    public function actionLabel(): string
    {
        return match ($this->action) {
            'created'   => 'Créé',
            'updated'   => 'Modifié',
            'deleted'   => 'Supprimé',
            'viewed'    => 'Consulté',
            'login'     => 'Connexion',
            'logout'    => 'Déconnexion',
            'validated' => 'Validé',
            'sent'      => 'Envoyé',
            'exported'  => 'Exporté',
            default     => ucfirst($this->action),
        };
    }

    public function actionColor(): string
    {
        return match ($this->action) {
            'created'   => 'green',
            'updated'   => 'blue',
            'deleted'   => 'red',
            'login'     => 'indigo',
            'logout'    => 'gray',
            'validated' => 'purple',
            'sent'      => 'sky',
            'exported'  => 'amber',
            default     => 'gray',
        };
    }

    public function modelLabel(): string
    {
        if (! $this->model_type) {
            return '—';
        }

        return match ($this->model_type) {
            'App\\Models\\Invoice'         => 'Facture',
            'App\\Models\\Quote'           => 'Devis',
            'App\\Models\\Order'           => 'Commande',
            'App\\Models\\DeliveryNote'    => 'Bon de livraison',
            'App\\Models\\CreditNote'      => 'Avoir',
            'App\\Models\\PurchaseOrder'   => 'Commande achat',
            'App\\Models\\SupplierInvoice' => 'Facture fournisseur',
            'App\\Models\\Reception'       => 'Réception',
            'App\\Models\\ClientPayment'   => 'Paiement client',
            'App\\Models\\SupplierPayment' => 'Paiement fournisseur',
            'App\\Models\\StockMovement'   => 'Mouvement stock',
            'App\\Models\\InventorySession'=> 'Inventaire',
            'App\\Models\\Product'         => 'Produit',
            'App\\Models\\Client'          => 'Client',
            'App\\Models\\Supplier'        => 'Fournisseur',
            'App\\Models\\User'            => 'Utilisateur',
            default => class_basename($this->model_type),
        };
    }
}
