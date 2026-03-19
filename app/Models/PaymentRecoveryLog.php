<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentRecoveryLog extends Model
{
    protected $guarded = [];

    protected $casts = [
        'request_payload' => 'array',
        'response_payload' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function checkoutSession()
    {
        return $this->belongsTo(CheckoutSession::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}

