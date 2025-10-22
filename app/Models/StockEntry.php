<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'warehouse_id',
        'variant_id',
        'quantity',
        'unit_cost',
        'type',
        'effective_at',
        'reason',
        'track_inventory',
        'employee_id',
        'note',
        'source_type',
        'source_id',
    ];


    protected $casts = [
        'quantity' => 'integer',
        'unit_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'effective_at' => 'datetime',
        'track_inventory' => 'boolean',
    ];

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function warehouse(): \Illuminate\Database\Eloquent\Relatiwons\BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function employee(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function source(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo();
    }

    public function stockLayers(): HasMany
    {
        return $this->hasMany(StockLayer::class, 'stock_entry_id');
    }

    public function stockConsumptions(): HasMany
    {
        return $this->hasMany(StockConsumption::class, 'stock_entry_id');
    }

    // Scope to recent entries
    public function scopeRecent($query, $limit = 50)
    {
        return $query->orderBy('effective_at', 'desc')->limit($limit);
    }

    // total_cost is a virtual column in DB; provide an accessor fallback if DB doesn't return it.
    public function getTotalCostAttribute($value)
    {
        if (!is_null($value)) return $value;

        return bcmul((string) $this->quantity, (string) $this->unit_cost, 2);
    }
}
