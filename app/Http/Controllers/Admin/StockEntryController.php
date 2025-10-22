<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\StockEntryRequest;
use App\Http\Resources\StockEntryResource;
use App\Models\Employee;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use App\Models\StockEntry;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;

class StockEntryController extends Controller
{
    protected InventoryService $inventoryService;

    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }


    public function index(Request $request)
    {
        $query = StockEntry::query()
            ->with(['variant.product', 'employee', 'warehouse'])
            ->when($request->search, fn($q) =>
            $q->whereHas('variant.product', fn($v) =>
            $v->where('name', 'like', "%{$request->search}%")
                ->orWhere('sku', 'like', "%{$request->search}%")
            )
            )
            ->when($request->type, fn($q) => $q->where('type', $request->type))
            ->when($request->warehouse_id, fn($q) => $q->where('warehouse_id', $request->warehouse_id))
            ->when($request->employee_id, fn($q) => $q->where('employee_id', $request->employee_id))
            ->when($request->from, fn($q) => $q->whereDate('effective_at', '>=', $request->from))
            ->when($request->to, fn($q) => $q->whereDate('effective_at', '<=', $request->to))
            ->orderByDesc('effective_at');

        return Inertia::render('Admin/StockEntries/Index', [
            'filters'    => $request->only('search', 'type', 'warehouse_id', 'employee_id', 'from', 'to'),

            // Paginated entries
            'entries'    => StockEntryResource::collection(
                $query->paginate(20)->withQueryString()
            ),

            // Warehouses
            'warehouses' => Warehouse::select('id', 'name')->orderBy('name')->get(),

            // Employees = users with roles
            // ✅ Employees = users that have any assigned role
            'employees'  => \App\Models\User::whereHas('roles')
                ->select('id', 'name')
                ->orderBy('name')
                ->get(),
        ]);
    }


    public function create()
    {
        return Inertia::render('Admin/StockEntries/Create', [
            // For warehouse drop-down
            'warehouses' => Warehouse::select('id', 'name')
                ->orderBy('name')
                ->get(),

            // For selecting the product/variant to adjust
            'variants' => ProductVariant::with('product:id,name')
                ->select('id', 'product_id', 'sku')
                ->orderBy('sku')
                ->get(),
        ]);
    }

    public function search(Request $request)
    {
        $q = $request->input('q', '');
        return ProductVariant::with('product:id,name')
            ->where('sku', 'like', "%{$q}%")
            ->orWhereHas('product', fn($p) => $p->where('name','like',"%{$q}%"))
            ->limit(15)
            ->get(['id','product_id','sku']);
    }

    /**
     * GET /stock-entries/{id}
     */
    public function show(int $id): JsonResponse
    {
        $entry = StockEntry::with(['variant', 'warehouse', 'employee', 'stockLayers', 'stockConsumptions'])->findOrFail($id);

        return response()->json($entry);
    }

    /**
     * POST /stock-entries/stock-in
     */
    public function stockIn(StockEntryRequest $request): JsonResponse
    {
        $entry = $this->inventoryService->stockIn($request->validated());

        return response()->json($entry, 201);
    }

    /**
     * POST /stock-entries/stock-out
     */
    public function stockOut(StockEntryRequest $request): JsonResponse
    {
        $entry = $this->inventoryService->stockOut($request->validated());

        return response()->json($entry, 201);
    }

    /**
     * Helper: get FIFO layers for a variant
     */
    public function layers(Request $request, int $variantId): JsonResponse
    {
        $layers = $this->inventoryService->getLayers($variantId);
        return response()->json($layers);
    }

    /**
     * Helper: get on-hand quantity
     */
    public function onHand(Request $request, int $variantId): JsonResponse
    {
        $qty = $this->inventoryService->getOnHandQuantity($variantId);
        return response()->json(['variant_id' => $variantId, 'on_hand' => $qty]);
    }
}
