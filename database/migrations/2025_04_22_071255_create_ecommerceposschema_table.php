<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->char('iso2', 2)->nullable();   // NG
            $table->char('iso3', 3)->nullable();   // NGA
            $table->char('currency', 3)->nullable(); // NGN
            $table->string('phone_code', 8)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['name']);
            $table->unique(['iso2']);
            $table->unique(['iso3']);
        });

        Schema::create('states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained()->cascadeOnDelete();
            $table->string('name');                 // e.g. Lagos
            $table->string('code', 10)->nullable(); // optional short code
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['country_id','name']);
            $table->index(['country_id','is_active']);
        });

        Schema::create('lgas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('state_id')->constrained('states')->cascadeOnDelete();
            $table->string('name');                 // e.g. Ikeja
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['state_id','name']);
            $table->index(['state_id','is_active']);
        });

        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('state_id')->constrained('states')->cascadeOnDelete();
            $table->foreignId('lga_id')->nullable()->constrained('lgas')->nullOnDelete();
            $table->string('name');                 // e.g. Ikeja, Yaba, Lekki
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_capital')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // City names are unique within an LGA when provided, otherwise within a State
            $table->unique(['lga_id','name']);
            $table->unique(['state_id','name']);
            // One of the above will apply depending on nullability. If your DB dislikes both, drop the second unique and keep only ['state_id','name'].
            $table->index(['state_id','lga_id','is_active']);
        });

        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('name');                                // Warehouse name
            $table->string('code')->unique();                       // Short unique code (e.g. "WH-NG-01")
            $table->string('address')->nullable();                  // Full address
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->default('Nigeria');          // Default country
            $table->string('contact_person')->nullable();           // Manager or key contact
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->boolean('active')->default(true)->index();      // Easy enable/disable
            $table->timestamps();
            $table->softDeletes();                                  // For audit/logical deletion

            $table->index(['name', 'city']);                        // Common search fields
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('banner')->nullable();
            $table->string('icon')->nullable();
            $table->string('cover_image')->nullable();
            $table->boolean('featured')->default(false);
            $table->integer('order')->default(0);
            $table->string('meta_title')->nullable();
            $table->string('meta_description')->nullable();
            $table->string('slug')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('logo')->nullable();
            $table->string('slug')->nullable();
            $table->string('meta_title')->nullable();
            $table->boolean('top_brand')->default(false);
            $table->string('meta_description')->nullable();
            $table->text('description')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('meta_title')->nullable();
            $table->string('meta_description')->nullable();
            $table->string('youtube_video_url')->nullable();
            $table->boolean('cash_on_delivery')->nullable();
            $table->boolean('featured')->nullable();
            $table->decimal('weight', 10, 3)->nullable();
            $table->enum('weight_unit', ['g','kg','lb','oz'])->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->decimal('length', 10, 2)->nullable();  // cm
            $table->decimal('width', 10, 2)->nullable();   // cm
            $table->decimal('height', 10, 2)->nullable();  // cm
            $table->softDeletes();
            $table->timestamps();


            // helpful indexes
            $table->index(['brand_id']);
            $table->index(['is_active']);
            $table->unique('slug');
        });

        Schema::create('category_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            $table->unique(['category_id', 'product_id']); // prevents duplicates
        });

        Schema::create('variant_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();

        });

        Schema::create('variant_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variant_type_id')->constrained()->cascadeOnDelete();
            $table->string('value');
            $table->timestamps();

        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            $table->string('sku');
            $table->string('barcode')->nullable()->unique();

            // Inventory tracking
            $table->integer('quantity')->default(0); // quantity_on_hand physical stock
            $table->integer('reserved')->default(0);
            $table->integer('available')->virtualAs('GREATEST(quantity - reserved, 0)');

            // Cost tracking
            $table->decimal('total_cost_on_hand', 15, 4)->default(0); // value of stock
            $table->decimal('average_cost', 15, 4)->default(0);       // WAC
            $table->decimal('last_purchase_price', 15, 4)->nullable(); // vendor's last bill

            // Selling prices
            $table->decimal('regular_price', 10, 2);
            $table->decimal('sale_price', 10, 2)->nullable();
            $table->timestamp('sale_starts_at')->nullable();
            $table->timestamp('sale_ends_at')->nullable();

            // Product metadata
            $table->decimal('weight', 10, 3)->nullable();
            $table->decimal('length', 10, 2)->nullable();
            $table->decimal('width', 10, 2)->nullable();
            $table->decimal('height', 10, 2)->nullable();
            $table->boolean('track_inventory')->default(true);
            $table->integer('reorder_point')->default(0);

            $table->softDeletes();
            $table->timestamps();

            $table->index(['product_id']);
            $table->index(['regular_price']);
            $table->unique(['sku', 'deleted_at'], 'product_variants_sku_deleted_at_unique');
        });

        Schema::create('product_variant_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_value_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['product_variant_id','variant_value_id'], 'uq_variant_value_combo');

        });

        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('path');          // storage path or URL
            $table->string('alt')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['product_id','sort_order']);
        });

        Schema::create('variant_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('alt')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['product_variant_id','sort_order']);
        });

        Schema::create('product_faqs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            // Optional. Attach to a specific variant when the question only applies to one SKU.
            $table->foreignId('product_variant_id')->nullable()
                ->constrained()->cascadeOnDelete();

            $table->string('question');
            $table->text('answer');
            $table->boolean('is_active')->default(true);

            // Order in the list
            $table->unsignedInteger('position')->default(0);

            // Tiny analytics. Helps you see which answers help.
            $table->unsignedInteger('helpful_yes')->default(0);
            $table->unsignedInteger('helpful_no')->default(0);

            // For in-page anchors like /product/nice-shoe#faq-returns
            $table->string('slug')->nullable();

            // Localisation if you need it later
            $table->char('locale', 5)->nullable(); // e.g. en, en-NG

            $table->timestamps();

            $table->index(['product_id', 'is_active', 'position']);
            $table->unique(['product_id', 'slug']); // only if you plan to use anchors
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable();
            $table->text('address')->nullable();
        });

        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['active', 'abandoned', 'converted'])->default('active');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->constrained('product_variants');
            $table->integer('quantity')->default(1);
            $table->timestamps();

        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('subtotal', 10, 2)->nullable();
            $table->decimal('shipping_total', 10, 2)->default(0);
            $table->decimal('tax_total', 10, 2)->default(0);
            $table->decimal('discount', 10, 2)->default(0);
            $table->char('currency', 3)->default('NGN');
            $table->enum('channel', ['online', 'pos'])->default('online');
            $table->string('order_number')->unique();
