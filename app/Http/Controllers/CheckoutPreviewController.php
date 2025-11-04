<?php

namespace App\Http\Controllers;

use App\Models\ProductVariant;
use App\Models\User;
use App\Services\DiscountService;
use App\Services\OrderService;
use App\Services\Shipping\ShippingCostService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

// app/Http/Controllers/CheckoutPreviewController.php
class CheckoutPreviewController extends Controller
{
    public function __construct(
        protected ShippingCostService $shipping,
        protected DiscountService $discounts
    ) {}

    public function preview(Request $request)
    {
        $data = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.variant_id' => 'required|integer|exists:product_variants,id',
            'items.*.quantity'   => 'required|numeric|min:0.0001',
            'shipping' => 'nullable|array',
            'coupon'   => 'nullable|string',
            'channel'  => 'nullable|in:online,pos',
            'customer_id' => 'nullable|integer|exists:users,id',
            'checkout_token' => 'nullable|string|exists:checkout_sessions,token'
        ]);

        $channel = $data['channel'] ?? 'online';

        // 1) Subtotal (map items with unit price fetched from DB, etc.)
        [$items, $subtotal] = $this->buildItemsAndSubtotal($data['items']);


        // 2) Shipping
        $shippingTotal = 0.0;
        if (!empty($data['shipping'])) {
            $shippingTotal = $this->shipping->calculate([
                'subtotal' => $subtotal,
                'items'    => $items,
                'shipping_method_id' => $data['shipping']['shipping_method_id'] ?? null,
                'shipping_zone_id'   => $data['shipping']['shipping_zone_id'] ?? null,
                'state_id'           => $data['shipping']['state_id'] ?? null,
            ])['total'] ?? 0.0;
        }

        // Resolve user (null for guest)
        $user = $this->resolveUserId($data, 'pos');


        // 3) Discount preview
        $discount = $this->discounts->previewQuote(
            user: $user,
            items: $items,
            subtotal: $subtotal,
            shippingTotal: $shippingTotal,
            taxTotal: 0.0,
            channel: $channel,
            couponCode: $data['coupon'] ?? null
        );

        // 4) Compose snapshot (exact values used for payment)
        $subtotalRounded = round($subtotal, 2);
        $shippingRounded = round($shippingTotal, 2);
        $discountAmount = round($discount['amount'] ?? 0.0, 2);
        $total = round($subtotalRounded + $shippingRounded - $discountAmount, 2);

        // 5) Create a server-side checkout session and return token

        $existing = DB::table('checkout_sessions')
            ->where('token', $data['checkout_token'])
            ->where('used', false)
            ->orderByDesc('id')
            ->first();

        if($existing){
            $token = $existing->token;
            $now = Carbon::now();
            DB::table('checkout_sessions')->where('id', $existing->id)->update([
                'user_id' => $user?->id,
                'items' => json_encode($items),
                'subtotal' => $subtotalRounded,
                'shipping_total' => $shippingRounded,
                'discount_amount' => $discountAmount,
                'discount_id' => $discount['discount_id'] ?? null,
                'discount_snapshot' => json_encode($discount),
                'total' => $total,
                'channel' => $channel,
                'expires_at' => $now->addMinutes(30),
                'updated_at' => $now,
            ]);
        }else {
            $token = hash_hmac('sha256', Str::uuid()->toString() . now()->timestamp, config('app.key'));

            DB::table('checkout_sessions')->insert([
                'token' => $token,
                'user_id' => $user?->id,
                'items' => json_encode($items),
                'subtotal' => $subtotalRounded,
                'shipping_total' => $shippingRounded,
                'discount_amount' => $discountAmount,
                'discount_id' => $discount['discount_id'] ?? null,
                'discount_snapshot' => json_encode($discount),
                'total' => $total,
                'channel' => $channel,
                'expires_at' => Carbon::now()->addMinutes(30), // tune as needed
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }


        return response()->json([
            'subtotal'       => $subtotalRounded,
            'shipping_total' => $shippingRounded,
            'discount'       => $discountAmount,
            'discount_label' => $discount['label'],
            'total'          => $total,
            'checkout_token' => $token,
        ]);
    }

    protected function buildItemsAndSubtotal(array $payloadItems): array
    {
        $variants = ProductVariant::with(['product.categories'])
            ->whereIn('id', collect($payloadItems)->pluck('variant_id'))
            ->get()
            ->keyBy('id');

        $subtotal = 0;
        $items = [];

        foreach ($payloadItems as $line) {
            $v = $variants[$line['variant_id']];
            $unit = $v->sale_price ?? $v->regular_price;
            $qty  = (float)$line['quantity'];
            $subtotal += $unit * $qty;

            $items[] = [
                'variant_id'   => $v->id,
                'quantity'     => $qty,
                'unit_price'   => $unit,
                'product_id'   => $v->product_id,
                'category_ids' => $v->product?->categories?->pluck('id')->all() ?? [],
            ];
        }

        return [$items, round($subtotal, 2)];
    }

    protected function resolveUserId(array $payload, string $channel)
    {
        $user = $payload['customer_id'] ? User::find($payload['customer_id']) : null;

        if ($channel === 'pos' && empty($user)) {
            $walkInEmail = 'walkInCustomer@example.com';
            $walkInUser = User::where('email', $walkInEmail)->first();
            if (!$walkInUser) {
                // Intentionally fail to prompt admin to create walk-in user as requested.
                throw new InvalidArgumentException("Walk-in customer user with email {$walkInEmail} not found. Please create that user or provide user_id.");
            }
            $user = $walkInUser;
        }

        return $user;
    }
}

