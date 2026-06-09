<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientStockException;
use App\Models\PosTerminal;
use App\Models\Setting;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\OrderService;
use App\Support\RoleNames;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use App\Models\ProductVariant;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Sale;
use Barryvdh\DomPDF\Facade\Pdf;
use Milon\Barcode\DNS1D;


class PosController extends Controller
{
    public function __construct(
        protected InventoryService $inventoryService  // ✅ Inject the inventory service
    ) {
    }


    public function index(Request $request)
    {
//        session()->forget('pos_terminal_id');
        $terminalAssigned = $this->ensureTerminalAssigned();

        // If this returned a redirect (e.g. back()->with('error'...)), stop execution
        if ($terminalAssigned instanceof \Illuminate\Http\RedirectResponse) {
            return $terminalAssigned;
        }

        // If selecting terminal, return early (this returns the Vue page from ensureTerminalAssigned())
        if ($terminalAssigned instanceof \Inertia\Response) {
            return $terminalAssigned;
        }

        $q = $request->input('q');
        $categoryId = $request->input('category_id');
        $brandId = $request->input('brand_id');

        $variantsQuery = ProductVariant::query()
            ->active()
            ->with(['product', 'product.images', 'values.type'])
            ->when($q, function ($query, $q) {
                $query->where(function ($q2) use ($q) {
                    $q2->where('sku', 'like', "%{$q}%")
                        ->orWhere('barcode', 'like', "%{$q}%")
                        ->orWhereHas('product', function ($qp) use ($q) {
                            $qp->where('name', 'like', "%{$q}%");
                        });
                });
            })
            ->when($brandId, fn($q) => $q->whereHas('product', fn($q2) => $q2->where('brand_id', $brandId)))
            ->when($categoryId, fn($q) => $q->whereHas('product.categories', fn($q2) => $q2->where('categories.id', $categoryId)))
            ->orderByDesc('id');

        $perPage = 12;
        $variants = $variantsQuery->paginate($perPage)->withQueryString();

        // append readable variant values
        $variants->getCollection()->transform(function ($variant) {
            $variant->variant_values = $variant->values->pluck('name')->join(' / ');
            $variant->requires_local_stock = $variant->requiresLocalStock();
            $variant->is_dropshipping = $variant->isDropshipping();
            $variant->is_sellable = $variant->requiresLocalStock()
                ? (($variant->quantity - ($variant->reserved ?? 0)) > 0)
                : (bool) $variant->show_as_available_when_dropshipping;
            return $variant;
        });

        $categories = Category::orderBy('name')->get(['id','name']);
        $brands = Brand::orderBy('name')->get(['id','name']);
        $cart = session('pos.cart', []);

        return Inertia::render('Admin/Pos/Index', [
            'variants'   => $variants,
            'categories' => $categories,
            'brands'     => $brands,
            'cart'       => $cart,
            'recent_customers' => $this->recentCustomers(),
            'pos_routes' => $this->posRoutes(),
            'filters'    => [
                'q' => $q,
                'category_id' => $categoryId,
                'brand_id' => $brandId,
            ],
        ]);
    }

