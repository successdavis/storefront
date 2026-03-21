<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Sale;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $today = now()->startOfDay();

        return Inertia::render('Sales/Dashboard', [
            'stats' => [
                'sales_today' => (float) Sale::query()
                    ->where('created_at', '>=', $today)
                    ->sum('total_amount'),
                'orders_today' => Order::query()
                    ->where('channel', 'pos')
                    ->where('created_at', '>=', $today)
                    ->count(),
                'customers_today' => Sale::query()
                    ->where('created_at', '>=', $today)
                    ->distinct('customer_id')
                    ->count('customer_id'),
            ],
            'recentSales' => Sale::query()
                ->with(['customer:id,name', 'order:id,order_number,status'])
                ->latest()
                ->limit(10)
                ->get()
                ->map(fn (Sale $sale) => [
                    'id' => (int) $sale->id,
                    'total_amount' => (float) $sale->total_amount,
                    'customer_name' => $sale->customer?->name ?? 'Walk-in Customer',
                    'order_number' => $sale->order?->order_number,
                    'order_status' => $sale->order?->status,
                    'created_at' => optional($sale->created_at)?->toIso8601String(),
                ])
                ->values(),
        ]);
    }
}
