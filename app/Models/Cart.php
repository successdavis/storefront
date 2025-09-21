<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cart extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'status',
    ];

    /**
     * 🔗 Customer (User) who owns this cart
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /**
     * 🔗 Items added to the cart
     */
    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * 💡 Get the total quantity of items in the cart
     */
    public function totalQuantity(): int
    {
        return $this->items->sum('quantity');
    }

    /**
     * 💰 Get the total price of items in the cart
     */
    public function totalPrice(): float
    {
        return $this->items->sum(function ($item) {
            return $item->variant->price * $item->quantity;
        });
    }
}
