<?php

namespace App\Services;

use App\Models\StockEntry;

class StockService
{
    public function list()
    {
        return StockEntry::with('variant.product')->latest()->get();
    }

    public function record(array $data)
    {
        return StockEntry::create($data);
    }
}
