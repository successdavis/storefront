<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CheckoutSession extends Model
{
    protected $guarded = [];

    protected $casts = [
        'items' => 'array',
        'discount_snapshot' => 'array',
        'shipping_snapshot' => 'array',
        'verification_payload' => 'array',
        'used' => 'boolean',
        'expires_at' => 'datetime',
        'payment_verified_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
