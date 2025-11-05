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
        Schema::create('checkout_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('token', 128)->unique();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->json('items'); // snapshot of items (variant_id, qty, unit_price, product_id, category_ids)
            $table->decimal('subtotal', 14, 2);
            $table->decimal('shipping_total', 14, 2);
            $table->decimal('discount_amount', 14, 2)->default(0.0);
            $table->unsignedBigInteger('discount_id')->nullable(); // optional
            $table->json('discount_snapshot')->nullable(); // whole discount metadata/label/code if you want
            $table->decimal('total', 14, 2);
            $table->string('channel', 20)->default('online');
            $table->boolean('used')->default(false);
            $table->timestampTz('expires_at')->nullable();
            $table->timestampsTz();

            $table->unique(['user_id', 'used']);
        });


    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('checkout_sessions');
    }
};
