<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ShippingRate extends Model
{
    protected $fillable = [
      'shipping_method_id', 'shipping_zone_id', 'state_id', 'lga_id', 'min_weight', 'max_weight', 'min_subtotal', 'max_subtotal',
      'rate_type', 'base_rate', 'per_kg', 'surcharge', 'free_shipping_threshold', 'estimated_delivery_text',
      'processing_days_min', 'processing_days_max', 'transit_days_min', 'transit_days_max', 'cutoff_time',
      'business_days_only', 'supports_weekend_delivery',
      'currency', 'starts_at', 'ends_at', 'is_active', 'sort_order'
    ];

    protected $casts = [
      'starts_at' => 'datetime',
      'ends_at' => 'datetime',
      'is_active' => 'boolean',
      'sort_order' => 'integer',
      'processing_days_min' => 'integer',
      'processing_days_max' => 'integer',
      'transit_days_min' => 'integer',
      'transit_days_max' => 'integer',
      'business_days_only' => 'boolean',
      'supports_weekend_delivery' => 'boolean',
    ];

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
