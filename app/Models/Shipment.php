<?php
namespace App\Models;
use App\Traits\HasPayments;
use Illuminate\Database\Eloquent\Model;

class Shipment extends Model
{
    use  HasPayments;
    protected $fillable = ['shipping_method_id','type','weight','cost','currency','status','ready_at','shipped_at','delivered_at','shippable_id','shippable_type','shipping_zone_id'];

    public function shippable() { return $this->morphTo(); }
    public function method() { return $this->belongsTo(ShippingMethod::class,'shipping_method_id'); }
    public function pickup() { return $this->hasOne(Pickup::class); }
    public function addresses() { return $this->hasMany(Address::class); }
}
