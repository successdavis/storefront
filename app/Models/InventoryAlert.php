<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
        'resolved_at',
        'resolved_by',
    ];

    protected $casts = [
        'meta' => 'array',
        'first_detected_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class);
    }
}
