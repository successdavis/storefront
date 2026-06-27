<?php

namespace App\Http\Controllers;

use App\Domain\Inventory\Audit\StockAuditService;
use App\Domain\Inventory\Support\VariantNameFormatter;
use App\Http\Requests\Admin\StoreStockAuditRequest;
use App\Models\Category;
use App\Models\InventoryAlert;
use App\Models\StockAuditSession;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class StockAuditController extends Controller
{
    public function __construct(
        protected StockAuditService $stockAuditService,
        protected VariantNameFormatter $variantNameFormatter,
    ) {}

    public function index(Request $request): Response
    {
        $scopeType = $request->string('scope_type')->toString() ?: StockAuditSession::SCOPE_FULL;
        $categoryId = $request->filled('category_id') ? (int) $request->input('category_id') : null;
        $warehouseId = $request->filled('warehouse_id') ? (int) $request->input('warehouse_id') : null;
        $requestedSessionId = $request->filled('session_id') ? (int) $request->input('session_id') : null;

        $session = $requestedSessionId
            ? $this->stockAuditService->getSession($requestedSessionId)
            : null;

        if (!$session || $session->status !== StockAuditSession::STATUS_IN_PROGRESS) {
            if ($session) {
                $scopeType = $session->scope_type;
                $categoryId = $session->category_id;
                $warehouseId = $session->warehouse_id;
            }

            $session = $this->stockAuditService->findOrCreateInProgressSession(
                startedBy: auth()->id(),
                scopeType: $scopeType,
                categoryId: $categoryId,
                warehouseId: $warehouseId,
                source: StockAuditSession::SOURCE_MANUAL,
            );
        }

        $session = $this->stockAuditService->touchSessionActivity($session, StockAuditSession::SOURCE_MANUAL);
        $resumableSessions = $this->stockAuditService->resumableSessions(auth()->id(), $session->id);

        return Inertia::render('InventoryStockAudit', [
            'variants' => $this->stockAuditService->sessionRows($session)->all(),
            'warehouses' => Warehouse::query()
                ->where('active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'categories' => Category::query()
                ->orderBy('name')
                ->get(['id', 'name']),
            'session' => $this->stockAuditService->sessionSummary($session),
            'resumableSessions' => $resumableSessions,
            'resumeShortcut' => $resumableSessions->first(),
            'routes' => $this->auditRouteMap($request),
            'defaultAuditNote' => sprintf(
                'Physical stock check for %s',
                now()->format('F j, Y')
            ),
        ]);
    }

    public function store(StoreStockAuditRequest $request)
    {
        $validated = $request->validated();

        $summary = $this->stockAuditService->storeAudit(
            counts: $validated['counts'],
            warehouseId: $validated['warehouse_id'] ?? null,
            employeeId: auth()->id(),
            note: $validated['note'] ?? null,
            sessionId: $validated['session_id'] ?? null,
            scopeType: $validated['scope_type'] ?? StockAuditSession::SCOPE_FULL,
            categoryId: $validated['category_id'] ?? null,
            submitAnyway: (bool) ($validated['submit_anyway'] ?? false),
            source: (string) ($validated['source'] ?? 'audit'),
        );
        $source = (string) ($validated['source'] ?? 'audit');

        $message = sprintf(
            'Audit complete. %d discrepancies detected, %d alerts raised.',
            count($summary['discrepancies']),
            (int) $summary['alerts_raised'],
        );

        if ((int) ($summary['missing_count'] ?? 0) > 0) {
            $message .= sprintf(
                ' %d items were not scanned and flagged as unknown state.',
                (int) $summary['missing_count'],
            );
        }

        $destination = $source === 'mobile'
            ? $this->auditRouteName($request, 'mobile')
            : $this->auditRouteName($request, 'index');

        return redirect()
            ->route($destination, [
                'session_id' => data_get($summary, 'session.id'),
            ])
            ->with('success', $message);
    }

    public function mobile(Request $request): Response
    {
        $scopeType = $request->string('scope_type')->toString() ?: StockAuditSession::SCOPE_FULL;
        $categoryId = $request->filled('category_id') ? (int) $request->input('category_id') : null;
        $warehouseId = $request->filled('warehouse_id') ? (int) $request->input('warehouse_id') : null;
        $requestedSessionId = $request->filled('session_id') ? (int) $request->input('session_id') : null;

        $session = $requestedSessionId
            ? $this->stockAuditService->getSession($requestedSessionId)
            : null;

        if (!$session || $session->status !== StockAuditSession::STATUS_IN_PROGRESS) {
            if ($session) {
                $scopeType = $session->scope_type;
                $categoryId = $session->category_id;
                $warehouseId = $session->warehouse_id;
            }

            $session = $this->stockAuditService->findOrCreateInProgressSession(
                startedBy: auth()->id(),
                scopeType: $scopeType,
                categoryId: $categoryId,
                warehouseId: $warehouseId,
                source: StockAuditSession::SOURCE_MOBILE,
            );
        }

        $session = $this->stockAuditService->touchSessionActivity($session, StockAuditSession::SOURCE_MOBILE);
        $resumableSessions = $this->stockAuditService->resumableSessions(auth()->id(), $session->id);
        $sessionRows = $this->stockAuditService->sessionRows($session);

        $sessionItems = $sessionRows
            ->filter(fn (array $row) => (bool) ($row['has_been_audited'] ?? false))
            ->map(fn (array $row): array => [
                'variant_id' => (int) $row['id'],
                'sku' => $row['sku'],
                'display_name' => $row['display_name'],
                'barcode' => $row['barcode'],
                'system_quantity' => (int) $row['system_quantity'],
                'physical_quantity' => (int) ($row['physical_quantity'] ?? $row['system_quantity']),
            ])
            ->values();

        return Inertia::render('InventoryStockAuditMobile', [
            'totalVariants' => (int) $session->total_expected_items,
            'session' => $this->stockAuditService->sessionSummary($session),
            'sessionItems' => $sessionItems,
            'routes' => $this->auditRouteMap($request),
            'categories' => Category::query()
                ->orderBy('name')
                ->get(['id', 'name']),
            'resumableSessions' => $resumableSessions,
            'resumeShortcut' => $resumableSessions->first(),
        ]);
    }

    public function lookupByBarcode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'barcode' => ['required', 'string'],
            'session_id' => ['nullable', 'integer', 'exists:stock_audit_sessions,id'],
        ]);

        $session = null;
        if (!empty($validated['session_id'])) {
            $session = $this->stockAuditService->getSession((int) $validated['session_id']);
        }

        $variant = $this->stockAuditService->findByBarcode($validated['barcode'], $session);

        if (!$variant) {
            return response()->json([
                'message' => $session
                    ? 'No product variant found for this barcode in the selected audit scope.'
                    : 'No product variant found for this barcode.',
            ], 404);
        }

        return response()->json($variant);
    }

    public function upsertItem(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_id' => ['required', 'integer', 'exists:stock_audit_sessions,id'],
            'variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'physical_quantity' => ['required', 'integer', 'min:0'],
            'source' => ['nullable', 'string', 'in:audit,mobile,manual,system'],
        ]);

        $session = $this->stockAuditService->getSession((int) $validated['session_id']);
        if (!$session || $session->status !== StockAuditSession::STATUS_IN_PROGRESS) {
            throw ValidationException::withMessages([
                'session_id' => 'The audit session is no longer in progress.',
            ]);
        }

        $summary = $this->stockAuditService->upsertSessionItems($session, [[
            'variant_id' => (int) $validated['variant_id'],
            'physical_quantity' => (int) $validated['physical_quantity'],
        ]], $validated['source'] ?? null);

        return response()->json([
            'ok' => true,
            'session' => data_get($summary, 'session'),
        ]);
    }

    public function sessions(Request $request): Response
    {
        return Inertia::render('InventoryAuditSessions', [
            'sessions' => $this->stockAuditService->resumableSessions(auth()->id()),
            'routes' => $this->auditRouteMap($request),
        ]);
    }

    public function history(Request $request): Response
    {
        $filters = [
            'status' => in_array($request->string('status')->toString(), ['in_progress', 'submitted', 'reviewed'], true)
                ? $request->string('status')->toString()
                : 'all',
            'source' => in_array($request->string('source')->toString(), ['manual', 'mobile', 'audit', 'system', 'unknown'], true)
                ? $request->string('source')->toString()
                : 'all',
            'scope' => in_array($request->string('scope')->toString(), ['full', 'category'], true)
                ? $request->string('scope')->toString()
                : 'all',
            'search' => trim($request->string('search')->toString()),
        ];

        $query = $this->auditHistoryQuery($filters)
            ->withCount([
                'items as discrepancy_count' => fn (Builder $query) => $query->where('variance', '!=', 0),
                'items as conflict_count' => fn (Builder $query) => $query->whereNotNull('conflict_reason'),
            ]);

        $summaryQuery = $this->auditHistoryQuery($filters);

        $sessions = $query
            ->orderByDesc('last_activity_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (StockAuditSession $session): array => $this->formatAuditHistorySession($request, $session));

        return Inertia::render('InventoryAuditHistory', [
            'sessions' => $sessions,
            'filters' => $filters,
            'summary' => [
                'total' => (clone $summaryQuery)->count(),
                'in_progress' => (clone $summaryQuery)->where('status', StockAuditSession::STATUS_IN_PROGRESS)->count(),
                'submitted' => (clone $summaryQuery)->where('status', StockAuditSession::STATUS_SUBMITTED)->count(),
                'reviewed' => (clone $summaryQuery)->where('status', StockAuditSession::STATUS_REVIEWED)->count(),
                'partial' => (clone $summaryQuery)->where('is_partial', true)->count(),
                'scanned_items' => (int) (clone $summaryQuery)->sum('total_scanned_items'),
                'expected_items' => (int) (clone $summaryQuery)->sum('total_expected_items'),
            ],
            'routes' => $this->auditRouteMap($request),
        ]);
    }

    public function discardSession(StockAuditSession $session): RedirectResponse
    {
        $this->stockAuditService->discardSession($session, (int) auth()->id());

        return back()->with('success', sprintf(
            'Audit session #%d was discarded.',
            $session->id,
        ));
    }

    public function discrepancies(Request $request): Response
    {
        $sessionFilter = $request->filled('session_id') ? (int) $request->input('session_id') : null;
        $sourceFilter = $request->string('source')->toString() ?: 'all';

        $alerts = InventoryAlert::query()
            ->with([
                'variant:id,product_id,sku,quantity,reserved',
                'variant.product:id,name',
                'variant.values:id,variant_type_id,value',
                'variant.values.type:id,name',
            ])
            ->where('status', 'open')
            ->whereNull('suppressed_at')
            ->where(function ($query): void {
                $query->whereNull('snoozed_until')
                    ->orWhere('snoozed_until', '<=', now());
            })
            ->whereIn('type', ['discrepancy', 'negative_stock'])
            ->orderByDesc('first_detected_at')
            ->get();

        $alerts = $alerts
            ->map(function (InventoryAlert $alert): array {
                $meta = is_array($alert->meta) ? $alert->meta : [];
                $systemQty = data_get($meta, 'system_quantity', $alert->variant?->quantity);
                $physicalQty = data_get($meta, 'physical_quantity');
                $variance = data_get($meta, 'variance');
                $source = (string) data_get($meta, 'source', 'system');
                $sessionId = data_get($meta, 'audit_session_id');

                if ($variance === null && $systemQty !== null && $physicalQty !== null) {
                    $variance = (int) $physicalQty - (int) $systemQty;
                }

                return [
                    'id' => (int) $alert->id,
                    'type' => $alert->type,
                    'severity' => $alert->severity,
                    'product' => $alert->variant ? $this->variantNameFormatter->format($alert->variant) : 'Unknown variant',
                    'sku' => $alert->variant?->sku,
                    'system_quantity' => $systemQty !== null ? (int) $systemQty : null,
                    'physical_quantity' => $physicalQty !== null ? (int) $physicalQty : null,
                    'variance' => $variance !== null ? (int) $variance : null,
                    'message' => $alert->message,
                    'status' => $alert->status,
                    'detected_at' => optional($alert->first_detected_at)->toDateTimeString(),
                    'adjustment_id' => data_get($meta, 'stock_adjustment_id'),
                    'session_id' => $sessionId !== null ? (int) $sessionId : null,
                    'source' => $source === 'audit' ? 'audit' : 'system',
                ];
            })
            ->when($sessionFilter, function ($collection) use ($sessionFilter) {
                return $collection->where('session_id', $sessionFilter);
            })
            ->when($sourceFilter === 'audit', function ($collection) {
                return $collection->where('source', 'audit');
            })
            ->when($sourceFilter === 'system', function ($collection) {
                return $collection->where('source', 'system');
            })
            ->values();

        $sessions = StockAuditSession::query()
            ->whereIn('status', [StockAuditSession::STATUS_SUBMITTED, StockAuditSession::STATUS_REVIEWED])
            ->orderByDesc('id')
            ->limit(100)
            ->get(['id', 'scope_type', 'coverage_percentage', 'submitted_at'])
            ->map(fn (StockAuditSession $session): array => [
                'id' => (int) $session->id,
                'label' => sprintf(
                    '#%d (%s, %s%%)',
                    $session->id,
                    ucfirst($session->scope_type),
                    number_format((float) $session->coverage_percentage, 2),
                ),
                'submitted_at' => optional($session->submitted_at)->toDateTimeString(),
            ])
            ->values();

        return Inertia::render('InventoryDiscrepancies', [
            'alerts' => $alerts,
            'sessions' => $sessions,
            'filters' => [
                'session_id' => $sessionFilter,
                'source' => $sourceFilter,
            ],
        ]);
    }

    public function resolveDiscrepancies(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'alert_ids' => ['required', 'array', 'min:1'],
            'alert_ids.*' => ['integer', 'exists:inventory_alerts,id'],
        ]);

        $resolved = InventoryAlert::query()
            ->whereIn('id', $validated['alert_ids'])
            ->where('status', 'open')
            ->whereNull('suppressed_at')
            ->where(function ($query): void {
                $query->whereNull('snoozed_until')
                    ->orWhere('snoozed_until', '<=', now());
            })
            ->whereIn('type', ['discrepancy', 'negative_stock'])
            ->update([
                'status' => 'resolved',
                'resolved_at' => now(),
                'resolved_by' => auth()->id(),
                'resolved_reason' => 'Resolved from discrepancy dashboard.',
            ]);

        $message = sprintf('%d discrepancy alert(s) resolved.', $resolved);

        return back()->with($resolved > 0 ? 'success' : 'warning', $message);
    }

    protected function auditRouteMap(Request $request): array
    {
        $prefix = $this->auditRoutePrefix($request);

        return [
            'index' => route($prefix . '.inventory.stock-audit.index'),
            'store' => route($prefix . '.inventory.stock-audit.store'),
            'mobile' => route($prefix . '.inventory.stock-audit.mobile'),
            'history' => route($prefix . '.inventory.stock-audit.history'),
            'sessions' => route($prefix . '.inventory.stock-audit.sessions'),
            'lookup' => route($prefix . '.inventory.stock-audit.lookup'),
            'upsert_item' => route($prefix . '.inventory.stock-audit.items.upsert'),
            'session_discard_base' => route($prefix . '.inventory.stock-audit.sessions.discard', ['session' => '__SESSION__']),
            'discrepancies' => Route::has($prefix . '.inventory.discrepancies')
                ? route($prefix . '.inventory.discrepancies')
                : null,
        ];
    }

    protected function auditHistoryQuery(array $filters): Builder
    {
        return StockAuditSession::query()
            ->with([
                'category:id,name',
                'warehouse:id,name',
                'starter:id,name',
                'submitter:id,name',
            ])
            ->when($filters['status'] !== 'all', fn (Builder $query) => $query->where('status', $filters['status']))
            ->when($filters['source'] !== 'all', function (Builder $query) use ($filters): void {
                if ($filters['source'] === 'unknown') {
                    $query->whereNull('source');

                    return;
                }

                $query->where('source', $filters['source']);
            })
            ->when($filters['scope'] !== 'all', fn (Builder $query) => $query->where('scope_type', $filters['scope']))
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $search = $filters['search'];
                $numericSearch = preg_replace('/\D+/', '', $search);

                $query->where(function (Builder $searchQuery) use ($search, $numericSearch): void {
                    if ($numericSearch !== '') {
                        $searchQuery->where('id', (int) $numericSearch);
                    }

                    $searchQuery
                        ->orWhereHas('category', fn (Builder $builder) => $builder->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('warehouse', fn (Builder $builder) => $builder->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('starter', fn (Builder $builder) => $builder->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('submitter', fn (Builder $builder) => $builder->where('name', 'like', "%{$search}%"));
                });
            });
    }

    protected function formatAuditHistorySession(Request $request, StockAuditSession $session): array
    {
        $prefix = $this->auditRoutePrefix($request);
        $expected = (int) $session->total_expected_items;
        $scanned = (int) $session->total_scanned_items;
        $missing = max($expected - $scanned, 0);
        $source = $session->source ?: 'unknown';

        return [
            'id' => (int) $session->id,
            'reference' => sprintf('AUD-%06d', $session->id),
            'status' => $session->status,
            'source' => $source,
            'source_label' => str($source)->replace('_', ' ')->title()->toString(),
            'scope_type' => $session->scope_type,
            'scope_label' => $session->scope_type === StockAuditSession::SCOPE_CATEGORY ? 'Category' : 'Full Inventory',
            'category_name' => $session->category?->name,
            'warehouse_name' => $session->warehouse?->name,
            'started_by_name' => $session->starter?->name,
            'submitted_by_name' => $session->submitter?->name,
            'started_at' => optional($session->started_at)->toDateTimeString(),
            'submitted_at' => optional($session->submitted_at)->toDateTimeString(),
            'last_activity_at' => optional($session->last_activity_at)->toDateTimeString(),
            'total_expected_items' => $expected,
            'total_scanned_items' => $scanned,
            'missing_items' => $missing,
            'coverage_percentage' => (float) $session->coverage_percentage,
            'is_partial' => (bool) $session->is_partial,
            'discrepancy_count' => (int) ($session->discrepancy_count ?? 0),
            'conflict_count' => (int) ($session->conflict_count ?? 0),
            'resume_manual_url' => $session->status === StockAuditSession::STATUS_IN_PROGRESS
                ? route($prefix . '.inventory.stock-audit.index', ['session_id' => $session->id])
                : null,
            'resume_mobile_url' => $session->status === StockAuditSession::STATUS_IN_PROGRESS
                ? route($prefix . '.inventory.stock-audit.mobile', ['session_id' => $session->id, 'ready' => 1])
                : null,
            'discrepancies_url' => Route::has($prefix . '.inventory.discrepancies') && $session->status !== StockAuditSession::STATUS_IN_PROGRESS
                ? route($prefix . '.inventory.discrepancies', ['session_id' => $session->id])
                : null,
        ];
    }

    protected function auditRouteName(Request $request, string $page): string
    {
        return $this->auditRoutePrefix($request) . '.inventory.stock-audit.' . $page;
    }

    protected function auditRoutePrefix(Request $request): string
    {
        return str_starts_with((string) $request->route()?->getName(), 'sales.')
            ? 'sales'
            : 'admin';
    }
}
