<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('shipments', 'type')) {
            Schema::table('shipments', function (Blueprint $table) {
                $table->string('type', 20)->default('delivery')->after('shipping_method_id');
            });
        }

        if (!Schema::hasColumn('shipments', 'shipping_zone_id')) {
            Schema::table('shipments', function (Blueprint $table) {
                $table->foreignId('shipping_zone_id')->nullable()->after('shipping_method_id')->constrained()->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('shipments', 'shipping_zone_id')) {
            Schema::table('shipments', function (Blueprint $table) {
                $table->dropConstrainedForeignId('shipping_zone_id');
            });
        }

        if (Schema::hasColumn('shipments', 'type')) {
            Schema::table('shipments', function (Blueprint $table) {
                $table->dropColumn('type');
            });
        }
    }
};