//            $table->enum('status', ['pending', 'paid', 'shipped', 'completed', 'cancelled'])->default('pending');
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->constrained('product_variants');
            $table->integer('quantity');
            $table->decimal('price', 10, 2);
            $table->timestamps();

        });

        Schema::create('pos_terminals', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('location')->nullable();
            $table->timestamps();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable()->unique();
            $table->enum('role', ['cashier', 'manager', 'admin'])->default('cashier');
            $table->timestamps();

        });

        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('pos_terminal_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('total_amount', 10, 2);
            $table->timestamps();
        });

        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->constrained('product_variants');
            $table->integer('quantity');
            $table->decimal('price', 10, 2);
            $table->timestamps();

        });

        Schema::create('stock_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('warehouse_id')->nullable()->index();
            $table->foreignId('variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->integer('quantity');
            $table->decimal('unit_cost', 10, 2);
            $table->decimal('total_cost', 10, 2)->virtualAs('quantity * unit_cost');
            $table->enum('type', ['stock_in', 'stock_out']);
            $table->timestamp('effective_at')->index();
            $table->string('reason')->nullable()->index();
            $table->nullableMorphs('source');                                      // e.g. PurchaseOrderItem, ShipmentItem
            $table->boolean('track_inventory')->default(true);
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['source_type','source_id','variant_id','type'], 'uniq_stock_post');

            // Useful indexes for performance
            $table->index(['variant_id','effective_at']);
            $table->index(['warehouse_id','variant_id']);
        });

        Schema::create('bulk_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->cascadeOnDelete();

            // quantity threshold for this tier
            $table->unsignedInteger('min_qty');

            // the unit price when threshold is met
            $table->decimal('unit_price', 10, 2);

            // optional validity window
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            $table->timestamps();

            $table->index(['product_id','min_qty']);
            $table->index(['product_variant_id','min_qty']);

            // prevent both product_id and product_variant_id being null
            // and prevent both being set at once
        });

        Schema::create('slug_histories', function (Blueprint $table) {
            $table->id();
            $table->morphs('sluggable');             // sluggable_type, sluggable_id
            $table->string('slug', 160)->unique();   // old slug
            $table->string('locale', 8)->nullable();
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            // Polymorphic owner: could be Order, Sale, VendorBill, Invoice, Subscription, etc.
            $table->morphs('payable'); // payable_type, payable_id

            // Direction of money
            $table->enum('type', ['inflow', 'outflow']);
            // inflow = customer payment (order, sale)
            // outflow = vendor/supplier payment, refund, expense

            // Payment info
            $table->enum('method', [
                'cash', 'card', 'transfer', 'wallet', 'paypal', 'stripe', 'cheque'
            ]);
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('NGN');

            // Gateway / bank tracking
            $table->string('transaction_reference')->nullable();
            $table->string('status')->default('pending'); // pending, paid, failed, refunded

            // Metadata
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete(); // who recorded it (for POS/manual)
            $table->json('meta')->nullable(); // gateway response, cheque number, notes

            $table->timestamps();
        });

