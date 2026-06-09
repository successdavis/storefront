<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->string('fulfillment_type')->default('stocked')->index()->after('reorder_point');
            $table->boolean('is_dropshippable')->default(false)->index()->after('fulfillment_type');
            $table->foreignId('default_supplier_id')->nullable()->after('is_dropshippable')->constrained('vendors')->nullOnDelete();
            $table->decimal('supplier_cost', 15, 2)->nullable()->after('default_supplier_id');
            $table->unsignedInteger('supplier_lead_time_days')->nullable()->after('supplier_cost');
            $table->boolean('show_as_available_when_dropshipping')->default(true)->after('supplier_lead_time_days');
            $table->text('dropshipping_note')->nullable()->after('show_as_available_when_dropshipping');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->string('fulfillment_type')->default('stocked')->index()->after('price');
            $table->foreignId('supplier_id')->nullable()->after('fulfillment_type')->constrained('vendors')->nullOnDelete();
            $table->decimal('supplier_cost', 15, 2)->nullable()->after('supplier_id');
            $table->string('dropship_status')->nullable()->index()->after('supplier_cost');
            $table->timestamp('supplier_ordered_at')->nullable()->after('dropship_status');
            $table->timestamp('supplier_confirmed_at')->nullable()->after('supplier_ordered_at');
            $table->timestamp('supplier_expected_delivery_at')->nullable()->after('supplier_confirmed_at');
            $table->timestamp('supplier_received_at')->nullable()->after('supplier_expected_delivery_at');
            $table->string('supplier_reference')->nullable()->after('supplier_received_at');
            $table->text('dropship_admin_note')->nullable()->after('supplier_reference');
            $table->json('dropship_meta')->nullable()->after('dropship_admin_note');
        });

        Schema::create('dropship_fulfillments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->decimal('supplier_cost', 15, 2)->nullable();
            $table->string('status')->default('pending_supplier_order')->index();
            $table->string('supplier_reference')->nullable();
            $table->timestamp('ordered_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('expected_delivery_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('shipped_to_customer_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('admin_note')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique('order_item_id');
            $table->index(['status', 'expected_delivery_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dropship_fulfillments');

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_id');
            $table->dropColumn([
                'fulfillment_type',
                'supplier_cost',
                'dropship_status',
                'supplier_ordered_at',
                'supplier_confirmed_at',
                'supplier_expected_delivery_at',
                'supplier_received_at',
                'supplier_reference',
                'dropship_admin_note',
                'dropship_meta',
            ]);
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_supplier_id');
            $table->dropColumn([
                'fulfillment_type',
                'is_dropshippable',
                'supplier_cost',
                'supplier_lead_time_days',
                'show_as_available_when_dropshipping',
                'dropshipping_note',
            ]);
        });
    }
};
