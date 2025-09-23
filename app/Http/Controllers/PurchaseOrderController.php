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
        ->select('id', 'sku', 'product_id')                   // we need product_id to match the relation
        ->orderByRaw('LOWER(sku)')                            // or name if you prefer
        ->get()
            ->map(function ($variant) {
                return [
                    'id'   => $variant->id,
                    'sku'  => $variant->sku,
                    'name' => $variant->product->name, // use product name
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
}
