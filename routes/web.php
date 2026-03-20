<?php

use App\Http\Controllers\Admin\AdminBrandController;
use App\Http\Controllers\Admin\AdminCategoryController;
use App\Http\Controllers\Admin\AdminOrderController;
use App\Http\Controllers\Admin\AdminProductController;
use App\Http\Controllers\Admin\AdminProductVariantController;
use App\Http\Controllers\Admin\AdminSkuController;
use App\Http\Controllers\Admin\ProductImageController;
use App\Http\Controllers\Admin\PaymentRecoveryController;
use App\Http\Controllers\Admin\VariantTypeController;
use App\Http\Controllers\Admin\AdminVariantValueController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\SaleController;
use App\Http\Controllers\Admin\SaleItemController;
use App\Http\Controllers\Admin\SalePaymentController;
use App\Http\Controllers\Admin\StockEntryController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\BarcodePrintController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CartItemController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\CheckoutPreviewController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InventoryAlertController;
use App\Http\Controllers\ItemReceiptController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\OrderController;
//use App\Http\Controllers\ProductController;
use App\Http\Controllers\Admin\ProductVariantController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\PosTerminalController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\ShippingController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\StockAdjustmentController;
use App\Http\Controllers\StockAuditController;
use App\Http\Controllers\StorefrontController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\PaystackWebhookController;
use App\Http\Controllers\VendorBillController;
use App\Http\Controllers\VendorController;
use App\Http\Controllers\VendorPaymentController;
use App\Http\Controllers\WareHouseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::redirect('/', '/store')->name('home');

Route::post('/webhooks/paystack', [PaystackWebhookController::class, 'handle'])
    ->name('webhooks.paystack');

Route::get('dashboard', [DashboardController::class, 'index'])->middleware(['auth', 'verified'])->name('dashboard');
Route::post('/barcodes/print', [BarcodePrintController::class, 'print'])
    ->middleware(['auth', 'verified'])
    ->name('barcodes.print');

Route::prefix('store')->name('store.')->group(function () {
    Route::get('/', [StorefrontController::class, 'home'])->name('home');
    Route::get('/search', [StorefrontController::class, 'search'])->name('search');
    Route::get('/product/{product:slug}', [StorefrontController::class, 'product'])->name('product');
    Route::get('/category/{category}', [StorefrontController::class, 'category'])->name('category');
    Route::get('/cart', [StorefrontController::class, 'cart'])->name('cart');

    Route::middleware('auth')->group(function () {
        Route::post('/cart/add', [StorefrontController::class, 'addToCart'])->name('cart.add');
        Route::patch('/cart/items/{variant}', [StorefrontController::class, 'updateCartItem'])
            ->name('cart.update');
        Route::delete('/cart/items/{variant}', [StorefrontController::class, 'removeCartItem'])
            ->name('cart.remove');
        Route::post('/cart/coupon', [StorefrontController::class, 'applyCoupon'])->name('cart.apply-coupon');
        Route::post('/cart/checkout', [StorefrontController::class, 'checkout'])->name('cart.checkout');
    });
});



// Catalog browsing
Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
Route::get('/categories/{category}', [CategoryController::class, 'show'])->name('categories.show');

Route::get('/brands', [BrandController::class, 'index'])->name('brands.index');
Route::get('/brands/{brand}', [BrandController::class, 'show'])->name('brands.show');




// Optional: expose variant detail pages if you show variant-level info
Route::get('/variants/{product_variant}', [ProductVariantController::class, 'show'])
    ->name('variants.show');

// Cart
Route::get('/cart', [CartController::class, 'show'])->name('cart.show');
//Route::post('/cart/items', [CartItemController::class, 'store'])->name('cart.items.store');             // body: variant_id, quantity
//Route::patch('/cart/items/{cart_item}', [CartItemController::class, 'update'])->name('cart.items.update'); // body: quantity
//Route::delete('/cart/items/{cart_item}', [CartItemController::class, 'destroy'])->name('cart.items.destroy');
Route::delete('/cart/empty', [CartController::class, 'empty'])->name('cart.empty');

// Checkout and orders
Route::middleware('auth')->group(function () {
    Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
    Route::post('/checkout/discount', [CheckoutController::class, 'applyDiscount'])->name('checkout.discount');
    Route::post('/checkout/pay', [CheckoutController::class, 'initializePayment'])->name('checkout.pay');
    Route::get('/payment/verify', [CheckoutController::class, 'verifyPayment'])->name('payment.verify');
    Route::post('/payment/reverify', [CheckoutController::class, 'reverifyPayment'])->name('payment.reverify');
    Route::get('/order/success', [CheckoutController::class, 'success'])->name('order.success');

    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');

    // Order state transitions suited to your enum
    Route::patch('/orders/{order}/pay', [OrderController::class, 'markPaid'])->name('orders.pay');
    Route::patch('/orders/{order}/ship', [OrderController::class, 'markShipped'])->name('orders.ship');
    Route::patch('/orders/{order}/complete', [OrderController::class, 'markCompleted'])->name('orders.complete');
    Route::patch('/orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');

    Route::get('/locations/countries', [LocationController::class, 'countries'])->name('locations.countries');
    Route::get('/locations/states/{country}', [LocationController::class, 'states'])->name('locations.states');
    Route::get('/locations/lgas/{state}', [LocationController::class, 'lgas'])->name('locations.lgas');
    Route::get('/locations/cities/{lga}', [LocationController::class, 'cities'])->name('locations.cities');

    Route::post('/checkout/preview', [CheckoutPreviewController::class, 'preview'])->name('checkout.preview');
});


