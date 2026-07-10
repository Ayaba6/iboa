<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

// [Sync ERP] journal central des synchronisations inter-modules.
class SyncLog extends Model
{
    public const STATUS_PENDING  = 'pending';
    public const STATUS_SUCCESS  = 'success';
    public const STATUS_FAILED   = 'failed';
    public const STATUS_SKIPPED  = 'skipped';
    public const STATUS_RETRYING = 'retrying';

    protected $fillable = [
        'source_module',
        'target_module',
        'event_name',
        'action',
        'source_type',
        'source_id',
        'status',
        'message',
        'payload',
        'error_trace',
        'handler_class',
        'attempts',
        'processed_at',
        'created_by',
    ];

    protected $casts = [
        'payload'      => 'array',
        'attempts'     => 'integer',
        'processed_at' => 'datetime',
    ];

    public function source(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'source_type', 'source_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeFailed(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_FAILED);
    }

    /** Clé logique d'idempotence. */
    public function scopeForLogicalKey(Builder $q, string $sourceType, int $sourceId, string $target, string $action): Builder
    {
        return $q->where('source_type', $sourceType)
                 ->where('source_id', $sourceId)
                 ->where('target_module', $target)
                 ->where('action', $action);
    }

    public function isRetryable(): bool
    {
        return $this->status === self::STATUS_FAILED && $this->handler_class !== null;
    }
}
