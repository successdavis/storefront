<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ShippingMethod extends Model
{
    public const TYPE_DELIVERY = 'delivery';
    public const TYPE_PICKUP = 'pickup';

    protected $fillable = ['name', 'description', 'method_type', 'is_active', 'sort_order'];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
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
