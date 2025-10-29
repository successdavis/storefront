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
        Schema::create('discounts', function (Blueprint $table) {
            $table->id();

            // Basic info
            $table->string('name');
            $table->string('code')->nullable()->unique(); // null = auto discount
            $table->enum('type', ['percentage', 'fixed_amount', 'free_shipping']);
            $table->decimal('value', 10, 2)->nullable();

            // Rules
            $table->decimal('min_order_amount', 10, 2)->nullable();
            $table->integer('usage_limit')->nullable();
            $table->integer('usage_limit_per_user')->nullable();

            // Time validity
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            // Customer scope
            $table->enum('customer_scope', ['all', 'new_customers', 'selected_customers'])->default('all');

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('discount_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discount_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
        });

        Schema::create('discount_variant', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discount_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->unique(['discount_id','product_variant_id']);
        });

        Schema::create('discount_category', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discount_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->unique(['discount_id','category_id']);
        });

        Schema::create('discount_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discount_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->integer('times_used')->default(0);
            $table->timestamps();
            $table->unique(['discount_id','user_id']);
        });

        Schema::create('order_discounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('discount_id')->constrained()->cascadeOnDelete();
            $table->decimal('discount_amount', 10, 2);
            $table->timestamps();

            // Amazon-like rule: only one discount per order
            $table->unique('order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_discounts');
        Schema::dropIfExists('discount_user');
        Schema::dropIfExists('discount_category');
        Schema::dropIfExists('discount_product');
        Schema::dropIfExists('discounts');
    }
};
