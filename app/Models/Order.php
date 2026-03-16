<?php

namespace App\Models;

use App\Traits\HasPayments;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory, HasPayments;

    protected $fillable = [
        'user_id', 'total_amount', 'discount', 'channel', 'status', 'order_number', 'subtotal', 'shipping_total', 'tax_total', 'currency'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function sale()
    {
        return $this->hasOne(Sale::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

}

