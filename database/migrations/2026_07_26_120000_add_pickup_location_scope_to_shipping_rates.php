<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('shipping_rates', 'pickup_location_id')) {
            Schema::table('shipping_rates', function (Blueprint $table) {
                $table->foreignId('pickup_location_id')
                    ->nullable()
                    ->after('lga_id')
                    ->constrained('pickup_locations')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('shipping_rates', 'pickup_location_id')) {
            Schema::table('shipping_rates', function (Blueprint $table) {
                $table->dropConstrainedForeignId('pickup_location_id');
            });
        }
    }
};
