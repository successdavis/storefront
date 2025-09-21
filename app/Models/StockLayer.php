<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockLayer extends Model
{
    use HasFactory;

    protected $table = 'stock_layers';

    protected $fillable = [
        'variant_id',
        'qty_remaining',
        'unit_cost',
        'stock_entry_id',
        'source_type',
        'source_id',
    ];

    protected $casts = [
        'qty_remaining' => 'integer',
        'unit_cost' => 'decimal:2',
    ];

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function stockEntry(): BelongsTo
    {
        return $this->belongsTo(StockEntry::class, 'stock_entry_id');
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function consumptions(): HasMany
    {
        return $this->hasMany(StockConsumption::class, 'stock_layer_id');
    }

    public function isDepleted(): bool
    {
        return $this->qty_remaining <= 0;
    }
}
