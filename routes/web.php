<?php

use App\Http\Controllers\Admin\AdminBrandController;
use App\Http\Controllers\Admin\AdminCategoryController;
use App\Http\Controllers\Admin\AdminCustomerController;
use App\Http\Controllers\Admin\AdminCustomerNoteController;
use App\Http\Controllers\Admin\Accounting\AccountController as AdminAccountingAccountController;
use App\Http\Controllers\Admin\Accounting\ExpenseController as AdminAccountingExpenseController;
use App\Http\Controllers\Admin\Accounting\JournalEntryController as AdminAccountingJournalEntryController;
use App\Http\Controllers\Admin\Accounting\PaymentGatewaySettlementController as AdminAccountingPaymentGatewaySettlementController;
use App\Http\Controllers\Admin\Accounting\ReportController as AdminAccountingReportController;
use App\Http\Controllers\Admin\StorefrontAnalyticsController as AdminStorefrontAnalyticsController;
use App\Http\Controllers\Admin\CategoryPriceListReportController;
use App\Http\Controllers\Admin\CouponController as AdminCouponController;
use App\Http\Controllers\Admin\DiscountController as AdminDiscountController;
use App\Http\Controllers\Admin\AdminOrderController;
use App\Http\Controllers\Admin\AdminProductController;
use App\Http\Controllers\Admin\ProductNoteController;
use App\Http\Controllers\Admin\AdminProductVariantController;
use App\Http\Controllers\Admin\AdminSkuController;
use App\Http\Controllers\Admin\ProductImageController;
use App\Http\Controllers\Admin\PaymentRecoveryController;
use App\Http\Controllers\Admin\ShippingMethodController as AdminShippingMethodController;
use App\Http\Controllers\Admin\ShippingRateController as AdminShippingRateController;
use App\Http\Controllers\Admin\VariantTypeController;
use App\Http\Controllers\Admin\AdminVariantValueController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\SaleController;
use App\Http\Controllers\Admin\SaleItemController;
use App\Http\Controllers\Admin\SalePaymentController;
use App\Http\Controllers\Admin\StockEntryController;
use App\Http\Controllers\Account\AddressController as AccountAddressController;
use App\Http\Controllers\Account\DashboardController as AccountDashboardController;
use App\Http\Controllers\Account\OrderController as AccountOrderController;
use App\Http\Controllers\Account\SavedItemController as AccountSavedItemController;
use App\Http\Controllers\Auth\GoogleOAuthController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\BarcodePrintController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CartItemController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\CheckoutPreviewController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardRedirectController;
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
use App\Http\Controllers\StorefrontAnalyticsController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\PaystackWebhookController;
use App\Http\Controllers\Sales\CustomerController as SalesCustomerController;
use App\Http\Controllers\Sales\DashboardController as SalesDashboardController;
use App\Http\Controllers\Sales\OrderController as SalesOrderController;
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

Route::get('dashboard', DashboardRedirectController::class)->middleware(['auth', 'verified'])->name('dashboard');
Route::post('/barcodes/print', [BarcodePrintController::class, 'print'])
    ->middleware(['auth', 'verified'])
    ->name('barcodes.print');

