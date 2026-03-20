<?php

namespace App\Providers;

use App\Models\ProductVariant;
use App\Observers\ProductVariantObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Services\SkuGenerator::class);
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ProductVariant::observe(ProductVariantObserver::class);
    }
}
