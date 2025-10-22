<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ShippingZone extends Model
{
    protected $fillable = ['name'];
    public function states() { return $this->hasMany(ShippingZoneState::class); }
    public function rates() { return $this->hasMany(ShippingRate::class); }
}
