<?php

namespace App\Domain\Inventory\Alerts;

use App\Domain\Inventory\Support\VariantNameFormatter;
use App\Models\Category;
use App\Models\InventoryAlert;
use App\Models\StockAdjustment;
use App\Models\StockAuditSession;
use App\Services\ProductService;
use App\Support\SearchTerms;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Filtering, facets and presentation for the inventory discrepancy dashboard:
 * open audit count mismatches, items left unscanned by an audit, system
 * ledger mismatches and negative stock.
 */
class DiscrepancyDashboard
{
    public const ALERT_TYPES = ['discrepancy', 'negative_stock'];

    public const KIND_MISMATCH = 'mismatch';
    public const KIND_MISSING = 'missing';
    public const KIND_NEGATIVE = 'negative';
    public const KIND_LEDGER = 'ledger';

    public const KINDS = [self::KIND_MISMATCH, self::KIND_MISSING, self::KIND_NEGATIVE, self::KIND_LEDGER];

    public const SEVERITIES = ['critical', 'high', 'medium', 'low'];

    public const SOURCES = ['audit', 'system'];

    public const SORTS = ['newest', 'oldest', 'variance', 'severity'];

    public const PER_PAGE = 50;

    public const RESOLVED_REASON = 'Resolved from discrepancy dashboard.';

    public function __construct(
        protected VariantNameFormatter $variantNameFormatter,
        protected ProductService $productService,
    ) {}

