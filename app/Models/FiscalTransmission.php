<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * [Phase 2.3] Transmission fiscale d'un document (facture normalisée).
 * Statut fiscal DISTINCT du statut commercial. Une transmission acceptée
 * rend le document immuable (voir FiscalTransmission::isDocumentLocked).
 */
class FiscalTransmission extends Model
{
    protected $fillable = [
        'company_id', 'document_type', 'document_id', 'status',
        'external_reference', 'idempotency_key', 'transmitted_at', 'responded_at',
        'request_payload', 'response_payload', 'rejection_reason',
        'retry_count', 'last_retry_at', 'created_by',
    ];

    protected $casts = [
        'request_payload'  => 'array',
        'response_payload' => 'array',
        'transmitted_at'   => 'datetime',
        'responded_at'     => 'datetime',
        'last_retry_at'    => 'datetime',
    ];

    public function document(): MorphTo
    {
        return $this->morphTo();
    }

    /** Un document fiscalement transmis (accepté) ne se modifie plus JAMAIS. */
    public static function isDocumentLocked(Model $document): bool
    {
        return static::where('document_type', get_class($document))
            ->where('document_id', $document->getKey())
            ->where('status', 'accepte')
            ->exists();
    }
}
