<?php
declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Enums\PurchaseOrderStatus;
use App\Events\PurchaseOrderStatusChanged;
use App\Services\InventoryService;
use App\Models\{PurchaseOrder, PurchaseOrderItem, ItemReceipt, ItemReceiptItem, VendorBill, VendorPayment};
use App\Services\Inventory\InventoryServiceInterface;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;
use RuntimeException;
use Throwable;

class PurchaseOrderService
{
    public function __construct(
        protected InventoryService $inventoryService  // ✅ Inject the inventory service
    ) {}

    /**
     * Create a purchase order with items (transactional).
     *
     * $data keys:
     *  - vendor_id, warehouse_id, order_date, expected_date?, note?
     *  - items: [ { product_variant_id, quantity_ordered, unit_cost } , ... ]
     */
    public function create(array $data): PurchaseOrder
    {
        $this->validateCreatePayload($data);

        return DB::transaction(function () use ($data) {
            $po = PurchaseOrder::create([
                'vendor_id' => $data['vendor_id'],
                'warehouse_id' => $data['warehouse_id'],
                'po_number' => $this->generatePoNumber(),
                'order_date' => $data['order_date'],
                'expected_date' => $data['expected_date'] ?? null,
                'status' => PurchaseOrderStatus::DRAFT->value,
                'note' => $data['note'] ?? null,
                'total_amount' => 0,
            ]);

            $total = 0.0;
            foreach ($data['items'] as $i) {
                $lineTotal = $this->calcLineTotal((int)$i['quantity_ordered'], (float)$i['unit_cost']);
                $total += $lineTotal;

                $po->items()->create([
                    'product_variant_id' => $i['product_variant_id'],
                    'quantity_ordered' => (int)$i['quantity_ordered'],
                    'quantity_received' => 0,
                    'unit_cost' => $i['unit_cost'],
                    'line_total' => $lineTotal,
                ]);
            }

            $po->update(['total_amount' => $total]);

            return $po->load('items');
        });
    }

    /**
     * Update a PO and its items.
     * - will not allow changes if PO is closed/cancelled.
     * - syncs items (update existing by id OR create new) and deletes removed lines.
     */
    public function update(PurchaseOrder $po, array $data): PurchaseOrder
    {
        if (! $po->isEditable()) {
            throw new RuntimeException('Cannot modify a closed or cancelled purchase order.');
        }

        // Validate shape
        if (isset($data['items']) && ! is_array($data['items'])) {
            throw ValidationException::withMessages(['items' => 'Items must be an array']);
        }

        return DB::transaction(function () use ($po, $data) {
            // Lock the PO row to prevent concurrent changes
            $po = PurchaseOrder::where('id', $po->id)->lockForUpdate()->firstOrFail();

            // update header
            $updatable = array_filter([
                'vendor_id' => $data['vendor_id'] ?? null,
                'warehouse_id' => $data['warehouse_id'] ?? null,
                'order_date' => $data['order_date'] ?? null,
                'expected_date' => $data['expected_date'] ?? null,
                'note' => $data['note'] ?? null,
            ], fn($v) => $v !== null);

            if (! empty($updatable)) {
                $po->update($updatable);
            }

            // sync items if provided
            if (! empty($data['items'])) {
                $existing = $po->items()->get()->keyBy(fn($it) => (string)$it->id);
                $incomingIds = [];

                foreach ($data['items'] as $item) {
                    // either updating existing by id OR create new
                    if (! empty($item['id']) && isset($existing[(string)$item['id']])) {
                        $existingItem = $existing[(string)$item['id']];
                        $incomingIds[] = $existingItem->id;

                        // ensure we don't drop below already received qty
                        $newOrdered = (int)$item['quantity_ordered'];
                        if ($existingItem->quantity_received > $newOrdered) {
                            throw ValidationException::withMessages([
                                'items' => "Cannot set quantity_ordered {$newOrdered} lower than already received ({$existingItem->quantity_received}) for item id {$existingItem->id}."
                            ]);
                        }

                        $existingItem->update([
                            'quantity_ordered' => $newOrdered,
                            'unit_cost' => $item['unit_cost'],
                            'line_total' => $this->calcLineTotal($newOrdered, (float)$item['unit_cost']),
                        ]);
                    } else {
                        // new item
                        $created = $po->items()->create([
                            'product_variant_id' => $item['product_variant_id'],
                            'quantity_ordered' => (int)$item['quantity_ordered'],
                            'quantity_received' => 0,
                            'unit_cost' => $item['unit_cost'],
                            'line_total' => $this->calcLineTotal((int)$item['quantity_ordered'], (float)$item['unit_cost']),
                        ]);
                        $incomingIds[] = $created->id;
                    }
                }

                // Delete removed items (only safe if quantity_received == 0)
                $toDelete = $existing->filter(fn($it) => !in_array($it->id, $incomingIds, true));
                foreach ($toDelete as $delItem) {
                    if ($delItem->quantity_received > 0) {
                        throw ValidationException::withMessages([
                            'items' => "Cannot delete purchase order item ID {$delItem->id} because some quantity has already been received."
                        ]);
                    }
                    $delItem->delete();
                }
            }

            // recalc totals
            $this->recalculateTotals($po);

            return $po->refresh()->load('items');
        });
    }

