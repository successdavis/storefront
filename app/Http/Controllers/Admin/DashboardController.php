<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\KpiService;
use App\Services\Dashboard\SalesChartService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request, KpiService $service)
    {
        $range = [
            'type' => $request->get('range', 'today'),
        ];

        return Inertia::render('Dashboard', [
            'stats' => $service->get($range),

            'sales' => [
                ['online' => 40,  'inStore' => 20],
                ['online' => 80,  'inStore' => 120],
                ['online' => 60,  'inStore' => 90],
                ['online' => 140, 'inStore' => 160],
                ['online' => 70,  'inStore' => 130],
                ['online' => 120, 'inStore' => 150],
                ['online' => 60,  'inStore' => 90],
            ],

            /* -------------------------------
               RECENT TRANSACTIONS
            --------------------------------*/
            'transactions' => [
                [
                    'id'       => 'ORD-1024',
                    'source'   => 'POS Terminal #2',
                    'customer' => 'Walk-in',
                    'amount'   => 12550,
                    'status'   => 'Completed',
                ],
                [
                    'id'       => 'ORD-1025',
                    'source'   => 'POS Terminal #1',
                    'customer' => 'Walk-in',
                    'amount'   => 8950,
                    'status'   => 'Completed',
                ],
                [
                    'id'       => 'WEB-5501',
                    'source'   => 'Online Store',
                    'customer' => 'Sarah J.',
                    'amount'   => 8999,
                    'status'   => 'Processing',
                ],
                [
                    'id'       => 'WEB-5502',
                    'source'   => 'Online Store',
                    'customer' => 'Michael A.',
                    'amount'   => 15400,
                    'status'   => 'Completed',
                ],
                [
                    'id'       => 'ORD-1026',
                    'source'   => 'POS Terminal #3',
                    'customer' => 'Walk-in',
                    'amount'   => 9250,
                    'status'   => 'Failed',
                ],
            ],

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
}