Route::prefix('store')->name('store.')->group(function () {
    Route::get('/', [StorefrontController::class, 'home'])->name('home');
    Route::get('/catalog', [StorefrontController::class, 'catalog'])->name('catalog');
    Route::get('/featured', [StorefrontController::class, 'featured'])->name('featured');
    Route::get('/latest', [StorefrontController::class, 'latest'])->name('latest');
    Route::get('/search', [StorefrontController::class, 'search'])->name('search');
    Route::get('/search/suggestions', [StorefrontController::class, 'suggestions'])->name('search.suggestions');
    Route::get('/product/{product:slug}', [StorefrontController::class, 'product'])->name('product');
    Route::post('/product/{product:slug}/delivery-estimate', [StorefrontController::class, 'productDeliveryEstimate'])->name('product.delivery-estimate');
    Route::get('/category/{category}', [StorefrontController::class, 'category'])->name('category');
    Route::get('/cart', [StorefrontController::class, 'cart'])->name('cart');
    Route::post('/location/browser', [StorefrontController::class, 'storeBrowserLocation'])->name('location.browser.store');
    Route::delete('/location/browser', [StorefrontController::class, 'clearBrowserLocation'])->name('location.browser.clear');

    Route::middleware('auth')->group(function () {
        Route::post('/cart/add', [StorefrontController::class, 'addToCart'])->name('cart.add');
        Route::patch('/cart/items/{variant}', [StorefrontController::class, 'updateCartItem'])
            ->name('cart.update');
        Route::delete('/cart/items/{variant}', [StorefrontController::class, 'removeCartItem'])
            ->name('cart.remove');
        Route::post('/cart/items/{variant}/save-for-later', [StorefrontController::class, 'saveCartItemForLater'])
            ->name('cart.save-for-later');
        Route::post('/cart/coupon', [StorefrontController::class, 'applyCoupon'])->name('cart.apply-coupon');
        Route::post('/cart/checkout', [StorefrontController::class, 'checkout'])->name('cart.checkout');
        Route::post('/wishlist', [StorefrontController::class, 'addToWishlist'])->name('wishlist.store');
    });
});

Route::post('/analytics/storefront/page-views', [StorefrontAnalyticsController::class, 'store'])
    ->middleware('throttle:storefront-analytics')
    ->name('analytics.storefront.page-views');



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

    Route::get('/orders', [AccountOrderController::class, 'index'])->middleware('permission.any:account.orders.view')->name('orders.index');
    Route::get('/orders/{order}', [AccountOrderController::class, 'show'])->middleware('permission.any:account.orders.view')->name('orders.show');

    Route::get('/locations/countries', [LocationController::class, 'countries'])->name('locations.countries');
    Route::get('/locations/states/{country}', [LocationController::class, 'states'])->name('locations.states');
    Route::get('/locations/lgas/{state}', [LocationController::class, 'lgas'])->name('locations.lgas');

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

Route::prefix('account')
    ->as('account.')
    ->middleware(['auth', 'verified', 'permission.any:account.access'])
    ->group(function () {
        Route::get('/', [AccountDashboardController::class, 'index'])->middleware('permission.any:account.dashboard.view')->name('dashboard');
        Route::get('/orders', [AccountOrderController::class, 'index'])->middleware('permission.any:account.orders.view')->name('orders.index');
        Route::get('/orders/{order}', [AccountOrderController::class, 'show'])->middleware('permission.any:account.orders.view')->name('orders.show');
        Route::get('/wishlist', [AccountSavedItemController::class, 'index'])->middleware('permission.any:account.saved_items.manage')
            ->defaults('listType', \App\Models\CustomerSavedItem::TYPE_WISHLIST)
            ->name('wishlist.index');
        Route::get('/saved-for-later', [AccountSavedItemController::class, 'index'])->middleware('permission.any:account.saved_items.manage')
            ->defaults('listType', \App\Models\CustomerSavedItem::TYPE_SAVED_FOR_LATER)
            ->name('saved.index');
        Route::post('/wishlist', [AccountSavedItemController::class, 'store'])->middleware('permission.any:account.saved_items.manage')
            ->defaults('listType', \App\Models\CustomerSavedItem::TYPE_WISHLIST)
            ->name('wishlist.store');
        Route::post('/saved-for-later', [AccountSavedItemController::class, 'store'])->middleware('permission.any:account.saved_items.manage')
            ->defaults('listType', \App\Models\CustomerSavedItem::TYPE_SAVED_FOR_LATER)
            ->name('saved.store');
        Route::post('/saved-items/{savedItem}/move-to-cart', [AccountSavedItemController::class, 'moveToCart'])->middleware('permission.any:account.saved_items.manage')
            ->name('saved.move-to-cart');
        Route::post('/saved-items/{savedItem}/move-to-wishlist', [AccountSavedItemController::class, 'moveToList'])->middleware('permission.any:account.saved_items.manage')
            ->defaults('targetListType', \App\Models\CustomerSavedItem::TYPE_WISHLIST)
            ->name('saved.move-to-wishlist');
        Route::post('/saved-items/{savedItem}/move-to-saved-for-later', [AccountSavedItemController::class, 'moveToList'])->middleware('permission.any:account.saved_items.manage')
            ->defaults('targetListType', \App\Models\CustomerSavedItem::TYPE_SAVED_FOR_LATER)
            ->name('saved.move-to-saved');
        Route::delete('/saved-items/{savedItem}', [AccountSavedItemController::class, 'destroy'])->middleware('permission.any:account.saved_items.manage')->name('saved.destroy');

        Route::get('/addresses', [AccountAddressController::class, 'index'])->middleware('permission.any:account.addresses.manage')->name('addresses.index');
        Route::post('/addresses', [AccountAddressController::class, 'store'])->middleware('permission.any:account.addresses.manage')->name('addresses.store');
        Route::put('/addresses/{customerAddress}', [AccountAddressController::class, 'update'])->middleware('permission.any:account.addresses.manage')->name('addresses.update');
        Route::delete('/addresses/{customerAddress}', [AccountAddressController::class, 'destroy'])->middleware('permission.any:account.addresses.manage')->name('addresses.destroy');
    });

