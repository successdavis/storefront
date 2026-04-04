<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderManagementService;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function __construct(protected OrderManagementService $orderManagementService) {}

    public function index(): Response
    {
        $this->authorize('viewAny', Order::class);

        $orders = $this->orderManagementService
            ->listCustomerOrders(auth()->user(), 10)
            ->through(fn (Order $order) => $this->orderManagementService->toCustomerListPayload($order));

        return Inertia::render('Account/Orders/Index', [
            'orders' => $orders,
        ]);
    }

    public function show(Order $order): Response
    {
        $this->authorize('view', $order);

        return Inertia::render('Account/Orders/Show', [
            'order' => $this->orderManagementService->customerDetailPayload($order),
        ]);
    }
}
