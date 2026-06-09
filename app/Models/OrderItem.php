<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'variant_id',
        'quantity',
        'price',
        'fulfillment_type',
        'supplier_id',
        'supplier_cost',
        'dropship_status',
        'supplier_ordered_at',
        'supplier_confirmed_at',
        'supplier_expected_delivery_at',
        'supplier_received_at',
        'supplier_reference',
        'dropship_admin_note',
        'dropship_meta',
    ];

    protected $casts = [
        'supplier_cost' => 'decimal:2',
        'supplier_ordered_at' => 'datetime',
        'supplier_confirmed_at' => 'datetime',
        'supplier_expected_delivery_at' => 'datetime',
        'supplier_received_at' => 'datetime',
        'dropship_meta' => 'array',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Vendor::class, 'supplier_id');
    }

    public function dropshipFulfillment()
    {
        return $this->hasOne(DropshipFulfillment::class);
    }

    public function isDropshipping(): bool
    {
        return $this->fulfillment_type === ProductVariant::FULFILLMENT_DROPSHIPPING;
    }
}
