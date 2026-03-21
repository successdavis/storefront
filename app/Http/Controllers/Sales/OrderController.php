<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Sale;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Order::class);

        $sales = Sale::query()
            ->with(['customer:id,name,email', 'order:id,order_number,status,total_amount,currency,created_at'])
            ->latest()
            ->paginate(15)
            ->through(fn (Sale $sale) => [
                'id' => (int) $sale->id,
                'customer' => [
                    'name' => $sale->customer?->name ?? 'Walk-in Customer',
                    'email' => $sale->customer?->email,
                ],
                'order' => [
                    'id' => (int) $sale->order?->id,
                    'order_number' => $sale->order?->order_number,
                    'status' => $sale->order?->status,
                    'total_amount' => (float) ($sale->order?->total_amount ?? $sale->total_amount),
                    'currency' => $sale->order?->currency ?? 'NGN',
                    'created_at' => optional($sale->order?->created_at ?? $sale->created_at)?->toIso8601String(),
                ],
            ]);

        return Inertia::render('Sales/Orders/Index', [
            'sales' => $sales,
        ]);
    }
}
