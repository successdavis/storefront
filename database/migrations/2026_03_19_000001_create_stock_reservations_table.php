<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checkout_session_id')->constrained('checkout_sessions')->cascadeOnDelete();
            $table->foreignId('variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->enum('status', ['active', 'consumed', 'released'])->default('active');
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('consumed_at')->nullable();
            $table->timestampTz('released_at')->nullable();
            $table->string('release_reason')->nullable();
            $table->timestampsTz();

            $table->unique(['checkout_session_id', 'variant_id'], 'uq_stock_reservation_session_variant');
            $table->index(['variant_id', 'status', 'expires_at'], 'idx_stock_reservation_variant_status_exp');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_reservations');
    }
};

