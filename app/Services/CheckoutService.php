<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\Lga;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PickupLocation;
use App\Models\ShippingMethod;
use App\Models\State;
use App\Models\User;
use App\Services\Shipping\ShippingCostService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutService
{
    public function __construct(
        protected CartService $cartService,
        protected PricingQuoteService $pricingQuoteService,
        protected OrderService $orderService,
        protected PaystackService $paystackService,
        protected ProductService $productService,
        protected StockReservationService $stockReservationService,
        protected DiscountService $discountService,
        protected CustomerAddressService $customerAddressService,
        protected ShippingCostService $shippingCostService,
    ) {}

    public function getCheckoutData(User $user, array $params = []): array
    {
        $savedAddresses = $this->customerAddressService->listForCheckout($user);
        $selection = $this->normalizeCheckoutSelection($user, $params);
        $isPickupMethod = $this->isPickupMethod($selection['shipping_method_id']);

        $cartData = $this->cartService->getDetailedCart($selection['coupon'], (int) $user->id);
        $cartItems = $cartData['cart']['items'] ?? [];

        $couponError = $cartData['coupon_error'] ?? null;
        $shippingError = null;

        $quoteSummary = [
            'item_count' => (int) collect($cartItems)->sum('quantity'),
            'subtotal' => round((float) ($cartData['summary']['subtotal'] ?? 0), 2),
            'discount' => round((float) ($cartData['summary']['discount'] ?? 0), 2),
            'discount_id' => null,
            'discount_label' => null,
            'coupon' => $selection['coupon'] ?? ($cartData['summary']['coupon'] ?? null),
            'shipping' => round((float) ($cartData['summary']['shipping'] ?? 0), 2),
            'shipping_free' => false,
            'tax' => 0.0,
            'total' => round((float) ($cartData['summary']['total'] ?? 0), 2),
        ];

        $pricingQuote = null;
        if (!empty($cartItems)) {
            $shippingPayload = $this->buildQuoteShippingPayload($selection);
            if (!empty($selection['shipping_method_id'])) {
                if ($isPickupMethod) {
                    if (empty($selection['state_id'])) {
                        $shippingError = 'Please select a state.';
                    } elseif (empty($selection['pickup_location_id'])) {
                        $shippingError = 'Please select a pickup location.';
                    }
                } elseif (empty($selection['state_id'])) {
                    $shippingError = 'Please select a state to calculate shipping.';
                }
            }

            try {
                $pricingQuote = $this->pricingQuoteService->quote([
                    'user' => $user,
                    'channel' => 'online',
                    'coupon' => $selection['coupon'] ?? ($cartData['summary']['coupon'] ?? null),
                    'items' => collect($cartItems)->map(fn (array $item) => [
                        'variant_id' => (int) ($item['variant']['id'] ?? 0),
                        'quantity' => (float) ($item['quantity'] ?? 0),
                    ])->values()->all(),
                    'shipping' => $shippingError ? null : $shippingPayload,
                    'tax_total' => 0.0,
                ]);

                $quoteSummary = [
                    'item_count' => (int) $pricingQuote['summary']['item_count'],
                    'subtotal' => (float) $pricingQuote['summary']['subtotal'],
                    'discount' => (float) $pricingQuote['summary']['discount_amount'],
                    'discount_id' => $pricingQuote['summary']['discount_id'],
                    'discount_label' => $pricingQuote['summary']['discount_label'],
                    'coupon' => $pricingQuote['summary']['coupon'],
                    'shipping' => (float) $pricingQuote['summary']['shipping_total'],
                    'shipping_free' => (bool) $pricingQuote['summary']['shipping_free'],
                    'tax' => (float) $pricingQuote['summary']['tax_total'],
                    'total' => (float) $pricingQuote['summary']['total'],
                ];
            } catch (ValidationException $exception) {
                $firstError = collect($exception->errors())->flatten()->first();

                if ($firstError) {
                    $couponError = $couponError ?: $firstError;
                }
            } catch (\Throwable $exception) {
                report($exception);
                $shippingError = $shippingError ?: $exception->getMessage();
            }
        }

        return [
            'cart' => [
                'id' => $cartData['cart']['id'] ?? null,
                'status' => $cartData['cart']['status'] ?? 'active',
                'items' => $cartItems,
            ],
            'summary' => $quoteSummary,
            'coupon_error' => $couponError,
            'shipping_error' => $shippingError,
            'selected_shipping' => $selection,
            'is_pickup_method' => $isPickupMethod,
            'shipping_methods' => $this->listShippingMethods(),
            'states' => $this->listStates(),
            'lgas' => $this->listLgas($selection['state_id']),
            'pickup_locations' => $this->listPickupLocations($selection['state_id'], $selection['shipping_method_id']),
            'saved_addresses' => $savedAddresses,
            'cartCount' => $this->cartService->getCartCount((int) $user->id),
            'categories' => $this->productService->listStoreCategories(),
            'pricing_quote' => $pricingQuote,
        ];
    }

    /**
     * @return array{authorization_url:string,reference:string,checkout_token:string}
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
        $this->rememberCheckoutAddressIfRequested($user, $checkoutData);

        $reference = $this->generateReference();
        $session = $this->createCheckoutSessionToken($user, $checkoutData, $reference);
        $sessionItems = DB::table('checkout_sessions')->where('id', $session['session_id'])->value('items');
        $items = is_string($sessionItems) ? (json_decode($sessionItems, true) ?? []) : [];

        try {
            $this->stockReservationService->reserveForSession(
                checkoutSessionId: (int) $session['session_id'],
                items: collect($items)->map(fn (array $line) => [
                    'variant_id' => (int) ($line['variant_id'] ?? 0),
                    'quantity' => (int) ($line['quantity'] ?? 0),
                ])->values()->all(),
                expiresAt: Carbon::parse($session['expires_at'])
            );
        } catch (InsufficientStockException $exception) {
            throw ValidationException::withMessages([
                'stock' => $exception->getMessage(),
            ]);
        }

        try {
            $gateway = $this->paystackService->initializePayment([
                'amount' => (int) round(((float) $checkoutData['summary']['total']) * 100),
                'email' => $user->email,
                'reference' => $reference,
                'callback_url' => route('payment.verify'),
                'metadata' => [
                    'checkout_token' => $session['token'],
                    'user_id' => $user->id,
                    'source' => 'storefront_checkout',
                ],
            ]);
        } catch (\Throwable $exception) {
            $this->stockReservationService->releaseForSession((int) $session['session_id'], 'payment_initialization_failed');
            throw $exception;
        }

        return [
            'authorization_url' => $gateway['authorization_url'],
            'reference' => $reference,
            'checkout_token' => $session['token'],
        ];
    }

    /**
     * @return array{success:bool,message:string,order:Order}
     */
    public function verifyPayment(User $user, string $reference): array
    {
        $result = $this->finalizeVerifiedPayment($reference, (int) $user->id);
        $this->cartService->clearCart((int) $user->id);

        return $result;
    }

    /**
     * Manual re-verification entry point (for admin/support tooling).
     *
     * @return array{success:bool,message:string,order:Order}
     */
    public function reverifyPayment(string $reference): array
    {
        return $this->finalizeVerifiedPayment($reference, null);
    }

    public function processPaystackWebhook(string $reference, array $payload = []): void
    {
        $this->finalizeVerifiedPayment($reference, null, $payload);
    }

    protected function finalizeVerifiedPayment(string $reference, ?int $expectedUserId = null, array $webhookPayload = []): array
    {
        if (trim($reference) === '') {
            throw ValidationException::withMessages([
                'reference' => 'Missing payment reference.',
            ]);
        }

        $verification = $this->paystackService->verifyPayment($reference);
        $status = strtolower((string) ($verification['status'] ?? ''));

        if ($status !== 'success') {
            throw ValidationException::withMessages([
                'payment' => 'Payment verification failed.',
            ]);
        }

        $session = DB::transaction(function () use ($reference, $expectedUserId, $verification, $webhookPayload) {
            $session = DB::table('checkout_sessions')
                ->where('reference', $reference)
                ->lockForUpdate()
                ->first();

            if (!$session) {
                throw ValidationException::withMessages([
                    'payment' => 'Invalid checkout session for this payment.',
                ]);
            }

            if ($expectedUserId !== null && (int) $session->user_id !== $expectedUserId) {
                throw ValidationException::withMessages([
                    'payment' => 'Unauthorized checkout session.',
                ]);
            }

            if (!empty($session->order_id)) {
                return $session;
            }

            $paidAmount = round(((float) ($verification['amount'] ?? 0)) / 100, 2);
            $expectedTotal = round((float) $session->total, 2);
            if ($paidAmount > 0 && abs($paidAmount - $expectedTotal) > $this->discountService->getMoneyTolerance()) {
                throw ValidationException::withMessages([
                    'payment' => 'Payment amount does not match checkout amount.',
                ]);
            }

            DB::table('checkout_sessions')
                ->where('id', $session->id)
                ->update([
                    'payment_status' => 'paid',
                    'payment_verified_at' => now(),
                    'payment_amount' => $paidAmount > 0 ? $paidAmount : null,
                    'payment_currency' => strtoupper((string) ($verification['currency'] ?? 'NGN')),
                    'verification_payload' => json_encode([
                        'verify' => $verification,
                        'webhook' => $webhookPayload,
                    ]),
                    'processing_error' => null,
                    'updated_at' => now(),
                ]);

            return DB::table('checkout_sessions')->where('id', $session->id)->first();
        }, 3);

        if (!empty($session->order_id)) {
            $order = Order::query()->findOrFail((int) $session->order_id);

            return [
                'success' => true,
                'message' => 'Payment already processed.',
                'order' => $order,
            ];
        }

        try {
            $method = $this->mapPaystackMethod((string) ($verification['channel'] ?? 'card'));

            $order = $this->orderService->handle([
                'customer_id' => (int) $session->user_id,
                'channel' => 'online',
                'payment_method' => $method,
                'payment_status' => 'paid',
                'transaction_reference' => $reference,
                'checkout_token' => (string) $session->token,
            ]);

            DB::table('checkout_sessions')
                ->where('id', $session->id)
                ->update([
                    'order_id' => $order->id,
                    'payment_status' => 'fulfilled',
                    'processed_at' => now(),
                    'processing_error' => null,
                    'used' => true,
                    'updated_at' => now(),
                ]);

            return [
                'success' => true,
                'message' => 'Payment verified successfully.',
                'order' => $order,
            ];
        } catch (InsufficientStockException $exception) {
            $this->stockReservationService->releaseForSession((int) $session->id, 'insufficient_stock_after_payment');

            $refundPayload = null;
            try {
                $refundPayload = $this->paystackService->refundPayment(
                    reference: $reference,
                    amountKobo: null,
                    reason: 'Auto refund: items unavailable at order finalization'
                );
            } catch (\Throwable $refundException) {
                $refundPayload = [
                    'error' => $refundException->getMessage(),
                ];
            }

            DB::table('checkout_sessions')
                ->where('id', $session->id)
                ->update([
                    'payment_status' => 'refund_initiated',
                    'processing_error' => $exception->getMessage(),
                    'verification_payload' => json_encode([
                        'verify' => $verification,
                        'refund' => $refundPayload,
                    ]),
                    'retry_count' => DB::raw('retry_count + 1'),
                    'updated_at' => now(),
                ]);

            throw ValidationException::withMessages([
                'payment' => 'Payment was received but stock became unavailable. Refund has been initiated.',
            ]);
        } catch (\Throwable $exception) {
            DB::table('checkout_sessions')
                ->where('id', $session->id)
                ->update([
                    'processing_error' => $exception->getMessage(),
                    'retry_count' => DB::raw('retry_count + 1'),
                    'updated_at' => now(),
                ]);

            throw $exception;
        }
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
                ->first();

            if (!$pickupLocation || !$this->shippingCostService->pickupLocationMatchesState($pickupLocation, (int) $selected['state_id'])) {
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

    protected function normalizeCheckoutSelection(User $user, array $params): array
    {
        $selection = [
            'coupon' => $this->normalizeCoupon($params['coupon'] ?? null),
            'address_id' => !empty($params['address_id']) ? (int) $params['address_id'] : null,
            'shipping_method_id' => !empty($params['shipping_method_id']) ? (int) $params['shipping_method_id'] : null,
            'state_id' => !empty($params['state_id']) ? (int) $params['state_id'] : null,
            'lga_id' => !empty($params['lga_id']) ? (int) $params['lga_id'] : null,
            'pickup_location_id' => !empty($params['pickup_location_id']) ? (int) $params['pickup_location_id'] : null,
            'phone' => $this->normalizeNullableString($params['phone'] ?? null),
            'line1' => $this->normalizeNullableString($params['line1'] ?? null),
            'line2' => $this->normalizeNullableString($params['line2'] ?? null),
            'recipient_name' => null,
            'email' => null,
            'postal_code' => null,
            'country_id' => null,
            'save_address' => !empty($params['save_address']),
        ];

        $selectedAddress = null;
        if (!empty($selection['address_id'])) {
            $selectedAddress = $this->customerAddressService->findForUser($user, (int) $selection['address_id']);
        } elseif (
            empty($selection['state_id'])
            && empty($selection['lga_id'])
            && empty($selection['phone'])
            && empty($selection['line1'])
            && empty($selection['line2'])
        ) {
            $selectedAddress = $this->customerAddressService->defaultForUser($user);
            $selection['address_id'] = $selectedAddress?->id;
        }

        if ($selectedAddress) {
            $selection['address_id'] = (int) $selectedAddress->id;
            $selection['country_id'] = $selectedAddress->country_id ? (int) $selectedAddress->country_id : null;
            $selection['state_id'] = $selection['state_id'] ?: ($selectedAddress->state_id ? (int) $selectedAddress->state_id : null);
            $selection['lga_id'] = $selection['lga_id'] ?: ($selectedAddress->lga_id ? (int) $selectedAddress->lga_id : null);
            $selection['phone'] = $selection['phone'] ?: $this->normalizeNullableString($selectedAddress->phone);
            $selection['line1'] = $selection['line1'] ?: $this->normalizeNullableString($selectedAddress->line1);
            $selection['line2'] = $selection['line2'] ?: $this->normalizeNullableString($selectedAddress->line2);
            $selection['recipient_name'] = $this->normalizeNullableString($selectedAddress->recipient_name);
            $selection['email'] = $this->normalizeNullableString($selectedAddress->email);
            $selection['postal_code'] = $this->normalizeNullableString($selectedAddress->postal_code);
        }

        return $selection;
    }

    protected function rememberCheckoutAddressIfRequested(User $user, array &$checkoutData): void
    {
        if (($checkoutData['is_pickup_method'] ?? false) || empty($checkoutData['selected_shipping']['save_address'])) {
            return;
        }

        $savedAddress = $this->customerAddressService->rememberCheckoutAddress($user, $checkoutData['selected_shipping']);

        if (!$savedAddress) {
            return;
        }

        $payload = $this->customerAddressService->toCheckoutPayload($savedAddress);
        $checkoutData['selected_shipping'] = array_merge($checkoutData['selected_shipping'], [
            'address_id' => $payload['id'],
            'recipient_name' => $payload['recipient_name'],
            'email' => $payload['email'],
            'phone' => $payload['phone'],
            'line1' => $payload['line1'],
            'line2' => $payload['line2'],
            'country_id' => $payload['country_id'],
            'state_id' => $payload['state_id'],
            'lga_id' => $payload['lga_id'],
            'postal_code' => $payload['postal_code'],
        ]);
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

        $countryId = $selected['country_id'] ?? $state?->country_id;
        $recipientName = $selected['recipient_name'] ?? $user->name;
        $email = $selected['email'] ?? $user->email;

        $payload = [
            'address_id' => $selected['address_id'] ?? null,
            'shipping_method_id' => (int) $selected['shipping_method_id'],
            'state_id' => $selected['state_id'],
            'pickup_location_id' => $selected['pickup_location_id'],
            'phone' => $selected['phone'],
            'line1' => $selected['line1'],
            'line2' => $selected['line2'] ?? null,
            'lga_id' => $selected['lga_id'],
            'postal_code' => $selected['postal_code'] ?? null,
            'country_id' => $countryId,
            'recipient_name' => $recipientName,
            'email' => $email,
        ];

        $payload['address'] = [
            'name' => $recipientName,
            'phone' => $selected['phone'],
            'email' => $email,
            'line1' => $selected['line1'] ?? 'Pickup collection',
            'line2' => $selected['line2'] ?? null,
            'postal_code' => $selected['postal_code'] ?? null,
            'state_id' => $selected['state_id'],
            'lga_id' => $selected['lga_id'],
            'country_id' => $countryId,
        ];

        return $payload;
    }

    /**
     * @return array{token:string,session_id:int,expires_at:string}
     */
    protected function createCheckoutSessionToken(User $user, array $checkoutData, string $reference): array
    {
        $token = hash_hmac(
            'sha256',
            Str::uuid()->toString().now()->timestamp.$user->id,
            (string) config('app.key')
        );

        $quote = $checkoutData['pricing_quote'] ?? null;
        $items = $quote['items'] ?? collect($checkoutData['cart']['items'])->map(fn ($item) => [
            'variant_id' => (int) $item['variant']['id'],
            'quantity' => (float) $item['quantity'],
            'unit_price' => (float) $item['variant']['price']['current'],
            'product_id' => (int) $item['product']['id'],
            'category_ids' => $item['category_ids'] ?? [],
        ])->values()->all();

        $discountSnapshot = $quote['discount_snapshot'] ?? [
            'discount_id' => $checkoutData['summary']['discount_id'] ?? null,
            'code' => $checkoutData['summary']['coupon'] ?? null,
            'label' => $checkoutData['summary']['discount_label'] ?? null,
            'amount' => (float) ($checkoutData['summary']['discount'] ?? 0),
        ];

        $shippingSnapshot = array_replace(
            $quote['shipping_snapshot'] ?? [],
            $this->buildOrderShippingPayload($user, $checkoutData),
        );
        $expiresAt = now()->addMinutes(30);

        $sessionId = DB::table('checkout_sessions')->insertGetId([
            'token' => $token,
            'reference' => $reference,
            'user_id' => $user->id,
            'items' => json_encode($items),
            'subtotal' => (float) $checkoutData['summary']['subtotal'],
            'shipping_total' => (float) $checkoutData['summary']['shipping'],
            'discount_amount' => (float) $checkoutData['summary']['discount'],
            'discount_id' => $checkoutData['summary']['discount_id'] ?? null,
            'discount_snapshot' => json_encode($discountSnapshot),
            'shipping_snapshot' => json_encode($shippingSnapshot),
            'total' => (float) $checkoutData['summary']['total'],
            'channel' => 'online',
            'payment_status' => 'pending',
            'used' => false,
            'expires_at' => $expiresAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'token' => $token,
            'session_id' => (int) $sessionId,
            'expires_at' => $expiresAt->toDateTimeString(),
        ];
    }

    protected function generateReference(): string
    {
        $reference = 'PSTK-' . strtoupper(Str::random(18));

        while (
            Payment::query()->where('transaction_reference', $reference)->exists()
            || DB::table('checkout_sessions')->where('reference', $reference)->exists()
        ) {
            $reference = 'PSTK-' . strtoupper(Str::random(18));
        }

        return $reference;
    }

    protected function listShippingMethods(): Collection
    {
        return Cache::remember(
            'checkout:shipping_methods',
            now()->addHours(12),
            fn () => $this->shippingCostService->listActiveMethods()
                ->map(fn (ShippingMethod $method) => [
                    'id' => (int) $method->id,
                    'name' => $method->name,
                    'description' => $method->description,
                    'method_type' => $method->method_type,
                    'sort_order' => (int) $method->sort_order,
                ])
        );
    }

    protected function listStates(): Collection
    {
        return Cache::remember(
            'checkout:states',
            now()->addDay(),
            fn () => State::query()
                ->select('id', 'name')
                ->orderBy('name')
                ->get()
        );
    }

    protected function listPickupLocations(?int $stateId, ?int $shippingMethodId): array
    {
        if (!$stateId || !$shippingMethodId || !$this->isPickupMethod($shippingMethodId)) {
            return [];
        }

        return $this->shippingCostService
            ->listPickupLocationsForState($stateId, $shippingMethodId)
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

        return $this->shippingCostService->isPickupMethod($shippingMethodId);
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

    protected function buildQuoteShippingPayload(array $selection): array
    {
        return [
            'address_id' => $selection['address_id'] ?? null,
            'shipping_method_id' => $selection['shipping_method_id'],
            'state_id' => $selection['state_id'],
            'lga_id' => $selection['lga_id'],
            'pickup_location_id' => $selection['pickup_location_id'],
            'phone' => $selection['phone'],
            'line1' => $selection['line1'],
            'line2' => $selection['line2'] ?? null,
            'country_id' => $selection['country_id'] ?? null,
        ];
    }

    protected function listLgas(?int $stateId): array
    {
        if (!$stateId) {
            return [];
        }

        return Lga::query()
            ->where('state_id', $stateId)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Lga $lga) => [
                'id' => (int) $lga->id,
                'name' => $lga->name,
            ])
            ->values()
            ->all();
    }
}











