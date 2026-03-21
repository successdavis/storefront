<?php

namespace App\Providers;

use App\Models\CustomerAddress;
use App\Models\CustomerSavedItem;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Observers\ProductVariantObserver;
use App\Policies\CustomerAddressPolicy;
use App\Policies\CustomerSavedItemPolicy;
use App\Policies\OrderPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Services\SkuGenerator::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ProductVariant::observe(ProductVariantObserver::class);

        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(CustomerAddress::class, CustomerAddressPolicy::class);
        Gate::policy(CustomerSavedItem::class, CustomerSavedItemPolicy::class);
    }
}
