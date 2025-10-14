<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OpeningBalance extends Model
{
    use HasFactory;

    protected $fillable = [
        'warehouse_id',
        'vendor_id',
        'employee_id',
        'reference',
        'effective_at',
        'note',
    ];

    protected $casts = [
        'effective_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function items()
    {
        return $this->hasMany(OpeningBalanceItem::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeActive($query)
    {
        return $query->whereNull('deleted_at');
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Compute total cost of all items.
     */
    public function getTotalCostAttribute(): float
    {
        return $this->items->sum(fn ($item) => $item->quantity * $item->unit_cost);
    }

    /**
     * Short label for UI or logs.
     */
    public function getLabelAttribute(): string
    {
        return 'Opening Balance #' . str_pad($this->id, 5, '0', STR_PAD_LEFT);
    }
}
