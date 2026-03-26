<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientStockException;
use App\Models\PosTerminal;
use App\Models\Setting;
use App\Services\InventoryService;
use App\Services\OrderService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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
        $variants = ProductVariant::query()
            ->active()
            ->with(['product','product.images'])
            ->when($request->input('q'), function ($q) use ($request) {
                $term = $request->input('q');
                $q->where('sku', 'like', "%{$term}%")
                    ->orWhere('barcode', 'like', "%{$term}%")
                    ->orWhereHas('product', fn($qp) => $qp->where('name','like', "%{$term}%"));
            })
            ->paginate(12);

        return response()->json($variants);
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
            'payment_method' => 'nullable|string',
            'subtotal' => 'required|numeric|min:0',
            'shipping' => 'nullable|array',
            'coupon'   => 'nullable|string',
            'channel'  => 'nullable|in:online,pos',
            'checkout_token' => 'required|string|exists:checkout_sessions,token',
        ]);

        $validated['channel'] = 'pos';


        try {
            $sale = $orderService->handle($validated);

            return back()->with('success', 'Sale placed successfully.');
        } catch (InsufficientStockException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', 'An unexpected error occurred while placing the order.');
        }
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
        $paperSize = Setting::get('receipt_paper_size', '80mm');

        // 🧾 Determine the paper dimension
        $paperConfig = match ($paperSize) {
            'A4' => [
                'paper' => 'A4',
                'view'  => 'receipts.a4',
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



}
