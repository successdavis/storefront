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
            $table->foreignId('vendor_bill_id')->constrained();
            $table->foreignId('product_id')->nullable();   // null for service/charge
            $table->string('description');
            $table->decimal('quantity', 12, 2)->default(1);
            $table->decimal('unit_cost', 10, 2);
            $table->enum('type', ['product','service','freight','duty','misc'])->default('product');
            $table->timestamps();
        });

        /**
         * PAYMENTS to vendor
         */
        Schema::create('vendor_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_bill_id')->constrained()->cascadeOnDelete();
            $table->date('payment_date');
            $table->string('payment_method')->nullable();
            $table->decimal('amount', 15, 2);
            $table->timestamps();
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
    }
};
