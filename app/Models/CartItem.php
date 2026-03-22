<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'cart_id',
        'variant_id',
        'quantity',
    ];

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function subtotal(): float
    {
        $variant = $this->variant;
        if (!$variant) {
            return 0.0;
        }

        $price = (float) app(\App\Services\ProductService::class)
            ->resolveVariantPricing($variant, $this->cart?->user, $variant->product)['current'];

        return round($price * (int) $this->quantity, 2);
    }
}
