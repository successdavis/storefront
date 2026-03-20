<?php

namespace App\Http\Controllers;

use App\Domain\Inventory\Audit\StockAuditService;
use App\Domain\Inventory\Support\VariantNameFormatter;
use App\Http\Requests\Admin\StoreStockAuditRequest;
use App\Models\InventoryAlert;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockAuditController extends Controller
{
    public function __construct(
        protected StockAuditService $stockAuditService,
        protected VariantNameFormatter $variantNameFormatter,
    ) {}

    public function index(): Response
    {
        return Inertia::render('InventoryStockAudit', [
            'variants' => $this->stockAuditService->auditRows()->all(),
            'warehouses' => Warehouse::query()
                ->where('active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'defaultAuditNote' => sprintf(
                'Physical stock check for %s',
                now()->format('F j, Y')
            ),
        ]);
    }

    public function store(StoreStockAuditRequest $request)
    {
        $validated = $request->validated();

        $summary = $this->stockAuditService->storeAudit(
            counts: $validated['counts'],
            warehouseId: $validated['warehouse_id'] ?? null,
            employeeId: auth()->id(),
            note: $validated['note'] ?? null,
        );

        return back()->with('success', sprintf(
            'Audit complete. %d discrepancies detected, %d alerts raised.',
            count($summary['discrepancies']),
            (int) $summary['alerts_raised'],
        ));
    }

    public function mobile(): Response
    {
        return Inertia::render('InventoryStockAuditMobile');
    }

    public function lookupByBarcode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'barcode' => ['required', 'string'],
        ]);

        $variant = $this->stockAuditService->findByBarcode($validated['barcode']);

        if (!$variant) {
            return response()->json([
                'message' => 'No product variant found for this barcode.',
            ], 404);
        }

        return response()->json($variant);
    }

    public function discrepancies(): Response
    {
        $alerts = InventoryAlert::query()
            ->with([
                'variant:id,product_id,sku,quantity,reserved',
                'variant.product:id,name',
                'variant.values:id,variant_type_id,value',
                'variant.values.type:id,name',
            ])
            ->where('status', 'open')
            ->whereIn('type', ['discrepancy', 'negative_stock'])
            ->orderByDesc('first_detected_at')
            ->get()
            ->map(function (InventoryAlert $alert): array {
                $meta = is_array($alert->meta) ? $alert->meta : [];
                $systemQty = data_get($meta, 'system_quantity', $alert->variant?->quantity);
                $physicalQty = data_get($meta, 'physical_quantity');
                $variance = data_get($meta, 'variance');

                if ($variance === null && $systemQty !== null && $physicalQty !== null) {
                    $variance = (int) $physicalQty - (int) $systemQty;
                }

                return [
                    'id' => (int) $alert->id,
                    'type' => $alert->type,
                    'severity' => $alert->severity,
                    'product' => $alert->variant ? $this->variantNameFormatter->format($alert->variant) : 'Unknown variant',
                    'sku' => $alert->variant?->sku,
                    'system_quantity' => $systemQty !== null ? (int) $systemQty : null,
                    'physical_quantity' => $physicalQty !== null ? (int) $physicalQty : null,
                    'variance' => $variance !== null ? (int) $variance : null,
                    'message' => $alert->message,
                    'status' => $alert->status,
                    'detected_at' => optional($alert->first_detected_at)->toDateTimeString(),
                    'adjustment_id' => data_get($meta, 'stock_adjustment_id'),
                ];
            })
            ->values();

        return Inertia::render('InventoryDiscrepancies', [
            'alerts' => $alerts,
        ]);
    }
}
