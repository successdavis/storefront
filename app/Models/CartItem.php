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

        $onSale = $variant->sale_price !== null
            && (float) $variant->sale_price < (float) $variant->regular_price
            && (!$variant->sale_starts_at || $variant->sale_starts_at->isPast())
            && (!$variant->sale_ends_at || $variant->sale_ends_at->isFuture());

        $price = $onSale ? (float) $variant->sale_price : (float) $variant->regular_price;

        return round($price * (int) $this->quantity, 2);
    }
}
