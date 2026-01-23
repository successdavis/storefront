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
        Schema::create('inventory_alerts', function (Blueprint $table) {
            $table->id();

            $table->enum('type', [
                'low_stock',
                'out_of_stock',
                'dead_stock',
                'negative_stock',
                'overstock',
                'slow_moving',
                'fast_selling',
                'near_expiry',
                'discrepancy',
                'supplier_delay'
            ]);

            $table->enum('severity', ['low','medium','high','critical'])->index();

            $table->foreignId('variant_id')->nullable()->constrained('product_variants');
            $table->foreignId('warehouse_id')->nullable()->constrained();

            $table->string('message');
            $table->json('meta')->nullable(); // thresholds, counts, predictions

            $table->enum('status', ['open','acknowledged','resolved'])->default('open')->index();

            $table->timestamp('first_detected_at')->useCurrent();
            $table->timestamp('last_seen_at')->useCurrent();
            $table->timestamp('resolved_at')->nullable();

            $table->foreignId('resolved_by')->nullable()->constrained('users');

            $table->timestamps();

            $table->unique(
                ['type','variant_id','warehouse_id','status'],
                'uq_active_alert'
            );
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_alerts');
    }
};