Route::middleware(['auth'])->prefix('shipping')->name('shipping.')->group(function () {
    Route::get('methods', [ShippingController::class, 'methods'])->name('methods');
    Route::get('zones', [ShippingController::class, 'zones'])->name('zones');
    Route::get('pickup-locations', [ShippingController::class, 'pickupLocations'])->name('pickup_locations');
    Route::get('pickup-locations-by-state/{stateId}', [ShippingController::class, 'pickupLocationsByState'])->name('locations.pickups');
    Route::get('zone-by-state/{state}', [ShippingController::class, 'zoneByState'])->name('zone_by_state');


    Route::post('calculate', [ShippingController::class, 'calculate'])->name('calculate');
    Route::post('create', [ShippingController::class, 'createShipment'])->name('create');
});

/*
|--------------------------------------------------------------------------
| Admin Dashboard
|--------------------------------------------------------------------------
|
| Protect with your preferred middleware. Example: 'auth' and a gate-backed 'admin'.
|
*/

Route::prefix('admin')
    ->as('admin.')
    ->middleware(['auth', 'verified'])
    ->group(function () {

        Route::get('/dashboard/kpis', [DashboardController::class, 'kpis'])->name('dashboard.kpis');
        Route::get('/dashboard/sales-chart', [DashboardController::class, 'salesChart']);
        Route::post('/inventory-alerts/{alert}/close', [InventoryAlertController::class, 'close'])
            ->name('inventory-alerts.close');
        Route::get('/transactions', [TransactionController::class, 'index'])
            ->name('transactions.index');

        Route::get('/payment-recovery', [PaymentRecoveryController::class, 'index'])
            ->name('payment-recovery.index');
        Route::post('/payment-recovery/reverify', [PaymentRecoveryController::class, 'reverify'])
            ->name('payment-recovery.reverify');
        Route::post('/payment-recovery/refund', [PaymentRecoveryController::class, 'refund'])
            ->name('payment-recovery.refund');

//        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        // Catalog management
        Route::resource('categories', AdminCategoryController::class)->except(['show']);
        Route::resource('brands', AdminBrandController::class)->except(['show']);
        Route::patch('brands/{brand}/toggle-top', [AdminBrandController::class, 'toggleTop'])
            ->name('brands.toggle-top');

        Route::get('/products', [AdminProductController::class, 'index'])->name('products.index');
        Route::get('/products/create', [AdminProductController::class, 'create'])->name('products.create');
        Route::get('/products/{product}/edit', [AdminProductController::class, 'edit'])->name('products.edit');
        Route::post('/products', [AdminProductController::class, 'store'])->name('products.store');
        Route::put('/products/{product}', [AdminProductController::class, 'update'])->name('products.update');
        Route::delete('/products/bulk-delete-products', [AdminProductController::class, 'bulkDestroy'])->name('products.bulk-destroy');
        Route::delete('/products/{product}', [AdminProductController::class, 'destroy'])->name('products.destroy');

        Route::patch('/bulk-un-published/products', [AdminProductController::class, 'bulkUnpublish'])
            ->name('products.bulk-un-published');
        Route::patch('/bulk-published/products', [AdminProductController::class, 'bulkPublish'])
            ->name('products.bulk-published');
        Route::patch('/products/{product}/toggle-published', [AdminProductController::class, 'togglePublished'])
            ->name('products.toggle-published');
        Route::patch('/products/{product}/toggle-featured', [AdminProductController::class, 'toggleFeatured'])
            ->name('products.toggle-featured');
        Route::post('/products/{product}/duplicate', [AdminProductController::class, 'duplicate'])
            ->name('products.duplicate');

        Route::resource('variant-types', VariantTypeController::class)
            ->parameters(['variant-types' => 'variantType']);
//        Route::resource('variant-values', AdminVariantValueController::class)->except(['show']);
//        Route::resource('product-variants', AdminProductVariantController::class)->except(['show']);
        Route::get('/skus/check', [AdminSkuController::class, 'check'])->name('admin.skus.check');



        Route::post('/product/{product}/images', [ProductImageController::class, 'store'])->name('products.images.store');

        // Inventory
        Route::resource('stock-entries', StockEntryController::class)->only(['index', 'create', 'store', 'show']);
        Route::resource('stock-adjustments', StockAdjustmentController::class);
        Route::get('barcodes', [BarcodePrintController::class, 'index'])->name('barcodes.index');
        Route::get('inventory/stock-audit', [StockAuditController::class, 'index'])->name('inventory.stock-audit.index');
        Route::post('inventory/stock-audit', [StockAuditController::class, 'store'])->name('inventory.stock-audit.store');
        Route::get('inventory/stock-audit/mobile', [StockAuditController::class, 'mobile'])->name('inventory.stock-audit.mobile');
        Route::get('inventory/stock-audit/lookup', [StockAuditController::class, 'lookupByBarcode'])->name('inventory.stock-audit.lookup');
        Route::get('inventory/discrepancies', [StockAuditController::class, 'discrepancies'])->name('inventory.discrepancies');

        Route::get('search-variants', [StockEntryController::class, 'search'])->name('variants.search');

        Route::get('purchase-order', [PurchaseOrderController::class, 'create'])->name('purchase-orders.create');
        Route::get('purchase-orders/index', [PurchaseOrderController::class, 'index'])->name('purchase-orders.index');
        Route::get('purchase-orders/{purchase_order}/show', [PurchaseOrderController::class, 'show'])
            ->name('purchase-orders.show');
        Route::get('purchase-orders/{purchase_order}/edit', [PurchaseOrderController::class, 'show'])
            ->name('purchase-orders.edit');
        Route::post('/purchase-orders/store', [PurchaseOrderController::class, 'store'])->name('purchase-orders.store');


        Route::get('/item-receipts/create', [PurchaseOrderController::class, 'store'])->name('item-receipts.create');
        Route::post('/purchase-orders/{purchaseOrder}/receive-items', [ItemReceiptController::class, 'store'])->name('item-receipts.store');

        Route::post('/vendors/store', [VendorController::class, 'store'])->name('vendors.store');
        Route::get('/vendors-bills/create', [VendorController::class, 'store'])->name('vendor-bills.create');

        Route::resource('vendor-bills', VendorBillController::class)->except('store');
        Route::post('vendor-bills/store', [VendorBillController::class, 'store'])->name('vendor-bills.store');

        Route::get('/vendor-bills/purchase-order/{order}', [VendorBillController::class, 'byPurchaseOrder'])
            ->name('vendor-bills.by-purchase-order');

        Route::post('vendor-bills/{vendorBill}/payments', [VendorPaymentController::class,'store'])
            ->name('vendor-bill-payments.store');

        Route::get('purchase-orders/{purchase_order}/item-receipts-for-billing', [PurchaseOrderController::class, 'itemReceiptsForBilling'])
            ->name('purchase-orders.item-receipts-for-billing');
        Route::get('purchase-orders/{purchase_order}/get-item-receipts', [PurchaseOrderController::class, 'getItemReceipts'])
            ->name('purchase-orders.get-item-receipts');


        // Orders
//        Route::resource('orders', AdminOrderController::class)->only(['index', 'show', 'update']);
//        Route::patch('orders/{order}/status', [AdminOrderController::class, 'updateStatus'])->name('orders.status');

//         POS management
        Route::resource('pos-terminals', PosTerminalController::class);

        Route::resource('warehouses', WarehouseController::class);

        Route::get('/staff/search-user', [StaffController::class, 'searchUser'])->name('staff.search-user');

        Route::resource('staff', StaffController::class);

        Route::get('/pos/select-terminal', [PosController::class, 'selectTerminal'])
            ->name('pos.selectTerminal');

        Route::post('/pos/select-terminal', [PosController::class, 'assignTerminal'])->name('pos.setTerminal');



//        Route::resource('employees', EmployeeController::class);

        // POS UI (Inertia)
        Route::get('/pos', [PosController::class, 'index'])->name('pos.index');

        // Cart operations (AJAX via Inertia/form)
        Route::post('/pos/cart/add', [PosController::class, 'addToCart'])->name('pos.cart.add');
        Route::post('/pos/cart/update', [PosController::class, 'updateCartItem'])->name('pos.cart.update');
        Route::post('/pos/cart/remove', [PosController::class, 'removeCartItem'])->name('pos.cart.remove');

        // Finalize sale
        Route::post('/pos/place-order', [PosController::class, 'placeOrder'])->name('pos.placeOrder');
        Route::get('/pos/sales', [PosController::class, 'salesOrders'])->name('pos.orders');
        Route::get('/pos/sales/{sale}/print', [PosController::class, 'printSaleOrder'])->name('pos.orders');

        // optional: incremental product loading API
        Route::get('/pos/products', [PosController::class, 'productsApi'])->name('pos.products.api');

        // Sales and POS flow
        Route::resource('sales', SaleController::class)->only(['index', 'create', 'store', 'show']);
        Route::post('sales/{sale}/finalize', [SaleController::class, 'finalize'])->name('sales.finalize');

        Route::get('/customers/list', [CustomerController::class, 'list'])->name('customers.list');
        Route::post('/customers/store', [CustomerController::class, 'store'])->name('customers.store');


        // Nested under a sale
//        Route::resource('sales.items', SaleItemController::class)->shallow()->only(['store', 'update', 'destroy']);
//        Route::resource('sales.payments', SalePaymentController::class)->shallow()->only(['store', 'destroy']);
    });



require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
