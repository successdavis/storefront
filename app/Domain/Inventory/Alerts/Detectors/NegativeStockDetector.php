<?php
namespace App\Domain\Inventory\Alerts\Detectors;
use App\Domain\Inventory\Alerts\Contracts\InventoryDetector;
use App\Models\ProductVariant;

class NegativeStockDetector implements InventoryDetector
{
    public function detect(): iterable
    {
        return ProductVariant::where('quantity', '<', 0)->get();
    }
}
