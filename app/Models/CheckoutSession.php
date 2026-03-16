<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CheckoutSession extends Model
{
    protected $casts = [
        'expires_at' => 'datetime',
    ];
}
