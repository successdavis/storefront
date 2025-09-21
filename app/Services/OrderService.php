<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function placeOrder()
    {
        $cart = Cart::with('items.variant')->where('user_id', Auth::id())->where('status', 'active')->firstOrFail();

        return DB::transaction(function () use ($cart) {
            $total = $cart->items->sum(fn($item) => $item->variant->price * $item->quantity);

            $order = Order::create([
                'user_id' => $cart->user_id,
                'total_amount' => $total,
                'discount' => 0,
                'channel' => 'online',
                'status' => 'pending',
            ]);

            foreach ($cart->items as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'variant_id' => $item->variant_id,
                    'quantity' => $item->quantity,
                    'price' => $item->variant->price,
                ]);
            }

            $cart->update(['status' => 'converted']);

            return $order->load('items.variant');
        });
    }

    public function listUserOrders()
    {
        return Order::with('items.variant')->where('user_id', Auth::id())->latest()->get();
    }
}

