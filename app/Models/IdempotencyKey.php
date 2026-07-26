<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class IdempotencyKey extends Model
{
    protected $fillable = [
        'company_id', 'scope', 'idempotency_key', 'payload_hash',
        'source', 'external_reference', 'subject_type', 'subject_id', 'status',
    ];

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
