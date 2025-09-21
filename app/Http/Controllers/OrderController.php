<?php

namespace App\Http\Controllers;

use App\Services\OrderService;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    public function __construct(protected OrderService $orderService) {}

    public function placeOrder(): JsonResponse
    {
        $order = $this->orderService->placeOrder();
        return response()->json(['message' => 'Order placed', 'data' => $order], 201);
    }

    public function list(): JsonResponse
    {
        return response()->json($this->orderService->listUserOrders());
    }
}
