<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class PickupLocation extends Model
{
    protected $fillable = [
      'shipping_method_id','shipping_zone_id','name','address_line1','address_line2',
      'state_code','postal_code','country_id','state_id','lga_id','city_id','latitude','longitude',
      'phone','email','timezone','opening_hours','slot_duration_minutes','capacity_per_slot','lead_time_hours','is_active'
    ];

    protected $casts = ['opening_hours' => 'array', 'is_active' => 'boolean'];

    public function method()
    {
        return $this->belongsTo(ShippingMethod::class, 'shipping_method_id');
    }

    public function zone()
    {
        return $this->belongsTo(ShippingZone::class, 'shipping_zone_id');
    }

    public function state()
    {
        return $this->belongsTo(State::class);
    }

    public function lga()
    {
        return $this->belongsTo(Lga::class);
    }
}
