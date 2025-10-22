<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ShippingRate extends Model
{
    protected $fillable = [
      'shipping_method_id','shipping_zone_id','min_weight','max_weight','min_subtotal','max_subtotal',
      'rate_type','base_rate','per_kg','surcharge','free_shipping_threshold','currency','starts_at','ends_at','is_active'
    ];

    protected $casts = [
      'starts_at' => 'datetime',
      'ends_at' => 'datetime',
      'is_active' => 'boolean',
    ];

    public function method() { return $this->belongsTo(ShippingMethod::class, 'shipping_method_id'); }
    public function zone() { return $this->belongsTo(ShippingZone::class, 'shipping_zone_id'); }
}
