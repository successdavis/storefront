<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lgas', function (Blueprint $table) {
            if (!Schema::hasColumn('lgas', 'latitude')) {
                $table->decimal('latitude', 10, 7)->nullable()->after('name');
            }

            if (!Schema::hasColumn('lgas', 'longitude')) {
                $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            }
        });

        Schema::table('customer_addresses', function (Blueprint $table) {
            if (Schema::hasColumn('customer_addresses', 'city_id')) {
                $table->dropConstrainedForeignId('city_id');
            }
        });

        Schema::table('pickup_locations', function (Blueprint $table) {
            if (Schema::hasColumn('pickup_locations', 'city_id')) {
                $table->dropConstrainedForeignId('city_id');
            }
        });

        Schema::dropIfExists('cities');
    }

    public function down(): void
    {
        if (!Schema::hasTable('cities')) {
            Schema::create('cities', function (Blueprint $table) {
                $table->id();
                $table->foreignId('state_id')->constrained('states')->cascadeOnDelete();
                $table->foreignId('lga_id')->nullable()->constrained('lgas')->nullOnDelete();
                $table->string('name');
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->boolean('is_capital')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['lga_id', 'name']);
                $table->unique(['state_id', 'name']);
                $table->index(['state_id', 'lga_id', 'is_active']);
            });
        }

        Schema::table('customer_addresses', function (Blueprint $table) {
            if (!Schema::hasColumn('customer_addresses', 'city_id')) {
                $table->foreignId('city_id')->nullable()->after('lga_id')->constrained('cities')->nullOnDelete();
            }
        });

        Schema::table('pickup_locations', function (Blueprint $table) {
            if (!Schema::hasColumn('pickup_locations', 'city_id')) {
                $table->foreignId('city_id')->nullable()->after('lga_id')->constrained('cities')->nullOnDelete();
            }
        });

        Schema::table('lgas', function (Blueprint $table) {
            if (Schema::hasColumn('lgas', 'longitude')) {
                $table->dropColumn('longitude');
            }

            if (Schema::hasColumn('lgas', 'latitude')) {
                $table->dropColumn('latitude');
            }
        });
    }
};
