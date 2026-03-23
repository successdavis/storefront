<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('shipping_methods', 'description')) {
            Schema::table('shipping_methods', function (Blueprint $table) {
                $table->text('description')->nullable()->after('name');
            });
        }

        if (!Schema::hasColumn('shipping_methods', 'method_type')) {
            Schema::table('shipping_methods', function (Blueprint $table) {
                $table->string('method_type', 20)->default('delivery')->after('description')->index();
            });
        }

        if (!Schema::hasColumn('shipping_methods', 'sort_order')) {
            Schema::table('shipping_methods', function (Blueprint $table) {
                $table->unsignedInteger('sort_order')->default(0)->after('is_active')->index();
            });
        }

        if (!Schema::hasColumn('shipping_rates', 'state_id')) {
            Schema::table('shipping_rates', function (Blueprint $table) {
                $table->foreignId('state_id')->nullable()->after('shipping_zone_id')->constrained('states')->nullOnDelete();
            });
        }

        if (!Schema::hasColumn('shipping_rates', 'lga_id')) {
            Schema::table('shipping_rates', function (Blueprint $table) {
                $table->foreignId('lga_id')->nullable()->after('state_id')->constrained('lgas')->nullOnDelete();
            });
        }

        if (!Schema::hasColumn('shipping_rates', 'estimated_delivery_text')) {
            Schema::table('shipping_rates', function (Blueprint $table) {
                $table->string('estimated_delivery_text')->nullable()->after('free_shipping_threshold');
            });
        }

        if (!Schema::hasColumn('shipping_rates', 'sort_order')) {
            Schema::table('shipping_rates', function (Blueprint $table) {
                $table->unsignedInteger('sort_order')->default(0)->after('is_active')->index();
            });
        }

        Schema::table('shipping_rates', function (Blueprint $table) {
            $table->index(['shipping_method_id', 'state_id', 'lga_id', 'shipping_zone_id', 'is_active'], 'shipping_rates_scope_lookup_idx');
        });

        DB::table('shipping_methods')
            ->whereRaw('LOWER(name) like ?', ['%pickup%'])
            ->update([
                'method_type' => 'pickup',
                'sort_order' => DB::raw('CASE WHEN sort_order = 0 THEN id ELSE sort_order END'),
            ]);

        DB::table('shipping_methods')
            ->whereRaw('LOWER(name) not like ?', ['%pickup%'])
            ->update([
                'method_type' => 'delivery',
                'sort_order' => DB::raw('CASE WHEN sort_order = 0 THEN id ELSE sort_order END'),
            ]);

        DB::table('shipping_rates')
            ->where('sort_order', 0)
            ->update(['sort_order' => DB::raw('id')]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('shipping_rates', 'sort_order')) {
            Schema::table('shipping_rates', function (Blueprint $table) {
                $table->dropIndex('shipping_rates_scope_lookup_idx');
                $table->dropColumn('sort_order');
            });
        }

        if (Schema::hasColumn('shipping_rates', 'estimated_delivery_text')) {
            Schema::table('shipping_rates', function (Blueprint $table) {
                $table->dropColumn('estimated_delivery_text');
            });
        }

        if (Schema::hasColumn('shipping_rates', 'lga_id')) {
            Schema::table('shipping_rates', function (Blueprint $table) {
                $table->dropConstrainedForeignId('lga_id');
            });
        }

        if (Schema::hasColumn('shipping_rates', 'state_id')) {
            Schema::table('shipping_rates', function (Blueprint $table) {
                $table->dropConstrainedForeignId('state_id');
            });
        }

        if (Schema::hasColumn('shipping_methods', 'sort_order')) {
            Schema::table('shipping_methods', function (Blueprint $table) {
                $table->dropColumn('sort_order');
            });
        }

        if (Schema::hasColumn('shipping_methods', 'method_type')) {
            Schema::table('shipping_methods', function (Blueprint $table) {
                $table->dropColumn('method_type');
            });
        }

        if (Schema::hasColumn('shipping_methods', 'description')) {
            Schema::table('shipping_methods', function (Blueprint $table) {
                $table->dropColumn('description');
            });
        }
    }
};
