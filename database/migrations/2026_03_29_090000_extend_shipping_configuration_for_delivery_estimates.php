<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('shipping_methods', 'processing_days_min')) {
            Schema::table('shipping_methods', function (Blueprint $table) {
                $table->unsignedSmallInteger('processing_days_min')->nullable()->after('sort_order');
                $table->unsignedSmallInteger('processing_days_max')->nullable()->after('processing_days_min');
                $table->unsignedSmallInteger('transit_days_min')->nullable()->after('processing_days_max');
                $table->unsignedSmallInteger('transit_days_max')->nullable()->after('transit_days_min');
                $table->time('cutoff_time')->nullable()->after('transit_days_max');
                $table->boolean('business_days_only')->default(true)->after('cutoff_time');
                $table->boolean('supports_weekend_delivery')->default(false)->after('business_days_only');
            });
        }

        if (!Schema::hasColumn('shipping_rates', 'processing_days_min')) {
            Schema::table('shipping_rates', function (Blueprint $table) {
                $table->unsignedSmallInteger('processing_days_min')->nullable()->after('estimated_delivery_text');
                $table->unsignedSmallInteger('processing_days_max')->nullable()->after('processing_days_min');
                $table->unsignedSmallInteger('transit_days_min')->nullable()->after('processing_days_max');
                $table->unsignedSmallInteger('transit_days_max')->nullable()->after('transit_days_min');
                $table->time('cutoff_time')->nullable()->after('transit_days_max');
                $table->boolean('business_days_only')->nullable()->after('cutoff_time');
                $table->boolean('supports_weekend_delivery')->nullable()->after('business_days_only');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('shipping_rates', 'supports_weekend_delivery')) {
            Schema::table('shipping_rates', function (Blueprint $table) {
                $table->dropColumn([
                    'processing_days_min',
                    'processing_days_max',
                    'transit_days_min',
                    'transit_days_max',
                    'cutoff_time',
                    'business_days_only',
                    'supports_weekend_delivery',
                ]);
            });
        }

        if (Schema::hasColumn('shipping_methods', 'supports_weekend_delivery')) {
            Schema::table('shipping_methods', function (Blueprint $table) {
                $table->dropColumn([
                    'processing_days_min',
                    'processing_days_max',
                    'transit_days_min',
                    'transit_days_max',
                    'cutoff_time',
                    'business_days_only',
                    'supports_weekend_delivery',
                ]);
            });
        }
    }
};
