<?php

namespace App\Services;

use App\Models\ProductVariant;
use App\Models\StockEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class InventoryService
{
    /**
     * Stock In (WAC Method).
     */
    public function stockIn(array $data): StockEntry
    {
        if (empty($data['variant_id']) || empty($data['quantity']) || !isset($data['unit_cost'])) {
            throw new InvalidArgumentException('variant_id, quantity and unit_cost are required for stock_in.');
        }

        return DB::transaction(function () use ($data) {
            $effectiveAt = isset($data['effective_at']) ? Carbon::parse($data['effective_at']) : Carbon::now();

            $variant = ProductVariant::lockForUpdate()->findOrFail($data['variant_id']);

            $qtyIn = (int) $data['quantity'];
            $unitCost = (float) $data['unit_cost'];
            $totalCostIn = $qtyIn * $unitCost;

            // Update running totals
            $variant->quantity += $qtyIn;
            $variant->total_cost_on_hand += $totalCostIn;
            $variant->average_cost = $variant->quantity > 0
                ? round($variant->total_cost_on_hand / $variant->quantity, 2)
                : 0.00;
            $variant->last_purchase_price = $unitCost;
            $variant->save();

            // Record entry
            return StockEntry::create([
                'warehouse_id'   => $data['warehouse_id'] ?? null,
                'variant_id'     => $variant->id,
                'quantity'       => $qtyIn,
                'unit_cost'      => $unitCost,
                'type'           => 'stock_in',
                'effective_at'   => $effectiveAt,
                'reason'         => $data['reason'] ?? null,
                'employee_id'    => $data['employee_id'] ?? null,
                'note'           => $data['note'] ?? null,
                'source_type'    => $data['source_type'] ?? null,
                'source_id'      => $data['source_id'] ?? null,
            ]);
        });
    }

    /**
     * Stock Out (WAC Method).
     */
    public function stockOut(array $data): StockEntry
    {
        if (empty($data['variant_id']) || empty($data['quantity'])) {
            throw new InvalidArgumentException('variant_id and quantity are required for stock_out.');
        }

        $qtyOut = (int) $data['quantity'];

        return DB::transaction(function () use ($data, $qtyOut) {
            $effectiveAt = isset($data['effective_at']) ? Carbon::parse($data['effective_at']) : Carbon::now();

            $variant = ProductVariant::lockForUpdate()->findOrFail($data['variant_id']);

            if ($variant->quantity < $qtyOut) {
                throw new \RuntimeException("Insufficient stock: requested $qtyOut, available {$variant->quantity}.");
            }

            $avgCost = (float) $variant->average_cost;
            $cogs = $qtyOut * $avgCost;

            // Reduce inventory totals
            $variant->quantity -= $qtyOut;
            $variant->total_cost_on_hand -= $cogs;

            if ($variant->quantity <= 0) {
                $variant->quantity = 0;
                $variant->total_cost_on_hand = 0;
                $variant->average_cost = 0;
            }

            $variant->save();

            // Record entry
            return StockEntry::create([
                'warehouse_id'   => $data['warehouse_id'] ?? null,
                'variant_id'     => $variant->id,
                'quantity'       => $qtyOut,
                'unit_cost'      => $avgCost,
                'type'           => 'stock_out',
                'effective_at'   => $effectiveAt,
                'reason'         => $data['reason'] ?? null,
                'employee_id'    => $data['employee_id'] ?? null,
                'note'           => $data['note'] ?? null,
                'source_type'    => $data['source_type'] ?? null,
                'source_id'      => $data['source_id'] ?? null,
            ]);
        });
    }

    /**
     * Get On-Hand Quantity.
     */
    public function getOnHandQuantity(int $variantId): int
    {
        return (int) ProductVariant::findOrFail($variantId)->quantity;
    }

    /**
     * Get Average Cost for Variant.
     */
    public function getAverageCost(int $variantId): float
    {
        return (float) ProductVariant::findOrFail($variantId)->average_cost;
    }
}
