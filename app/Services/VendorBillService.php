<?php

namespace App\Services;

use App\Services\Accounting\AccountingService;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\VendorBill;
use App\Models\VendorBillItem;
use App\Models\ProductVariant;
use App\Models\InventoryCostAdjustment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VendorBillService
{
    public function __construct(
        protected AccountingService $accountingService,
    ) {}

    public function create(array $data): VendorBill
    {
        $purchaseOrderId = $data['purchase_order_id'] ?? null;
        $purchaseOrder   = $purchaseOrderId
            ? PurchaseOrder::with(['items', 'vendorBills.items'])->findOrFail($purchaseOrderId)
            : null;

        $this->validateItemQuantities($data['items'], $purchaseOrder);

        return DB::transaction(function () use ($data, $purchaseOrder) {
            $bill = VendorBill::create([
                'vendor_id'        => $data['vendor_id'],
                'purchase_order_id'=> $purchaseOrder?->id,
                'bill_number'      => $this->generateBillNumber(),
                'bill_date'        => $data['bill_date'],
                'due_date'         => $data['due_date'] ?? null,
                'status'           => 'unpaid',
                'total_amount'     => 0,
            ]);

            $total = $this->createBillItems($bill, $data['items']);
            $total += $this->createBillExpenses($bill, $data['expenses'] ?? []);

            $bill->update(['total_amount' => $total]);
            $this->accountingService->postVendorBill($bill, auth()->id());

            return $bill;
        });
    }

    protected function validateItemQuantities(array $items, ?PurchaseOrder $purchaseOrder): void
    {
        if (!$purchaseOrder) return;

        $alreadyBilled = [];
        foreach ($purchaseOrder->vendorBills as $b) {
            foreach ($b->items as $bi) {
                if (!$bi->product_variant_id) continue;
                $alreadyBilled[$bi->product_variant_id] =
                    ($alreadyBilled[$bi->product_variant_id] ?? 0) + (float) $bi->quantity;
            }
        }

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

    protected function createBillItems(VendorBill $bill, array $items): float
    {
        $total = 0.0;

        foreach ($items as $it) {
            $qty       = (float) $it['quantity'];
            $billCost  = (float) $it['unit_cost'];
            $discount  = (float) ($it['discount_amount'] ?? 0);

            // ✅ Check if this PO item has been received
            $poItem = isset($it['purchase_order_item_id'])
                ? PurchaseOrderItem::find($it['purchase_order_item_id'])
                : null;

            $hasBeenReceived = $poItem && $poItem->quantity_received > 0;

            if (! $hasBeenReceived) {
                // 🚫 Skip this item entirely if it was never received
                continue;
            }

            $lineTotal = $qty * $billCost - $discount;

            // ✅ Only create bill item if received
            $billItem = $bill->items()->create([
                'purchase_order_item_id' => $it['purchase_order_item_id'] ?? null,
                'product_id'             => $it['product_id'] ?? null,
                'product_variant_id'     => $it['product_variant_id'] ?? null,
                'description'            => $this->composeDescription($it),
                'quantity'               => $qty,
                'unit_cost'              => $billCost,
                'discount_amount'        => $discount,
                'type'                   => $it['type'] ?? 'product',
            ]);

            // 🔑 Handle inventory cost adjustments
            if ($it['product_variant_id']) {
                $variant = ProductVariant::lockForUpdate()->find($it['product_variant_id']);
                if ($variant) {
                    $lastCost = (float) $variant->last_purchase_price ?? 0;

                    // Only adjust if bill cost differs from last recorded receipt cost
                    if ($lastCost > 0 && abs($billCost - $lastCost) > 0.0001) {
                        $difference = $billCost - $lastCost;
                        $adjustment = $difference * $qty;

                        InventoryCostAdjustment::create([
                            'product_variant_id'    => $variant->id,
                            'vendor_bill_id'        => $bill->id,
                            'purchase_order_item_id'=> $it['purchase_order_item_id'] ?? null,
                            'quantity'              => $qty,
                            'old_unit_cost'         => $lastCost,
                            'new_unit_cost'         => $billCost,
                            'difference_per_unit'   => $difference,
                            'total_adjustment'      => $adjustment,
                            'clearing_account'      => 'GRNI Clearing',
                            'notes'                 => 'Cost adjusted from receipt to bill price',
                        ]);
                    }

                    // Always update last purchase price to latest bill cost
                    $variant->last_purchase_price = $billCost;
                    $variant->save();
                }
            }

            $total += $lineTotal;
        }

        return $total;
    }


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

    protected function composeDescription(array $item): string
    {
        $desc = $item['description'] ?? '';
        if (!empty($item['purchase_order_item_id'])) {
            $desc .= " (PO Item #{$item['purchase_order_item_id']})";
        }
        return $desc;
    }

    protected function generateBillNumber(): string
    {
        $prefix = 'BILL';
        $next   = (VendorBill::max('id') ?? 0) + 1;
        return sprintf('%s-%06d', $prefix, $next);
    }
}
