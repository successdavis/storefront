<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\{VendorBill, PurchaseOrder};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class VendorBillService
{
    public function createFromPurchaseOrder(PurchaseOrder $po, array $data): VendorBill
    {
        if (! $po->allItemsFullyReceived()) {
            throw new RuntimeException('Cannot create bill until all items are received.');
        }

        return DB::transaction(function () use ($po, $data) {
            $bill = VendorBill::create([
                'purchase_order_id' => $po->id,
                'vendor_id'         => $po->vendor_id,
                'bill_number'       => $data['bill_number'],
                'bill_date'         => $data['bill_date'],
                'due_date'          => $data['due_date'] ?? null,
                'amount'            => $data['amount'] ?? $po->total_amount,
                'status'            => 'unpaid',
                'note'              => $data['note'] ?? null,
            ]);

            return $bill;
        });
    }

    public function markPaid(VendorBill $bill): VendorBill
    {
        return DB::transaction(function () use ($bill) {
            $bill->status = 'paid';
            $bill->paid_at = now();
            $bill->save();
            return $bill;
        });
    }
}
