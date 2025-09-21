<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddToCartRequest;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;

class CartController extends Controller
{
    public function __construct(protected CartService $cartService) {}

    public function show(): JsonResponse
    {
        return response()->json($this->cartService->getActiveCart());
    }

    public function addItem(AddToCartRequest $request): JsonResponse
    {
        $item = $this->cartService->addItem($request->validated());
        return response()->json(['message' => 'Item added to cart', 'data' => $item]);
    }

    public function removeItem(int $itemId): JsonResponse
    {
        $this->cartService->removeItem($itemId);
        return response()->json(['message' => 'Item removed']);
    }
}
