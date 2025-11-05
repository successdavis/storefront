<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StorePurchaseOrderRequest;
use App\Http\Requests\UpdatePurchaseOrderRequest;
use App\Http\Resources\PurchaseOrderResource;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\Vendor;
use App\Models\Warehouse;
use App\Services\Purchasing\PurchaseOrderService;
use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use App\Enums\PurchaseOrderStatus;


class PurchaseOrderController extends Controller
{
    public function __construct(private PurchaseOrderService $service) {}

    public function index(Request $request)
    {
        // Validate filters
        $filters = $request->validate([
            'search'        => ['nullable', 'string', 'max:255'],
            'status'        => ['nullable', 'string', 'in:' . implode(',', array_column(PurchaseOrderStatus::cases(), 'value'))],
            'vendor_id'     => ['nullable', 'integer', 'exists:vendors,id'],
            'warehouse_id'  => ['nullable', 'integer', 'exists:warehouses,id'],
            'per_page'      => ['nullable', 'integer', 'in:10,25,50,100'],
        ]);

        $perPage = $filters['per_page'] ?? 10;

        $query = PurchaseOrder::query()
            ->with(['vendor:id,name', 'warehouse:id,name'])
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('po_number', 'like', "%{$search}%")
                        ->orWhereHas('vendor', fn ($v) => $v->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($filters['status'] ?? null, fn ($q, $status) =>
            $q->where('status', $status)
            )
            ->when($filters['vendor_id'] ?? null, fn ($q, $vendorId) =>
            $q->where('vendor_id', $vendorId)
            )
            ->when($filters['warehouse_id'] ?? null, fn ($q, $warehouseId) =>
            $q->where('warehouse_id', $warehouseId)
            )
            ->latest();

        $purchaseOrders = $query->paginate($perPage)->withQueryString();

        return Inertia::render('PurchaseOrders/Index', [
            'purchaseOrders' => $purchaseOrders,
            'filters'        => $filters,
            // ✅ Build statuses array from the enum
            'statuses'       => collect(PurchaseOrderStatus::cases())
                ->map(fn ($case) => [
                    'value' => $case->value,
                    'label' => $case->name, // or ucfirst($case->name)
                ]),
            'vendors'        => Vendor::select('id', 'name')->orderBy('name')->get(),
            'warehouses'     => Warehouse::select('id', 'name')->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        $productVariants = ProductVariant::with('product:id,name') // eager load product name only
        ->select('id', 'sku', 'product_id','last_purchase_price')                   // we need product_id to match the relation
        ->orderByRaw('LOWER(sku)')                            // or name if you prefer
        ->get()
            ->map(function ($variant) {
                return [
                    'id'   => $variant->id,
                    'sku'  => $variant->sku,
                    'name' => $variant->product->name, // use product name
                    'last_purchase_price' => $variant->last_purchase_price, // use product name
                ];
            });

        return Inertia::render('PurchaseOrders/Create', [
            'vendors'         => Vendor::select('id','name')->orderBy('name')->get(),
            'warehouses'      => Warehouse::select('id','name')->orderBy('name')->get(),
            'productVariants' => $productVariants,
        ]);
    }

    public function store(StorePurchaseOrderRequest $request): RedirectResponse
    {
        $po = $this->service->create($request->validated());
        return redirect()->route('admin.purchase-orders.show',$po)->with('success','Purchase order created.');
    }

    public function show(PurchaseOrder $purchase_order)
    {
        // Authorization here if you use policies
        // $this->authorize('view', $purchase_order);

        // Eager load relevant relations used by the view to avoid N+1
        $purchase_order->load([
            'vendor',
            'warehouse',
            'items.productVariant.product',      // productVariant->product if available
            'itemReceipts.items.productVariant',
            'vendorBills.payments',
        ]);

        return Inertia::render('PurchaseOrders/Show', [
            'purchaseOrder' => new PurchaseOrderResource($purchase_order),
            'warehouses'    => Warehouse::select('id','name')->get(),
        ]);
    }

    public function update(UpdatePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $po = $this->service->update($purchaseOrder, $request->validated());
        return redirect()->route('purchase-orders.show',$po)->with('success','Purchase order updated.');
    }

    public function destroy(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->service->cancel($purchaseOrder);
        return back()->with('success','Purchase order cancelled.');
    }

    public function send(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->service->send($purchaseOrder, $request->user()->name ?? null);
        return back()->with('success','Purchase order sent to vendor.');
    }

    public function close(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->service->close($purchaseOrder);
        return back()->with('success','Purchase order closed.');
    }

    public function itemReceiptsForBilling(PurchaseOrder $purchase_order)
    {
        // load items, receipts and vendor bills
        $purchase_order->load(['items.productVariant', 'itemReceipts.items', 'vendorBills.items']);

        // compute billed quantities per product_variant_id for this PO
        $billedByVariant = [];
        foreach ($purchase_order->vendorBills as $bill) {
            foreach ($bill->items as $bi) {
                $pv = $bi->product_variant_id;
                if (!$pv) continue;
                $billedByVariant[$pv] = ($billedByVariant[$pv] ?? 0) + (float)$bi->quantity;
            }
        }

        // compute received (sum of item_receipt_items grouped by purchase_order_item_id or product_variant)
        $receivedByPOItem = [];
        foreach ($purchase_order->itemReceipts as $r) {
            foreach ($r->items as $ri) {
                // prefer purchase_order_item_id if available
                if ($ri->purchase_order_item_id) {
                    $receivedByPOItem[$ri->purchase_order_item_id] = ($receivedByPOItem[$ri->purchase_order_item_id] ?? 0) + (float)$ri->quantity_received;
                } else {
                    // fallback group by variant for calculation convenience
                    $receivedByPOItem['variant_'.$ri->product_variant_id] = ($receivedByPOItem['variant_'.$ri->product_variant_id] ?? 0) + (float)$ri->quantity_received;
                }
            }
        }

        $items = [];
        foreach ($purchase_order->items as $poItem) {
            $variantId = $poItem->product_variant_id;
            $ordered = (int)$poItem->quantity_ordered;
            $received = (int)($poItem->quantity_received ?? ($receivedByPOItem[$poItem->id] ?? 0)); // migration already tracks quantity_received
            $billed = (float)($billedByVariant[$variantId] ?? 0);
            $remaining_ordered = max(0, $ordered - $billed);
            $billable_received = max(0, min($received - $billed, $remaining_ordered));
            $billable_unreceived = max(0, $remaining_ordered - $billable_received);

            $items[] = [
                'purchase_order_item_id' => $poItem->id,
                'product_variant_id' => $variantId,
                'sku' => optional($poItem->productVariant)->sku,
                'title' => optional($poItem->productVariant)->title,
                'ordered' => $ordered,
                'received' => $received,
                'billed' => $billed,
                'remaining_ordered' => $remaining_ordered,
                'billable_received' => $billable_received,
                'billable_unreceived' => $billable_unreceived,
                'unit_cost' => (float)$poItem->unit_cost,
                'line_total' => (float)$poItem->line_total,
            ];
        }

        // check if PO partially received (some items received but not all)
        $totalOrdered = 0;
        $totalReceived = 0;
        foreach ($purchase_order->items as $i) {
            $totalOrdered += $i->quantity_ordered;
            $totalReceived += $i->quantity_received;
        }
        $partialMessage = null;
        if ($totalReceived > 0 && $totalReceived < $totalOrdered) {
            $partialMessage = 'Part of these goods have been received. You may create a bill for received items only, or include unreceived items explicitly.';
        }

        return response()->json([
            'purchase_order' => [
                'id' => $purchase_order->id,
                'po_number' => $purchase_order->po_number,
                'vendor_id' => $purchase_order->vendor_id,
            ],
            'items' => $items,
            'partial_message' => $partialMessage,
        ]);
    }

    public function getItemReceipts(PurchaseOrder $purchase_order)
    {
        $receipts = $purchase_order->itemReceipts()
            ->with(['items' => function ($q) {
                $q->select(
                    'id',
                    'item_receipt_id',
                    'purchase_order_item_id',
                    'product_variant_id',
                    'quantity_received',
                    'unit_cost',
                    'line_total'
                )->with([
                    'productVariant:id,product_id,sku',
                    'productVariant.product:id,name'
                ]);
            }])
            ->where('status', 'completed')
            ->get(['id', 'receipt_number', 'received_date', 'status']);

        return response()->json([
            'purchase_order' => $purchase_order->only(['id', 'vendor_id', 'po_number']),
            'item_receipts'  => $receipts,
        ]);
    }
}
