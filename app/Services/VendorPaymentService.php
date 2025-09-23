<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\{VendorPayment, VendorBill};
use Illuminate\Support\Facades\DB;

class VendorPaymentService
{
    public function payBill(VendorBill $bill, array $payload): VendorPayment
    {
        return DB::transaction(function () use ($bill, $payload) {
            $payment = VendorPayment::create([
                'vendor_bill_id' => $bill->id,
                'amount'         => $payload['amount'],
                'payment_date'   => $payload['payment_date'] ?? now(),
                'method'         => $payload['method'] ?? 'cash',
                'reference'      => $payload['reference'] ?? null,
                'note'           => $payload['note'] ?? null,
            ]);

            // if total payments >= bill amount mark bill paid
            $totalPaid = $bill->payments()->sum('amount') + $payment->amount;
            if ($totalPaid >= $bill->amount) {
                $bill->update(['status' => 'paid','paid_at' => now()]);
            }

            return $payment;
        });
    }
}
