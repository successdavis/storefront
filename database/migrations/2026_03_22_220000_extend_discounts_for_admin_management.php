<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discounts', function (Blueprint $table) {
            if (!Schema::hasColumn('discounts', 'description')) {
                $table->text('description')->nullable()->after('name');
            }

            if (!Schema::hasColumn('discounts', 'application_method')) {
                $table->enum('application_method', ['order_total', 'line_item'])
                    ->default('order_total')
                    ->after('value');
            }

            if (!Schema::hasColumn('discounts', 'priority')) {
                $table->unsignedInteger('priority')->default(0)->after('customer_scope');
            }
        });

        Schema::table('discounts', function (Blueprint $table) {
            $table->index(['application_method', 'is_active'], 'discounts_application_method_is_active_idx');
            $table->index(['starts_at', 'ends_at'], 'discounts_starts_at_ends_at_idx');
            $table->index(['code', 'is_active'], 'discounts_code_is_active_idx');
        });
    }

    public function down(): void
    {
        Schema::table('discounts', function (Blueprint $table) {
            $table->dropIndex('discounts_application_method_is_active_idx');
            $table->dropIndex('discounts_starts_at_ends_at_idx');
            $table->dropIndex('discounts_code_is_active_idx');
        });

        Schema::table('discounts', function (Blueprint $table) {
            if (Schema::hasColumn('discounts', 'priority')) {
                $table->dropColumn('priority');
            }

            if (Schema::hasColumn('discounts', 'application_method')) {
                $table->dropColumn('application_method');
            }

            if (Schema::hasColumn('discounts', 'description')) {
                $table->dropColumn('description');
            }
        });
    }
};
