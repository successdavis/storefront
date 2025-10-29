<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $order_id
 * @property int $discount_id
 * @property string $discount_amount   // decimal(10,2) casted
 */
class OrderDiscount extends Model
{
    use HasFactory;

    protected $table = 'order_discounts';

    protected $fillable = [
        'order_id',
        'discount_id',
        'discount_amount',
    ];

    protected $casts = [
        'discount_amount' => 'decimal:2',
    ];

    /* -----------------------------------------
     | Relationships
     |------------------------------------------*/

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function discount()
    {
        return $this->belongsTo(Discount::class);
    }
}
