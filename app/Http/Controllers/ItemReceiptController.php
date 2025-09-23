<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ItemReceipt\ItemReceiptStoreRequest;
use App\Models\PurchaseOrder;
use App\Services\Purchasing\PurchaseOrderService;
use Illuminate\Http\RedirectResponse;

class ItemReceiptController extends Controller
{
    public function __construct(private PurchaseOrderService $service) {}

    public function store(ItemReceiptStoreRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->service->processItemReceipt($purchaseOrder, $request->validated());
        return back()->with('success','Items received successfully.');
    }
}
