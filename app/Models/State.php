<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class State extends Model
{
    protected $fillable = ['name', 'country_id'];
    public $timestamps = false;

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function lgas()
    {
        return $this->hasMany(Lga::class);
    }

    public function shippingZone()
    {
        return $this->belongsToMany(ShippingZone::class, 'shipping_zone_states');
    }
}
