<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string|null $code
 * @property string $type              // percentage | fixed_amount | free_shipping
 * @property string|null $value        // decimal(10,2) as string by default; casted to decimal:2
 * @property string|null $min_order_amount
 * @property int|null $usage_limit
 * @property int|null $usage_limit_per_user
 * @property \Illuminate\Support\Carbon|null $starts_at
 * @property \Illuminate\Support\Carbon|null $ends_at
 * @property string $customer_scope     // all | new_customers | selected_customers
 * @property bool $is_active
 */
class Discount extends Model
{
    use HasFactory;

    public const TYPE_PERCENTAGE = 'percentage';
    public const TYPE_FIXED_AMOUNT = 'fixed_amount';
    public const TYPE_FREE_SHIPPING = 'free_shipping';

    public const APPLICATION_ORDER_TOTAL = 'order_total';
    public const APPLICATION_LINE_ITEM = 'line_item';

    public const CUSTOMER_SCOPE_ALL = 'all';
    public const CUSTOMER_SCOPE_NEW = 'new_customers';
    public const CUSTOMER_SCOPE_SELECTED = 'selected_customers';

    protected $table = 'discounts';

    protected $fillable = [
        'name', 'description', 'code', 'type', 'value', 'application_method',
        'min_order_amount', 'usage_limit', 'usage_limit_per_user',
        'starts_at', 'ends_at', 'customer_scope', 'priority', 'is_active',
    ];

    protected $casts = [
        'value'             => 'decimal:2',
        'min_order_amount'  => 'decimal:2',
        'starts_at'         => 'datetime',
        'ends_at'           => 'datetime',
        'priority'          => 'integer',
        'is_active'         => 'boolean',
    ];

    /* -----------------------------------------
     | Relationships
     |------------------------------------------*/

    public function variants()
    {
        // Pivot: discount_variant(discount_id, product_variant_id)
        return $this->belongsToMany(ProductVariant::class, 'discount_variant', 'discount_id', 'product_variant_id')
            ->withTimestamps();
    }
    public function products()
    {
        // Pivot: discount_variant(discount_id, product_variant_id)
        return $this->belongsToMany(Product::class, 'discount_product', 'discount_id', 'product_id')
            ->withTimestamps();
    }

    public function categories()
    {
        // Pivot: discount_category(discount_id, category_id)
        return $this->belongsToMany(Category::class, 'discount_category')->withTimestamps();
    }

    public function users()
    {
        // Pivot: discount_user(discount_id, user_id, times_used)
        return $this->belongsToMany(User::class, 'discount_user')
            ->withPivot(['times_used'])
            ->withTimestamps();
    }

    public function orderDiscounts()
    {
        return $this->hasMany(OrderDiscount::class);
    }

    /* -----------------------------------------
     | Scopes
     |------------------------------------------*/

    /**
     * Only active rows.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Only those within the current date window (or with open-ended windows).
     */
    public function scopeWithinDateWindow(Builder $query, ?\DateTimeInterface $at = null): Builder
    {
        $now = $at ? CarbonImmutable::instance($at) : CarbonImmutable::now();
        return $query
            ->where(function ($q) use ($now) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            });
    }

    public function scopeAutomatic(Builder $query): Builder
    {
        return $query->whereNull('code');
    }

    public function scopeCoupons(Builder $query): Builder
    {
        return $query->whereNotNull('code');
    }

    public function scopeOrderTotal(Builder $query): Builder
    {
        return $query->where('application_method', self::APPLICATION_ORDER_TOTAL);
    }

    public function scopeLineItem(Builder $query): Builder
    {
        return $query->where('application_method', self::APPLICATION_LINE_ITEM);
    }

    public function scopeForCode(Builder $query, string $code): Builder
    {
        return $query->where('code', $code);
    }

    /**
     * If $code is provided → search by code; else → automatic.
     */
    public function scopeAutomaticOrCode(Builder $query, ?string $code): Builder
    {
        return $code
            ? $this->scopeForCode($query, $code)
            : $this->scopeAutomatic($query);
    }

    /* -----------------------------------------
     | Helpers
     |------------------------------------------*/

    /**
     * True if active flag + within time window right now.
     */
    public function isCurrentlyActive(?\DateTimeInterface $at = null): bool
    {
        $now = $at ? CarbonImmutable::instance($at) : CarbonImmutable::now();
        if (!$this->is_active) return false;

        if ($this->starts_at && $this->starts_at->gt($now)) return false;
        if ($this->ends_at && $this->ends_at->lt($now)) return false;

        return true;
    }

    /**
     * Remaining global uses (null = unlimited).
     */
    public function remainingGlobalUses(): ?int
    {
        if (is_null($this->usage_limit)) {
            return null; // unlimited
        }
        $used = $this->orderDiscounts()->count();
        return max($this->usage_limit - $used, 0);
    }

    /**
     * Remaining uses for a specific user (null = unlimited or no user).
     */
    public function remainingUserUses(?User $user): ?int
    {
        if (!$user || is_null($this->usage_limit_per_user)) {
            return null; // not applicable or unlimited
        }

        $pivot = $this->users()->where('user_id', $user->id)->first()?->pivot;
        $used = $pivot ? (int) $pivot->times_used : 0;

        return max($this->usage_limit_per_user - $used, 0);
    }

    public function isCoupon(): bool
    {
        return filled($this->code);
    }

    public function isLineItemDiscount(): bool
    {
        return $this->application_method === self::APPLICATION_LINE_ITEM;
    }

    public function hasScopeConstraints(): bool
    {
        return $this->products()->exists()
            || $this->variants()->exists()
            || $this->categories()->exists();
    }
}
