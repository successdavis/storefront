<?php
declare(strict_types=1);

namespace App\Services\Inventory;

interface InventoryServiceInterface
{
    /**
     * Increase stock for a specific product variant at a warehouse.
     * Should be idempotent for repeated receipts if the caller avoids double-processing.
     *
     * @param int $warehouseId
     * @param int $productVariantId
     * @param int $quantity
     * @return void
     */
    public function increaseStock(int $warehouseId, int $productVariantId, int $quantity): void;

    /**
     * Optionally reserve stock for a PO (if you implement reservation).
     */
    public function reserveStock(int $warehouseId, int $productVariantId, int $quantity): void;

    /**
     * Release a previous reservation.
     */
    public function releaseReservation(int $warehouseId, int $productVariantId, int $quantity): void;
}
