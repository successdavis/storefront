<?php

namespace App\Http\Controllers;

use App\Domain\Inventory\Audit\StockAuditService;
use App\Domain\Inventory\Support\VariantNameFormatter;
use App\Http\Requests\Admin\StoreStockAuditRequest;
use App\Models\Category;
use App\Models\InventoryAlert;
use App\Models\StockAuditSession;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
            );
        }

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
            ? 'admin.inventory.stock-audit.mobile'
            : 'admin.inventory.stock-audit.index';

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
            );
        }

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
            'categories' => Category::query()
                ->orderBy('name')
                ->get(['id', 'name']),
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
        ]]);

        return response()->json([
            'ok' => true,
            'session' => data_get($summary, 'session'),
        ]);
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
}
