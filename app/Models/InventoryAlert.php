<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryAlert extends Model
{
    protected $fillable = [
        'type',
        'severity',
        'variant_id',
        'warehouse_id',
        'message',
        'meta',
        'status',
        'first_detected_at',
        'last_seen_at',
        'acknowledged_at',
        'acknowledged_by',
        'snoozed_until',
        'snoozed_by',
        'snooze_reason',
        'suppressed_at',
        'suppressed_by',
        'suppress_reason',
        'resolved_at',
        'resolved_by',
        'resolved_reason',
    ];

    protected $casts = [
        'meta' => 'array',
        'first_detected_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'snoozed_until' => 'datetime',
        'suppressed_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function snoozedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'snoozed_by');
    }

    public function suppressedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'suppressed_by');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
