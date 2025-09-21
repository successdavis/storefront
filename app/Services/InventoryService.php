<?php

namespace App\Services;

use App\Models\StockEntry;
use App\Models\StockLayer;
use App\Models\StockConsumption;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class InventoryService
{
    /**
     * Create a stock_in entry and corresponding layer.
     *
     * @param array $data
     *   expected keys:
     *     - variant_id (int)
     *     - quantity (int)
     *     - unit_cost (string|float)
     *     - warehouse_id (int|null)
     *     - effective_at (datetime|string) optional
     *     - reason (string|null)
     *     - track_inventory (bool) optional
     *     - employee_id (int|null)
     *     - source_type, source_id optional for morph
     *     - note (string|null)
     *
     * @return StockEntry
     */
    public function stockIn(array $data): StockEntry
    {
        if (empty($data['variant_id']) || empty($data['quantity']) || !isset($data['unit_cost'])) {
            throw new InvalidArgumentException('variant_id, quantity and unit_cost are required for stock_in.');
        }

        return DB::transaction(function () use ($data) {
            $effectiveAt = isset($data['effective_at']) ? Carbon::parse($data['effective_at']) : Carbon::now();

            $entry = StockEntry::create([
                'warehouse_id' => $data['warehouse_id'] ?? null,
                'variant_id' => $data['variant_id'],
                'quantity' => (int) $data['quantity'],
                'unit_cost' => $data['unit_cost'],
                'type' => 'stock_in',
                'effective_at' => $effectiveAt,
                'reason' => $data['reason'] ?? null,
                'track_inventory' => $data['track_inventory'] ?? true,
                'employee_id' => $data['employee_id'] ?? null,
                'note' => $data['note'] ?? null,
                'source_type' => $data['source_type'] ?? null,
                'source_id' => $data['source_id'] ?? null,
            ]);

            // create a stock layer referencing this stock_in entry
            StockLayer::create([
                'variant_id' => $entry->variant_id,
                'qty_remaining' => (int) $entry->quantity,
                'unit_cost' => $entry->unit_cost,
                'stock_entry_id' => $entry->id,
                'source_type' => $entry->source_type,
                'source_id' => $entry->source_id,
            ]);

            // 🔑 **Adjust product_variants.quantity**
            $variant = ProductVariant::lockForUpdate()->findOrFail($entry->variant_id);
            $variant->increment('quantity', $entry->quantity);

            return $entry->fresh();
        });
    }

    /**
     * Create a stock_out entry and consume layers FIFO.
     *
     * @param array $data
     *   expected keys:
     *     - variant_id (int)
     *     - quantity (int)
     *     - warehouse_id (int|null)
     *     - effective_at (datetime|string) optional
     *     - reason (string|null)
     *     - employee_id (int|null)
     *     - source_type, source_id optional for morph
     *     - note (string|null)
     *
     * @return StockEntry
     *
     * @throws \Exception
     */
    public function stockOut(array $data): StockEntry
    {
        if (empty($data['variant_id']) || empty($data['quantity'])) {
            throw new InvalidArgumentException('variant_id and quantity are required for stock_out.');
        }

        $quantityToConsume = (int) $data['quantity'];

        return DB::transaction(function () use ($data, $quantityToConsume) {
            $effectiveAt = isset($data['effective_at']) ? Carbon::parse($data['effective_at']) : Carbon::now();

            // create the stock out entry (unit_cost will be computed per consumed layer)
            $entry = StockEntry::create([
                'warehouse_id' => $data['warehouse_id'] ?? null,
                'variant_id' => $data['variant_id'],
                'quantity' => $quantityToConsume,
                'unit_cost' => 0, // placeholder; each consumption has its own cost
                'type' => 'stock_out',
                'effective_at' => $effectiveAt,
                'reason' => $data['reason'] ?? null,
                'track_inventory' => $data['track_inventory'] ?? true,
                'employee_id' => $data['employee_id'] ?? null,
                'note' => $data['note'] ?? null,
                'source_type' => $data['source_type'] ?? null,
                'source_id' => $data['source_id'] ?? null,
            ]);

            // consume FIFO layers
            $layers = StockLayer::where('variant_id', $data['variant_id'])
                ->where('qty_remaining', '>', 0)
                ->orderBy('created_at') // FIFO: oldest layers first
                ->lockForUpdate()
                ->get();

            if ($layers->sum('qty_remaining') < $quantityToConsume) {
                throw new \RuntimeException('Insufficient inventory to fulfill stock out (requested: ' . $quantityToConsume . ', available: ' . $layers->sum('qty_remaining') . ').');
            }

            $remaining = $quantityToConsume;

            foreach ($layers as $layer) {
                if ($remaining <= 0) break;

                $take = min($layer->qty_remaining, $remaining);

                // create consumption record
                StockConsumption::create([
                    'stock_entry_id' => $entry->id,
                    'stock_layer_id' => $layer->id,
                    'quantity' => $take,
                    'unit_cost' => $layer->unit_cost,
                ]);

                // decrement layer
                $layer->qty_remaining = $layer->qty_remaining - $take;
                $layer->save();

                $remaining -= $take;
            }

            // Optionally, compute and persist an average unit_cost for this stock_out entry
            $consumptions = StockConsumption::where('stock_entry_id', $entry->id)->get();
            $totalCost = $consumptions->reduce(function ($carry, $c) {
                return $carry + ($c->quantity * (float)$c->unit_cost);
            }, 0.0);

            $avgUnitCost = $quantityToConsume > 0 ? round($totalCost / $quantityToConsume, 2) : 0.00;

            $entry->unit_cost = $avgUnitCost;
            // For DB which computes total_cost via virtual column, no need to set; but we can set attribute for convenience
            $entry->save();

            // 🔑 **Adjust product_variants.quantity**
            $variant = ProductVariant::lockForUpdate()->findOrFail($entry->variant_id);
            $variant->decrement('quantity', $quantityToConsume);

            return $entry->fresh();
        });
    }

    /**
     * Return current FIFO layers for a variant (useful for reporting).
     */
    public function getLayers(int $variantId)
    {
        return StockLayer::where('variant_id', $variantId)
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Return current on-hand quantity for a variant.
     */
    public function getOnHandQuantity(int $variantId): int
    {
        return (int) StockLayer::where('variant_id', $variantId)->sum('qty_remaining');
    }
}
