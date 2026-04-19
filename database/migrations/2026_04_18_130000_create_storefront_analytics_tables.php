<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('storefront_analytics_visitors')) {
            Schema::create('storefront_analytics_visitors', function (Blueprint $table) {
                $table->id();
                $table->string('visitor_key', 64)->unique();
                $table->foreignId('first_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('last_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('first_page_path')->nullable();
                $table->string('last_page_path')->nullable();
                $table->string('first_referrer_domain', 160)->nullable();
                $table->string('last_referrer_domain', 160)->nullable();
                $table->string('first_country_code', 8)->nullable();
                $table->string('first_country_name', 120)->nullable();
                $table->string('first_region_name', 120)->nullable();
                $table->string('last_country_code', 8)->nullable();
                $table->string('last_country_name', 120)->nullable();
                $table->string('last_region_name', 120)->nullable();
                $table->string('first_device_type', 20)->nullable();
                $table->string('last_device_type', 20)->nullable();
                $table->unsignedBigInteger('total_page_views')->default(0);
                $table->timestamp('first_seen_at')->nullable()->index();
                $table->timestamp('last_seen_at')->nullable()->index();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('storefront_analytics_page_views')) {
            Schema::create('storefront_analytics_page_views', function (Blueprint $table) {
                $table->id();
                $table->string('visitor_key', 64)->index();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->date('occurred_on')->index();
                $table->timestamp('occurred_at')->index();
                $table->string('page_path');
                $table->string('page_title')->nullable();
                $table->string('component', 160)->nullable();
                $table->string('country_code', 8)->nullable()->index();
                $table->string('country_name', 120)->nullable()->index();
                $table->string('region_name', 120)->nullable()->index();
                $table->string('device_type', 20)->nullable()->index();
                $table->string('referrer_domain', 160)->nullable()->index();
                $table->boolean('is_authenticated')->default(false)->index();
                $table->boolean('is_new_visitor')->default(false)->index();
                $table->timestamps();

                $table->index(['occurred_on', 'page_path'], 'storefront_analytics_views_date_path_idx');
                $table->index(['occurred_on', 'visitor_key'], 'storefront_analytics_views_date_visitor_idx');
            });
        }

        if (!Schema::hasTable('storefront_analytics_daily_visitors')) {
            Schema::create('storefront_analytics_daily_visitors', function (Blueprint $table) {
                $table->id();
                $table->date('date');
                $table->string('visitor_key', 64);
                $table->boolean('is_authenticated')->default(false);
                $table->boolean('is_new_visitor')->default(false);
                $table->timestamps();

                $table->unique(['date', 'visitor_key'], 'storefront_analytics_daily_visitors_unique');
                $table->index(['date', 'is_authenticated'], 'storefront_analytics_daily_visitors_auth_idx');
            });
        }

        if (!Schema::hasTable('storefront_analytics_daily_totals')) {
            Schema::create('storefront_analytics_daily_totals', function (Blueprint $table) {
                $table->id();
                $table->date('date')->unique();
                $table->unsignedBigInteger('page_views')->default(0);
                $table->unsignedBigInteger('unique_visitors')->default(0);
                $table->unsignedBigInteger('new_visitors')->default(0);
                $table->unsignedBigInteger('returning_visitors')->default(0);
                $table->unsignedBigInteger('guest_page_views')->default(0);
                $table->unsignedBigInteger('authenticated_page_views')->default(0);
                $table->unsignedBigInteger('guest_visitors')->default(0);
                $table->unsignedBigInteger('authenticated_visitors')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('storefront_analytics_daily_pages')) {
            Schema::create('storefront_analytics_daily_pages', function (Blueprint $table) {
                $table->id();
                $table->date('date');
                $table->string('page_path');
                $table->string('page_title')->nullable();
                $table->string('component', 160)->nullable();
                $table->unsignedBigInteger('page_views')->default(0);
                $table->unsignedBigInteger('unique_visitors')->default(0);
                $table->timestamps();

                $table->unique(['date', 'page_path'], 'storefront_analytics_daily_pages_unique');
                $table->index(['page_views', 'date'], 'storefront_analytics_daily_pages_views_idx');
            });
        }

        if (!Schema::hasTable('storefront_analytics_daily_geos')) {
            Schema::create('storefront_analytics_daily_geos', function (Blueprint $table) {
                $table->id();
                $table->date('date');
                $table->string('country_code', 8)->nullable();
                $table->string('country_name', 120)->nullable();
                $table->string('region_name', 120)->nullable();
                $table->unsignedBigInteger('page_views')->default(0);
                $table->unsignedBigInteger('unique_visitors')->default(0);
                $table->timestamps();

                $table->unique(['date', 'country_code', 'country_name', 'region_name'], 'storefront_analytics_daily_geos_unique');
                $table->index(['date', 'country_name'], 'storefront_analytics_daily_geos_country_idx');
            });
        }

        if (!Schema::hasTable('storefront_analytics_daily_devices')) {
            Schema::create('storefront_analytics_daily_devices', function (Blueprint $table) {
                $table->id();
                $table->date('date');
                $table->string('device_type', 20);
                $table->unsignedBigInteger('page_views')->default(0);
                $table->unsignedBigInteger('unique_visitors')->default(0);
                $table->timestamps();

                $table->unique(['date', 'device_type'], 'storefront_analytics_daily_devices_unique');
            });
        }

        if (!Schema::hasTable('storefront_analytics_daily_referrers')) {
            Schema::create('storefront_analytics_daily_referrers', function (Blueprint $table) {
                $table->id();
                $table->date('date');
                $table->string('referrer_domain', 160);
                $table->unsignedBigInteger('page_views')->default(0);
                $table->unsignedBigInteger('unique_visitors')->default(0);
                $table->timestamps();

                $table->unique(['date', 'referrer_domain'], 'storefront_analytics_daily_referrers_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('storefront_analytics_daily_referrers');
        Schema::dropIfExists('storefront_analytics_daily_devices');
        Schema::dropIfExists('storefront_analytics_daily_geos');
        Schema::dropIfExists('storefront_analytics_daily_pages');
        Schema::dropIfExists('storefront_analytics_daily_totals');
        Schema::dropIfExists('storefront_analytics_daily_visitors');
        Schema::dropIfExists('storefront_analytics_page_views');
        Schema::dropIfExists('storefront_analytics_visitors');
    }
};
