<?php

namespace App\Models;

use App\Support\PermissionNames;
use App\Support\RoleNames;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable, HasRoles;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_SUSPENDED = 'suspended';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'customer_slug',
        'email',
        'email_verified_at',
        'status',
        'password',
        'phone',
        'address',
        'country_id',
        'state_id',
        'lga_id',
        'gender',
        'passport_path',
        'last_login_at',
        'last_login_ip',
        'login_count',
        'is_vip',
        'is_risky',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $user) {
            if (blank($user->customer_slug)) {
                $slug = static::generateUniqueCustomerSlug($user->name, $user->email);

                if (filled($slug)) {
                    $user->customer_slug = $slug;
                }
            }
        });

        static::updating(function (self $user) {
            if (blank($user->customer_slug)) {
                $slug = static::generateUniqueCustomerSlug($user->name, $user->email, $user->id);

                if (filled($slug)) {
                    $user->customer_slug = $slug;
                }
            }
        });

        static::created(function (self $user) {
            if (!$user->hasAnyRole(RoleNames::all())) {
                $user->assignRole(RoleNames::CUSTOMER);
            }
        });
    }

    public function discounts()
    {
        return $this->belongsToMany(Discount::class, 'discount_user')
            ->withPivot(['times_used'])
            ->withTimestamps();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_login_at' => 'datetime',
            'is_vip' => 'boolean',
            'is_risky' => 'boolean',
        ];
    }


    /**
     * 🔗 Orders made by this user (customer side)
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function stockEntries(): HasMany
    {
        return $this->hasMany(StockEntry::class, 'employee_id');
    }

    public function stockAdjustments(): HasMany
    {
        return $this->hasMany(StockAdjustment::class, 'employee_id');
    }


    public function adminPaymentEntryRecords(): HasMany
    {
        return $this->hasMany(Payment::class, 'employee_id');
    }

    public function openingBalanceEntries(): HasMany
    {
        return $this->hasMany(OpeningBalance::class, 'employee_id');
    }

//    public function employee()
//    {
//        return $this->hasOne(Employee::class);
//    }

    public function warehouses()
    {
        return $this->belongsToMany(Warehouse::class, 'employee_warehouse', 'employee_id', 'warehouse_id');
    }

    /**
     * 🔗 Carts for this user (online shopping session)
     */
    public function carts(): HasMany
    {
        return $this->hasMany(Cart::class, 'user_id');
    }

    /**
     * 🔗 Sales (if user is customer at POS)
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class, 'customer_id');
    }

    public function savedItems(): HasMany
    {
        return $this->hasMany(CustomerSavedItem::class);
    }

    public function customerAddresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }

    public function customerNotes(): HasMany
    {
        return $this->hasMany(CustomerNote::class)->latest();
    }

    public function authoredCustomerNotes(): HasMany
    {
        return $this->hasMany(CustomerNote::class, 'author_id')->latest();
    }

    public function customerActivityLogs(): HasMany
    {
        return $this->hasMany(CustomerActivityLog::class)->latest();
    }

    public function authoredCustomerActivityLogs(): HasMany
    {
        return $this->hasMany(CustomerActivityLog::class, 'actor_id')->latest();
    }

    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    public function primaryRole(): string
    {
        $this->loadMissing('roles');

        if ($this->hasRole(RoleNames::DIRECTOR)) {
            return RoleNames::DIRECTOR;
        }

        if ($this->hasRole(RoleNames::SALES_REPRESENTATIVE)) {
            return RoleNames::SALES_REPRESENTATIVE;
        }

        return RoleNames::CUSTOMER;
    }

    public function isCustomer(): bool
    {
        return $this->hasRole(RoleNames::CUSTOMER);
    }

    public function isActive(): bool
    {
        return ($this->status ?? self::STATUS_ACTIVE) === self::STATUS_ACTIVE;
    }

    public function hasRealEmail(): bool
    {
        $email = strtolower((string) $this->email);

        return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false && !str_ends_with($email, '@example.invalid');
    }

    public function customerRouteKey(): string
    {
        return filled($this->customer_slug)
            ? (string) $this->customer_slug
            : (string) $this->getKey();
    }

    public function capabilityFlags(): array
    {
        return [
            'can_access_admin' => $this->can(PermissionNames::ACCESS_ADMIN),
            'can_access_sales' => $this->can(PermissionNames::ACCESS_SALES),
            'can_access_account' => $this->can(PermissionNames::ACCESS_ACCOUNT),
            'can_manage_catalog' => $this->can(PermissionNames::MANAGE_ADMIN_CATALOG),
            'can_manage_inventory' => $this->can(PermissionNames::MANAGE_ADMIN_INVENTORY),
            'can_manage_staff' => $this->can(PermissionNames::MANAGE_ADMIN_STAFF),
            'can_view_analytics' => $this->can(PermissionNames::VIEW_ADMIN_ANALYTICS),
            'can_manage_analytics' => $this->can(PermissionNames::MANAGE_ADMIN_ANALYTICS),
            'can_view_accounting' => $this->can(PermissionNames::VIEW_ADMIN_ACCOUNTING),
            'can_manage_accounting' => $this->can(PermissionNames::MANAGE_ADMIN_ACCOUNTING),
            'can_post_accounting_journals' => $this->can(PermissionNames::POST_ADMIN_ACCOUNTING_JOURNALS),
            'can_view_accounting_reports' => $this->can(PermissionNames::VIEW_ADMIN_ACCOUNTING_REPORTS),
            'can_manage_accounting_expenses' => $this->can(PermissionNames::MANAGE_ADMIN_ACCOUNTING_EXPENSES),
            'can_manage_orders' => $this->can(PermissionNames::MANAGE_ADMIN_ORDERS),
            'can_manage_payment_recovery' => $this->can(PermissionNames::MANAGE_ADMIN_PAYMENT_RECOVERY),
            'can_view_sales_orders' => $this->can(PermissionNames::VIEW_SALES_ORDERS),
            'can_use_pos' => $this->can(PermissionNames::USE_SALES_POS),
            'can_manage_customers' => $this->canAny([
                PermissionNames::VIEW_SALES_CUSTOMERS,
                PermissionNames::CREATE_SALES_CUSTOMERS,
            ]),
            'can_manage_saved_items' => $this->can(PermissionNames::MANAGE_ACCOUNT_SAVED_ITEMS),
            'can_manage_addresses' => $this->can(PermissionNames::MANAGE_ACCOUNT_ADDRESSES),
            'can_use_checkout' => $this->can(PermissionNames::USE_CHECKOUT),
        ];
    }

    public function toInertiaAuth(): array
    {
        $this->loadMissing('roles');

        return [
            'id' => (int) $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'email_verified_at' => optional($this->email_verified_at)?->toIso8601String(),
            'status' => $this->status ?? self::STATUS_ACTIVE,
            'is_vip' => (bool) ($this->is_vip ?? false),
            'is_risky' => (bool) ($this->is_risky ?? false),
            'last_login_at' => optional($this->last_login_at)?->toIso8601String(),
            'roles' => $this->roles->pluck('name')->values()->all(),
            'primary_role' => $this->primaryRole(),
            'capabilities' => $this->capabilityFlags(),
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
        ];
    }

    public function customerInvoices()
    {
        return $this->hasMany(CustomerInvoice::class, 'customer_id');
    }

    protected static function generateUniqueCustomerSlug(?string $name, ?string $email = null, ?int $ignoreId = null): ?string
    {
        if (!Schema::hasColumn('users', 'customer_slug')) {
            return null;
        }

        $base = Str::slug((string) $name);

        if ($base === '') {
            $base = Str::slug(Str::before((string) $email, '@'));
        }

        if ($base === '') {
            $base = 'customer';
        }

        $candidate = Str::limit($base, 150, '');
        $suffix = 2;

        while (static::customerSlugExists($candidate, $ignoreId)) {
            $candidate = Str::limit($base, 150 - strlen('-'.$suffix), '').'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    protected static function customerSlugExists(string $candidate, ?int $ignoreId = null): bool
    {
        return static::query()
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->where('customer_slug', $candidate)
            ->exists();
    }
}