    /**
     * @return array{search: string, session_id: ?int, category_id: ?int, issue: string, severity: string, source: string, from: ?string, to: ?string, tz: ?string, sort: string}
     */
    public function normalizeFilters(array $input): array
    {
        $string = fn (string $key): string => is_scalar($input[$key] ?? null) ? trim((string) $input[$key]) : '';
        $choice = fn (string $key, array $allowed, string $default): string => in_array($string($key), $allowed, true)
            ? $string($key)
            : $default;
        $id = function (string $key) use ($string): ?int {
            $value = filter_var($string($key), FILTER_VALIDATE_INT);

            return $value !== false && $value > 0 ? $value : null;
        };
        $date = function (string $key) use ($string): ?string {
            $value = $string($key);

            return preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts)
                && checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])
                ? $value
                : null;
        };

        $filters = [
            'search' => mb_substr($string('search'), 0, 120),
            'session_id' => $id('session_id'),
            'category_id' => $id('category_id'),
            'issue' => $choice('issue', self::KINDS, 'all'),
            'severity' => $choice('severity', self::SEVERITIES, 'all'),
            'source' => $choice('source', self::SOURCES, 'all'),
            'from' => $date('from'),
            'to' => $date('to'),
            // The browser's zone, so a "detected on" day matches the admin's calendar.
            'tz' => in_array($string('tz'), timezone_identifiers_list(), true) ? $string('tz') : null,
            'sort' => $choice('sort', self::SORTS, 'newest'),
        ];

        if ($filters['from'] && $filters['to'] && $filters['from'] > $filters['to']) {
            [$filters['from'], $filters['to']] = [$filters['to'], $filters['from']];
        }

        return $filters;
    }

    public function openQuery(): Builder
    {
        return InventoryAlert::query()
            ->where('status', 'open')
            ->whereNull('suppressed_at')
            ->where(fn (Builder $query) => $query
                ->whereNull('snoozed_until')
                ->orWhere('snoozed_until', '<=', now()))
            ->whereIn('type', self::ALERT_TYPES);
    }

    public function filteredQuery(array $filters): Builder
    {
        $query = $this->openQuery();

        if ($filters['session_id']) {
            $query->where('meta->audit_session_id', $filters['session_id']);
        }

        // Audit alerts carry the session that raised them; meta.source holds the
        // audit channel (manual/mobile), so it cannot tell audits from scans.
        if ($filters['source'] === 'audit') {
            $query->whereNotNull('meta->audit_session_id');
        } elseif ($filters['source'] === 'system') {
            $query->whereNull('meta->audit_session_id');
        }

        match ($filters['issue']) {
            self::KIND_NEGATIVE => $query->where('type', 'negative_stock'),
            self::KIND_MISSING => $query->where('type', 'discrepancy')->where('meta->missing_item', true),
            self::KIND_LEDGER => $query->where('type', 'discrepancy')
                ->whereNull('meta->missing_item')
                ->whereNotNull('meta->ledger_quantity'),
            self::KIND_MISMATCH => $query->where('type', 'discrepancy')
                ->whereNull('meta->missing_item')
                ->whereNull('meta->ledger_quantity'),
            default => null,
        };

        if ($filters['severity'] !== 'all') {
            $query->where('severity', $filters['severity']);
        }

        // Exact category match, the same rule category-scoped audits use.
        if ($filters['category_id']) {
            $query->whereHas('variant.product.categories', fn (Builder $categoryQuery) => $categoryQuery
                ->where('categories.id', $filters['category_id']));
        }

        if ($filters['search'] !== '') {
            $termGroups = SearchTerms::groups($filters['search']);

            $query->whereHas('variant', function (Builder $variantQuery) use ($termGroups): void {
                foreach ($termGroups as $expansions) {
                    $variantQuery->where(function (Builder $termQuery) use ($expansions): void {
                        foreach ($expansions as $expansion) {
                            $pattern = "%{$expansion}%";
                            $termQuery
                                ->orWhere('sku', 'like', $pattern)
                                ->orWhere('barcode', 'like', $pattern)
                                ->orWhereHas('product', fn (Builder $productQuery) => $productQuery->where('name', 'like', $pattern))
                                ->orWhereHas('values', fn (Builder $valueQuery) => $valueQuery->where('variant_values.value', 'like', $pattern));
                        }
                    });
                }
            });
        }

        $appTimezone = config('app.timezone');
        $filterTimezone = $filters['tz'] ?? $appTimezone;

        if ($filters['from']) {
            $query->where(
                'first_detected_at',
                '>=',
                CarbonImmutable::parse($filters['from'], $filterTimezone)->startOfDay()->setTimezone($appTimezone)
            );
        }

        if ($filters['to']) {
            $query->where(
                'first_detected_at',
                '<=',
                CarbonImmutable::parse($filters['to'], $filterTimezone)->endOfDay()->setTimezone($appTimezone)
            );
        }

        return $query;
    }

    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = $this->filteredQuery($filters)->with([
            'variant:id,product_id,sku,barcode,quantity',
            'variant.images',
            'variant.product:id,name,slug',
            'variant.product.images',
            'variant.values:id,variant_type_id,value',
            'variant.values.type:id,name',
        ]);

        $this->applySort($query, $filters['sort']);

        $paginator = $query->paginate(self::PER_PAGE)->withQueryString();
        $alerts = $paginator->getCollection();

        $sessions = StockAuditSession::query()
            ->with('category:id,name')
            ->whereIn('id', $alerts->map(fn (InventoryAlert $alert) => $this->sessionId($alert))->filter()->unique()->values())
            ->get()
            ->keyBy('id');

        $adjustments = StockAdjustment::query()
            ->whereIn('id', $alerts->map(fn (InventoryAlert $alert) => (int) data_get($alert->meta, 'stock_adjustment_id'))->filter()->unique()->values())
            ->get(['id', 'status'])
            ->keyBy('id');

        $paginator->setCollection($alerts->map(
            fn (InventoryAlert $alert): array => $this->formatAlert($alert, $sessions, $adjustments)
        ));

        return $paginator;
    }

    /**
     * Issue counts ignore the issue filter itself so the summary cards can act
     * as quick filters; everything else in the current filter set applies.
     */
    public function summary(array $filters): array
    {
        $alerts = $this->filteredQuery(array_merge($filters, ['issue' => 'all']))
            ->get(['id', 'type', 'meta']);

        $summary = [
            'total' => $alerts->count(),
            self::KIND_MISMATCH => 0,
            self::KIND_MISSING => 0,
            self::KIND_NEGATIVE => 0,
            self::KIND_LEDGER => 0,
            'shortage_units' => 0,
            'overage_units' => 0,
            'sessions' => $alerts->map(fn (InventoryAlert $alert) => $this->sessionId($alert))->filter()->unique()->count(),
            // Upper bound for "resolve all matching", so alerts raised after the
            // page was rendered are never resolved unseen.
            'matching_latest_id' => ((int) $this->filteredQuery($filters)->max('id')) ?: null,
        ];

        foreach ($alerts as $alert) {
            $kind = $this->kind($alert);
            $summary[$kind]++;

            $meta = $this->meta($alert);
            if ($kind === self::KIND_LEDGER || data_get($meta, 'physical_quantity') === null) {
                continue;
            }

            $variance = $this->variance($meta);
            if ($variance < 0) {
                $summary['shortage_units'] += abs($variance);
            } elseif ($variance > 0) {
                $summary['overage_units'] += $variance;
            }
        }

        return $summary;
    }

    /**
     * Session and category filter options, limited to what currently has open
     * alerts. The selected value is always included so its label resolves.
     *
     * @return array{sessions: array<int, array>, categories: array<int, array>}
     */
    public function facets(?int $selectedSessionId, ?int $selectedCategoryId): array
    {
        $alerts = $this->openQuery()->get(['id', 'variant_id', 'meta']);

        return [
            'sessions' => $this->sessionOptions($alerts, $selectedSessionId),
            'categories' => $this->categoryOptions($alerts, $selectedCategoryId),
        ];
    }

    public function resolve(Builder $query, ?int $resolvedBy): int
    {
        $resolved = 0;

        $query->select('inventory_alerts.id')->chunkById(500, function (Collection $alerts) use (&$resolved, $resolvedBy): void {
            $resolved += $this->openQuery()
                ->whereIn('id', $alerts->pluck('id'))
                ->update([
                    'status' => 'resolved',
                    'resolved_at' => now(),
                    'resolved_by' => $resolvedBy,
                    'resolved_reason' => self::RESOLVED_REASON,
                ]);
        });

        return $resolved;
    }

    public function kind(InventoryAlert $alert): string
    {
        $meta = $this->meta($alert);

        return match (true) {
            $alert->type === 'negative_stock' => self::KIND_NEGATIVE,
            (bool) data_get($meta, 'missing_item') => self::KIND_MISSING,
            data_get($meta, 'ledger_quantity') !== null => self::KIND_LEDGER,
            default => self::KIND_MISMATCH,
        };
    }

    public function formatSession(StockAuditSession $session, int $openCount): array
    {
        return [
            'id' => (int) $session->id,
            'reference' => $session->reference(),
            'status' => $session->status,
            'scope_type' => $session->scope_type,
            'scope_label' => $this->scopeLabel($session),
            'source_label' => $session->source ? ucfirst($session->source) : null,
            'submitted_at' => $session->submitted_at?->toIso8601String(),
            'submitted_by' => $session->submitter?->name,
            'coverage_percentage' => (float) $session->coverage_percentage,
            'total_expected_items' => (int) $session->total_expected_items,
            'total_scanned_items' => (int) $session->total_scanned_items,
            'is_partial' => (bool) $session->is_partial,
            'open_count' => $openCount,
            'history_url' => route('admin.inventory.stock-audit.history', ['search' => $session->reference()]),
        ];
    }

    protected function sessionOptions(Collection $alerts, ?int $selectedId): array
    {
        $openCounts = $alerts
            ->map(fn (InventoryAlert $alert) => $this->sessionId($alert))
            ->filter()
            ->countBy();

        $ids = $openCounts->keys()
            ->map(fn ($id) => (int) $id)
            ->when($selectedId, fn (Collection $ids) => $ids->push($selectedId))
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return StockAuditSession::query()
            ->with(['category:id,name', 'submitter:id,name'])
            ->whereIn('id', $ids)
            ->whereIn('status', [StockAuditSession::STATUS_SUBMITTED, StockAuditSession::STATUS_REVIEWED])
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (StockAuditSession $session): array => $this->formatSession(
                $session,
                (int) ($openCounts[$session->id] ?? 0)
            ))
            ->values()
            ->all();
    }

    protected function categoryOptions(Collection $alerts, ?int $selectedId): array
    {
        $alertsPerVariant = $alerts->countBy('variant_id');
        $openCounts = collect();

        if ($alertsPerVariant->isNotEmpty()) {
            $openCounts = DB::table('category_product')
                ->join('product_variants', 'product_variants.product_id', '=', 'category_product.product_id')
                ->whereIn('product_variants.id', $alertsPerVariant->keys()->all())
                ->distinct()
                ->get(['category_product.category_id', 'product_variants.id as variant_id'])
                ->groupBy('category_id')
                ->map(fn (Collection $rows): int => (int) $rows->sum(
                    fn (object $row): int => (int) ($alertsPerVariant[$row->variant_id] ?? 0)
                ));
        }

        $ids = $openCounts->keys()
            ->map(fn ($id) => (int) $id)
            ->when($selectedId, fn (Collection $ids) => $ids->push($selectedId))
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return Category::query()
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Category $category): array => [
                'id' => (int) $category->id,
                'name' => $category->name,
                'open_count' => (int) ($openCounts[$category->id] ?? 0),
            ])
            ->values()
            ->all();
    }

    protected function formatAlert(InventoryAlert $alert, Collection $sessions, Collection $adjustments): array
    {
        $meta = $this->meta($alert);
        $variant = $alert->variant;
        $kind = $this->kind($alert);
        $sessionId = $this->sessionId($alert);
        $session = $sessionId ? $sessions->get($sessionId) : null;
        $adjustmentId = (int) data_get($meta, 'stock_adjustment_id') ?: null;
        $auditChannel = (string) data_get($meta, 'source', '');
        $systemQuantity = data_get($meta, 'system_quantity', $variant?->quantity);
        $physicalQuantity = data_get($meta, 'physical_quantity');
        $ledgerQuantity = data_get($meta, 'ledger_quantity');

        return [
            'id' => (int) $alert->id,
            'kind' => $kind,
            'severity' => $alert->severity,
            'message' => $alert->message,
            'product' => $variant ? $this->variantNameFormatter->format($variant) : 'Unknown variant',
            'sku' => $variant?->sku,
            'image' => $variant?->product
                ? $this->productService->resolveProductImage($variant->product, $variant)
                : null,
            'product_url' => $variant?->product ? route('admin.products.show', $variant->product) : null,
            'system_quantity' => $systemQuantity !== null ? (int) $systemQuantity : null,
            'physical_quantity' => $physicalQuantity !== null ? (int) $physicalQuantity : null,
            'ledger_quantity' => $ledgerQuantity !== null ? (int) $ledgerQuantity : null,
            'variance' => $kind === self::KIND_MISSING ? null : $this->variance($meta, $variant?->quantity),
            'detected_at' => $alert->first_detected_at?->toIso8601String(),
            'source' => $sessionId ? 'audit' : 'system',
            'source_label' => match (true) {
                $sessionId === null => 'System scan',
                in_array($auditChannel, ['manual', 'mobile'], true) => ucfirst($auditChannel) . ' audit',
                default => 'Audit',
            },
            'session' => $sessionId ? [
                'id' => $sessionId,
                'reference' => StockAuditSession::referenceFor($sessionId),
                'scope_label' => $session ? $this->scopeLabel($session) : null,
                'submitted_at' => $session?->submitted_at?->toIso8601String(),
            ] : null,
            'adjustment' => $adjustmentId ? [
                'id' => $adjustmentId,
                'status' => $adjustments->get($adjustmentId)?->status,
                'url' => route('admin.stock-adjustments.show', $adjustmentId),
            ] : null,
        ];
    }

    protected function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            'oldest' => $query->orderBy('first_detected_at')->orderBy('id'),
            'severity' => $query
                ->orderByRaw("CASE severity WHEN 'critical' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END")
                ->orderByDesc('first_detected_at')
                ->orderByDesc('id'),
            'variance' => $query
                ->orderByRaw($this->absoluteVarianceExpression() . ' DESC')
                ->orderByDesc('first_detected_at')
                ->orderByDesc('id'),
            default => $query->orderByDesc('first_detected_at')->orderByDesc('id'),
        };
    }

    protected function absoluteVarianceExpression(): string
    {
        return InventoryAlert::query()->getConnection()->getDriverName() === 'sqlite'
            ? "ABS(CAST(json_extract(meta, '$.variance') AS INTEGER))"
            : "ABS(CAST(JSON_UNQUOTE(JSON_EXTRACT(meta, '$.variance')) AS SIGNED))";
    }

    protected function scopeLabel(StockAuditSession $session): string
    {
        return $session->scope_type === StockAuditSession::SCOPE_CATEGORY
            ? ($session->category?->name ?? 'Category audit')
            : 'Full inventory';
    }

    protected function variance(array $meta, ?int $fallbackSystemQuantity = null): ?int
    {
        $variance = data_get($meta, 'variance');
        if ($variance !== null) {
            return (int) $variance;
        }

        $systemQuantity = data_get($meta, 'system_quantity', $fallbackSystemQuantity);
        $physicalQuantity = data_get($meta, 'physical_quantity');

        return $systemQuantity !== null && $physicalQuantity !== null
            ? (int) $physicalQuantity - (int) $systemQuantity
            : null;
    }

    protected function sessionId(InventoryAlert $alert): ?int
    {
        return ((int) data_get($this->meta($alert), 'audit_session_id')) ?: null;
    }

    protected function meta(InventoryAlert $alert): array
    {
        return is_array($alert->meta) ? $alert->meta : [];
    }
}
