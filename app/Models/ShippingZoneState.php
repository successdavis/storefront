<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShippingZoneState extends Model
{
    protected $fillable = ['shipping_zone_id', 'state_id'];

    public function shippingZone()
    {
        return $this->belongsTo(ShippingZone::class);
    }

    public function state()
    {
        return $this->belongsTo(State::class);
    }
}
