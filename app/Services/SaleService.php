<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use Illuminate\Support\Facades\DB;

class SaleService
{
    public function recordSale(array $data)
    {
        return DB::transaction(function () use ($data) {
            $sale = Sale::create([
                'employee_id' => $data['employee_id'] ?? null,
                'user_id' => $data['user_id'] ?? null,
                'pos_terminal_id' => $data['pos_terminal_id'] ?? null,
                'total_amount' => $data['total_amount'],
            ]);

            foreach ($data['items'] as $item) {
                SaleItem::create([
                    'sale_id' => $sale->id,
                    'variant_id' => $item['variant_id'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                ]);
            }

            foreach ($data['payments'] as $payment) {
                SalePayment::create([
                    'sale_id' => $sale->id,
                    'method' => $payment['method'],
                    'amount' => $payment['amount'],
                ]);
            }

            return $sale->load(['items.variant', 'payments']);
        });
    }
}
