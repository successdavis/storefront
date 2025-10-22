<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Pickup extends Model
{
    protected $fillable = ['shipment_id','pickup_location_id','window_start','window_end','contact_name','contact_phone','reference'];
    public function shipment() { return $this->belongsTo(Shipment::class); }
    public function location() { return $this->belongsTo(PickupLocation::class,'pickup_location_id'); }
}
