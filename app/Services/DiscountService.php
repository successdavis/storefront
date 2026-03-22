<?php

namespace App\Services;

use App\Models\Discount;
use App\Models\Order;
use App\Models\OrderDiscount;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class DiscountService
{
    protected ?Collection $cachedLineItemCandidates = null;

    public function __construct(
        protected float $moneyTolerance = 0.01
    ) {}

    /**
     * PREVIEW: Compute order-level discount amount without mutating DB.
     *
     * Automatic line-item discounts are resolved earlier in product pricing and are therefore
     * intentionally excluded from this method. This method remains responsible for coupons and
     * order-level automatic promotions like free shipping.
     *
     * @param User|null   $user
     * @param array       $items   Each: ['variant_id'=>int,'quantity'=>float,'unit_price'=>float,'product_id'=>int|null,'category_ids'=>array<int>]
     * @param float       $subtotal
     * @param float       $shippingTotal
     * @param float       $taxTotal
     * @param string      $channel  'online' | 'pos'
     * @param string|null $couponCode
     * @param Order|null  $contextOrder Optional - if provided, ignore this order when checking user's prior orders.
     * @return array{discount_id:int|null, code:string|null, label:string|null, amount:float}
     *
     * @throws ValidationException
     */
    public function previewQuote(
        ?User $user,
        array $items,
        float $subtotal,
        float $shippingTotal = 0.0,
        float $taxTotal = 0.0,
        string $channel = 'online',
        ?string $couponCode = null,
        ?Order $contextOrder = null
    ): array {
        $couponCode = $this->normalizeCoupon($couponCode);

        $candidates = Discount::query()
            ->with(['products:id', 'categories:id', 'variants:id', 'users:id'])
            ->orderTotal()
            ->where('is_active', true)
            ->when(
                $couponCode,
                fn ($query) => $query->where('code', $couponCode),
                fn ($query) => $query->whereNull('code')
            )
            ->withinDateWindow()
            ->get();

        if ($candidates->isEmpty() && $couponCode) {
            throw ValidationException::withMessages(['coupon' => 'Invalid or expired coupon.']);
        }

        $best = ['discount_id' => null, 'code' => $couponCode, 'label' => null, 'amount' => 0.0];

        foreach ($candidates as $discount) {
            if (!$this->isApplicable($discount, $user, $subtotal, $shippingTotal, $channel, $items, $contextOrder)) {
                continue;
            }

            $amount = $this->calculateAmount($discount, $items, $subtotal, $shippingTotal, $taxTotal, $channel);
            if ($amount > $best['amount']) {
                $best = [
                    'discount_id' => $discount->id,
                    'code' => $discount->code,
                    'label' => $discount->name,
                    'amount' => round($amount, 2),
                ];
            }
        }

        return $best;
    }

    /**
     * Resolve the best active automatic line-item discount for a variant.
     *
     * This powers storefront and cart item pricing. It does not mutate usage counts because the
     * order-level discount pipeline remains responsible for committed coupon/order promotions.
     *
     * @return array{
     *     discount_id:int|null,
     *     label:string|null,
     *     type:string|null,
     *     scope:string|null,
     *     amount:float,
     *     current:float,
     *     priority:int,
     *     percentage:int,
     *     value:float|null
     * }
     */
    public function resolveLineItemDiscount(
        ProductVariant $variant,
        float $regularPrice,
        ?User $user = null,
        string $channel = 'online',
        ?\DateTimeInterface $at = null,
        ?array $categoryIds = null
    ): array {
        $regularPrice = round($regularPrice, 2);

        if ($regularPrice <= 0) {
            return $this->emptyLineItemDiscount($regularPrice);
        }

        $at ??= CarbonImmutable::now();
        $categoryIds ??= $variant->product?->categories?->pluck('id')->map(fn ($id) => (int) $id)->all() ?? [];

        $best = null;

        foreach ($this->lineItemCandidates() as $discount) {
            if (!$this->isApplicableLineItemDiscount($discount, $variant, $user, $categoryIds, $channel, $at)) {
                continue;
            }

            $candidate = $this->buildLineItemDiscountPayload($discount, $variant, $regularPrice, $categoryIds);

            if ($best === null || $this->compareLineItemCandidates($candidate, $best) < 0) {
                $best = $candidate;
            }
        }

        return $best ?? $this->emptyLineItemDiscount($regularPrice);
    }

    public function commitForOrder(Order $order, ?string $couponCode = null): array
    {
        $user = $order->user;
        $items = $this->mapOrderItems($order->items);
        $subtotal = (float) $order->subtotal;
        $shippingTotal = (float) $order->shipping_total;
        $taxTotal = (float) $order->tax_total;
        $channel = $order->channel ?? 'online';

        $quote = $this->previewQuote($user, $items, $subtotal, $shippingTotal, $taxTotal, $channel, $couponCode, $order);

        if (!$quote['discount_id'] || $quote['amount'] <= 0) {
            return $this->ensureOrderHasNoDiscount($order);
        }

        return DB::transaction(function () use ($order, $user, $quote, $subtotal, $shippingTotal, $taxTotal) {
            $existing = OrderDiscount::where('order_id', $order->id)->first();
            if ($existing) {
                $this->applyOrderDiscountValues($order, (float) $existing->discount_amount, $subtotal, $shippingTotal, $taxTotal);

                return [
                    'applied' => true,
                    'amount' => (float) $existing->discount_amount,
                    'discount_id' => $existing->discount_id,
                ];
            }

            $row = OrderDiscount::create([
                'order_id' => $order->id,
                'discount_id' => $quote['discount_id'],
                'discount_amount' => $quote['amount'],
            ]);

            $this->applyOrderDiscountValues($order, (float) $row->discount_amount, $subtotal, $shippingTotal, $taxTotal);

            if ($user) {
                DB::table('discount_user')->updateOrInsert(
                    ['discount_id' => $quote['discount_id'], 'user_id' => $user->id],
                    [
                        'times_used' => DB::raw('COALESCE(times_used,0) + 1'),
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }

            return [
                'applied' => true,
                'amount' => (float) $row->discount_amount,
                'discount_id' => $row->discount_id,
            ];
        }, 3);
    }

    public function getMoneyTolerance(): float
    {
        return $this->moneyTolerance;
    }

    protected function ensureOrderHasNoDiscount(Order $order): array
    {
        return DB::transaction(function () use ($order) {
            $row = OrderDiscount::where('order_id', $order->id)->first();

            if ($row) {
                $discountId = $row->discount_id;
                $userId = $order->user_id;
                $row->delete();

                if ($discountId && $userId) {
                    $pivot = DB::table('discount_user')
                        ->where('discount_id', $discountId)
                        ->where('user_id', $userId)
                        ->lockForUpdate()
                        ->first();

                    if ($pivot && $pivot->times_used > 0) {
                        DB::table('discount_user')
                            ->where('discount_id', $discountId)
                            ->where('user_id', $userId)
                            ->update(['times_used' => $pivot->times_used - 1, 'updated_at' => now()]);
                    }
                }
            }

            $order->discount = 0.0;
            $order->total_amount = round($order->subtotal + $order->shipping_total + $order->tax_total, 2);
            $order->save();

            return ['applied' => false, 'amount' => 0.0, 'discount_id' => null];
        });
    }

    protected function applyOrderDiscountValues(Order $order, float $discountAmount, float $subtotal, float $shippingTotal, float $taxTotal): void
    {
        $order->discount = round($discountAmount, 2);
        $order->total_amount = max(round($subtotal + $shippingTotal + $taxTotal - $discountAmount, 2), 0);
        $order->save();
    }

    public function revokeForOrder(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $row = OrderDiscount::where('order_id', $order->id)->lockForUpdate()->first();
            if (!$row) {
                return;
            }

            $discountId = $row->discount_id;
            $userId = $order->user_id;

            $row->delete();

            if ($discountId && $userId) {
                $pivot = DB::table('discount_user')
                    ->where('discount_id', $discountId)
                    ->where('user_id', $userId)
                    ->lockForUpdate()
                    ->first();

                if ($pivot && $pivot->times_used > 0) {
                    DB::table('discount_user')
                        ->where('discount_id', $discountId)
                        ->where('user_id', $userId)
                        ->update(['times_used' => $pivot->times_used - 1, 'updated_at' => now()]);
                }
            }

            $order->discount = 0.0;
            $order->total_amount = round($order->subtotal + $order->shipping_total + $order->tax_total, 2);
            $order->save();
        });
    }

    protected function isApplicable(
        Discount $discount,
        ?User $user,
        float $subtotal,
        float $shippingTotal,
        string $channel,
        array $items,
        ?Order $contextOrder = null
    ): bool {
        $now = now();

        if ($discount->application_method !== Discount::APPLICATION_ORDER_TOTAL) {
            return false;
        }

        if (!$this->passesBasicLifecycleChecks($discount, $now)) {
            return false;
        }

        if (!$this->passesCustomerScope($discount, $user, $contextOrder)) {
            return false;
        }

        if ($discount->min_order_amount && $subtotal < (float) $discount->min_order_amount) {
            return false;
        }

        if ($discount->usage_limit && $discount->orderDiscounts()->count() >= $discount->usage_limit) {
            return false;
        }

        if ($user && $discount->usage_limit_per_user) {
            $timesUsed = (int) ($user->discounts()
                ->where('discount_id', $discount->id)
                ->first()?->pivot?->times_used ?? 0);

            if ($timesUsed >= $discount->usage_limit_per_user) {
                return false;
            }
        }

        if ($discount->type === Discount::TYPE_FREE_SHIPPING && $shippingTotal <= 0) {
            return false;
        }

        if ($this->discountHasItemScope($discount)) {
            return $this->filterEligibleItems($discount, $items)->isNotEmpty();
        }

        return true;
    }

    protected function calculateAmount(
        Discount $discount,
        array $items,
        float $subtotal,
        float $shippingTotal,
        float $taxTotal,
        string $channel = 'online'
    ): float {
        $eligibleItems = $this->filterEligibleItems($discount, $items);
        $base = $eligibleItems->isNotEmpty()
            ? (float) $eligibleItems->sum(fn (array $item) => $item['unit_price'] * $item['quantity'])
            : $subtotal;

        return match ($discount->type) {
            Discount::TYPE_PERCENTAGE => round($base * ((float) $discount->value / 100.0), 2),
            Discount::TYPE_FIXED_AMOUNT => min(round((float) $discount->value, 2), round($base, 2)),
            Discount::TYPE_FREE_SHIPPING => $shippingTotal > 0 ? round($shippingTotal, 2) : 0.0,
            default => 0.0,
        };
    }

    protected function mapOrderItems(Collection $orderItems): array
    {
        return $orderItems->map(function (OrderItem $item) {
            return [
                'variant_id' => (int) $item->variant_id,
                'quantity' => (float) $item->quantity,
                'unit_price' => (float) $item->price,
                'product_id' => optional($item->variant->product)->id ?? null,
                'category_ids' => optional($item->variant->product)?->categories?->pluck('id')->map(fn ($id) => (int) $id)->all() ?? [],
            ];
        })->all();
    }

    /**
     * Commit a discount using a snapshot (no recalculation).
     *
     * @param array{discount_id:int|null, amount:float, code:string|null, label:string|null} $discountSnapshot
     * @return array{applied:bool, amount:float, discount_id:int|null}
     */
    public function commitFromSnapshot(Order $order, array $discountSnapshot): array
    {
        $discountId = $discountSnapshot['discount_id'] ?? null;
        $amount = (float) ($discountSnapshot['amount'] ?? 0.0);

        if (!$discountId || $amount <= 0) {
            return ['applied' => false, 'amount' => 0.0, 'discount_id' => null];
        }

        return DB::transaction(function () use ($order, $discountId, $amount) {
            $existing = OrderDiscount::where('order_id', $order->id)->first();
            if ($existing) {
                return [
                    'applied' => true,
                    'amount' => (float) $existing->discount_amount,
                    'discount_id' => $existing->discount_id,
                ];
            }

            $row = OrderDiscount::create([
                'order_id' => $order->id,
                'discount_id' => $discountId,
                'discount_amount' => $amount,
            ]);

            $order->discount = round($amount, 2);
            $order->total_amount = max(round($order->subtotal + $order->shipping_total + $order->tax_total - $amount, 2), 0);
            $order->save();

            if ($order->user_id) {
                DB::table('discount_user')->updateOrInsert(
                    ['discount_id' => $discountId, 'user_id' => $order->user_id],
                    ['times_used' => DB::raw('COALESCE(times_used,0) + 1'), 'updated_at' => now(), 'created_at' => now()]
                );
            }

            return [
                'applied' => true,
                'amount' => (float) $row->discount_amount,
                'discount_id' => $row->discount_id,
            ];
        });
    }

    protected function lineItemCandidates(): Collection
    {
        if ($this->cachedLineItemCandidates !== null) {
            return $this->cachedLineItemCandidates;
        }

        return $this->cachedLineItemCandidates = Discount::query()
            ->with(['products:id', 'categories:id', 'variants:id', 'users:id'])
            ->automatic()
            ->lineItem()
            ->active()
            ->withinDateWindow()
            ->whereIn('type', [Discount::TYPE_PERCENTAGE, Discount::TYPE_FIXED_AMOUNT])
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get();
    }

    protected function passesBasicLifecycleChecks(Discount $discount, Carbon|CarbonImmutable $now): bool
    {
        if (!$discount->is_active) {
            return false;
        }

        if ($discount->starts_at && $now->lt($discount->starts_at)) {
            return false;
        }

        if ($discount->ends_at && $now->gt($discount->ends_at)) {
            return false;
        }

        return true;
    }

    protected function passesCustomerScope(Discount $discount, ?User $user, ?Order $contextOrder = null): bool
    {
        if ($discount->customer_scope === Discount::CUSTOMER_SCOPE_NEW && $user) {
            $priorOrdersQuery = $user->orders();

            if ($contextOrder) {
                $priorOrdersQuery->where('id', '!=', $contextOrder->id);
            }

            if ($priorOrdersQuery->exists()) {
                return false;
            }
        }

        if ($discount->customer_scope === Discount::CUSTOMER_SCOPE_SELECTED) {
            if (!$user) {
                return false;
            }

            return $this->scopeIds($discount, 'users')->contains((int) $user->id);
        }

        return true;
    }

    protected function isApplicableLineItemDiscount(
        Discount $discount,
        ProductVariant $variant,
        ?User $user,
        array $categoryIds,
        string $channel,
        \DateTimeInterface $at
    ): bool {
        $now = CarbonImmutable::instance($at);

        if ($discount->application_method !== Discount::APPLICATION_LINE_ITEM) {
            return false;
        }

        if ($discount->type === Discount::TYPE_FREE_SHIPPING) {
            return false;
        }

        if (!$this->passesBasicLifecycleChecks($discount, $now)) {
            return false;
        }

        if (!$this->passesCustomerScope($discount, $user)) {
            return false;
        }

        $variantIds = $this->scopeIds($discount, 'variants');
        if ($variantIds->isNotEmpty() && !$variantIds->contains((int) $variant->id)) {
            return false;
        }

        $productIds = $this->scopeIds($discount, 'products');
        if ($productIds->isNotEmpty() && !$productIds->contains((int) $variant->product_id)) {
            return false;
        }

        $discountCategoryIds = $this->scopeIds($discount, 'categories');
        if ($discountCategoryIds->isNotEmpty() && empty(array_intersect($discountCategoryIds->all(), $categoryIds))) {
            return false;
        }

        return true;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function filterEligibleItems(Discount $discount, array $items): Collection
    {
        $eligible = collect($items);

        $variantIds = $this->scopeIds($discount, 'variants');
        if ($variantIds->isNotEmpty()) {
            $eligible = $eligible->filter(fn (array $item) => $variantIds->contains((int) ($item['variant_id'] ?? 0)));
        }

        $productIds = $this->scopeIds($discount, 'products');
        if ($productIds->isNotEmpty()) {
            $eligible = $eligible->filter(fn (array $item) => $productIds->contains((int) ($item['product_id'] ?? 0)));
        }

        $categoryIds = $this->scopeIds($discount, 'categories');
        if ($categoryIds->isNotEmpty()) {
            $eligible = $eligible->filter(function (array $item) use ($categoryIds) {
                $itemCategoryIds = array_map('intval', $item['category_ids'] ?? []);

                return !empty(array_intersect($itemCategoryIds, $categoryIds->all()));
            });
        }

        return $eligible->values();
    }

    protected function discountHasItemScope(Discount $discount): bool
    {
        return $this->scopeIds($discount, 'variants')->isNotEmpty()
            || $this->scopeIds($discount, 'products')->isNotEmpty()
            || $this->scopeIds($discount, 'categories')->isNotEmpty();
    }

    protected function scopeIds(Discount $discount, string $relation): Collection
    {
        $loaded = $discount->relationLoaded($relation)
            ? $discount->getRelation($relation)
            : $discount->{$relation}()->get(['id']);

        return $loaded->pluck('id')->map(fn ($id) => (int) $id)->values();
    }

    /**
     * Lower compare result means a better candidate.
     */
    protected function compareLineItemCandidates(array $candidate, array $best): int
    {
        $scopeComparison = ($best['scope_rank'] ?? 0) <=> ($candidate['scope_rank'] ?? 0);
        if ($scopeComparison !== 0) {
            return $scopeComparison;
        }

        $priorityComparison = ($best['priority'] ?? 0) <=> ($candidate['priority'] ?? 0);
        if ($priorityComparison !== 0) {
            return $priorityComparison;
        }

        $priceComparison = ($candidate['current'] ?? 0) <=> ($best['current'] ?? 0);
        if ($priceComparison !== 0) {
            return $priceComparison;
        }

        return ($candidate['discount_id'] ?? 0) <=> ($best['discount_id'] ?? 0);
    }

    /**
     * @return array{
     *   discount_id:int,
     *   label:string,
     *   type:string,
     *   scope:string,
     *   scope_rank:int,
     *   amount:float,
     *   current:float,
     *   priority:int,
     *   percentage:int,
     *   value:float|null
     * }
     */
    protected function buildLineItemDiscountPayload(
        Discount $discount,
        ProductVariant $variant,
        float $regularPrice,
        array $categoryIds
    ): array {
        $current = match ($discount->type) {
            Discount::TYPE_PERCENTAGE => max(round($regularPrice - ($regularPrice * ((float) $discount->value / 100)), 2), 0.0),
            Discount::TYPE_FIXED_AMOUNT => max(round($regularPrice - (float) $discount->value, 2), 0.0),
            default => $regularPrice,
        };

        $amount = max(round($regularPrice - $current, 2), 0.0);
        $percentage = $regularPrice > 0
            ? (int) round(($amount / $regularPrice) * 100)
            : 0;

        return [
            'discount_id' => (int) $discount->id,
            'label' => $discount->name,
            'type' => $discount->type,
            'scope' => $this->resolveLineItemScope($discount, $variant, $categoryIds),
            'scope_rank' => $this->resolveLineItemScopeRank($discount, $variant, $categoryIds),
            'amount' => $amount,
            'current' => $current,
            'priority' => (int) $discount->priority,
            'percentage' => $percentage,
            'value' => $discount->value !== null ? (float) $discount->value : null,
        ];
    }

    protected function resolveLineItemScope(Discount $discount, ProductVariant $variant, array $categoryIds): string
    {
        return match ($this->resolveLineItemScopeRank($discount, $variant, $categoryIds)) {
            4 => 'variant',
            3 => 'product',
            2 => 'category',
            default => 'global',
        };
    }

    protected function resolveLineItemScopeRank(Discount $discount, ProductVariant $variant, array $categoryIds): int
    {
        if ($this->scopeIds($discount, 'variants')->contains((int) $variant->id)) {
            return 4;
        }

        if ($this->scopeIds($discount, 'products')->contains((int) $variant->product_id)) {
            return 3;
        }

        $discountCategoryIds = $this->scopeIds($discount, 'categories');
        if ($discountCategoryIds->isNotEmpty() && !empty(array_intersect($discountCategoryIds->all(), $categoryIds))) {
            return 2;
        }

        return 1;
    }

    /**
     * @return array{
     *   discount_id:null,
     *   label:null,
     *   type:null,
     *   scope:null,
     *   amount:float,
     *   current:float,
     *   priority:int,
     *   percentage:int,
     *   value:null
     * }
     */
    protected function emptyLineItemDiscount(float $regularPrice): array
    {
        return [
            'discount_id' => null,
            'label' => null,
            'type' => null,
            'scope' => null,
            'amount' => 0.0,
            'current' => round($regularPrice, 2),
            'priority' => 0,
            'percentage' => 0,
            'value' => null,
        ];
    }

    protected function normalizeCoupon(?string $couponCode): ?string
    {
        if ($couponCode === null) {
            return null;
        }

        $couponCode = strtoupper(trim($couponCode));

        return $couponCode !== '' ? $couponCode : null;
    }
}
