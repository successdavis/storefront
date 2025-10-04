<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        /**
         * VENDORS (suppliers)
         */
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        /**
         * PURCHASE ORDERS
         *  - which vendor we buy from
         *  - which warehouse will receive the goods
         */
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->string('po_number')->unique();
            $table->date('order_date');
            $table->date('expected_date')->nullable();
            $table->enum('status', [
                'draft',            // created but not sent
                'sent',             // sent to vendor
                'partially_received',
                'received',
                'closed',
                'cancelled'
            ])->default('draft');
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->text('note')->nullable();
            $table->timestamps();
        });

        /**
         * PURCHASE ORDER ITEMS
         *  - each line is tied to a product variant (your SKU)
         *  - quantity_received lets you track partial deliveries
         */
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity_ordered');
            $table->integer('quantity_received')->default(0);
            $table->decimal('unit_cost', 10, 2);
            $table->decimal('line_total', 12, 2);
            $table->timestamps();

            $table->unique(['purchase_order_id', 'product_variant_id'],
                'po_variant_unique');
        });

        /**
         * ITEM RECEIPTS
         *  - when a shipment arrives (partial or full)
         *  - references the warehouse actually receiving the goods
         */
        Schema::create('item_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->string('receipt_number')->unique();
            $table->date('received_date');
            $table->enum('status', ['pending','completed'])->default('pending');
            $table->timestamps();
        });

        Schema::create('item_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity_received');
            $table->decimal('unit_cost', 10, 2);
            $table->decimal('line_total', 12, 2);
            $table->timestamps();
        });

        /**
         * BILLS (vendor invoices)
         *  - link back to purchase order
         */
        Schema::create('vendor_bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('item_receipt_id')->nullable()->constrained()->nullOnDelete();
            $table->string('bill_number')->unique();
            $table->date('bill_date');
            $table->date('due_date')->nullable();
            $table->enum('status', ['unpaid','partially_paid','paid','void'])
                ->default('unpaid');
            $table->decimal('total_amount', 15, 2);
            $table->timestamps();
        });

        Schema::create('vendor_bill_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_bill_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained();
            $table->foreignId('product_variant_id')->nullable()->constrained();
            $table->foreignId('purchase_order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->decimal('quantity', 18, 4)->default(1);
            $table->decimal('unit_cost', 18, 4);
            $table->decimal('discount_amount', 18, 4)->default(0);
            $table->enum('type', ['product','service','freight','duty','misc'])->default('product');
            $table->timestamps();
            $table->index(['vendor_bill_id','product_id']);
        });

        Schema::create('purchase_price_variances', function (Blueprint $table) {
            $table->id();

            // Links back to financial side
            $table->foreignId('vendor_bill_item_id')
                ->constrained()
                ->cascadeOnDelete();

            // Links back to operational side (optional if bill doesn't match receipts exactly)
            $table->foreignId('item_receipt_item_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('product_variant_id')->constrained();

            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_cost_received', 18, 4);
            $table->decimal('unit_cost_billed', 18, 4);
            $table->decimal('variance_amount', 18, 4); // (billed - received) * qty

            $table->timestamps();

            $table->index(['product_variant_id']);
            $table->index(['vendor_bill_item_id']);
        });

        Schema::create('inventory_cost_adjustments', function (Blueprint $table) {
            $table->id();

            // Link to specific product variant
            $table->foreignId('product_variant_id')
                ->constrained()
                ->cascadeOnDelete();

            // Optional: link to bill and PO for traceability
            $table->foreignId('vendor_bill_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('purchase_order_item_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            // Adjustment details
            $table->decimal('quantity', 15, 4)->default(0); // qty affected
            $table->decimal('old_unit_cost', 15, 4);        // cost at receiving
            $table->decimal('new_unit_cost', 15, 4);        // cost from bill
            $table->decimal('difference_per_unit', 15, 4);  // new - old
            $table->decimal('total_adjustment', 18, 4);     // difference * qty

            // Accounting side (clearing account reference)
            $table->string('clearing_account')->nullable(); // e.g., "GRNI Clearing"

            $table->text('notes')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['product_variant_id']);
            $table->index(['vendor_bill_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_payments');
        Schema::dropIfExists('vendor_bill_items');
        Schema::dropIfExists('vendor_bills');
        Schema::dropIfExists('item_receipt_items');
        Schema::dropIfExists('item_receipts');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('vendors');
        Schema::dropIfExists('inventory_cost_adjustments');

    }
};
