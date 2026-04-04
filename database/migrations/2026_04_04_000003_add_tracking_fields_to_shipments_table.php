<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            if (!Schema::hasColumn('shipments', 'courier_name')) {
                $table->string('courier_name')->nullable()->after('status');
            }

            if (!Schema::hasColumn('shipments', 'tracking_number')) {
                $table->string('tracking_number')->nullable()->after('courier_name');
            }

            if (!Schema::hasColumn('shipments', 'tracking_url')) {
                $table->string('tracking_url')->nullable()->after('tracking_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            foreach (['tracking_url', 'tracking_number', 'courier_name'] as $column) {
                if (Schema::hasColumn('shipments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
