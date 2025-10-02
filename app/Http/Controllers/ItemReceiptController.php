<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ItemReceiptStoreRequest;
use App\Models\ItemReceipt;
use App\Models\PurchaseOrder;
use App\Services\Purchasing\PurchaseOrderService;
use Illuminate\Http\RedirectResponse;

class ItemReceiptController extends Controller
{
    public function __construct(private PurchaseOrderService $service) {}

    public function store(ItemReceiptStoreRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        // Take all validated fields
        $data = $request->validated();

        // If the user/UI didn’t provide a receipt_number, generate one
        if (empty($data['receipt_number'])) {
            $data['receipt_number'] = ItemReceipt::nextReceiptNumber();
        }


        // Process the receipt with a guaranteed unique number
        $this->service->processItemReceipt($purchaseOrder, $data);

        return back()->with('success', 'Items received successfully.');
    }

    public function receiptsForBilling(PurchaseOrder $purchaseOrder): \Illuminate\Http\JsonResponse
    {
        // Fetch only receipts with at least one unbilled line
        $receipts = $purchaseOrder->itemReceipts()
            ->with(['items' => function ($query) {
                $query->whereDoesntHave('vendorBillItems'); // not yet fully billed
            }, 'items.product'])
            ->get()
            ->filter(fn ($receipt) => $receipt->items->isNotEmpty())
            ->values();

        // Transform into a compact structure for the Vue table
        $data = $receipts->map(function ($receipt) {
            return [
                'id'        => $receipt->id,
                'reference' => $receipt->reference_number ?? 'IR-' . $receipt->id,
                'date'      => $receipt->received_date->toDateString(),
                'items'     => $receipt->items->map(function ($item) {
                    return [
                        'id'           => $item->id,
                        'product_id'   => $item->product_id,
                        'product_name' => optional($item->product)->name,
                        'quantity'     => $item->quantity,
                        'unit_cost'    => $item->unit_cost,
                        'description'  => $item->description,
                    ];
                })->values(),
            ];
        });

        return response()->json($data);
    }
}
