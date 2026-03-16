<?php

namespace App\Services;

use App\Models\Lga;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PickupLocation;
use App\Models\ShippingMethod;
use App\Models\State;
use App\Models\User;
use App\Services\Shipping\ShippingCostService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutService
{
    public function __construct(
        protected CartService $cartService,
        protected DiscountService $discountService,
        protected ShippingCostService $shippingCostService,
        protected OrderService $orderService,
        protected PaystackService $paystackService,
        protected ProductService $productService,
    ) {}

    public function getCheckoutData(User $user, array $params = []): array
    {
        $selection = $this->normalizeCheckoutSelection($params);
        $isPickupMethod = $this->isPickupMethod($selection['shipping_method_id']);

        $cartData = $this->cartService->getDetailedCart($selection['coupon'], (int) $user->id);
        $cartItems = $cartData['cart']['items'] ?? [];

        $shippingTotal = 0.0;
        $shippingError = null;

        if (!empty($cartItems) && !empty($selection['shipping_method_id'])) {
            if (empty($selection['state_id'])) {
                $shippingError = 'Please select a state to calculate shipping.';
            } elseif ($isPickupMethod && empty($selection['pickup_location_id'])) {
                $shippingError = 'Please select a pickup location.';
            } else {
                try {
                    $shippingTotal = $this->calculateShippingFromCart(
                        cartItems: $cartItems,
                        subtotal: (float) ($cartData['summary']['subtotal'] ?? 0),
                        selection: $selection,
                    );
                } catch (\Throwable $exception) {
                    report($exception);
                    $shippingError = 'Unable to calculate shipping with the selected options.';
                }
            }
        }

        $discountQuote = [
            'discount_id' => null,
            'label' => null,
            'amount' => 0.0,
        ];
        $couponError = $cartData['coupon_error'] ?? null;

        if (!empty($cartItems)) {
            try {
                $discountQuote = $this->discountService->previewQuote(
                    user: $user,
                    items: $this->toDiscountItems($cartItems),
                    subtotal: (float) ($cartData['summary']['subtotal'] ?? 0),
                    shippingTotal: $shippingTotal,
                    taxTotal: 0.0,
                    channel: 'online',
                    couponCode: $selection['coupon'] ?? ($cartData['summary']['coupon'] ?? null),
                );
            } catch (ValidationException $exception) {
                $couponError = collect($exception->errors())->flatten()->first() ?: 'Invalid coupon code.';
            }
        }

        $subtotal = round((float) ($cartData['summary']['subtotal'] ?? 0), 2);
        $discountAmount = round((float) ($discountQuote['amount'] ?? 0), 2);
        $shippingTotal = round($shippingTotal, 2);
        $total = max(round($subtotal + $shippingTotal - $discountAmount, 2), 0);

        return [
            'cart' => [
                'id' => $cartData['cart']['id'] ?? null,
                'status' => $cartData['cart']['status'] ?? 'active',
                'items' => $cartItems,
            ],
            'summary' => [
                'item_count' => (int) collect($cartItems)->sum('quantity'),
                'subtotal' => $subtotal,
                'discount' => $discountAmount,
                'discount_id' => $discountQuote['discount_id'] ?? null,
                'discount_label' => $discountQuote['label'] ?? null,
                'coupon' => $selection['coupon'] ?? ($cartData['summary']['coupon'] ?? null),
                'shipping' => $shippingTotal,
                'tax' => 0.0,
                'total' => $total,
            ],
            'coupon_error' => $couponError,
            'shipping_error' => $shippingError,
            'selected_shipping' => $selection,
            'is_pickup_method' => $isPickupMethod,
            'shipping_methods' => $this->listShippingMethods(),
            'states' => $this->listStates(),
//            'lgas' => $this->listLgas($selection['state_id']),
            'pickup_locations' => $this->listPickupLocations($selection['state_id'], $selection['shipping_method_id']),
            'cartCount' => $this->cartService->getCartCount((int) $user->id),
            'categories' => $this->productService->listStoreCategories(),
        ];
    }

    /**
     * @return array{authorization_url:string,reference:string,order_id:int}
     */
    public function initializePayment(User $user, array $params): array
    {
        $checkoutData = $this->getCheckoutData($user, $params);

        if (!empty($checkoutData['coupon_error'])) {
            throw ValidationException::withMessages([
                'coupon' => $checkoutData['coupon_error'],
            ]);
        }

        if (!empty($checkoutData['shipping_error'])) {
            throw ValidationException::withMessages([
                'shipping' => $checkoutData['shipping_error'],
            ]);
        }

        $this->assertCheckoutCanProceed($checkoutData);

        $reference = $this->generateReference();

        $token = $this->createCheckoutSessionToken($user, $checkoutData, $reference);

        $gateway = $this->paystackService->initializePayment([
            'amount' => (int) round(((float) $checkoutData['summary']['total']) * 100),
            'email' => $user->email,
            'reference' => $reference,
            'callback_url' => route('payment.verify'),
            'metadata' => [
                'checkout_token' => $token,
                'user_id' => $user->id,
                'source' => 'storefront_checkout',
            ],
        ]);

        return [
            'authorization_url' => $gateway['authorization_url'],
            'reference' => $reference,
            'checkout_token' => $token,
        ];
    }

    /**
     * @return array{success:bool,message:string,order:Order}
     */
    public function verifyPayment(User $user, string $reference): array
    {
        $verification = $this->paystackService->verifyPayment($reference);

        $status = strtolower($verification['status'] ?? '');

        if ($status !== 'success') {
            throw ValidationException::withMessages([
                'payment' => 'Payment verification failed.',
            ]);
        }

        $session = DB::table('checkout_sessions')
            ->where('reference', $reference)
            ->where('used', false)
            ->lockForUpdate()
            ->first();

        if (!$session) {
            throw ValidationException::withMessages([
                'payment' => 'Invalid or expired checkout session.',
            ]);
        }

        if ((int) $session->user_id !== (int) $user->id) {
            throw ValidationException::withMessages([
                'payment' => 'Unauthorized checkout session.',
            ]);
        }


        if ($session->expires_at && now()->isAfter($session->expires_at)) {
            throw ValidationException::withMessages([
                'payment' => 'Checkout session expired.',
            ]);
        }
        return DB::transaction(function () use ($session, $verification, $user, $reference) {

            $items = json_decode($session->items, true);

            $order = $this->orderService->handle([
                'customer_id' => $user->id,
                'channel' => 'online',
                'items' => collect($items)->map(fn ($i) => [
                    'variant_id' => $i['variant_id'],
                    'quantity' => $i['quantity'],
                    'price' => $i['unit_price'],
                ])->values()->all(),

                'subtotal' => $session->subtotal,
                'tax_total' => 0,
                'coupon' => data_get(json_decode($session->discount_snapshot, true), 'code'),

                'payment_method' => 'card',
                'payment_status' => 'paid',

                'transaction_reference' => $reference,
                'checkout_token' => $session->token,
            ]);


            DB::table('checkout_sessions')
                ->where('id', $session->id)
                ->update(['used' => true]);

            // clear redis cart
            $this->cartService->clearCart($user->id);

            return [
                'success' => true,
                'message' => 'Payment verified successfully.',
                'order' => $order,
            ];
        });
    }

    protected function assertCheckoutCanProceed(array $checkoutData): void
    {
        $items = $checkoutData['cart']['items'] ?? [];
        if (empty($items)) {
            throw ValidationException::withMessages([
                'cart' => 'Your cart is empty.',
            ]);
        }

        $selected = $checkoutData['selected_shipping'] ?? [];

        if (empty($selected['shipping_method_id'])) {
            throw ValidationException::withMessages([
                'shipping_method_id' => 'Please select a shipping method.',
            ]);
        }

        if (empty($selected['state_id'])) {
            throw ValidationException::withMessages([
                'state_id' => 'Please select a state.',
            ]);
        }

        if (empty($selected['phone'])) {
            throw ValidationException::withMessages([
                'phone' => 'Phone number is required for checkout.',
            ]);
        }

        $isPickup = (bool) ($checkoutData['is_pickup_method'] ?? false);

        if ($isPickup) {
            if (empty($selected['pickup_location_id'])) {
                throw ValidationException::withMessages([
                    'pickup_location_id' => 'Please select a pickup location.',
                ]);
            }

            $pickupLocation = PickupLocation::query()
                ->whereKey((int) $selected['pickup_location_id'])
                ->where('is_active', true)
                ->where('state_id', (int) $selected['state_id'])
                ->first();

            if (!$pickupLocation) {
                throw ValidationException::withMessages([
                    'pickup_location_id' => 'Selected pickup location is invalid for the chosen state.',
                ]);
            }

            return;
        }

        if (empty($selected['line1'])) {
            throw ValidationException::withMessages([
                'line1' => 'Delivery address is required.',
            ]);
        }

        if (empty($selected['lga_id'])) {
            throw ValidationException::withMessages([
                'lga_id' => 'Please select a city/LGA.',
            ]);
        }

        $lgaIsValid = Lga::query()
            ->whereKey((int) $selected['lga_id'])
            ->where('state_id', (int) $selected['state_id'])
            ->exists();

        if (!$lgaIsValid) {
            throw ValidationException::withMessages([
                'lga_id' => 'Selected city/LGA does not belong to the chosen state.',
            ]);
        }
    }

    protected function calculateShippingFromCart(array $cartItems, float $subtotal, array $selection): float
    {
        $payload = [
            'shipping_method_id' => $selection['shipping_method_id'],
            'state_id' => $selection['state_id'],
            'shipping_zone_id' => null,
            'pickup_location_id' => $selection['pickup_location_id'],
            'subtotal' => $subtotal,
            'items' => collect($cartItems)
                ->map(fn (array $item) => [
                    'variant_id' => (int) ($item['variant']['id'] ?? 0),
                    'quantity' => (float) ($item['quantity'] ?? 0),
                ])
                ->values()
                ->all(),
        ];

        $result = $this->shippingCostService->calculate($payload);

        return round((float) ($result['total'] ?? 0), 2);
    }

    protected function normalizeCheckoutSelection(array $params): array
    {
        return [
            'coupon' => $this->normalizeCoupon($params['coupon'] ?? null),
            'shipping_method_id' => !empty($params['shipping_method_id']) ? (int) $params['shipping_method_id'] : null,
            'state_id' => !empty($params['state_id']) ? (int) $params['state_id'] : null,
            'lga_id' => !empty($params['lga_id']) ? (int) $params['lga_id'] : null,
            'pickup_location_id' => !empty($params['pickup_location_id']) ? (int) $params['pickup_location_id'] : null,
            'phone' => $this->normalizeNullableString($params['phone'] ?? null),
            'line1' => $this->normalizeNullableString($params['line1'] ?? null),
        ];
    }

    protected function normalizeCoupon(mixed $coupon): ?string
    {
        if ($coupon === null) {
            return null;
        }

        $normalized = Str::upper(trim((string) $coupon));

        return $normalized !== '' ? $normalized : null;
    }

    protected function normalizeNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    protected function toDiscountItems(array $cartItems): array
    {
        return collect($cartItems)->map(fn (array $item) => [
            'variant_id' => (int) ($item['variant']['id'] ?? 0),
            'quantity' => (float) ($item['quantity'] ?? 0),
            'unit_price' => (float) data_get($item, 'variant.price.current', 0),
            'product_id' => data_get($item, 'product.id'),
            'category_ids' => $item['category_ids'] ?? [],
        ])->values()->all();
    }

    protected function toOrderItems(array $cartItems): array
    {
        return collect($cartItems)->map(fn (array $item) => [
            'variant_id' => (int) ($item['variant']['id'] ?? 0),
            'quantity' => (int) ($item['quantity'] ?? 0),
            'price' => (float) data_get($item, 'variant.price.current', 0),
        ])->values()->all();
    }

    protected function buildOrderShippingPayload(User $user, array $checkoutData): array
    {
        $selected = $checkoutData['selected_shipping'] ?? [];

        if (empty($selected['shipping_method_id'])) {
            return [];
        }

        $state = null;
        if (!empty($selected['state_id'])) {
            $state = State::query()->select(['id', 'country_id'])->find((int) $selected['state_id']);
        }

        $payload = [
            'shipping_method_id' => (int) $selected['shipping_method_id'],
            'state_id' => $selected['state_id'],
            'pickup_location_id' => $selected['pickup_location_id'],
        ];

        if (!($checkoutData['is_pickup_method'] ?? false)) {
            $payload['address'] = [
                'line1' => $selected['line1'],
                'phone' => $selected['phone'],
                'state_id' => $selected['state_id'],
                'lga_id' => $selected['lga_id'],
                'country_id' => $state?->country_id,
            ];

            return $payload;
        }

        $payload['address'] = [
            'line1' => $selected['line1'] ?? 'Pickup collection',
            'phone' => $selected['phone'],
            'state_id' => $selected['state_id'],
            'lga_id' => $selected['lga_id'],
            'country_id' => $state?->country_id,
        ];

        return $payload;
    }

    protected function createCheckoutSessionToken(User $user, array $checkoutData, string $reference): string
    {
        $token = hash_hmac(
            'sha256',
            Str::uuid()->toString().now()->timestamp.$user->id,
            config('app.key')
        );

        DB::table('checkout_sessions')->insert([
            'token' => $token,
            'reference' => $reference,
            'user_id' => $user->id,

            'items' => json_encode(
                collect($checkoutData['cart']['items'])->map(fn ($item) => [
                    'variant_id' => $item['variant']['id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['variant']['price']['current'],
                    'product_id' => $item['product']['id'],
                    'category_ids' => $item['category_ids'] ?? [],
                ])
            ),

            'subtotal' => $checkoutData['summary']['subtotal'],
            'shipping_total' => $checkoutData['summary']['shipping'],
            'discount_amount' => $checkoutData['summary']['discount'],
            'discount_id' => $checkoutData['summary']['discount_id'],

            'discount_snapshot' => json_encode([
                'code' => $checkoutData['summary']['coupon'],
                'label' => $checkoutData['summary']['discount_label'],
            ]),

            'total' => $checkoutData['summary']['total'],
            'channel' => 'online',
            'used' => false,
            'expires_at' => now()->addMinutes(30),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $token;
    }

    protected function generateReference(): string
    {
        $reference = 'PSTK-' . strtoupper(Str::random(18));

        while (Payment::query()->where('transaction_reference', $reference)->exists()) {
            $reference = 'PSTK-' . strtoupper(Str::random(18));
        }

        return $reference;
    }

    protected function markCartAsConverted(int $userId): void
    {
        $activeCart = DB::table('carts')
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->orderByDesc('id')
            ->first();

        if (!$activeCart) {
            return;
        }

        DB::table('carts')->where('id', $activeCart->id)->update(['status' => 'converted']);
        DB::table('cart_items')->where('cart_id', $activeCart->id)->delete();
    }

    protected function listShippingMethods(): Collection
    {
        return Cache::remember(
            'checkout:shipping_methods',
            now()->addHours(12),
            fn () => ShippingMethod::query()
                ->select('id','name')
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
        );
    }

    protected function listStates(): Collection
    {
        return Cache::remember(
            'checkout:states',
            now()->addDay(),
            fn () => State::query()
                ->select('id','name')
                ->orderBy('name')
                ->get()
        );
    }


    protected function listPickupLocations(?int $stateId, ?int $shippingMethodId): array
    {
        if (!$stateId || !$shippingMethodId || !$this->isPickupMethod($shippingMethodId)) {
            return [];
        }

        return PickupLocation::query()
            ->where('is_active', true)
            ->where('state_id', $stateId)
            ->where('shipping_method_id', $shippingMethodId)
            ->orderBy('name')
            ->get(['id', 'name', 'address_line1', 'phone'])
            ->map(fn (PickupLocation $location) => [
                'id' => (int) $location->id,
                'name' => $location->name,
                'address_line1' => $location->address_line1,
                'phone' => $location->phone,
            ])
            ->values()
            ->all();
    }

    protected function isPickupMethod(?int $shippingMethodId): bool
    {
        if (!$shippingMethodId) {
            return false;
        }

        $methodName = ShippingMethod::query()->whereKey($shippingMethodId)->value('name');

        return is_string($methodName) && str_contains(Str::lower($methodName), 'pickup');
    }

    protected function mapPaystackMethod(string $channel): string
    {
        return match (strtolower($channel)) {
            'card' => 'card',
            'bank_transfer', 'bank', 'transfer', 'eft' => 'transfer',
            'mobile_money', 'wallet', 'ussd', 'qr' => 'wallet',
            default => 'card',
        };
    }

    protected function decodeMeta(mixed $meta): array
    {
        if (is_array($meta)) {
            return $meta;
        }

        if (is_string($meta) && $meta !== '') {
            $decoded = json_decode($meta, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }
}
