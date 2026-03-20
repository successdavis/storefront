<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAuditItem extends Model
{
    protected $fillable = [
        'session_id',
        'variant_id',
        'system_quantity',
        'physical_quantity',
        'variance',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(StockAuditSession::class, 'session_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }
}
