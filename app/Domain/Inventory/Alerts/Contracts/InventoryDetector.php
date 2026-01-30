<?php

namespace App\Domain\Inventory\Alerts\Contracts;

interface InventoryDetector
{
    public function detect(): iterable;
}
