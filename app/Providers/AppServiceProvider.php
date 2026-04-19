<?php

namespace App\Providers;

use App\Models\CustomerAddress;
use App\Models\CustomerSavedItem;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\User;
use App\Observers\ProductVariantObserver;
use App\Policies\CustomerPolicy;
use App\Policies\CustomerAddressPolicy;
use App\Policies\CustomerSavedItemPolicy;
use App\Policies\OrderPolicy;
use App\Services\Analytics\StorefrontAnalyticsSettings;
use App\Support\RoleNames;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Services\SkuGenerator::class);
        $this->app->singleton(StorefrontAnalyticsSettings::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ProductVariant::observe(ProductVariantObserver::class);

        RateLimiter::for('storefront-analytics', function (Request $request) {
            $settings = app(StorefrontAnalyticsSettings::class);
            $key = hash('sha256', implode('|', [
                (string) $request->ip(),
                (string) $request->userAgent(),
                (string) $request->header('X-Forwarded-For', ''),
            ]));

            return [
                Limit::perMinute($settings->throttlePerMinute())->by($key),
            ];
        });

        Route::bind('customer', function (string $value) {
            $query = User::query()->role(RoleNames::CUSTOMER);

            if (Schema::hasColumn('users', 'customer_slug')) {
                $query->where(function ($builder) use ($value) {
                    $builder->where('customer_slug', $value);

                    if (is_numeric($value)) {
                        $builder->orWhere('id', (int) $value);
                    }
                });
            } else {
                $query->whereKey($value);
            }

            return $query->firstOrFail();
        });

        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(CustomerAddress::class, CustomerAddressPolicy::class);
        Gate::policy(CustomerSavedItem::class, CustomerSavedItemPolicy::class);
        Gate::policy(User::class, CustomerPolicy::class);
    }
}
