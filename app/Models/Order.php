<?php

namespace App\Models;

use App\Traits\HasPayments;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;

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

    public function invoice(): HasOne
    {
        return $this->hasOne(CustomerInvoice::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function dropshipFulfillments(): HasMany
    {
        return $this->hasMany(DropshipFulfillment::class);
    }

    public function shipment(): MorphOne
    {
        return $this->morphOne(Shipment::class, 'shippable');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->latest();
    }

    public function notes(): HasMany
    {
        return $this->hasMany(OrderNote::class)->latest();
    }
}
