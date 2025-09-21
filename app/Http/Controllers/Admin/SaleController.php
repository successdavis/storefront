<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\RecordSaleRequest;
use App\Services\SaleService;
use Illuminate\Http\JsonResponse;

class SaleController extends Controller
{
    public function __construct(protected SaleService $saleService) {}

    public function store(RecordSaleRequest $request): JsonResponse
    {
        $sale = $this->saleService->recordSale($request->validated());
        return response()->json(['message' => 'Sale recorded', 'data' => $sale]);
    }
}

