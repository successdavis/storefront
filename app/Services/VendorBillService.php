<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\VendorBill;
use App\Models\VendorBillItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VendorBillService
{
    /**
     * Create a vendor bill with items and optional expenses.
     *
     * @param  array  $data
     * @return \App\Models\VendorBill
     *
     * @throws \Throwable|\Illuminate\Validation\ValidationException
     */
    public function create(array $data): VendorBill
    {
        // Locate purchase order if provided
        $purchaseOrderId = $data['purchase_order_id'] ?? null;
        $purchaseOrder   = $purchaseOrderId
            ? PurchaseOrder::with(['items', 'vendorBills.items'])->findOrFail($purchaseOrderId)
            : null;

        // Validate requested item quantities
        $this->validateItemQuantities($data['items'], $purchaseOrder);

        return DB::transaction(function () use ($data, $purchaseOrder) {
            $bill = VendorBill::create([
                'vendor_id'        => $data['vendor_id'],
                'purchase_order_id'=> $purchaseOrder?->id,
                'bill_number'      => $this->generateBillNumber(),
                'bill_date'        => $data['bill_date'],
                'due_date'         => $data['due_date'] ?? null,
                'status'           => 'unpaid',
                'total_amount'     => 0, // will update below
            ]);

            $total = $this->createBillItems($bill, $data['items']);
            $total += $this->createBillExpenses($bill, $data['expenses'] ?? []);

            $bill->update(['total_amount' => $total]);

            return $bill;
        });
    }

    /**
     * Validate each requested item against purchase order limits.
     */
    protected function validateItemQuantities(array $items, ?PurchaseOrder $purchaseOrder): void
    {
        if (!$purchaseOrder) {
            return;
        }

        // Precompute already billed quantities
        $alreadyBilled = [];
        foreach ($purchaseOrder->vendorBills as $b) {
            foreach ($b->items as $bi) {
                if (!$bi->product_variant_id) continue;
                $alreadyBilled[$bi->product_variant_id] =
                    ($alreadyBilled[$bi->product_variant_id] ?? 0) + (float) $bi->quantity;
            }
        }

        // Map PO items for quick access
        $poItemsById = $purchaseOrder->items->keyBy('id');

        foreach ($items as $it) {
            $qty     = (float) $it['quantity'];
            $variant = $it['product_variant_id'] ?? null;
            $poItemId = $it['purchase_order_item_id'] ?? null;

            if ($poItemId) {
                $poItem = $poItemsById->get($poItemId);
                if (!$poItem) {
                    throw ValidationException::withMessages([
                        'items' => "Invalid purchase order item ID {$poItemId}."
                    ]);
                }

                $maxAllowed = max(0, (float)$poItem->quantity_ordered - ($alreadyBilled[$poItem->product_variant_id] ?? 0));
                if ($qty > $maxAllowed + 0.0001) {
                    throw ValidationException::withMessages([
                        'items' => "Requested quantity for PO item {$poItemId} exceeds allowed billable quantity ({$maxAllowed})."
                    ]);
                }
            } elseif ($variant) {
                $poItem = $purchaseOrder->items->first(fn($x) => $x->product_variant_id == $variant);
                if ($poItem) {
                    $maxAllowed = max(0, (float)$poItem->quantity_ordered - ($alreadyBilled[$variant] ?? 0));
                    if ($qty > $maxAllowed + 0.0001) {
                        throw ValidationException::withMessages([
                            'items' => "Requested quantity for variant {$variant} exceeds allowed billable quantity ({$maxAllowed})."
                        ]);
                    }
                }
            }
        }
    }

    /**
     * Persist bill line items and return total.
     */
    protected function createBillItems(VendorBill $bill, array $items): float
    {
        $total = 0.0;

        foreach ($items as $it) {
            $qty       = (float) $it['quantity'];
            $unit      = (float) $it['unit_cost'];
            $discount  = (float) ($it['discount_amount'] ?? 0);
            $lineTotal = $qty * $unit - $discount;

            $bill->items()->create([
                'purchase_order_item_id' => $it['purchase_order_item_id'] ?? null,
                'product_id'             => $it['product_id'] ?? null,
                'product_variant_id'     => $it['product_variant_id'] ?? null,
                'description'            => $this->composeDescription($it),
                'quantity'               => $qty,
                'unit_cost'              => $unit,
                'discount_amount'        => $discount,
                'type'                   => $it['type'] ?? 'product',
            ]);

            $total += $lineTotal;
        }

        return $total;
    }

    /**
     * Save extra expenses as misc bill items and return their total.
     */
    protected function createBillExpenses(VendorBill $bill, array $expenses): float
    {
        $sum = 0.0;
        foreach ($expenses as $exp) {
            $amt = (float) $exp['amount'];
            $bill->items()->create([
                'description'     => $exp['description'],
                'quantity'        => 1,
                'unit_cost'       => $amt,
                'discount_amount' => 0,
                'type'            => 'misc',
            ]);
            $sum += $amt;
        }
        return $sum;
    }

    /**
     * Add optional PO reference to description if given.
     */
    protected function composeDescription(array $item): string
    {
        $desc = $item['description'];
        if (!empty($item['purchase_order_item_id'])) {
            $desc .= " (PO Item #{$item['purchase_order_item_id']})";
        }
        return $desc;
    }

    /**
     * Basic sequential bill numbering.
     */
    protected function generateBillNumber(): string
    {
        $prefix = 'BILL';
        $next   = (VendorBill::max('id') ?? 0) + 1;
        return sprintf('%s-%06d', $prefix, $next);
    }
}
