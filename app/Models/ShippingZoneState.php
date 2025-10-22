<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShippingZoneState extends Model
{
    public function shippingZone()
    {
        return $this->belongsTo(ShippingZone::class);
    }
}