    // Optional separate endpoint for infinite load or client-side fetching
    public function productsApi(Request $request)
    {
        $barcode = trim((string) $request->input('barcode', ''));
        $search = trim((string) $request->input('q', ''));

        $variants = ProductVariant::query()
            ->active()
            ->with(['product', 'product.images', 'values.type'])
            ->when($barcode !== '', function ($query) use ($barcode) {
                $query->where(function ($barcodeQuery) use ($barcode) {
                    $barcodeQuery
                        ->where('barcode', $barcode)
                        ->orWhere('sku', $barcode);
                });
            })
            ->when($barcode === '' && $search !== '', function ($query) use ($search) {
                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery
                        ->where('sku', 'like', "%{$search}%")
                        ->orWhere('barcode', 'like', "%{$search}%")
                        ->orWhereHas('product', fn ($productQuery) => $productQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->paginate(12);

        $variants->getCollection()->transform(fn (ProductVariant $variant) => $this->decoratePosVariant($variant));

        return response()->json($variants);
    }

    protected function decoratePosVariant(ProductVariant $variant): ProductVariant
    {
        $variant->variant_values = $variant->values->pluck('name')->join(' / ');
        $variant->requires_local_stock = $variant->requiresLocalStock();
        $variant->is_dropshipping = $variant->isDropshipping();
        $variant->is_sellable = $variant->requiresLocalStock()
            ? (($variant->quantity - ($variant->reserved ?? 0)) > 0)
            : (bool) $variant->show_as_available_when_dropshipping;

        return $variant;
    }

    public function placeOrder(Request $request, OrderService $orderService)
    {
        $validated = $request->validate([
            'customer_id' => 'nullable|exists:users,id',
            'items' => 'required|array',
            'items.*.variant_id' => 'required|integer|exists:product_variants,id',
            'items.*.quantity' => 'required|numeric|min:1',
            'items.*.price' => 'required|numeric|min:0',
            'total' => 'required|numeric|min:0',
            'payment_mode' => 'nullable|in:full,partial',
            'payment_method' => 'nullable|string|in:cash,card,transfer,wallet,paypal,stripe,cheque',
            'payment_lines' => 'nullable|array|min:1',
            'payment_lines.*.method' => 'required_with:payment_lines|in:cash,card,transfer,wallet,paypal,stripe,cheque',
            'payment_lines.*.amount' => 'required_with:payment_lines|numeric|min:0.01',
            'payment_lines.*.transaction_reference' => 'nullable|string|max:255',
            'subtotal' => 'required|numeric|min:0',
            'shipping' => 'nullable|array',
            'coupon'   => 'nullable|string',
            'channel'  => 'nullable|in:online,pos',
            'checkout_token' => 'required|string|exists:checkout_sessions,token',
            'due_date' => 'nullable|date',
            'repayment_terms' => 'nullable|string|max:255',
        ]);

        $validated['channel'] = 'pos';
        $validated['payment_lines'] = collect($validated['payment_lines'] ?? [])
            ->filter(fn ($line) => is_array($line) && (float) ($line['amount'] ?? 0) > 0)
            ->values()
            ->all();

        if ($validated['payment_lines'] === [] && !empty($validated['payment_method'])) {
            $validated['payment_lines'] = [[
                'method' => (string) $validated['payment_method'],
                'amount' => round((float) $validated['total'], 2),
            ]];
        }

        if ($validated['payment_lines'] === []) {
            return $this->posValidationErrorResponse($request, [
                'payment_lines' => 'Add at least one payment line before placing the sale.',
            ]);
        }

        $totalPaid = round((float) collect($validated['payment_lines'])->sum('amount'), 2);
        $total = round((float) $validated['total'], 2);
        $outstanding = round(max(0, $total - $totalPaid), 2);

        if ($totalPaid > $total + 0.01) {
            return $this->posValidationErrorResponse($request, [
                'payment_lines' => 'Total paid cannot exceed the order total.',
            ]);
        }

        if ($outstanding > 0) {
            if (empty($validated['customer_id'])) {
                return $this->posValidationErrorResponse($request, [
                    'customer_id' => 'Select a saved customer before creating a credit sale.',
                ]);
            }

            $customer = User::query()->find($validated['customer_id']);
            if (!$customer || strtolower((string) $customer->email) === 'walkincustomer@example.com') {
                return $this->posValidationErrorResponse($request, [
                    'customer_id' => 'Walk-in customers cannot be used for partial payment or receivable sales.',
                ]);
            }

            if (empty($validated['due_date'])) {
                return $this->posValidationErrorResponse($request, [
                    'due_date' => 'A due date is required when part of the sale remains outstanding.',
                ]);
            }

            if (blank($validated['repayment_terms'] ?? null)) {
                return $this->posValidationErrorResponse($request, [
                    'repayment_terms' => 'Repayment terms are required for credit sales.',
                ]);
            }

            $validated['payment_mode'] = 'partial';
        } else {
            $validated['payment_mode'] = 'full';
        }


        try {
            $order = $orderService->handle($validated);
            $message = 'Sale placed successfully.';

            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'success',
                    'type' => 'success',
                    'message' => $message,
                    'order' => [
                        'id' => (int) $order->id,
                        'order_number' => $order->order_number,
                    ],
                    'stock_updates' => $this->posStockUpdates($validated['items'] ?? []),
                ]);
            }

            return back()->with('success', $message);
        } catch (InsufficientStockException $e) {
            return $this->posOutOfStockResponse($request, $validated['items'] ?? [], $e->getDetails());
        } catch (ValidationException $e) {
            return $this->posValidationErrorResponse($request, $e->errors());
        } catch (\Throwable $e) {
            if (str_contains(strtolower($e->getMessage()), 'insufficient stock')) {
                return $this->posOutOfStockResponse($request, $validated['items'] ?? []);
            }

            report($e);
            return $this->posUnexpectedErrorResponse($request);
        }
    }

