<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Order::class);

        $orders = Order::query()
            ->where('user_id', auth()->id())
            ->latest()
            ->paginate(10)
            ->through(fn (Order $order) => [
                'id' => (int) $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'total_amount' => (float) $order->total_amount,
                'currency' => $order->currency,
                'item_count' => (int) $order->items()->sum('quantity'),
                'created_at' => optional($order->created_at)?->toIso8601String(),
            ]);

        return Inertia::render('Account/Orders/Index', [
            'orders' => $orders,
        ]);
    }

    public function show(Order $order): Response
    {
        $this->authorize('view', $order);

        $order->load([
            'items.variant.product.images',
            'items.variant.values.type',
            'payments',
            'shipment.addresses.country',
            'shipment.addresses.state',
            'shipment.addresses.lga',
        ]);

        return Inertia::render('Account/Orders/Show', [
            'order' => [
                'id' => (int) $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'subtotal' => (float) $order->subtotal,
                'shipping_total' => (float) $order->shipping_total,
                'tax_total' => (float) $order->tax_total,
                'discount' => (float) $order->discount,
                'total_amount' => (float) $order->total_amount,
                'currency' => $order->currency,
                'created_at' => optional($order->created_at)?->toIso8601String(),
                'items' => $order->items->map(function ($item) {
                    $variant = $item->variant;
                    $product = $variant?->product;

                    return [
                        'id' => (int) $item->id,
                        'quantity' => (int) $item->quantity,
                        'price' => (float) $item->price,
                        'subtotal' => round((float) $item->price * (int) $item->quantity, 2),
                        'product' => [
                            'name' => $product?->name,
                            'slug' => $product?->slug,
                            'image' => $product && $variant ? app(\App\Services\ProductService::class)->resolveProductImage($product, $variant) : null,
                        ],
                        'variant' => [
                            'sku' => $variant?->sku,
                            'label' => $variant?->values?->map(fn ($value) => trim(($value->type?->name ? $value->type->name . ': ' : '') . $value->value))->implode(' / '),
                        ],
                    ];
                })->values(),
                'payments' => $order->payments->map(fn ($payment) => [
                    'id' => (int) $payment->id,
                    'method' => $payment->method,
                    'amount' => (float) $payment->amount,
                    'currency' => $payment->currency,
                    'status' => $payment->status,
                    'paid_at' => optional($payment->paid_at)?->toIso8601String(),
                    'reference' => $payment->transaction_reference,
                ])->values(),
                'shipment' => $order->shipment ? [
                    'status' => $order->shipment->status,
                    'method' => $order->shipment->method?->name,
                    'addresses' => $order->shipment->addresses->map(fn ($address) => [
                        'type' => $address->type,
                        'name' => $address->name,
                        'phone' => $address->phone,
                        'line1' => $address->line1,
                        'line2' => $address->line2,
                        'state' => $address->state?->name,
                        'lga' => $address->lga?->name,
                        'country' => $address->country?->name,
                    ])->values(),
                ] : null,
            ],
        ]);
    }
}
