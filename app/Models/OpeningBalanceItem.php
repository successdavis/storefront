<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class OpeningBalanceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'opening_balance_id',
        'variant_id',
        'quantity',
        'unit_cost',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function openingBalance()
    {
        return $this->belongsTo(OpeningBalance::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }


    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function getTotalCostAttribute(): float
    {
        return $this->quantity * ($this->unit_cost ?? 0);
    }
}
