<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('shipping_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name');                 // e.g. "Lagos", "South West", "National"
            $table->timestamps();
        });

// shipping_zone_states: map states to zones
        Schema::create('shipping_zone_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipping_zone_id')->constrained()->cascadeOnDelete();
            $table->string('state_code');           // e.g. "LA" for Lagos, or full name
            $table->unique(['shipping_zone_id','state_code']);
            $table->timestamps();
        });

// shipping_methods: courier or delivery mode
        Schema::create('shipping_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name');                 // e.g. "Standard Courier", "Express", "Pickup"
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

// shipping_rates: pricing rules per method + zone, with optional tiers and windows
        Schema::create('shipping_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipping_method_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shipping_zone_id')->nullable()->constrained()->nullOnDelete();

            // criteria
            $table->decimal('min_weight', 10, 3)->nullable();   // kg
            $table->decimal('max_weight', 10, 3)->nullable();   // kg
            $table->decimal('min_subtotal', 10, 2)->nullable(); // cart subtotal filter
            $table->decimal('max_subtotal', 10, 2)->nullable(); // optional

            // pricing model
            $table->enum('rate_type', ['flat','per_kg','hybrid']);  // choose a strategy
            $table->decimal('base_rate', 10, 2)->default(0);        // flat part
            $table->decimal('per_kg', 10, 2)->default(0);           // variable part

            // extras
            $table->decimal('surcharge', 10, 2)->default(0);        // remote area or handling
            $table->decimal('free_shipping_threshold', 10, 2)->nullable();
            $table->char('currency', 3)->default('NGN');

            // validity
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // lookups
            $table->index(['shipping_method_id','shipping_zone_id']);
            $table->index(['min_weight','max_weight']);
            $table->index(['min_subtotal','max_subtotal']);
        });

        Schema::create('pickup_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipping_method_id')->constrained()->cascadeOnDelete(); // your "Pickup" method
            $table->foreignId('shipping_zone_id')->nullable()->constrained()->nullOnDelete(); // restrict by zone/state if you want

            $table->string('name');                                // e.g. "Ikeja Store"
            $table->string('address_line1');
            $table->string('address_line2')->nullable();
            $table->string('state_code')->nullable();              // e.g. "LA" or "Lagos"
            $table->string('postal_code')->nullable();
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('state_id')->nullable()->constrained('states')->nullOnDelete();
            $table->foreignId('lga_id')->nullable()->constrained('lgas')->nullOnDelete();
            $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();


            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->string('phone')->nullable();
            $table->string('email')->nullable();

            $table->string('timezone')->default('Africa/Lagos');
            $table->json('opening_hours')->nullable();             // per-day windows, see note below

            $table->unsignedInteger('slot_duration_minutes')->default(0);  // 0 means no slotting
            $table->unsignedInteger('capacity_per_slot')->default(0);       // 0 means unlimited
            $table->unsignedInteger('lead_time_hours')->default(0);         // prep time before pickup

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['shipping_method_id','is_active']);
            $table->index(['shipping_zone_id']);
            $table->index(['state_code']);
        });

        Schema::create('order_shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shipping_method_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['delivery','pickup']);
            $table->decimal('weight', 10, 3)->nullable();
            $table->decimal('cost', 10, 2)->default(0);
            $table->char('currency', 3)->default('NGN');
            $table->string('status')->default('pending'); // pending, ready, shipped, completed, cancelled
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['order_id','type']);
        });

        // Only for pickup shipments
        Schema::create('order_pickups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_shipment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pickup_location_id')->constrained()->cascadeOnDelete();
            $table->timestamp('window_start')->nullable();
            $table->timestamp('window_end')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('reference')->nullable();
            $table->unique('reference');
            $table->timestamps();
        });

        Schema::create('order_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['billing','shipping']);
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('line1');
            $table->string('line2')->nullable();
            $table->string('state_code')->nullable();
            $table->string('postal_code')->nullable();
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('state_id')->nullable()->constrained('states')->nullOnDelete();
            $table->foreignId('lga_id')->nullable()->constrained('lgas')->nullOnDelete();
            $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->timestamps();
            $table->unique(['order_id','type']);
        });
    }



    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('shipping_method_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('shipping_weight', 10, 3)->nullable();     // kg snapshot
            $table->decimal('shipping_cost', 10, 2)->default(0);
        });
    }
};
