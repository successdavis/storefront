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
        'stock_adjustment_id',
        'conflict_reason',
        'conflicted_with_session_id',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(StockAuditSession::class, 'session_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function stockAdjustment(): BelongsTo
    {
        return $this->belongsTo(StockAdjustment::class, 'stock_adjustment_id');
    }

    public function conflictedWithSession(): BelongsTo
    {
        return $this->belongsTo(StockAuditSession::class, 'conflicted_with_session_id');
    }
}
