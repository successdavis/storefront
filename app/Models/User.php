<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
        'password',
        'phone',
        'address',
        'country_id',
        'state_id',
        'lga_id',
        'gender',
        'passport_path'
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
    public function orders(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Order::class, 'customer_id');
    }

    public function stockEntries()
    {
        return $this->hasMany(StockEntry::class, 'employee_id');
    }

    public function stockAdjustments()
    {
        return $this->hasMany(StockAdjustment::class, 'employee_id');
    }

    public function admingPaymentEntryRecords()
    {
        return $this->hasMany(Payment::class, 'employee_id');
    }

    public function openingBalanceEntries()
    {
        return $this->hasMany(OpeningBalance::class, 'employee_id');
    }

//    public function employee()
//    {
//        return $this->hasOne(Employee::class);
//    }

    /**
     * 🔗 Carts for this user (online shopping session)
     */
    public function carts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Cart::class, 'customer_id');
    }

    /**
     * 🔗 Sales (if user is customer at POS)
     */
    public function sales(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Sale::class, 'customer_id');
    }

}
