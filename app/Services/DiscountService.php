<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\Discount;
use App\Models\OrderDiscount;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class DiscountService
{
    public function __construct(
        protected float $moneyTolerance = 0.01
    ) {}

    /**
     * PREVIEW: Compute discount amount without mutating DB.
     *
     * @param User|null   $user
     * @param array       $items   Each: ['variant_id'=>int,'quantity'=>float,'unit_price'=>float,'product_id'=>int|null,'category_ids'=>array<int>]
     * @param float       $subtotal
     * @param float       $shippingTotal
     * @param float       $taxTotal
     * @param string      $channel  'online' | 'pos'
     * @param string|null $couponCode
     * @param Order|null  $contextOrder Optional — if provided, ignore this order when checking user's prior orders (important for "new_customers" scope).
     * @return array{discount_id:int|null, code:string|null, label:string|null, amount:float}
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
        $now = Carbon::now();

        // 1) Candidate discounts
        $candidates = Discount::query()
            ->where('is_active', true)
            ->when($couponCode, fn($q) => $q->where('code', $couponCode),
                fn($q) => $q->whereNull('code'))
            ->where(fn($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now))
            ->get();

        if ($candidates->isEmpty() && $couponCode) {
            throw ValidationException::withMessages(['coupon' => 'Invalid or expired coupon.']);
        }

        // 2) Evaluate best discount (Amazon-like: only one)
        $best = ['discount_id' => null, 'code' => $couponCode, 'label' => null, 'amount' => 0.0];

        foreach ($candidates as $discount) {
            if (!$this->isApplicable($discount, $user, $subtotal, $shippingTotal, $channel, $items, $contextOrder)) {
                continue;
            }

            $amount = $this->calculateAmount($discount, $items, $subtotal, $shippingTotal, $taxTotal, $channel);
            if ($amount > $best['amount']) {
                $best = [
                    'discount_id' => $discount->id,
                    'code'        => $discount->code,
                    'label'       => $discount->name,
                    'amount'      => round($amount, 2),
                ];
            }
        }

        return $best;
    }

    /**
     * COMMIT: Persist discount usage for an order.
     * Safe for retries, idempotent.
     *
     * If the discount cannot be committed (ineligible / limits reached), this method will
     * ensure the order has no dangling preview discount (it clears the discount field and updates totals).
     *
     * @return array{applied:bool, amount:float, discount_id:int|null}
     */
    public function commitForOrder(Order $order, ?string $couponCode = null): array
    {
        $user = $order->user;
        $items = $this->mapOrderItems($order->items);
        $subtotal = (float) $order->subtotal;
        $shippingTotal = (float) $order->shipping_total;
        $taxTotal = (float) $order->tax_total;
        $channel = $order->channel ?? 'online';

        // Recompute best discount but IGNORE the current order from "prior orders" checks.
        $quote = $this->previewQuote($user, $items, $subtotal, $shippingTotal, $taxTotal, $channel, $couponCode, $order);

        // If no discount applicable, ensure order does not carry a preview discount.
        if (!$quote['discount_id'] || $quote['amount'] <= 0) {
            return $this->ensureOrderHasNoDiscount($order);
        }

        return DB::transaction(function () use ($order, $user, $quote, $subtotal, $shippingTotal, $taxTotal) {

            // Idempotency: if already committed, return existing record
            $existing = OrderDiscount::where('order_id', $order->id)->first();
            if ($existing) {
                // Make sure order fields reflect the committed discount (self-healing)
                $this->applyOrderDiscountValues($order, (float)$existing->discount_amount, $subtotal, $shippingTotal, $taxTotal);
                return ['applied' => true, 'amount' => (float) $existing->discount_amount, 'discount_id' => $existing->discount_id];
            }

            // Persist OrderDiscount row
            $row = OrderDiscount::create([
                'order_id'        => $order->id,
                'discount_id'     => $quote['discount_id'],
                'discount_amount' => $quote['amount'],
            ]);

            // Update order totals
            $this->applyOrderDiscountValues($order, (float) $row->discount_amount, $subtotal, $shippingTotal, $taxTotal);

            // Track per-user usage (idempotent via updateOrInsert)
            if ($user) {
                DB::table('discount_user')->updateOrInsert(
                    ['discount_id' => $quote['discount_id'], 'user_id' => $user->id],
                    [
                        'times_used' => DB::raw('COALESCE(times_used,0) + 1'),
                        'updated_at' => now(),
                        'created_at' => now()
                    ]
                );
            }

            return ['applied' => true, 'amount' => (float) $row->discount_amount, 'discount_id' => $row->discount_id];
        }, 3);
    }

    public function getMoneyTolerance(): float
    {
        return $this->moneyTolerance;
    }

    /**
     * Ensure order does not carry a preview discount — clear any stale discount and associated OrderDiscount row if present.
     */
    protected function ensureOrderHasNoDiscount(Order $order): array
    {
        return DB::transaction(function () use ($order) {
            $row = OrderDiscount::where('order_id', $order->id)->first();

            // If there was a persisted row (odd), remove it and decrement usage.
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

            // Clear any preview discount on the order
            $order->discount = 0.0;
            $order->total_amount = round($order->subtotal + $order->shipping_total + $order->tax_total, 2);
            $order->save();

            return ['applied' => false, 'amount' => 0.0, 'discount_id' => null];
        });
    }

    /**
     * Applies discount values to order model and saves.
     */
    protected function applyOrderDiscountValues(Order $order, float $discountAmount, float $subtotal, float $shippingTotal, float $taxTotal): void
    {
        $order->discount = round($discountAmount, 2);
        $order->total_amount = max(
            round($subtotal + $shippingTotal + $taxTotal - $discountAmount, 2),
            0
        );
        $order->save();
    }

    /**
     * Roll back a discount if an order is cancelled/refunded.
     */
    public function revokeForOrder(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $row = OrderDiscount::where('order_id', $order->id)->lockForUpdate()->first();
            if (!$row) return;

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

    /**
     * Rules: min order, usage limits, customer scope, free shipping only if shipping > 0, etc.
     *
     * Now accepts $contextOrder so the "new_customers" check can ignore the current order.
     */
    protected function isApplicable(Discount $discount, ?User $user, float $subtotal, float $shippingTotal, string $channel, array $items, ?Order $contextOrder = null): bool
    {
        $now = now();

        // Active check
        if (! $discount->is_active) {
            Log::debug("Discount {$discount->id} skipped: not active");
            return false;
        }

        // Time validity
        if ($discount->starts_at && $now->lt($discount->starts_at)) {
            Log::debug("Discount {$discount->id} skipped: not started yet");
            return false;
        }
        if ($discount->ends_at && $now->gt($discount->ends_at)) {
            Log::debug("Discount {$discount->id} skipped: already expired");
            return false;
        }

        // Min order check
        if ($discount->min_order_amount && $subtotal < $discount->min_order_amount) {
            Log::debug("Discount {$discount->id} skipped: subtotal {$subtotal} < min_order_amount {$discount->min_order_amount}");
            return false;
        }

        // Global usage limit
        if ($discount->usage_limit && $discount->orderDiscounts()->count() >= $discount->usage_limit) {
            Log::debug("Discount {$discount->id} skipped: usage_limit {$discount->usage_limit} reached");
            return false;
        }

        // Per-user usage
        if ($user && $discount->usage_limit_per_user) {
            $timesUsed = (int) ($user->discounts()
                ->where('discount_id', $discount->id)
                ->first()?->pivot?->times_used ?? 0);

            if ($timesUsed >= $discount->usage_limit_per_user) {
                Log::debug("Discount {$discount->id} skipped: user {$user->id} already used {$timesUsed} times (limit {$discount->usage_limit_per_user})");
                return false;
            }
        }

        // Scope: new customers only
        if ($discount->customer_scope === 'new_customers' && $user) {
            // Exclude the $contextOrder id when checking for prior orders.
            $priorOrdersQuery = $user->orders();
            if ($contextOrder) {
                $priorOrdersQuery = $priorOrdersQuery->where('id', '!=', $contextOrder->id);
            }

            if ($priorOrdersQuery->exists()) {
                Log::debug("Discount {$discount->id} skipped: user {$user->id} not a new customer (prior orders exist)");
                return false;
            }
        }

        // Scope: selected customers only
        if ($discount->customer_scope === 'selected_customers') {
            if (! $user) {
                Log::debug("Discount {$discount->id} skipped: requires a logged-in user for selected_customers scope");
                return false;
            }

            $isSelected = $discount->users()->where('user_id', $user->id)->exists();
            if (! $isSelected) {
                Log::debug("Discount {$discount->id} skipped: user {$user->id} not in selected customers list");
                return false;
            }
        }

        // Free shipping only if shipping > 0
        if ($discount->type === 'free_shipping' && $shippingTotal <= 0) {
            Log::debug("Discount {$discount->id} skipped: free shipping but shippingTotal={$shippingTotal}");
            return false;
        }

        // Product restrictions
        if ($discount->products()->count() > 0) {
            $eligibleProductIds = collect($items)
                ->pluck('product_id')
                ->intersect($discount->products->pluck('id'));
            if ($eligibleProductIds->isEmpty()) {
                Log::debug("Discount {$discount->id} skipped: no eligible products in cart");
                return false;
            }
        }

        // Variant restrictions
        if ($discount->variants()->count() > 0) {
            $eligibleVariantIds = collect($items)
                ->pluck('variant_id')
                ->intersect($discount->variants->pluck('id'));
            if ($eligibleVariantIds->isEmpty()) {
                Log::debug("Discount {$discount->id} skipped: no eligible variants in cart");
                return false;
            }
        }

        // Category restrictions
        if ($discount->categories()->count() > 0) {
            $allCategoryIds = collect($items)->flatMap(fn($i) => $i['category_ids'])->unique();
            $eligibleCategoryIds = $allCategoryIds->intersect($discount->categories->pluck('id'));
            if ($eligibleCategoryIds->isEmpty()) {
                Log::debug("Discount {$discount->id} skipped: no eligible categories in cart");
                return false;
            }
        }

        Log::debug("Discount {$discount->id} PASSED all checks");
        return true;
    }

    /**
     * Compute discount amount.
     */
    protected function calculateAmount(
        Discount $discount,
        array $items,
        float $subtotal,
        float $shippingTotal,
        float $taxTotal,
        string $channel = 'online'
    ): float {
        $base = $subtotal;

        // Adjust base if discount is restricted to variants or categories
        if ($discount->variants()->exists()) {
            $eligibleVariantIds = $discount->variants->pluck('id')->all();
            $base = collect($items)->whereIn('variant_id', $eligibleVariantIds)
                ->sum(fn($i) => $i['unit_price'] * $i['quantity']);
        }

        if ($discount->categories()->exists()) {
            $eligibleCategoryIds = $discount->categories->pluck('id')->all();
            $base = collect($items)->filter(function ($i) use ($eligibleCategoryIds) {
                return !empty(array_intersect($i['category_ids'], $eligibleCategoryIds));
            })->sum(fn($i) => $i['unit_price'] * $i['quantity']);
        }

        return match ($discount->type) {
            'percentage'   => round($base * ((float) $discount->value / 100.0), 2),
            'fixed_amount' => min(round((float) $discount->value, 2), round($base, 2)),
            'free_shipping'=> $shippingTotal > 0 ? round($shippingTotal, 2) : 0.0,
            default        => 0.0,
        };
    }

    /**
     * Map Order->items into array for calculations.
     */
    protected function mapOrderItems(Collection $orderItems): array
    {
        return $orderItems->map(function (OrderItem $i) {
            return [
                'variant_id'   => (int) $i->variant_id,
                'quantity'     => (float) $i->quantity,
                'unit_price'   => (float) $i->price,
                'product_id'   => optional($i->variant->product)->id ?? null,
                'category_ids' => optional($i->variant->product)?->categories?->pluck('id')->all() ?? [],
            ];
        })->all();
    }

    /**
     * Commit a discount using a snapshot (no recalculation).
     * Assumes the caller already validated the snapshot is still valid.
     *
     * @param Order $order
     * @param array $discountSnapshot ['discount_id' => int|null, 'amount' => float, 'code' => string|null, 'label' => string|null]
     * @return array{applied:bool, amount:float, discount_id:int|null}
     */
    public function commitFromSnapshot(Order $order, array $discountSnapshot): array
    {
        $discountId = $discountSnapshot['discount_id'] ?? null;
        $amount = (float)($discountSnapshot['amount'] ?? 0.0);

        if (!$discountId || $amount <= 0) {
            return ['applied' => false, 'amount' => 0.0, 'discount_id' => null];
        }

        return DB::transaction(function () use ($order, $discountId, $amount) {
            // Idempotency: if already committed, return existing
            $existing = OrderDiscount::where('order_id', $order->id)->first();
            if ($existing) {
                return ['applied' => true, 'amount' => (float)$existing->discount_amount, 'discount_id' => $existing->discount_id];
            }

            $row = OrderDiscount::create([
                'order_id' => $order->id,
                'discount_id' => $discountId,
                'discount_amount' => $amount,
            ]);

            // Update order totals (order subtotal/shipping/tax should be the snapshot values)
            $order->discount = round($amount, 2);
            $order->total_amount = max(round($order->subtotal + $order->shipping_total + $order->tax_total - $amount, 2), 0);
            $order->save();

            // Track per-user usage
            if ($order->user_id) {
                DB::table('discount_user')->updateOrInsert(
                    ['discount_id' => $discountId, 'user_id' => $order->user_id],
                    ['times_used' => DB::raw('COALESCE(times_used,0) + 1'), 'updated_at' => now(), 'created_at' => now()]
                );
            }

            return ['applied' => true, 'amount' => (float)$row->discount_amount, 'discount_id' => $row->discount_id];
        });
    }

}