//        Schema::create('stock_layers', function (Blueprint $table) {
//            $table->id();
//
//            $table->foreignId('variant_id')
//                ->constrained('product_variants')
//                ->cascadeOnDelete();
//
//            $table->integer('qty_remaining');              // Units left in this layer
//            $table->decimal('unit_cost', 10, 2);          // Cost per unit for FIFO COGS
//            $table->foreignId('stock_entry_id')           // Link back to original stock_in entry
//            ->constrained('stock_entries')
//                ->cascadeOnDelete();
//
//            $table->nullableMorphs('source');              // Optional link to PO, shipment, supplier, etc.
//            $table->timestamps();
//
//            $table->index(['variant_id']);
//            $table->index(['stock_entry_id']);
//        });
//
//        Schema::create('stock_consumptions', function (Blueprint $table) {
//            $table->id();
//
//            $table->foreignId('stock_entry_id')           // Link to the stock_out entry
//            ->constrained('stock_entries')
//                ->cascadeOnDelete();
//
//            $table->foreignId('stock_layer_id')           // The layer from which stock is consumed
//            ->constrained('stock_layers')
//                ->cascadeOnDelete();
//
//            $table->integer('quantity');                  // Number of units taken from this layer
//            $table->decimal('unit_cost', 10, 2);         // Cost at which this stock was consumed
//            $table->timestamps();
//
//            $table->index(['stock_entry_id']);
//            $table->index(['stock_layer_id']);
//        });



    }

    public function down(): void
    {
        Schema::dropIfExists('stock_entries');
        Schema::dropIfExists('sale_payments');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('pos_terminals');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
        Schema::dropIfExists('product_variant_values');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('variant_values');
        Schema::dropIfExists('variant_types');
        Schema::dropIfExists('products');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('variant_images');
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('bulk_prices');
        Schema::dropIfExists('countries');
        Schema::dropIfExists('states');
        Schema::dropIfExists('lgas');
        Schema::dropIfExists('warehouses');
    }
};
