<?php

namespace App\Models;

use App\Support\PermissionNames;
use App\Support\RoleNames;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'email_verified_at',
        'password',
        'phone',
        'address',
        'country_id',
        'state_id',
        'lga_id',
        'gender',
        'passport_path',
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

    public function capabilityFlags(): array
    {
        return [
            'can_access_admin' => $this->can(PermissionNames::ACCESS_ADMIN),
            'can_access_sales' => $this->can(PermissionNames::ACCESS_SALES),
            'can_access_account' => $this->can(PermissionNames::ACCESS_ACCOUNT),
            'can_manage_catalog' => $this->can(PermissionNames::MANAGE_ADMIN_CATALOG),
            'can_manage_inventory' => $this->can(PermissionNames::MANAGE_ADMIN_INVENTORY),
            'can_manage_staff' => $this->can(PermissionNames::MANAGE_ADMIN_STAFF),
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
            'roles' => $this->roles->pluck('name')->values()->all(),
            'primary_role' => $this->primaryRole(),
            'capabilities' => $this->capabilityFlags(),
            'created_at' => optional($this->created_at)?->toIso8601String(),
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
        ];
    }
}




