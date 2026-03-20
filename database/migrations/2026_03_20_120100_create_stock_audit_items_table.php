<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_audit_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')
                ->constrained('stock_audit_sessions')
                ->cascadeOnDelete();
            $table->foreignId('variant_id')
                ->constrained('product_variants')
                ->cascadeOnDelete();
            $table->integer('system_quantity');
            $table->integer('physical_quantity');
            $table->integer('variance');
            $table->timestamps();

            $table->unique(['session_id', 'variant_id']);
            $table->index('variant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_audit_items');
    }
};