Route::prefix('sales')
    ->as('sales.')
    ->middleware(['auth', 'verified', 'permission.any:sales.access'])
    ->group(function () {
        Route::get('/', [SalesDashboardController::class, 'index'])->middleware('permission.any:sales.dashboard.view')->name('dashboard');
        Route::get('/orders', [SalesOrderController::class, 'index'])->middleware('permission.any:sales.orders.view')->name('orders.index');
        Route::get('/customers', [SalesCustomerController::class, 'index'])->middleware('permission.any:sales.customers.view')->name('customers.index');
        Route::post('/customers', [SalesCustomerController::class, 'store'])->middleware('permission.any:sales.customers.create')->name('customers.store');

        Route::get('/pos/select-terminal', [PosController::class, 'selectTerminal'])->middleware('permission.any:sales.pos.use')
            ->name('pos.selectTerminal');
        Route::post('/pos/select-terminal', [PosController::class, 'assignTerminal'])->middleware('permission.any:sales.pos.use')->name('pos.setTerminal');
        Route::get('/pos/customers', [CustomerController::class, 'list'])->middleware('permission.any:sales.pos.use')->name('pos.customers.list');
        Route::post('/pos/customers', [CustomerController::class, 'store'])->middleware('permission.any:sales.pos.use')->name('pos.customers.store');
        Route::get('/pos', [PosController::class, 'index'])->middleware('permission.any:sales.pos.use')->name('pos.index');
        Route::post('/pos/place-order', [PosController::class, 'placeOrder'])->middleware('permission.any:sales.pos.use')->name('pos.placeOrder');
        Route::get('/pos/sales', [PosController::class, 'salesOrders'])->middleware('permission.any:sales.pos.use')->name('pos.orders');
        Route::get('/pos/sales/{sale}/print', [PosController::class, 'printSaleOrder'])->middleware('permission.any:sales.pos.use')->name('pos.print');

        Route::get('inventory/stock-audit', [StockAuditController::class, 'index'])
            ->middleware('permission.any:sales.pos.use')
            ->name('inventory.stock-audit.index');
        Route::get('inventory/stock-audit/sessions', [StockAuditController::class, 'sessions'])
            ->middleware('permission.any:sales.pos.use')
            ->name('inventory.stock-audit.sessions');
        Route::delete('inventory/stock-audit/sessions/{session}', [StockAuditController::class, 'discardSession'])
            ->middleware('permission.any:sales.pos.use')
            ->name('inventory.stock-audit.sessions.discard');
        Route::post('inventory/stock-audit', [StockAuditController::class, 'store'])
            ->middleware('permission.any:sales.pos.use')
            ->name('inventory.stock-audit.store');
        Route::get('inventory/stock-audit/mobile', [StockAuditController::class, 'mobile'])
            ->middleware('permission.any:sales.pos.use')
            ->name('inventory.stock-audit.mobile');
        Route::get('inventory/stock-audit/lookup', [StockAuditController::class, 'lookupByBarcode'])
            ->middleware('permission.any:sales.pos.use')
            ->name('inventory.stock-audit.lookup');
        Route::post('inventory/stock-audit/items', [StockAuditController::class, 'upsertItem'])
            ->middleware('permission.any:sales.pos.use')
            ->name('inventory.stock-audit.items.upsert');
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
    ->middleware(['auth', 'verified', 'permission.any:admin.access'])
    ->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->middleware('permission.any:admin.dashboard.view')->name('dashboard');

        Route::get('/dashboard/kpis', [DashboardController::class, 'kpis'])->name('dashboard.kpis');
        Route::get('/dashboard/sales-chart', [DashboardController::class, 'salesChart']);
        Route::post('/inventory-alerts/{alert}/close', [InventoryAlertController::class, 'close'])
            ->name('inventory-alerts.close');
        Route::get('/transactions', [TransactionController::class, 'index'])
            ->middleware('permission.any:admin.transactions.view')
            ->name('transactions.index');
        Route::get('/analytics', [AdminStorefrontAnalyticsController::class, 'index'])
            ->middleware('permission.any:admin.analytics.view')
            ->name('analytics.index');
        Route::get('/analytics/export', [AdminStorefrontAnalyticsController::class, 'export'])
            ->middleware('permission.any:admin.analytics.view')
            ->name('analytics.export');
        Route::get('/analytics/settings', [AdminStorefrontAnalyticsController::class, 'settings'])
            ->middleware('permission.any:admin.analytics.manage')
            ->name('analytics.settings');
        Route::patch('/analytics/settings', [AdminStorefrontAnalyticsController::class, 'updateSettings'])
            ->middleware('permission.any:admin.analytics.manage')
            ->name('analytics.settings.update');

        Route::get('/accounting', [AdminAccountingReportController::class, 'overview'])
            ->middleware('permission.any:admin.accounting.view')
            ->name('accounting.index');
        Route::post('/accounting/sync-history', [AdminAccountingReportController::class, 'syncHistory'])
            ->middleware('permission.any:admin.accounting.manage')
            ->name('accounting.sync-history');
        Route::get('/accounting/accounts', [AdminAccountingAccountController::class, 'index'])
            ->middleware('permission.any:admin.accounting.manage')
            ->name('accounting.accounts.index');
        Route::post('/accounting/accounts', [AdminAccountingAccountController::class, 'store'])
            ->middleware('permission.any:admin.accounting.manage')
            ->name('accounting.accounts.store');
        Route::put('/accounting/accounts/{account}', [AdminAccountingAccountController::class, 'update'])
            ->middleware('permission.any:admin.accounting.manage')
            ->name('accounting.accounts.update');
        Route::patch('/accounting/accounts/{account}/toggle', [AdminAccountingAccountController::class, 'toggle'])
            ->middleware('permission.any:admin.accounting.manage')
            ->name('accounting.accounts.toggle');
        Route::get('/accounting/journal-entries', [AdminAccountingJournalEntryController::class, 'index'])
            ->middleware('permission.any:admin.accounting.view')
            ->name('accounting.journal-entries.index');
        Route::get('/accounting/journal-entries/{journalEntry}', [AdminAccountingJournalEntryController::class, 'show'])
            ->middleware('permission.any:admin.accounting.view')
            ->name('accounting.journal-entries.show');
        Route::post('/accounting/journal-entries', [AdminAccountingJournalEntryController::class, 'store'])
            ->middleware('permission.any:admin.accounting.journals.post')
            ->name('accounting.journal-entries.store');
        Route::get('/accounting/gateway-settlements', [AdminAccountingPaymentGatewaySettlementController::class, 'index'])
            ->middleware('permission.any:admin.accounting.manage')
            ->name('accounting.gateway-settlements.index');
        Route::post('/accounting/gateway-settlements', [AdminAccountingPaymentGatewaySettlementController::class, 'store'])
            ->middleware('permission.any:admin.accounting.manage')
            ->name('accounting.gateway-settlements.store');
        Route::get('/accounting/expenses', [AdminAccountingExpenseController::class, 'index'])
            ->middleware('permission.any:admin.accounting.expenses.manage')
            ->name('accounting.expenses.index');
        Route::post('/accounting/expenses', [AdminAccountingExpenseController::class, 'store'])
            ->middleware('permission.any:admin.accounting.expenses.manage')
            ->name('accounting.expenses.store');
        Route::get('/accounting/reports/ledger', [AdminAccountingReportController::class, 'ledger'])
            ->middleware('permission.any:admin.accounting.reports.view')
            ->name('accounting.reports.ledger');
        Route::get('/accounting/reports/trial-balance', [AdminAccountingReportController::class, 'trialBalance'])
            ->middleware('permission.any:admin.accounting.reports.view')
            ->name('accounting.reports.trial-balance');
        Route::get('/accounting/reports/profit-loss', [AdminAccountingReportController::class, 'profitAndLoss'])
            ->middleware('permission.any:admin.accounting.reports.view')
            ->name('accounting.reports.profit-loss');
        Route::get('/accounting/reports/balance-sheet', [AdminAccountingReportController::class, 'balanceSheet'])
            ->middleware('permission.any:admin.accounting.reports.view')
            ->name('accounting.reports.balance-sheet');

        Route::get('/payment-recovery', [PaymentRecoveryController::class, 'index'])
            ->middleware('permission.any:admin.payment_recovery.manage')
            ->name('payment-recovery.index');
        Route::post('/payment-recovery/reverify', [PaymentRecoveryController::class, 'reverify'])
            ->middleware('permission.any:admin.payment_recovery.manage')
            ->name('payment-recovery.reverify');
        Route::post('/payment-recovery/refund', [PaymentRecoveryController::class, 'refund'])
            ->middleware('permission.any:admin.payment_recovery.manage')
            ->name('payment-recovery.refund');

//        Route::get('/', [DashboardController::class, 'index'])->middleware('permission.any:admin.dashboard.view')->name('dashboard');

        // Catalog management
        Route::resource('categories', AdminCategoryController::class)->except(['show']);
        Route::resource('brands', AdminBrandController::class)->except(['show']);
        Route::patch('brands/{brand}/toggle-top', [AdminBrandController::class, 'toggleTop'])
            ->name('brands.toggle-top');
        Route::get('discounts', [AdminDiscountController::class, 'index'])
            ->middleware('permission.any:admin.catalog.manage')
            ->name('discounts.index');
        Route::get('discounts/create', [AdminDiscountController::class, 'create'])
            ->middleware('permission.any:admin.catalog.manage')
            ->name('discounts.create');
        Route::post('discounts', [AdminDiscountController::class, 'store'])
            ->middleware('permission.any:admin.catalog.manage')
            ->name('discounts.store');
        Route::get('discounts/{discount}/edit', [AdminDiscountController::class, 'edit'])
            ->middleware('permission.any:admin.catalog.manage')
            ->name('discounts.edit');
        Route::put('discounts/{discount}', [AdminDiscountController::class, 'update'])
            ->middleware('permission.any:admin.catalog.manage')
            ->name('discounts.update');
        Route::patch('discounts/{discount}/toggle-status', [AdminDiscountController::class, 'toggleStatus'])
            ->middleware('permission.any:admin.catalog.manage')
            ->name('discounts.toggle-status');

        Route::get('coupons', [AdminCouponController::class, 'index'])
            ->middleware('permission.any:admin.catalog.manage')
            ->name('coupons.index');
        Route::get('coupons/create', [AdminCouponController::class, 'create'])
            ->middleware('permission.any:admin.catalog.manage')
            ->name('coupons.create');
        Route::post('coupons', [AdminCouponController::class, 'store'])
            ->middleware('permission.any:admin.catalog.manage')
            ->name('coupons.store');
        Route::get('coupons/{coupon}/edit', [AdminCouponController::class, 'edit'])
            ->middleware('permission.any:admin.catalog.manage')
            ->name('coupons.edit');
        Route::put('coupons/{coupon}', [AdminCouponController::class, 'update'])
            ->middleware('permission.any:admin.catalog.manage')
            ->name('coupons.update');
        Route::patch('coupons/{coupon}/toggle-status', [AdminCouponController::class, 'toggleStatus'])
            ->middleware('permission.any:admin.catalog.manage')
            ->name('coupons.toggle-status');

        Route::get('shipping-methods', [AdminShippingMethodController::class, 'index'])
            ->middleware('permission.any:admin.catalog.manage')
            ->name('shipping-methods.index');
        Route::get('shipping-methods/create', [AdminShippingMethodController::class, 'create'])
            ->middleware('permission.any:admin.catalog.manage')
            ->name('shipping-methods.create');
        Route::post('shipping-methods', [AdminShippingMethodController::class, 'store'])
            ->middleware('permission.any:admin.catalog.manage')
            ->name('shipping-methods.store');
        Route::get('shipping-methods/{shippingMethod}/edit', [AdminShippingMethodController::class, 'edit'])
            ->middleware('permission.any:admin.catalog.manage')
            ->name('shipping-methods.edit');
        Route::put('shipping-methods/{shippingMethod}', [AdminShippingMethodController::class, 'update'])
            ->middleware('permission.any:admin.catalog.manage')
            ->name('shipping-methods.update');
        Route::patch('shipping-methods/{shippingMethod}/toggle-status', [AdminShippingMethodController::class, 'toggleStatus'])
            ->middleware('permission.any:admin.catalog.manage')
            ->name('shipping-methods.toggle-status');

        Route::get('shipping-rates', [AdminShippingRateController::class, 'index'])
            ->middleware('permission.any:admin.catalog.manage')
            ->name('shipping-rates.index');
        Route::get('shipping-rates/create', [AdminShippingRateController::class, 'create'])
            ->middleware('permission.any:admin.catalog.manage')
            ->name('shipping-rates.create');
        Route::post('shipping-rates', [AdminShippingRateController::class, 'store'])
            ->middleware('permission.any:admin.catalog.manage')
            ->name('shipping-rates.store');
        Route::get('shipping-rates/{shippingRate}/edit', [AdminShippingRateController::class, 'edit'])
            ->middleware('permission.any:admin.catalog.manage')
            ->name('shipping-rates.edit');
        Route::put('shipping-rates/{shippingRate}', [AdminShippingRateController::class, 'update'])
            ->middleware('permission.any:admin.catalog.manage')
            ->name('shipping-rates.update');
        Route::patch('shipping-rates/{shippingRate}/toggle-status', [AdminShippingRateController::class, 'toggleStatus'])
            ->middleware('permission.any:admin.catalog.manage')
            ->name('shipping-rates.toggle-status');
        Route::get('reports/category-price-list', [CategoryPriceListReportController::class, 'index'])
            ->middleware('permission.any:admin.catalog.manage')
            ->name('reports.category-price-list.index');
        Route::get('reports/category-price-list/export', [CategoryPriceListReportController::class, 'export'])
            ->middleware('permission.any:admin.catalog.manage')
            ->name('reports.category-price-list.export');

        Route::get('/products', [AdminProductController::class, 'index'])->name('products.index');
        Route::get('/products/create', [AdminProductController::class, 'create'])->name('products.create');
        Route::get('/products/{product}', [AdminProductController::class, 'show'])->name('products.show');
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
        Route::post('/products/{product}/notes', [ProductNoteController::class, 'store'])
            ->middleware('permission.any:admin.catalog.manage')
            ->name('products.notes.store');
        Route::put('/products/{product}/notes/{note}', [ProductNoteController::class, 'update'])
            ->middleware('permission.any:admin.catalog.manage')
            ->name('products.notes.update');
        Route::delete('/products/{product}/notes/{note}', [ProductNoteController::class, 'destroy'])
            ->middleware('permission.any:admin.catalog.manage')
            ->name('products.notes.destroy');

        Route::resource('variant-types', VariantTypeController::class)
            ->parameters(['variant-types' => 'variantType']);
//        Route::resource('variant-values', AdminVariantValueController::class)->except(['show']);
//        Route::resource('product-variants', AdminProductVariantController::class)->except(['show']);
        Route::get('/skus/check', [AdminSkuController::class, 'check'])->name('admin.skus.check');



        Route::post('/product/{product}/images', [ProductImageController::class, 'store'])->name('products.images.store');

        // Inventory
        Route::resource('stock-entries', StockEntryController::class)->only(['index', 'create', 'store', 'show']);
        Route::resource('stock-adjustments', StockAdjustmentController::class);
        Route::post('stock-adjustments/bulk-review', [StockAdjustmentController::class, 'bulkReview'])
            ->name('stock-adjustments.bulk-review');
        Route::post('stock-adjustments/{stockAdjustment}/approve', [StockAdjustmentController::class, 'approve'])
            ->name('stock-adjustments.approve');
        Route::post('stock-adjustments/{stockAdjustment}/reject', [StockAdjustmentController::class, 'reject'])
            ->name('stock-adjustments.reject');
        Route::get('barcodes', [BarcodePrintController::class, 'index'])->name('barcodes.index');
        Route::get('inventory/stock-audit', [StockAuditController::class, 'index'])->name('inventory.stock-audit.index');
        Route::get('inventory/stock-audit/sessions', [StockAuditController::class, 'sessions'])->name('inventory.stock-audit.sessions');
        Route::delete('inventory/stock-audit/sessions/{session}', [StockAuditController::class, 'discardSession'])->name('inventory.stock-audit.sessions.discard');
        Route::post('inventory/stock-audit', [StockAuditController::class, 'store'])->name('inventory.stock-audit.store');
        Route::get('inventory/stock-audit/mobile', [StockAuditController::class, 'mobile'])->name('inventory.stock-audit.mobile');
        Route::get('inventory/stock-audit/lookup', [StockAuditController::class, 'lookupByBarcode'])->name('inventory.stock-audit.lookup');
        Route::post('inventory/stock-audit/items', [StockAuditController::class, 'upsertItem'])->name('inventory.stock-audit.items.upsert');
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
        Route::get('orders', [AdminOrderController::class, 'index'])->middleware('permission.any:admin.orders.manage')->name('orders.index');
        Route::get('orders/{order}', [AdminOrderController::class, 'show'])->middleware('permission.any:admin.orders.manage')->name('orders.show');
        Route::patch('orders/{order}/status', [AdminOrderController::class, 'updateStatus'])->middleware('permission.any:admin.orders.manage')->name('orders.status');
        Route::post('orders/{order}/notes', [AdminOrderController::class, 'storeNote'])->middleware('permission.any:admin.orders.manage')->name('orders.notes.store');
        Route::post('orders/bulk', [AdminOrderController::class, 'bulkUpdate'])->middleware('permission.any:admin.orders.manage')->name('orders.bulk');
        Route::post('orders/{order}/notifications/resend', [AdminOrderController::class, 'resendNotification'])->middleware('permission.any:admin.orders.manage')->name('orders.notifications.resend');

        Route::get('customers', [AdminCustomerController::class, 'index'])
            ->middleware('permission.any:admin.customers.view')
            ->name('customers.index');
        Route::get('customers/export', [AdminCustomerController::class, 'export'])
            ->middleware('permission.any:admin.customers.export')
            ->name('customers.export');
        Route::post('customers/bulk', [AdminCustomerController::class, 'bulk'])
            ->middleware('permission.any:admin.customers.bulk_actions')
            ->name('customers.bulk');
        Route::get('customers/{customer}', [AdminCustomerController::class, 'show'])
            ->middleware('permission.any:admin.customers.view_details')
            ->name('customers.show');
        Route::put('customers/{customer}', [AdminCustomerController::class, 'update'])
            ->middleware('permission.any:admin.customers.update')
            ->name('customers.update');
        Route::patch('customers/{customer}/status', [AdminCustomerController::class, 'updateStatus'])
            ->middleware('permission.any:admin.customers.suspend')
            ->name('customers.status');
        Route::post('customers/{customer}/mark-verified', [AdminCustomerController::class, 'markVerified'])
            ->middleware('permission.any:admin.customers.update')
            ->name('customers.mark-verified');
        Route::post('customers/{customer}/verification/resend', [AdminCustomerController::class, 'resendVerification'])
            ->middleware('permission.any:admin.customers.email')
            ->name('customers.verification.resend');
        Route::post('customers/{customer}/password-reset', [AdminCustomerController::class, 'sendPasswordReset'])
            ->middleware('permission.any:admin.customers.email')
            ->name('customers.password-reset');
        Route::post('customers/{customer}/email', [AdminCustomerController::class, 'sendEmail'])
            ->middleware('permission.any:admin.customers.email')
            ->name('customers.email');
        Route::post('customers/{customer}/notes', [AdminCustomerNoteController::class, 'store'])
            ->middleware('permission.any:admin.customers.notes.manage')
            ->name('customers.notes.store');
        Route::put('customers/{customer}/notes/{note}', [AdminCustomerNoteController::class, 'update'])
            ->middleware('permission.any:admin.customers.notes.manage')
            ->name('customers.notes.update');
        Route::delete('customers/{customer}/notes/{note}', [AdminCustomerNoteController::class, 'destroy'])
            ->middleware('permission.any:admin.customers.notes.manage')
            ->name('customers.notes.destroy');
//        Route::resource('orders', AdminOrderController::class)->only(['index', 'show', 'update']);
//        Route::patch('orders/{order}/status', [AdminOrderController::class, 'updateStatus'])->name('orders.status');

//         POS management
        Route::resource('pos-terminals', PosTerminalController::class);

        Route::resource('warehouses', WarehouseController::class);

        Route::get('/staff/search-user', [StaffController::class, 'searchUser'])->name('staff.search-user');

        Route::resource('staff', StaffController::class);

        Route::get('/pos/select-terminal', [PosController::class, 'selectTerminal'])->middleware('permission.any:sales.pos.use')
            ->name('pos.selectTerminal');

        Route::post('/pos/select-terminal', [PosController::class, 'assignTerminal'])->middleware('permission.any:sales.pos.use')->name('pos.setTerminal');



//        Route::resource('employees', EmployeeController::class);

        // POS UI (Inertia)
        Route::get('/pos', [PosController::class, 'index'])->middleware('permission.any:sales.pos.use')->name('pos.index');

        // Cart operations (AJAX via Inertia/form)
        Route::post('/pos/cart/add', [PosController::class, 'addToCart'])->name('pos.cart.add');
        Route::post('/pos/cart/update', [PosController::class, 'updateCartItem'])->name('pos.cart.update');
        Route::post('/pos/cart/remove', [PosController::class, 'removeCartItem'])->name('pos.cart.remove');

        // Finalize sale
        Route::post('/pos/place-order', [PosController::class, 'placeOrder'])->middleware('permission.any:sales.pos.use')->name('pos.placeOrder');
        Route::get('/pos/sales', [PosController::class, 'salesOrders'])->middleware('permission.any:sales.pos.use')->name('pos.orders');
        Route::get('/pos/sales/{sale}/print', [PosController::class, 'printSaleOrder'])->name('pos.print');

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




