<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientStockException;
use App\Models\Address;
use App\Models\Shipment;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\OrderService;
use App\Services\SaleService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use App\Models\ProductVariant;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Sale;
use Illuminate\Support\Facades\View;
use App\Models\SaleItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mike42\Escpos\Printer;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\PrintConnectors\FilePrintConnector;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;

class PosController extends Controller
{
    public function __construct(
        protected InventoryService $inventoryService  // ✅ Inject the inventory service
    ) {}

    public function index(Request $request)
    {
        $q = $request->input('q');
        $categoryId = $request->input('category_id');
        $brandId = $request->input('brand_id');

        $variantsQuery = ProductVariant::with(['product', 'product.images', 'values.type'])
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
        $variants = ProductVariant::with(['product','product.images'])
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
        ]);

        $validated['channel'] = 'pos';

        try {
            $sale = $orderService->handle($validated);

            return response()->json([
                'success' => true,
                'message' => 'Sale placed successfully.',
                'sale_id' => $sale->id,
            ]);
        } catch (InsufficientStockException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'details' => $e->getDetails(),
            ], 422);
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'Failed to place order: ' . $e->getMessage(),
            ], 500);
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

//    public function printSaleOrder($id)
//    {
//        $sale = Sale::with('customer')->findOrFail($id);
//        $printer->text("DAILY STORE\n");
//        $printer->text("------------------------------\n");
//        $printer->setJustification(Printer::JUSTIFY_LEFT);
//        $printer->text("SALE ID: {$sale->id}\n");
//        $printer->text("CUSTOMER: " . ($sale->customer?->name ?? 'Walk In Customer') . "\n");
//        $printer->text("TOTAL: ₦" . number_format($sale->total_amount, 2) . "\n");
//        $printer->text("TIME: " . $sale->created_at->format('h:i A') . "\n");
//        $printer->text("------------------------------\n");
//        $printer->setJustification(Printer::JUSTIFY_CENTER);
//        $printer->text("Thank you for your purchase!\n");
//        $printer->cut();
//        $printer->close();
//    }

    public function printSaleOrder(Request $request)
    {
        $sale = Sale::with('customer')->findOrFail($id);

        try {
            $order = [
                'customer'  => $sale->customer?->name ?? 'Walk In Customer',
                'receipt_no' => 'RCP-' . now()->timestamp,
                'date' => now()->format('d M, Y h:i A'),
                'items' => [
                    ['name' => 'Product A', 'qty' => 2, 'price' => 1500],
                    ['name' => 'Product B', 'qty' => 1, 'price' => 2000],
                ],
                'subtotal' => 5000,
                'discount' => 500,
                'total' => "₦ " . number_format($sale->total_amount, 2),
            ];

            // Generate HTML content
            $html = View::make('receipts.thermal', compact('order'))->render();

            // Return as JSON to be printed in browser
            return response()->json([
                'status' => 'success',
                'html' => $html,
            ]);
        } catch (\Throwable $e) {
            Log::channel('receipt')->error('Receipt print error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to generate receipt.',
            ], 500);
        }
    }
}
