<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAuditItemLock extends Model
{
    protected $fillable = [
        'session_id',
        'variant_id',
        'warehouse_id',
        'warehouse_scope_key',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(StockAuditSession::class, 'session_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }
}
