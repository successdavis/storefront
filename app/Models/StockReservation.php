<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockReservation extends Model
{
    protected $fillable = [
        'checkout_session_id',
        'variant_id',
        'quantity',
        'status',
        'expires_at',
        'consumed_at',
        'released_at',
        'release_reason',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'consumed_at' => 'datetime',
        'released_at' => 'datetime',
    ];
}

