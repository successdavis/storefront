<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockConsumption extends Model
{
    use HasFactory;

    protected $table = 'stock_consumptions';

    protected $fillable = [
        'stock_entry_id',
        'stock_layer_id',
        'quantity',
        'unit_cost',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_cost' => 'decimal:2',
    ];

    public function stockEntry(): BelongsTo
    {
        return $this->belongsTo(StockEntry::class, 'stock_entry_id');
    }

    public function stockLayer(): BelongsTo
    {
        return $this->belongsTo(StockLayer::class, 'stock_layer_id');
    }
}
