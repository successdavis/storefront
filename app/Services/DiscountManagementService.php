<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Discount;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class DiscountManagementService
{
    public function listAutomaticDiscounts(array $filters = []): LengthAwarePaginator
    {
        return $this->baseListQuery($filters, false)
            ->automatic()
            ->paginate(12)
            ->withQueryString();
    }

    public function listCoupons(array $filters = []): LengthAwarePaginator
    {
        return $this->baseListQuery($filters, true)
            ->coupons()
            ->paginate(12)
            ->withQueryString();
    }

    public function createAutomaticDiscount(array $data): Discount
    {
        return $this->persistDiscount(new Discount(), $data, false);
    }

    public function updateAutomaticDiscount(Discount $discount, array $data): Discount
    {
        return $this->persistDiscount($discount, $data, false);
    }

    public function createCoupon(array $data): Discount
    {
        return $this->persistDiscount(new Discount(), $data, true);
    }

    public function updateCoupon(Discount $discount, array $data): Discount
    {
        return $this->persistDiscount($discount, $data, true);
    }

    public function formOptions(): array
    {
        return [
            'categories' => Category::query()
                ->select(['id', 'name'])
                ->orderBy('name')
                ->get()
                ->map(fn (Category $category) => [
                    'id' => (int) $category->id,
                    'label' => $category->name,
                ])
                ->values()
                ->all(),
            'products' => Product::query()
                ->select(['id', 'name', 'slug'])
                ->orderBy('name')
                ->limit(250)
                ->get()
                ->map(fn (Product $product) => [
                    'id' => (int) $product->id,
                    'label' => $product->name,
                    'meta' => $product->slug,
                ])
                ->values()
                ->all(),
            'customers' => User::query()
                ->select(['id', 'name', 'email'])
                ->orderBy('name')
                ->limit(250)
                ->get()
                ->map(fn (User $user) => [
                    'id' => (int) $user->id,
                    'label' => $user->name,
                    'meta' => $user->email,
                ])
                ->values()
                ->all(),
        ];
    }

    public function toListPayload(Discount $discount): array
    {
        $scope = $this->resolveScopeLabel($discount);

        return [
            'id' => (int) $discount->id,
            'name' => $discount->name,
            'description' => $discount->description,
            'code' => $discount->code,
            'type' => $discount->type,
            'application_method' => $discount->application_method,
            'is_active' => (bool) $discount->is_active,
            'priority' => (int) $discount->priority,
            'customer_scope' => $discount->customer_scope,
            'starts_at' => optional($discount->starts_at)?->toIso8601String(),
            'ends_at' => optional($discount->ends_at)?->toIso8601String(),
            'status' => $this->resolveStatusLabel($discount),
            'scope' => $scope,
            'category_count' => (int) ($discount->categories_count ?? 0),
            'product_count' => (int) ($discount->products_count ?? 0),
            'variant_count' => (int) ($discount->variants_count ?? 0),
            'customer_count' => (int) ($discount->users_count ?? 0),
            'uses_count' => (int) ($discount->order_discounts_count ?? 0),
            'value' => $discount->value !== null ? (float) $discount->value : null,
            'min_order_amount' => $discount->min_order_amount !== null ? (float) $discount->min_order_amount : null,
            'usage_limit' => $discount->usage_limit,
            'usage_limit_per_user' => $discount->usage_limit_per_user,
        ];
    }

    public function toFormPayload(Discount $discount): array
    {
        $discount->loadMissing(['categories:id', 'products:id', 'users:id']);

        return [
            'id' => (int) $discount->id,
            'name' => $discount->name,
            'description' => $discount->description,
            'code' => $discount->code,
            'type' => $discount->type,
            'application_method' => $discount->application_method,
            'value' => $discount->value !== null ? (float) $discount->value : null,
            'min_order_amount' => $discount->min_order_amount !== null ? (float) $discount->min_order_amount : null,
            'usage_limit' => $discount->usage_limit,
            'usage_limit_per_user' => $discount->usage_limit_per_user,
            'starts_at' => optional($discount->starts_at)?->format('Y-m-d\TH:i'),
            'ends_at' => optional($discount->ends_at)?->format('Y-m-d\TH:i'),
            'customer_scope' => $discount->customer_scope,
            'priority' => (int) $discount->priority,
            'is_active' => (bool) $discount->is_active,
            'category_ids' => $discount->categories->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            'product_ids' => $discount->products->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            'selected_customer_ids' => $discount->users->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
        ];
    }

    protected function persistDiscount(Discount $discount, array $data, bool $coupon): Discount
    {
        return DB::transaction(function () use ($discount, $data, $coupon) {
            $normalized = $this->normalizePayload($data, $coupon);

            $discount->fill($normalized);
            $discount->save();

            $discount->categories()->sync($normalized['category_ids'] ?? []);
            $discount->products()->sync($normalized['product_ids'] ?? []);

            $existingUsage = $discount->users()
                ->pluck('discount_user.times_used', 'users.id')
                ->map(fn ($count) => (int) $count)
                ->all();

            $selectedUsers = collect($normalized['selected_customer_ids'] ?? [])
                ->mapWithKeys(fn (int $userId) => [
                    $userId => ['times_used' => $existingUsage[$userId] ?? 0],
                ])
                ->all();

            if ($discount->customer_scope === Discount::CUSTOMER_SCOPE_SELECTED) {
                $discount->users()->sync($selectedUsers);
            } else {
                $discount->users()->detach();
            }

            return $discount->fresh(['categories:id', 'products:id', 'users:id']);
        });
    }

    protected function normalizePayload(array $data, bool $coupon): array
    {
        $applicationMethod = $coupon
            ? Discount::APPLICATION_ORDER_TOTAL
            : ($data['application_method'] ?? Discount::APPLICATION_LINE_ITEM);

        $type = $data['type'];
        if ($type === Discount::TYPE_FREE_SHIPPING) {
            $applicationMethod = Discount::APPLICATION_ORDER_TOTAL;
        }

        $payload = [
            'name' => trim((string) $data['name']),
            'description' => $this->nullableString($data['description'] ?? null),
            'code' => $coupon ? strtoupper(trim((string) $data['code'])) : null,
            'type' => $type,
            'value' => $type === Discount::TYPE_FREE_SHIPPING ? null : (float) $data['value'],
            'application_method' => $applicationMethod,
            'min_order_amount' => $applicationMethod === Discount::APPLICATION_ORDER_TOTAL
                ? $this->nullableFloat($data['min_order_amount'] ?? null)
                : null,
            'usage_limit' => $applicationMethod === Discount::APPLICATION_ORDER_TOTAL
                ? $this->nullableInt($data['usage_limit'] ?? null)
                : null,
            'usage_limit_per_user' => $applicationMethod === Discount::APPLICATION_ORDER_TOTAL
                ? $this->nullableInt($data['usage_limit_per_user'] ?? null)
                : null,
            'starts_at' => $this->nullableCarbon($data['starts_at'] ?? null),
            'ends_at' => $this->nullableCarbon($data['ends_at'] ?? null),
            'customer_scope' => $data['customer_scope'] ?? Discount::CUSTOMER_SCOPE_ALL,
            'priority' => $applicationMethod === Discount::APPLICATION_LINE_ITEM ? (int) ($data['priority'] ?? 0) : 0,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'category_ids' => Arr::wrap($data['category_ids'] ?? []),
            'product_ids' => Arr::wrap($data['product_ids'] ?? []),
            'selected_customer_ids' => ($data['customer_scope'] ?? Discount::CUSTOMER_SCOPE_ALL) === Discount::CUSTOMER_SCOPE_SELECTED
                ? Arr::wrap($data['selected_customer_ids'] ?? [])
                : [],
        ];

        return $payload;
    }

    protected function baseListQuery(array $filters, bool $coupons): Builder
    {
        $now = now();
        $search = trim((string) ($filters['search'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));
        $scope = trim((string) ($filters['scope'] ?? ''));

        return Discount::query()
            ->withCount(['categories', 'products', 'variants', 'users', 'orderDiscounts'])
            ->when($search !== '', function (Builder $query) use ($search, $coupons) {
                $query->where(function (Builder $nested) use ($search, $coupons) {
                    $nested->where('name', 'like', "%{$search}%");

                    if ($coupons) {
                        $nested->orWhere('code', 'like', "%{$search}%");
                    }
                });
            })
            ->when($status !== '', function (Builder $query) use ($status, $now) {
                match ($status) {
                    'active' => $query
                        ->where('is_active', true)
                        ->where(fn (Builder $nested) => $nested->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
                        ->where(fn (Builder $nested) => $nested->whereNull('ends_at')->orWhere('ends_at', '>=', $now)),
                    'inactive' => $query->where('is_active', false),
                    'scheduled' => $query->where('is_active', true)->where('starts_at', '>', $now),
                    'expired' => $query->whereNotNull('ends_at')->where('ends_at', '<', $now),
                    default => null,
                };
            })
            ->when($scope !== '', function (Builder $query) use ($scope) {
                match ($scope) {
                    'global' => $query
                        ->whereDoesntHave('categories')
                        ->whereDoesntHave('products')
                        ->whereDoesntHave('variants'),
                    'category' => $query->whereHas('categories'),
                    'product' => $query->whereHas('products'),
                    'variant' => $query->whereHas('variants'),
                    default => null,
                };
            })
            ->latest('id');
    }

    protected function resolveStatusLabel(Discount $discount): string
    {
        $now = now();

        if (!$discount->is_active) {
            return 'Inactive';
        }

        if ($discount->starts_at && $discount->starts_at->isFuture()) {
            return 'Scheduled';
        }

        if ($discount->ends_at && $discount->ends_at->isPast()) {
            return 'Expired';
        }

        return 'Active';
    }

    protected function resolveScopeLabel(Discount $discount): string
    {
        if (($discount->products_count ?? 0) > 0) {
            return 'Product';
        }

        if (($discount->categories_count ?? 0) > 0) {
            return 'Category';
        }

        if (($discount->variants_count ?? 0) > 0) {
            return 'Variant';
        }

        return 'Global';
    }

    protected function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    protected function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    protected function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    protected function nullableCarbon(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse((string) $value);
    }
}
