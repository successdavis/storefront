<?php

namespace App\Http\Controllers;

use App\Services\InventoryService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\ProductVariant;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

    public function placeOrder(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'nullable|exists:users,id',
            'items' => 'required|array',
            'items.*.variant_id' => 'required|integer|exists:product_variants,id',
            'items.*.quantity' => 'required|numeric|min:1',
            'items.*.price' => 'required|numeric|min:0',
            'total' => 'required|numeric|min:0',
            'payment_method' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            $sale = Sale::create([
                'employee_id' => auth()->id(),
                'customer_id' => $validated['customer_id'] ?? null,
                'total_amount' => $validated['total'],
                'payment_method' => $validated['payment_method'] ?? 'cash',
            ]);

            foreach ($validated['items'] as $item) {
                $saleItem = SaleItem::create([
                    'sale_id' => $sale->id,
                    'variant_id' => $item['variant_id'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                ]);

                $this->inventoryService->stockOut([
                    'variant_id' => $item['variant_id'],
                    'quantity' => $item['quantity'],
                    'employee_id' => auth()->id(),
                    'reason' => 'Sale to customer - POS',
                    'source_type' => Sale::class,
                    'source_id' => $sale->id,
                    'note' => "Sale Item #{$saleItem->id}",
                ]);
            }

            $sale->addPayment([
                'type' => 'inflow',
                'method' => $validated['payment_method'] ?? 'cash',
                'amount' => $validated['total'],
                'status' => 'paid',
                'note' => 'Sale to customer - POS',
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Sale placed successfully.',
                'sale_id' => $sale->id,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
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
}
