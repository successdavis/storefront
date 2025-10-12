<?php

namespace App\Http\Controllers;

use App\Models\StockAdjustment;
use App\Models\ProductVariant;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Throwable;

class StockAdjustmentController extends Controller
{
    public function index()
    {
        $adjustments = StockAdjustment::with([
            'variant:id,product_id,sku',
            'variant.product:id,name',
            'warehouse:id,name',
            'employee:id,name'
        ])
            ->latest('adjusted_at')
            ->paginate(15)
            ->through(fn ($item) => [
                'id' => $item->id,
                'variant_sku' => $item->variant?->sku,
                'product_name' => $item->variant?->product?->name,
                'warehouse' => $item->warehouse?->name,
                'employee' => $item->employee?->name,
                'previous_quantity' => $item->previous_quantity,
                'adjusted_quantity' => $item->adjusted_quantity,
                'new_quantity' => $item->new_quantity,
                'reason' => ucfirst(str_replace('_', ' ', $item->reason)),
                'adjusted_at' => $item->adjusted_at->toDateTimeString(),
            ]);

        return Inertia::render('StockAdjustments/Index', [
            'adjustments' => $adjustments,
        ]);
    }

    public function create()
    {
        $variants = ProductVariant::with('product:id,name')
            ->select('id', 'product_id', 'sku', 'quantity')
            ->distinct()
            ->get()
            ->map(function ($variant) {
                return [
                    'id' => $variant->id,
                    'label' => $variant->product->name . ' — ' . $variant->sku,
                    'current_quantity' => $variant->quantity ?? 0,
                ];
            });

        return Inertia::render('StockAdjustments/Create', [
            'variants' => $variants,
        ]);
    }


    public function store(Request $request, InventoryService $inventoryService)
    {
        $validated = $request->validate([
            'warehouse_id'       => 'nullable|exists:warehouses,id',
            'variant_id'         => 'required|exists:product_variants,id',
            'previous_quantity'  => 'required|integer',
            'adjusted_quantity'  => 'required|integer|not_in:0',
            'reason'             => 'required|string|max:50',
            'employee_id'        => 'nullable|exists:employees,id',
            'reference'          => 'nullable|string|max:100',
            'note'               => 'nullable|string',
            'adjusted_at'        => 'nullable|date',
        ]);

        $validated['adjusted_at'] = $validated['adjusted_at'] ?? now();

        try {
            DB::transaction(function () use ($validated, $inventoryService) {
                // 1️⃣ Create stock adjustment record
                $adjustment = StockAdjustment::create($validated);

                // 2️⃣ Fetch variant cost data
                $variantId = $validated['variant_id'];
                $lastPurchaseCost   = ProductVariant::find($variantId)->last_purchase_cost;

                // 3️⃣ Prepare common stock entry payload
                $baseData = [
                    'warehouse_id' => $validated['warehouse_id'] ?? null,
                    'variant_id'   => $variantId,
                    'reason'       => $validated['reason'],
                    'employee_id'  => $validated['employee_id'] ?? null,
                    'note'         => $validated['note'] ?? null,
                    'effective_at' => $validated['adjusted_at'],
                    'source_type'  => StockAdjustment::class,
                    'source_id'    => $adjustment->id,
                ];

                // 4️⃣ Handle stock direction precisely
                if ($validated['adjusted_quantity'] > 0) {
                    // 🔹 Increase in stock — Stock In
                    $inventoryService->stockIn([
                        ...$baseData,
                        'quantity'  => $validated['adjusted_quantity'],
                        'unit_cost' => $lastPurchaseCost, // could also use last purchase price if you prefer
                    ]);
                } else {
                    // 🔹 Decrease in stock — Stock Out
                    $inventoryService->stockOut([
                        ...$baseData,
                        'quantity'  => abs($validated['adjusted_quantity']),
                    ]);
                }
            });

            return redirect()
                ->route('admin.stock-adjustments.index')
                ->with('success', 'Stock adjustment recorded and inventory updated successfully.');

        } catch (Throwable $e) {
            report($e);

            return back()
                ->withInput()
                ->with('error', 'An error occurred while processing the stock adjustment: ' . $e->getMessage());
        }
    }


    public function show(StockAdjustment $stockAdjustment)
    {
        $stockAdjustment->load([
            'variant.product:id,name',
            'warehouse:id,name',
            'employee:id,name',
        ]);

        return Inertia::render('StockAdjustments/Show', [
            'adjustment' => [
                'id' => $stockAdjustment->id,
                'product_sku' => $stockAdjustment->variant->product->name . ' - ' . $stockAdjustment->variant->sku ?? 'N/A',
                'previous_quantity' => $stockAdjustment->previous_quantity,
                'adjusted_quantity' => $stockAdjustment->adjusted_quantity,
                'new_quantity' => $stockAdjustment->new_quantity,
                'reason' => $stockAdjustment->reason,
                'note' => $stockAdjustment->note,
                'warehouse' => $stockAdjustment->warehouse->name ?? 'N/A',
                'employee' => $stockAdjustment->employee->name ?? 'N/A',
                'created_at' => $stockAdjustment->created_at->format('Y-m-d H:i'),
            ],
        ]);
    }

    public function destroy(StockAdjustment $stockAdjustment)
    {
        $stockAdjustment->delete();

        return back()->with('success', 'Stock adjustment deleted successfully.');
    }
}