    protected function posValidationErrorResponse(Request $request, array $errors, ?string $message = null, int $status = 422)
    {
        $message ??= collect($errors)->flatten()->first() ?: 'There was an error placing your order.';

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'error',
                'type' => 'validation',
                'message' => $message,
                'errors' => $errors,
            ], $status);
        }

        return back()
            ->withErrors($errors)
            ->with('error', $message);
    }

    protected function posStockUpdates(array $items): array
    {
        $variantIds = collect($items)
            ->filter(fn ($item) => is_array($item) && !empty($item['variant_id']))
            ->pluck('variant_id')
            ->map(fn ($variantId) => (int) $variantId)
            ->unique()
            ->values();

        if ($variantIds->isEmpty()) {
            return [];
        }

        return ProductVariant::query()
            ->whereIn('id', $variantIds->all())
            ->get()
            ->map(function (ProductVariant $variant) {
                $available = $variant->requiresLocalStock()
                    ? max(0, (float) $variant->quantity - (float) ($variant->reserved ?? 0))
                    : null;

                return [
                    'variant_id' => (int) $variant->id,
                    'quantity' => (float) $variant->quantity,
                    'reserved' => (float) ($variant->reserved ?? 0),
                    'available' => $available,
                    'is_sellable' => $variant->requiresLocalStock()
                        ? (($available ?? 0) > 0)
                        : (bool) $variant->show_as_available_when_dropshipping,
                ];
            })
            ->values()
            ->all();
    }

    protected function posUnexpectedErrorResponse(Request $request)
    {
        $message = 'There was an error placing your order.';

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'error',
                'type' => 'error',
                'message' => $message,
                'errors' => [
                    'order' => [$message],
                ],
            ], 500);
        }

        return back()->with('error', $message);
    }

    protected function posOutOfStockResponse(Request $request, array $items, array $details = [])
    {
        $outOfStockItems = $this->normalizeOutOfStockDetails($items, $details);
        $message = 'An item in your cart is out of stock please remove';

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'error',
                'type' => 'stock',
                'message' => $message,
                'errors' => [
                    'stock' => [$message],
                ],
                'out_of_stock_items' => $outOfStockItems,
                'pos_out_of_stock_items' => $outOfStockItems,
            ], 409);
        }

        return back()
            ->withErrors([
                'stock' => $message,
                'pos_out_of_stock_items' => json_encode($outOfStockItems),
            ])
            ->with('error', $message)
            ->with('pos_out_of_stock_items', $outOfStockItems);
    }

    protected function normalizeOutOfStockDetails(array $items, array $details = []): array
    {
        $normalized = collect($details)
            ->filter(fn ($detail) => is_array($detail))
            ->map(fn (array $detail) => [
                'variant_id' => (int) ($detail['variant_id'] ?? 0),
                'sku' => $detail['sku'] ?? null,
                'requested' => (float) ($detail['requested'] ?? 0),
                'available' => max(0, (float) ($detail['available'] ?? 0)),
            ])
            ->filter(fn (array $detail) => $detail['variant_id'] > 0)
            ->values();

        if ($normalized->isNotEmpty()) {
            return $normalized->all();
        }

        $requestedByVariant = collect($items)
            ->filter(fn ($item) => is_array($item) && !empty($item['variant_id']))
            ->mapWithKeys(fn (array $item) => [
                (int) $item['variant_id'] => (float) ($item['quantity'] ?? 0),
            ]);

        if ($requestedByVariant->isEmpty()) {
            return [];
        }

        return ProductVariant::query()
            ->whereIn('id', $requestedByVariant->keys()->all())
            ->get()
            ->map(function (ProductVariant $variant) use ($requestedByVariant) {
                $requested = (float) ($requestedByVariant[(int) $variant->id] ?? 0);
                $available = $variant->requiresLocalStock()
                    ? max(0, (float) $variant->quantity - (float) ($variant->reserved ?? 0))
                    : $requested;

                return [
                    'variant_id' => (int) $variant->id,
                    'sku' => $variant->sku ?? null,
                    'requested' => $requested,
                    'available' => $available,
                ];
            })
            ->filter(fn (array $detail) => $detail['requested'] > 0 && $detail['available'] < $detail['requested'])
            ->values()
            ->all();
    }

    protected function variantLabel(ProductVariant $variant)
    {
        // If you store variant values via relation, build a label like "Size: L, Color: Red"
        if (method_exists($variant, 'values')) {
            return $variant->values->pluck('value')->join(', ');
        }
        return $variant->sku;
    }

    public function salesOrders()
    {
        $today = Carbon::today();

        $sales = Sale::with('customer')
            ->whereDate('created_at', $today->toDateString())
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($sale) => [
                'id' => $sale->id,
                'total_amount' => number_format((float)$sale->total_amount, 2, '.', ''),
                'customer_name' => $sale->customer ? $sale->customer->name : 'Walk-in Customer',
                'time' => $sale->created_at->format('H:i:s'),
            ]);

        return response()->json(['data' => $sales]);
    }


    public function printSaleOrder($id)
    {
        $sale = Sale::with([
            'employee',
            'order.user',
            'order.items.variant.product',
            'order.items.variant.values.type',
        ])->findOrFail($id);

        $order = $sale->order;

        // 🧠 Load paper size setting from settings table
        $paperSize = strtoupper(str_replace(' ', '', (string) Setting::get('receipt_paper_size', '80mm')));

        // 🧾 Determine the paper dimension
        $paperConfig = match ($paperSize) {
            'A4' => [
                'paper' => 'A4',
                'view'  => 'receipts.a4',
            ],
            '58MM' => [
                'paper' => [0, 0, 164.41, 1000],
                'view'  => 'receipts.thermal',
            ],
            default => [
                'paper' => [0, 0, 226.77, 1000],
                'view'  => 'receipts.thermal',
            ],
        };

        $data = [
            'sale' => $sale,
            'order' => $order,
            'date' => Carbon::parse($sale->created_at)->format('d/m/Y H:i'),
            'business_name' => Setting::get('business_name'),
            'business_tagline' => Setting::get('business_tagline'),
            'business_email' => Setting::get('business_email'),
            'business_phone' => Setting::get('business_phone'),
            'business_address' => Setting::get('business_address'),
            'business_website' => Setting::get('business_website'),
            'business_logo' => Setting::get('business_logo'),
            'business_receipt_footer' => Setting::get('business_receipt_footer'),
            'business_receipt_footer_refund' => Setting::get('business_receipt_footer_refund'),
        ];

        $pdf = Pdf::loadView($paperConfig['view'], $data)
            ->setPaper($paperConfig['paper']);

        return $pdf->stream("receipt-{$sale->id}.pdf");
    }

    private function ensureTerminalAssigned()
    {
        if (session()->has('pos_terminal_id')) {
            return session('pos_terminal_id');
        }

        $user = auth()->user();
        $warehouse = $user->warehouses()->first();

        if (!$warehouse) {
            return back()->with('error', 'You are not assigned to a warehouse.');
        }

        if (Setting::get('use_pos_terminal_password', 'true')) {
            return redirect()->route($this->posRouteName('selectTerminal'));
        }

        if ($warehouse->posTerminals()->count() === 1) {
            $terminals = $warehouse->posTerminals();
            $terminal = $terminals->first();

            // Auto-lock
            $terminal->update([
                'locked_by_employee_id' => auth()->id(),
                'locked_at' => now(),
            ]);

            session(['pos_terminal_id' => $terminal->id]);

            return $terminal->id;
        }

        $terminals = $warehouse->posTerminals()
            ->where(function ($q) {
                // Do not show terminals locked by others
                $q->whereNull('locked_by_employee_id')
                    ->orWhere('locked_by_employee_id', auth()->id());
            })
            ->orderBy('name')
            ->get();

        if ($terminals->count() === 0) {
            return back()->with('error', 'No POS terminal is available for use (all locked).');

        }


        return redirect()->route($this->posRouteName('selectTerminal'));
    }

    public function assignTerminal(Request $request)
    {
        $request->validate([
            'terminal_id' => 'required|exists:pos_terminals,id',
            'supervisor_password' => 'nullable|string',
        ]);

        if (filter_var(Setting::get('use_pos_terminal_password'), FILTER_VALIDATE_BOOLEAN) === true) {
            $dbPassword = Setting::get('pos_supervisor_password');

            if (!Hash::check($request->supervisor_password, $dbPassword)) {
                return back()->with('error', 'Incorrect Supervisor password');
            }
        }

        $terminal = PosTerminal::findOrFail($request->terminal_id);

        if ($terminal->locked_by_employee_id && $terminal->locked_by_employee_id !== auth()->id()) {
            return back()->with('error', 'This terminal is already in use by another staff.');
        }
        $terminal->update([
            'locked_by_employee_id' => auth()->id(),
            'locked_at' => now(),
        ]);

        session(['pos_terminal_id' => $terminal->id]);

        return redirect()->route($this->posRouteName('index'));
    }

    public function selectTerminal()
    {
        $user = auth()->user();
        $warehouse = $user->warehouses()->first();

        $terminals = $warehouse->posTerminals()
            ->where(function ($q) {
                // Do not show terminals locked by others
                $q->whereNull('locked_by_employee_id')
                    ->orWhere('locked_by_employee_id', auth()->id());
            })
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/Pos/SelectTerminal', [
            'terminals' => $terminals,
            'use_pos_terminal_password' => filter_var(Setting::get('use_pos_terminal_password'), FILTER_VALIDATE_BOOLEAN),
            'pos_routes' => $this->posRoutes(),
        ]);
    }

    protected function posRoutes(): array
    {
        return [
            'index' => route($this->posRouteName('index')),
            'set_terminal' => route($this->posRouteName('setTerminal')),
            'select_terminal' => route($this->posRouteName('selectTerminal')),
            'place_order' => route($this->posRouteName('placeOrder')),
            'sales_orders' => route($this->posRouteName('orders')),
            'print_sale_template' => route($this->posRouteName('print'), ['sale' => '__SALE__']),
            'products_api' => route($this->posRouteName('products.api')),
            'customers_list' => route($this->customerRouteName('list')),
            'customers_store' => route($this->customerRouteName('store')),
        ];
    }

    protected function posRouteName(string $suffix): string
    {
        return $this->isAdminPosContext() ? "admin.pos.{$suffix}" : "sales.pos.{$suffix}";
    }

    protected function customerRouteName(string $suffix): string
    {
        return $this->isAdminPosContext() ? "admin.customers.{$suffix}" : "sales.pos.customers.{$suffix}";
    }

    protected function isAdminPosContext(): bool
    {
        $routeName = (string) request()->route()?->getName();

        return str_starts_with($routeName, 'admin.');
    }

    protected function recentCustomers(int $limit = 10): array
    {
        return User::query()
            ->role(RoleNames::CUSTOMER)
            ->where(function ($query) {
                $query
                    ->whereNull('email')
                    ->orWhereRaw('LOWER(email) <> ?', ['walkincustomer@example.com']);
            })
            ->select('id', 'name', 'email', 'phone', 'created_at')
            ->withSum([
                'customerInvoices as outstanding_receivable' => fn ($query) => $query->where('outstanding_balance', '>', 0),
            ], 'outstanding_balance')
            ->withCount([
                'customerInvoices as overdue_invoice_count' => fn ($query) => $query
                    ->where('outstanding_balance', '>', 0)
                    ->whereDate('due_date', '<', now()->toDateString()),
            ])
            ->withMax('sales as latest_sale_at', 'created_at')
            ->withMax('orders as latest_order_at', 'created_at')
            ->orderByRaw("
                GREATEST(
                    COALESCE(latest_sale_at, '1970-01-01 00:00:00'),
                    COALESCE(latest_order_at, '1970-01-01 00:00:00'),
                    COALESCE(created_at, '1970-01-01 00:00:00')
                ) DESC
            ")
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (User $customer) => [
                'id' => (int) $customer->id,
                'name' => $customer->name,
                'email' => $customer->hasRealEmail() ? $customer->email : null,
                'phone' => $customer->phone,
                'outstanding_receivable' => round((float) ($customer->outstanding_receivable ?? 0), 2),
                'overdue_invoice_count' => (int) ($customer->overdue_invoice_count ?? 0),
            ])
            ->values()
            ->all();
    }



}
