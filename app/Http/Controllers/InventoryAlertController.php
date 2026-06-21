<?php

namespace App\Http\Controllers;

use App\Domain\Inventory\Support\VariantNameFormatter;
use App\Models\InventoryAlert;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class InventoryAlertController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, VariantNameFormatter $variantNameFormatter)
    {
        $state = $request->string('state')->toString() ?: 'active';
        $type = $request->string('type')->toString();
        $severity = $request->string('severity')->toString();
        $search = trim($request->string('search')->toString());
        $state = in_array($state, ['active', 'snoozed', 'suppressed', 'resolved', 'all'], true)
            ? $state
            : 'active';

        $baseQuery = InventoryAlert::query()
            ->with([
                'variant:id,product_id,sku,quantity,reserved,replenishment_status,replenishment_note',
                'variant.product:id,name,is_active,deleted_at',
                'variant.values:id,variant_type_id,value',
                'variant.values.type:id,name',
                'acknowledgedBy:id,name',
                'snoozedBy:id,name',
                'suppressedBy:id,name',
                'resolvedBy:id,name',
            ]);

        $query = (clone $baseQuery)
            ->when($type !== '', fn (Builder $query) => $query->where('type', $type))
            ->when($severity !== '', fn (Builder $query) => $query->where('severity', $severity))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery
                        ->where('message', 'like', "%{$search}%")
                        ->orWhere('type', 'like', "%{$search}%")
                        ->orWhereHas('variant', fn (Builder $variantQuery) => $variantQuery
                            ->where('sku', 'like', "%{$search}%")
                            ->orWhereHas('product', fn (Builder $productQuery) => $productQuery
                                ->where('name', 'like', "%{$search}%")));
                });
            });

        $this->applyStateFilter($query, $state);

        $alerts = $query
            ->orderByRaw("CASE severity WHEN 'critical' THEN 4 WHEN 'high' THEN 3 WHEN 'medium' THEN 2 ELSE 1 END DESC")
            ->orderByDesc('first_detected_at')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (InventoryAlert $alert): array => $this->formatAlert($alert, $variantNameFormatter));

        return Inertia::render('Admin/InventoryAlerts/Index', [
            'alerts' => $alerts,
            'filters' => [
                'state' => $state,
                'type' => $type,
                'severity' => $severity,
                'search' => $search,
            ],
            'summary' => $this->summaryCounts($baseQuery),
            'typeOptions' => InventoryAlert::query()
                ->select('type')
                ->distinct()
                ->orderBy('type')
                ->pluck('type')
                ->map(fn (string $type): array => [
                    'value' => $type,
                    'label' => str($type)->replace('_', ' ')->title()->toString(),
                ])
                ->values(),
            'severityOptions' => ['critical', 'high', 'medium', 'low'],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(InventoryAlert $inventoryAlert)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(InventoryAlert $inventoryAlert)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, InventoryAlert $inventoryAlert)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(InventoryAlert $inventoryAlert)
    {
        //
    }

    public function close(InventoryAlert $alert)
    {
        $alert->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolved_by' => auth()->id(),
            'resolved_reason' => request('resolved_reason', 'Resolved from inventory alerts panel.'),
        ]);

        return back();
    }

    public function acknowledge(InventoryAlert $alert)
    {
        $alert->update([
            'status' => 'open',
            'acknowledged_at' => now(),
            'acknowledged_by' => auth()->id(),
        ]);

        return back();
    }

    public function snooze(Request $request, InventoryAlert $alert)
    {
        $validated = $request->validate([
            'snoozed_until' => ['required', 'date', 'after:now'],
            'snooze_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $alert->update([
            'status' => 'open',
            'snoozed_until' => $validated['snoozed_until'],
            'snoozed_by' => auth()->id(),
            'snooze_reason' => $validated['snooze_reason'] ?? null,
        ]);

        return back();
    }

    public function suppress(Request $request, InventoryAlert $alert)
    {
        $validated = $request->validate([
            'suppress_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $alert->update([
            'status' => 'open',
            'suppressed_at' => now(),
            'suppressed_by' => auth()->id(),
            'suppress_reason' => $validated['suppress_reason'] ?? null,
        ]);

        return back();
    }

    public function unsuppress(InventoryAlert $alert)
    {
        $alert->update([
            'suppressed_at' => null,
            'suppressed_by' => null,
            'suppress_reason' => null,
            'snoozed_until' => null,
            'snoozed_by' => null,
            'snooze_reason' => null,
        ]);

        return back();
    }

    public function bulk(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:inventory_alerts,id'],
            'action' => ['required', Rule::in(['acknowledge', 'snooze', 'suppress', 'resolve'])],
            'snoozed_until' => ['required_if:action,snooze', 'nullable', 'date', 'after:now'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $query = InventoryAlert::query()
            ->whereIn('id', $validated['ids'])
            ->where('status', 'open');

        $updated = match ($validated['action']) {
            'acknowledge' => $query->update([
                'acknowledged_at' => now(),
                'acknowledged_by' => auth()->id(),
            ]),
            'snooze' => $query->update([
                'snoozed_until' => $validated['snoozed_until'],
                'snoozed_by' => auth()->id(),
                'snooze_reason' => $validated['reason'] ?? null,
                'suppressed_at' => null,
                'suppressed_by' => null,
                'suppress_reason' => null,
            ]),
            'suppress' => $query->update([
                'suppressed_at' => now(),
                'suppressed_by' => auth()->id(),
                'suppress_reason' => $validated['reason'] ?? null,
                'snoozed_until' => null,
                'snoozed_by' => null,
                'snooze_reason' => null,
            ]),
            'resolve' => $query->update([
                'status' => 'resolved',
                'resolved_at' => now(),
                'resolved_by' => auth()->id(),
                'resolved_reason' => $validated['reason'] ?? 'Resolved from inventory alerts management page.',
            ]),
        };

        return back()->with('success', sprintf('%d inventory alert(s) updated.', $updated));
    }

    protected function applyStateFilter(Builder $query, string $state): void
    {
        match ($state) {
            'snoozed' => $query
                ->where('status', 'open')
                ->whereNull('suppressed_at')
                ->where('snoozed_until', '>', now()),
            'suppressed' => $query
                ->where('status', 'open')
                ->whereNotNull('suppressed_at'),
            'resolved' => $query->where('status', 'resolved'),
            'all' => null,
            default => $query
                ->where('status', 'open')
                ->whereNull('suppressed_at')
                ->where(function (Builder $query): void {
                    $query->whereNull('snoozed_until')
                        ->orWhere('snoozed_until', '<=', now());
                }),
        };
    }

    protected function summaryCounts(Builder $baseQuery): array
    {
        $activeQuery = (clone $baseQuery)
            ->where('status', 'open')
            ->whereNull('suppressed_at')
            ->where(function (Builder $query): void {
                $query->whereNull('snoozed_until')
                    ->orWhere('snoozed_until', '<=', now());
            });

        return [
            'active' => (clone $activeQuery)->count(),
            'critical' => (clone $activeQuery)->where('severity', 'critical')->count(),
            'snoozed' => (clone $baseQuery)
                ->where('status', 'open')
                ->whereNull('suppressed_at')
                ->where('snoozed_until', '>', now())
                ->count(),
            'suppressed' => (clone $baseQuery)
                ->where('status', 'open')
                ->whereNotNull('suppressed_at')
                ->count(),
            'resolved' => (clone $baseQuery)
                ->where('status', 'resolved')
                ->count(),
            'all' => (clone $baseQuery)->count(),
        ];
    }

    protected function formatAlert(InventoryAlert $alert, VariantNameFormatter $variantNameFormatter): array
    {
        $variant = $alert->variant;

        return [
            'id' => (int) $alert->id,
            'type' => $alert->type,
            'type_label' => str($alert->type)->replace('_', ' ')->title()->toString(),
            'severity' => $alert->severity,
            'status' => $alert->status,
            'state' => $this->stateLabel($alert),
            'message' => $alert->message,
            'product' => $variant ? $variantNameFormatter->format($variant) : 'Unknown variant',
            'sku' => $variant?->sku,
            'variant_id' => $variant?->id ? (int) $variant->id : null,
            'quantity' => $variant?->quantity !== null ? (int) $variant->quantity : null,
            'reserved' => $variant?->reserved !== null ? (int) $variant->reserved : null,
            'available' => $variant ? max((int) $variant->quantity - (int) ($variant->reserved ?? 0), 0) : null,
            'replenishment_status' => $variant?->replenishment_status ?? 'unknown',
            'replenishment_note' => $variant?->replenishment_note,
            'first_detected_at' => optional($alert->first_detected_at)->toDateTimeString(),
            'last_seen_at' => optional($alert->last_seen_at)->toDateTimeString(),
            'acknowledged_at' => optional($alert->acknowledged_at)->toDateTimeString(),
            'snoozed_until' => optional($alert->snoozed_until)->toDateTimeString(),
            'snooze_reason' => $alert->snooze_reason,
            'suppressed_at' => optional($alert->suppressed_at)->toDateTimeString(),
            'suppress_reason' => $alert->suppress_reason,
            'resolved_at' => optional($alert->resolved_at)->toDateTimeString(),
            'resolved_reason' => $alert->resolved_reason,
            'audit' => [
                'acknowledged' => $this->actorPayload($alert->acknowledgedBy, $alert->acknowledged_at),
                'snoozed' => $this->actorPayload($alert->snoozedBy, $alert->snoozed_until, 'until'),
                'suppressed' => $this->actorPayload($alert->suppressedBy, $alert->suppressed_at),
                'resolved' => $this->actorPayload($alert->resolvedBy, $alert->resolved_at),
            ],
            'meta' => $alert->meta ?? [],
        ];
    }

    protected function stateLabel(InventoryAlert $alert): string
    {
        if ($alert->status === 'resolved') {
            return 'resolved';
        }

        if ($alert->suppressed_at) {
            return 'suppressed';
        }

        if ($alert->snoozed_until && $alert->snoozed_until->isFuture()) {
            return 'snoozed';
        }

        return 'active';
    }

    protected function actorPayload($user, $at, string $dateLabel = 'at'): ?array
    {
        if (! $user && ! $at) {
            return null;
        }

        return [
            'name' => $user?->name ?? 'Unknown staff',
            'at' => optional($at)->toDateTimeString(),
            'date_label' => $dateLabel,
        ];
    }
}