    /**
     * Send PO to vendor (change status DRAFT -> SENT).
     */
    public function send(PurchaseOrder $po, ?string $sentBy = null): PurchaseOrder
    {
        return DB::transaction(function () use ($po, $sentBy) {
            $po = PurchaseOrder::where('id', $po->id)->lockForUpdate()->firstOrFail();
            $current = PurchaseOrderStatus::from($po->status);

            $target = PurchaseOrderStatus::SENT;
            if (! $current->canTransitionTo($target)) {
                throw new RuntimeException("Cannot transition PO from {$current->value} to {$target->value}");
            }

            $old = $po->status;
            $po->status = $target->value;
            $po->save();

            // Hook for sending email/notification. Implement your Notification or Mail logic.
            // event(new PurchaseOrderStatusChanged($po, $old, $po->status, "Sent by: {$sentBy}"));
            event(new PurchaseOrderStatusChanged($po, $old, $po->status, $sentBy));

            return $po->refresh();
        });
    }

    /**
     * Process an item receipt (full or partial). Idempotent by design if receipt_number is same.
     *
     * $payload:
     *  - receipt_number (string, unique)
     *  - warehouse_id
     *  - received_date
     *  - items: [ { purchase_order_item_id, product_variant_id, quantity_received, unit_cost } ... ]
     *
     * Returns created ItemReceipt model.
     */
    public function processItemReceipt(PurchaseOrder $po, array $payload): ItemReceipt
    {
        if (empty($payload['receipt_number']) || empty($payload['items']) || !is_array($payload['items'])) {
            throw ValidationException::withMessages(['receipt' => 'Invalid receipt payload']);
        }

        return DB::transaction(function () use ($po, $payload) {
            // idempotency
            $existing = ItemReceipt::where('receipt_number', $payload['receipt_number'])->first();
            if ($existing) {
                return $existing->load('items');
            }

            $po = PurchaseOrder::where('id', $po->id)->lockForUpdate()->firstOrFail();
            $po->load('items');

            $receipt = ItemReceipt::create([
                'purchase_order_id' => $po->id,
                'warehouse_id'      => $payload['warehouse_id'],
                'receipt_number'    => $payload['receipt_number'],
                'received_date'     => $payload['received_date'] ?? now()->toDateString(),
                'status'            => 'pending',
            ]);

            $poItemsById = $po->items->keyBy(fn($it) => (string)$it->id);

            foreach ($payload['items'] as $rItem) {
                $poItemId  = $rItem['purchase_order_item_id'] ?? null;
                $variantId = (int)$rItem['product_variant_id'];
                $qty       = (int)$rItem['quantity_received'];
                $unitCost  = (float)$rItem['unit_cost'];

                if ($qty <= 0) {
                    throw ValidationException::withMessages(['items' => 'quantity_received must be > 0']);
                }

                // Validate and update PO item quantities
                if ($poItemId && isset($poItemsById[(string)$poItemId])) {
                    $poItem = $poItemsById[(string)$poItemId];
                } else {
                    $poItem = $po->items->firstWhere('product_variant_id', $variantId);
                    if (! $poItem) {
                        throw ValidationException::withMessages([
                            'items' => "Received item variant {$variantId} not present on Purchase Order."
                        ]);
                    }
                }

                $allowable = $poItem->quantity_ordered - $poItem->quantity_received;
                if ($qty > $allowable) {
                    throw ValidationException::withMessages([
                        'items' => "Receiving quantity {$qty} exceeds outstanding ({$allowable}) for PO item {$poItem->id}."
                    ]);
                }

                $poItem->increment('quantity_received', $qty);

                // Record the receipt line
                ItemReceiptItem::create([
                    'item_receipt_id'       => $receipt->id,
                    'purchase_order_item_id'=> $poItem->id,
                    'product_variant_id'    => $variantId,
                    'quantity_received'     => $qty,
                    'unit_cost'             => $unitCost,
                    'line_total'            => round($qty * $unitCost, 2),
                ]);

                /**
                 * ✅ Actual inventory update using stockIn
                 */
                $this->inventoryService->stockIn([
                    'variant_id'  => $variantId,
                    'quantity'    => $qty,
                    'unit_cost'   => $unitCost,
                    'warehouse_id'=> $payload['warehouse_id'],
                    'source_type' => ItemReceipt::class,
                    'source_id'   => $receipt->id,
                    'reason'      => 'purchase_order_receipt',
                    'note'        => 'PO #' . $po->po_number,
                ]);
            }

            // Mark receipt completed
            $receipt->status = 'completed';
            $receipt->save();

            // Update PO totals and status
            $this->recalculateTotals($po);
            $po->status = $po->allItemsFullyReceived()
                ? PurchaseOrderStatus::RECEIVED->value
                : PurchaseOrderStatus::PARTIALLY_RECEIVED->value;
            $po->save();

            event(new PurchaseOrderStatusChanged($po, $po->getOriginal('status'), $po->status, 'Items received'));

            return $receipt->load('items');
        });
    }

