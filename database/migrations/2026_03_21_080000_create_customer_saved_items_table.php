<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_saved_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('list_type', 30);
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('price_snapshot', 10, 2)->nullable();
            $table->char('currency', 3)->default('NGN');
            $table->string('product_name_snapshot')->nullable();
            $table->string('variant_label_snapshot')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'list_type', 'variant_id'], 'uq_customer_saved_items_variant');
            $table->index(['user_id', 'list_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_saved_items');
    }
};
