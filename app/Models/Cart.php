<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cart extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'status',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function totalQuantity(): int
    {
        return (int) $this->items->sum('quantity');
    }

    public function totalPrice(): float
    {
        return (float) $this->items->sum(function (CartItem $item) {
            $variant = $item->variant;
            if (!$variant) {
                return 0;
            }

            $onSale = $variant->sale_price !== null
                && (float) $variant->sale_price < (float) $variant->regular_price
                && (!$variant->sale_starts_at || $variant->sale_starts_at->isPast())
                && (!$variant->sale_ends_at || $variant->sale_ends_at->isFuture());

            $price = $onSale ? $variant->sale_price : $variant->regular_price;

            return (float) $price * (int) $item->quantity;
        });
    }
}
