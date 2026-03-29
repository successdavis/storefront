<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ShippingMethod extends Model
{
    public const TYPE_DELIVERY = 'delivery';
    public const TYPE_PICKUP = 'pickup';

    protected $fillable = [
        'name',
        'description',
        'method_type',
        'is_active',
        'sort_order',
        'processing_days_min',
        'processing_days_max',
        'transit_days_min',
        'transit_days_max',
        'cutoff_time',
        'business_days_only',
        'supports_weekend_delivery',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'processing_days_min' => 'integer',
        'processing_days_max' => 'integer',
        'transit_days_min' => 'integer',
        'transit_days_max' => 'integer',
        'business_days_only' => 'boolean',
        'supports_weekend_delivery' => 'boolean',
    ];

    public function rates()
    {
        return $this->hasMany(ShippingRate::class);
    }

    public function pickupLocations()
    {
        return $this->hasMany(PickupLocation::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function isPickup(): bool
    {
        $type = strtolower((string) ($this->method_type ?? ''));

        return $type === self::TYPE_PICKUP || str_contains(strtolower((string) $this->name), 'pickup');
    }
}
