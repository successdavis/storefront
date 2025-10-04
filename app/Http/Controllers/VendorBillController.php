<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVendorBillRequest;
use App\Models\PurchaseOrder;
use App\Services\VendorBillService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class VendorBillController extends Controller
{
    public function __construct(
        protected VendorBillService $vendorBillService
    ) {}

    public function store(StoreVendorBillRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            $bill = $this->vendorBillService->create($data);

            return response()->json([
                'message' => 'Vendor bill created successfully.',
                'bill_id' => $bill->id,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('Vendor bill creation failed: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Could not create vendor bill.'], 500);
        }
    }

    public function byPurchaseOrder(PurchaseOrder $order): JsonResponse
    {
        $bills = $order->vendorBills()
            ->select('id', 'bill_number', 'total_amount')
            ->whereIn('status', ['unpaid','partially_paid'])
            ->get()
            ->map(function ($bill) {
                return [
                    'id' => $bill->id,
                    'bill_number' => $bill->bill_number,
                    'total_amount' => $bill->total_amount,
                    'outstanding_balance' => $bill->outstandingBalance(),
                ];
            });

        return response()->json($bills);
    }

}
