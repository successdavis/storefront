<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Inventory\Support\VariantNameFormatter;
use App\Http\Controllers\Controller;
use App\Models\InventoryAlert;
use App\Models\Order;
use App\Services\Dashboard\KpiService;
use App\Services\Dashboard\RecentTransactionService;
use App\Services\Dashboard\SalesChartService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(
        Request $request,
        KpiService $service,
        RecentTransactionService $recentTransaction,
        SalesChartService $chartservice,
        VariantNameFormatter $variantNameFormatter
    )
    {
        $range = [
            'type' => $request->get('range', 'today'),
        ];

        return Inertia::render('Dashboard', [
            'stats' => $service->get($range),

            'sales' => $chartservice->get($request->range ?? 'last_7_days'),

            'transactions' => $recentTransaction->get(),

            'inventoryAlerts' => $this->inventoryAlertPayload($variantNameFormatter),

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

    protected function inventoryAlertPayload(VariantNameFormatter $variantNameFormatter): array
    {
        $baseQuery = InventoryAlert::query()
            ->with([
                'variant:id,product_id,sku,quantity,reserved,replenishment_status,replenishment_note',
                'variant.product:id,name,is_active,deleted_at',
                'variant.values:id,variant_type_id,value',
                'variant.values.type:id,name',
            ])
            ->where('status', 'open');

        $activeQuery = (clone $baseQuery)
            ->whereNull('suppressed_at')
            ->where(function ($query): void {
                $query->whereNull('snoozed_until')
                    ->orWhere('snoozed_until', '<=', now());
            });

        $snoozedQuery = (clone $baseQuery)
            ->whereNull('suppressed_at')
            ->where('snoozed_until', '>', now());

        $suppressedQuery = (clone $baseQuery)
            ->whereNotNull('suppressed_at');

        $activeCount = (clone $activeQuery)->count();
        $criticalCount = (clone $activeQuery)->where('severity', 'critical')->count();
        $snoozedCount = (clone $snoozedQuery)->count();
        $suppressedCount = (clone $suppressedQuery)->count();

        $activeAlerts = $activeQuery
            ->orderByRaw("CASE severity WHEN 'critical' THEN 4 WHEN 'high' THEN 3 WHEN 'medium' THEN 2 ELSE 1 END DESC")
            ->orderByDesc('first_detected_at')
            ->limit(15)
            ->get();

        $snoozedAlerts = $snoozedQuery
            ->orderBy('snoozed_until')
            ->limit(10)
            ->get();

        $suppressedAlerts = $suppressedQuery
            ->orderByDesc('suppressed_at')
            ->limit(10)
            ->get();

        return [
            'active' => $activeAlerts
                ->map(fn (InventoryAlert $alert): array => $this->formatInventoryAlert($alert, $variantNameFormatter))
                ->values()
                ->all(),
            'snoozed' => $snoozedAlerts
                ->map(fn (InventoryAlert $alert): array => $this->formatInventoryAlert($alert, $variantNameFormatter))
                ->values()
                ->all(),
            'suppressed' => $suppressedAlerts
                ->map(fn (InventoryAlert $alert): array => $this->formatInventoryAlert($alert, $variantNameFormatter))
                ->values()
                ->all(),
            'summary' => [
                'active' => $activeCount,
                'critical' => $criticalCount,
                'snoozed' => $snoozedCount,
                'suppressed' => $suppressedCount,
            ],
        ];
    }

    protected function formatInventoryAlert(InventoryAlert $alert, VariantNameFormatter $variantNameFormatter): array
    {
        $variant = $alert->variant;

        return [
            'id' => (int) $alert->id,
            'type' => $alert->type,
            'type_label' => str($alert->type)->replace('_', ' ')->title()->toString(),
            'severity' => $alert->severity,
            'message' => $alert->message,
            'product' => $variant ? $variantNameFormatter->format($variant) : 'Unknown variant',
            'sku' => $variant?->sku,
            'variant_id' => $variant?->id ? (int) $variant->id : null,
            'replenishment_status' => $variant?->replenishment_status ?? 'unknown',
            'replenishment_note' => $variant?->replenishment_note,
            'first_detected_at' => optional($alert->first_detected_at)->toDateTimeString(),
            'last_seen_at' => optional($alert->last_seen_at)->toDateTimeString(),
            'acknowledged_at' => optional($alert->acknowledged_at)->toDateTimeString(),
            'snoozed_until' => optional($alert->snoozed_until)->toDateTimeString(),
            'snooze_reason' => $alert->snooze_reason,
            'suppressed_at' => optional($alert->suppressed_at)->toDateTimeString(),
            'suppress_reason' => $alert->suppress_reason,
            'meta' => $alert->meta ?? [],
        ];
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
