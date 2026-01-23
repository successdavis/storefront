<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Dashboard\KpiService;
use App\Services\Dashboard\RecentTransactionService;
use App\Services\Dashboard\SalesChartService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request, KpiService $service, RecentTransactionService  $recentTransaction, SalesChartService $chartservice)
    {
        $range = [
            'type' => $request->get('range', 'today'),
        ];

        return Inertia::render('Dashboard', [
            'stats' => $service->get($range),

            'sales' => $chartservice->get($request->range ?? 'last_7_days'),

            'transactions' => $recentTransaction->get(),

            /* -------------------------------
               INVENTORY ALERTS
            --------------------------------*/
            'inventoryAlerts' => [
                "Low Stock: Premium Coffee Beans (5 units left)",
                "Out of Stock: Ceramic Mugs – Blue",
                "Reorder Soon: Gift Boxes",
                "Low Stock: Laptop Chargers (8 units left)",
            ],

            /* -------------------------------
               POS TERMINAL STATUS
            --------------------------------*/
            'terminals' => [
                [
                    'name'   => 'Terminal #1',
                    'status' => 'Active',
                ],
                [
                    'name'   => 'Terminal #2',
                    'status' => 'Active',
                ],
                [
                    'name'   => 'Terminal #3',
                    'status' => 'Offline (Maintenance)',
                ],
            ],
        ]);
    }

    public function kpis(Request $request, KpiService $service)
    {
        $range = [
            'type' => $request->get('range', 'today'),
        ];

        return response()->json(
            $service->get($range)
        );
    }

    public function salesChart(Request $request, SalesChartService $service)
    {
        return response()->json(
            $service->get($request->range ?? 'last_7_days')
        );
    }

    public function getRecentTransactions(int $limit = 10, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $from ??= now()->subDays(7)->startOfDay();
        $to   ??= now()->endOfDay();

        $orders = Order::query()
            ->with([
                'user:id,name',
                'sales.posTerminal:id,name',
                'sales.customer:id,name',
                'payments' => function ($q) {
                    $q->where('type', 'inflow')->latest();
                }
            ])
            ->whereBetween('created_at', [$from, $to])
            ->latest()
            ->limit($limit)
            ->get();

        return $orders->map(function (Order $order) {
            $payment = $order->payments->first();

            return [
                'id'       => $order->order_number,
                'source'   => $this->resolveSource($order),
                'customer' => $this->resolveCustomer($order),
                'amount'   => (float) ($payment?->amount ?? $order->total_amount),
                'status'   => ucfirst($payment?->status ?? $order->status),
            ];
        })->toArray();
    }
}
