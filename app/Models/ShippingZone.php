<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ShippingZone extends Model
{
    protected $fillable = ['name'];
    public function states() { return $this->belongsToMany(ShippingZoneState::class, 'shipping_zone_states'); }
    public function rates() { return $this->hasMany(ShippingRate::class); }
}
