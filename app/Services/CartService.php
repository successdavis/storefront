<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CartService
{
    public function getActiveCart()
    {
        return Cart::with('items.variant')->firstOrCreate([
            'user_id' => Auth::id(),
            'status' => 'active',
        ]);
    }

    public function addItem(array $data)
    {
        $cart = $this->getActiveCart();

        return $cart->items()->updateOrCreate(
            ['variant_id' => $data['variant_id']],
            ['quantity' => DB::raw("quantity + {$data['quantity']}")]
        );
    }

    public function removeItem(int $itemId)
    {
        CartItem::findOrFail($itemId)->delete();
    }
}