    /**
     * Recalculate totals from PO items (line_total).
     */
    public function recalculateTotals(PurchaseOrder $po): PurchaseOrder
    {
        // Lock and recalc in a transaction
        return DB::transaction(function () use ($po) {
            $po = PurchaseOrder::where('id', $po->id)->lockForUpdate()->firstOrFail();
            $po->load('items');

            $total = 0.0;
            foreach ($po->items as $it) {
                // Ensure line_total is consistent with unit_cost * quantity_ordered
                $itLineTotal = $this->calcLineTotal($it->quantity_ordered, (float)$it->unit_cost);
                // If there is drift, fix it
                if ((float)$it->line_total !== (float)$itLineTotal) {
                    $it->line_total = $itLineTotal;
                    $it->save();
                }
                $total += (float)$it->line_total;
            }

            $po->total_amount = round($total, 2);
            $po->save();

            return $po->refresh();
        });
    }

    /**
     * Cancel a purchase order.
     * If $force=false and items already received, throws.
     */
    public function cancel(PurchaseOrder $po, bool $force = false, ?string $note = null): PurchaseOrder
    {
        return DB::transaction(function () use ($po, $force, $note) {
            $po = PurchaseOrder::where('id', $po->id)->lockForUpdate()->firstOrFail();

            if ($po->status === PurchaseOrderStatus::CANCELLED->value) {
                return $po;
            }

            if (! $force && $po->items()->where('quantity_received', '>', 0)->exists()) {
                throw new RuntimeException('Cannot cancel purchase order with received items without force=true.');
            }

            $old = $po->status;
            $po->status = PurchaseOrderStatus::CANCELLED->value;
            $po->note = $note ?? $po->note;
            $po->save();

            // release reservations if any (inventory service)
            foreach ($po->items as $it) {
                if ($it->quantity_ordered > 0) {
                    // if you implemented reservation, release here:
                    try {
                        $this->inventoryService->releaseReservation($po->warehouse_id, $it->product_variant_id, $it->quantity_ordered);
                    } catch (Throwable $e) {
                        // log but don't break cancellation
                    }
                }
            }

            event(new PurchaseOrderStatusChanged($po, $old, $po->status, $note));

            return $po;
        });
    }

    /**
     * Close a Purchase Order. Commonly used after everything received and bills settled.
     */
    public function close(PurchaseOrder $po, ?string $note = null): PurchaseOrder
    {
        return DB::transaction(function () use ($po, $note) {
            $po = PurchaseOrder::where('id', $po->id)->lockForUpdate()->firstOrFail();
            if (! $po->allItemsFullyReceived()) {
                throw new RuntimeException('Cannot close purchase order until all items are fully received.');
            }

            $old = $po->status;
            $po->status = PurchaseOrderStatus::CLOSED->value;
            $po->note = $note ?? $po->note;
            $po->save();

            event(new PurchaseOrderStatusChanged($po, $old, $po->status, $note));

            return $po->refresh();
        });
    }

    // -------------------------
    // Helpers & private methods
    // -------------------------

    protected function validateCreatePayload(array $data): void
    {
        if (empty($data['vendor_id']) || empty($data['warehouse_id']) || empty($data['order_date']) || empty($data['items']) || !is_array($data['items'])) {
            throw ValidationException::withMessages(['payload' => 'vendor_id, warehouse_id, order_date and items[] are required.']);
        }

        foreach ($data['items'] as $i) {
            if (empty($i['product_variant_id']) || empty($i['quantity_ordered']) || !isset($i['unit_cost'])) {
                throw ValidationException::withMessages(['items' => 'Each item must have product_variant_id, quantity_ordered and unit_cost']);
            }
            if ((int)$i['quantity_ordered'] <= 0) {
                throw ValidationException::withMessages(['items' => 'quantity_ordered must be > 0']);
            }
        }
    }

    protected function calcLineTotal(int $qty, float $unitCost): float
    {
        return round($qty * $unitCost, 2);
    }

    protected function generatePoNumber(): string
    {
        // Example: PO-20250922-0001
        $date = now()->format('Ymd');
        // Count today's POS to generate small sequence (race condition avoided by DB unique constraint on po_number)
        $countToday = PurchaseOrder::whereDate('created_at', now())->count() + 1;
        return sprintf('PO-%s-%04d', $date, $countToday);
    }
}
