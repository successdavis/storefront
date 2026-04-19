<?php

namespace App\Services\Analytics;

use App\Models\StorefrontAnalyticsDailyTotal;
use App\Models\StorefrontAnalyticsDailyVisitor;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class StorefrontAnalyticsReportService
{
    public function __construct(
        protected StorefrontAnalyticsAggregationService $aggregationService,
        protected StorefrontAnalyticsSettings $settings,
    ) {}

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function dashboard(array $filters = []): array
    {
        [$from, $to] = $this->resolveRange($filters);
        $trend = $this->resolveTrend($filters['trend'] ?? null, $from, $to);

        $this->aggregationService->ensureFresh();

        $totals = StorefrontAnalyticsDailyTotal::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get();
        $visitorQuery = StorefrontAnalyticsDailyVisitor::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()]);
        $rangeUniqueVisitors = (clone $visitorQuery)->distinct('visitor_key')->count('visitor_key');
        $newVisitors = (clone $visitorQuery)->where('is_new_visitor', true)->distinct('visitor_key')->count('visitor_key');
        $authenticatedVisitors = (clone $visitorQuery)->where('is_authenticated', true)->distinct('visitor_key')->count('visitor_key');
        $guestVisitors = (clone $visitorQuery)->where('is_authenticated', false)->distinct('visitor_key')->count('visitor_key');

        return [
            'filters' => [
                'range' => $filters['range'] ?? '30_days',
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'trend' => $trend,
            ],
            'date_presets' => [
                ['value' => 'today', 'label' => 'Today'],
                ['value' => '7_days', 'label' => 'Last 7 days'],
                ['value' => '30_days', 'label' => 'Last 30 days'],
                ['value' => '90_days', 'label' => 'Last 90 days'],
                ['value' => 'custom', 'label' => 'Custom range'],
            ],
            'trend_options' => [
                ['value' => 'daily', 'label' => 'Daily'],
                ['value' => 'weekly', 'label' => 'Weekly'],
                ['value' => 'monthly', 'label' => 'Monthly'],
            ],
            'summary_cards' => [
                ['key' => 'page_views', 'label' => 'Page views', 'value' => (int) $totals->sum('page_views')],
                ['key' => 'unique_visitors', 'label' => 'Unique visitors', 'value' => (int) $rangeUniqueVisitors],
                ['key' => 'new_visitors', 'label' => 'New visitors', 'value' => (int) $newVisitors],
                ['key' => 'returning_visitors', 'label' => 'Returning visitors', 'value' => max((int) $rangeUniqueVisitors - (int) $newVisitors, 0)],
                ['key' => 'authenticated_visitors', 'label' => 'Signed-in visitors', 'value' => (int) $authenticatedVisitors],
                ['key' => 'guest_visitors', 'label' => 'Guest visitors', 'value' => (int) $guestVisitors],
            ],
            'trend_chart' => $this->trendChart($from, $to, $trend),
            'top_pages' => $this->topPages($from, $to),
            'countries' => $this->geoBreakdown($from, $to, 'country'),
            'regions' => $this->geoBreakdown($from, $to, 'region'),
            'devices' => $this->deviceBreakdown($from, $to),
            'referrers' => $this->referrerBreakdown($from, $to),
            'settings' => [
                'enabled' => $this->settings->enabled(),
                'capture_referrers' => $this->settings->captureReferrers(),
                'track_authenticated_pages' => $this->settings->trackAuthenticatedPages(),
                'raw_retention_days' => $this->settings->rawRetentionDays(),
                'aggregation_refresh_window_days' => $this->settings->aggregationRefreshWindowDays(),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{filename:string,columns:list<string>,rows:list<array<int, string|int|float|null>>}
     */
    public function export(string $type, array $filters = []): array
    {
        [$from, $to] = $this->resolveRange($filters);
        $trend = $this->resolveTrend($filters['trend'] ?? null, $from, $to);
        $suffix = sprintf('%s-to-%s', $from->toDateString(), $to->toDateString());

        return match ($type) {
            'summary' => [
                'filename' => "storefront-analytics-summary-{$suffix}.csv",
                'columns' => ['Metric', 'Value'],
                'rows' => collect($this->dashboard($filters)['summary_cards'])
                    ->map(fn (array $card) => [$card['label'], $card['value']])
                    ->values()
                    ->all(),
            ],
            'trend' => [
                'filename' => "storefront-analytics-trend-{$suffix}.csv",
                'columns' => ['Period', 'Page Views', 'Unique Visitors'],
                'rows' => collect($this->trendRows($from, $to, $trend))
                    ->map(fn (array $row) => [$row['label'], $row['page_views'], $row['unique_visitors']])
                    ->values()
                    ->all(),
            ],
            'pages' => [
                'filename' => "storefront-analytics-pages-{$suffix}.csv",
                'columns' => ['Page Path', 'Page Title', 'Component', 'Page Views', 'Unique Visitors'],
                'rows' => collect($this->topPages($from, $to))
                    ->map(fn (array $row) => [$row['page_path'], $row['page_title'], $row['component'], $row['page_views'], $row['unique_visitors']])
                    ->values()
                    ->all(),
            ],
            'countries' => [
                'filename' => "storefront-analytics-countries-{$suffix}.csv",
                'columns' => ['Country', 'Country Code', 'Page Views', 'Unique Visitors'],
                'rows' => collect($this->geoBreakdown($from, $to, 'country'))
                    ->map(fn (array $row) => [$row['label'], $row['country_code'], $row['page_views'], $row['unique_visitors']])
                    ->values()
                    ->all(),
            ],
            'regions' => [
                'filename' => "storefront-analytics-regions-{$suffix}.csv",
                'columns' => ['Region', 'Page Views', 'Unique Visitors'],
                'rows' => collect($this->geoBreakdown($from, $to, 'region'))
                    ->map(fn (array $row) => [$row['label'], $row['page_views'], $row['unique_visitors']])
                    ->values()
                    ->all(),
            ],
            'devices' => [
                'filename' => "storefront-analytics-devices-{$suffix}.csv",
                'columns' => ['Device Type', 'Page Views', 'Unique Visitors'],
                'rows' => collect($this->deviceBreakdown($from, $to))
                    ->map(fn (array $row) => [$row['device_type'], $row['page_views'], $row['unique_visitors']])
                    ->values()
                    ->all(),
            ],
            default => [
                'filename' => "storefront-analytics-referrers-{$suffix}.csv",
                'columns' => ['Referrer Domain', 'Page Views', 'Unique Visitors'],
                'rows' => collect($this->referrerBreakdown($from, $to))
                    ->map(fn (array $row) => [$row['referrer_domain'], $row['page_views'], $row['unique_visitors']])
                    ->values()
                    ->all(),
            ],
        };
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    protected function resolveRange(array $filters): array
    {
        $range = (string) ($filters['range'] ?? '30_days');

        [$from, $to] = match ($range) {
            'today' => [now()->startOfDay(), now()->endOfDay()],
            '7_days' => [now()->subDays(6)->startOfDay(), now()->endOfDay()],
            '90_days' => [now()->subDays(89)->startOfDay(), now()->endOfDay()],
            'custom' => [
                Carbon::parse($filters['from'] ?? now()->subDays(29)->toDateString())->startOfDay(),
                Carbon::parse($filters['to'] ?? now()->toDateString())->endOfDay(),
            ],
            default => [now()->subDays(29)->startOfDay(), now()->endOfDay()],
        };

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return [$from, $to];
    }

    protected function resolveTrend(mixed $trend, CarbonInterface $from, CarbonInterface $to): string
    {
        if (in_array($trend, ['daily', 'weekly', 'monthly'], true)) {
            return $trend;
        }

        $days = $from->diffInDays($to) + 1;

        if ($days > 120) {
            return 'monthly';
        }

        if ($days > 45) {
            return 'weekly';
        }

        return 'daily';
    }

    /**
     * @return array<string, mixed>
     */
    protected function trendChart(CarbonInterface $from, CarbonInterface $to, string $trend): array
    {
        $rows = $this->trendRows($from, $to, $trend);

        return [
            'labels' => collect($rows)->pluck('label')->all(),
            'series' => [
                [
                    'name' => 'Page views',
                    'type' => 'line',
                    'smooth' => true,
                    'data' => collect($rows)->pluck('page_views')->all(),
                ],
                [
                    'name' => 'Unique visitors',
                    'type' => 'line',
                    'smooth' => true,
                    'data' => collect($rows)->pluck('unique_visitors')->all(),
                ],
            ],
        ];
    }

    /**
     * @return list<array{label:string,page_views:int,unique_visitors:int}>
     */
    protected function trendRows(CarbonInterface $from, CarbonInterface $to, string $trend): array
    {
        $rows = StorefrontAnalyticsDailyTotal::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('date')
            ->get(['date', 'page_views', 'unique_visitors']);
        $visitorRows = StorefrontAnalyticsDailyVisitor::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get(['date', 'visitor_key']);

        $grouped = match ($trend) {
            'weekly' => $rows->groupBy(fn ($row) => Carbon::parse($row->date)->startOfWeek()->toDateString()),
            'monthly' => $rows->groupBy(fn ($row) => Carbon::parse($row->date)->startOfMonth()->toDateString()),
            default => $rows->groupBy(fn ($row) => Carbon::parse($row->date)->toDateString()),
        };
        $visitorGrouped = match ($trend) {
            'weekly' => $visitorRows->groupBy(fn ($row) => Carbon::parse($row->date)->startOfWeek()->toDateString()),
            'monthly' => $visitorRows->groupBy(fn ($row) => Carbon::parse($row->date)->startOfMonth()->toDateString()),
            default => $visitorRows->groupBy(fn ($row) => Carbon::parse($row->date)->toDateString()),
        };

        $labels = [];
        $pageViews = [];
        $uniqueVisitors = [];

        foreach ($grouped as $bucket => $items) {
            $labels[] = $this->formatBucketLabel($bucket, $trend);
            $pageViews[] = (int) $items->sum('page_views');
            $uniqueVisitors[] = (int) ($visitorGrouped->get($bucket)?->pluck('visitor_key')->unique()->count() ?? 0);
        }

        return collect($labels)
            ->map(fn (string $label, int $index) => [
                'label' => $label,
                'page_views' => $pageViews[$index] ?? 0,
                'unique_visitors' => $uniqueVisitors[$index] ?? 0,
            ])
            ->all();
    }

    protected function formatBucketLabel(string $bucket, string $trend): string
    {
        $date = Carbon::parse($bucket);

        return match ($trend) {
            'weekly' => 'Week of '.$date->format('j M Y'),
            'monthly' => $date->format('M Y'),
            default => $date->format('j M'),
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function topPages(CarbonInterface $from, CarbonInterface $to): array
    {
        return DB::table('storefront_analytics_page_views')
            ->whereBetween('occurred_on', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('page_path, MAX(page_title) as page_title, MAX(component) as component')
            ->selectRaw('COUNT(*) as page_views')
            ->selectRaw('COUNT(DISTINCT visitor_key) as unique_visitors')
            ->groupBy('page_path')
            ->orderByDesc('page_views')
            ->limit(15)
            ->get()
            ->map(fn ($row) => [
                'page_path' => $row->page_path,
                'page_title' => $row->page_title ?: $row->page_path,
                'component' => $row->component,
                'page_views' => (int) $row->page_views,
                'unique_visitors' => (int) $row->unique_visitors,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function geoBreakdown(CarbonInterface $from, CarbonInterface $to, string $mode): array
    {
        $query = DB::table('storefront_analytics_page_views')
            ->whereBetween('occurred_on', [$from->toDateString(), $to->toDateString()]);

        if ($mode === 'country') {
            $query->selectRaw('country_code, MAX(country_name) as label, COUNT(*) as page_views, COUNT(DISTINCT visitor_key) as unique_visitors')
                ->whereNotNull('country_code')
                ->groupBy('country_code');
        } else {
            $query->selectRaw('region_name as label, COUNT(*) as page_views, COUNT(DISTINCT visitor_key) as unique_visitors')
                ->whereNotNull('region_name')
                ->groupBy('region_name');
        }

        return $query->orderByDesc('page_views')
            ->limit(15)
            ->get()
            ->map(fn ($row) => [
                'label' => $row->label ?: 'Unknown',
                'country_code' => $row->country_code ?? null,
                'page_views' => (int) $row->page_views,
                'unique_visitors' => (int) $row->unique_visitors,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function deviceBreakdown(CarbonInterface $from, CarbonInterface $to): array
    {
        return DB::table('storefront_analytics_page_views')
            ->whereBetween('occurred_on', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('device_type, COUNT(*) as page_views, COUNT(DISTINCT visitor_key) as unique_visitors')
            ->groupBy('device_type')
            ->orderByDesc('page_views')
            ->get()
            ->map(fn ($row) => [
                'device_type' => $row->device_type ?: 'unknown',
                'page_views' => (int) $row->page_views,
                'unique_visitors' => (int) $row->unique_visitors,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function referrerBreakdown(CarbonInterface $from, CarbonInterface $to): array
    {
        if (!$this->settings->captureReferrers()) {
            return [];
        }

        return DB::table('storefront_analytics_page_views')
            ->whereBetween('occurred_on', [$from->toDateString(), $to->toDateString()])
            ->whereNotNull('referrer_domain')
            ->selectRaw('referrer_domain, COUNT(*) as page_views, COUNT(DISTINCT visitor_key) as unique_visitors')
            ->groupBy('referrer_domain')
            ->orderByDesc('page_views')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'referrer_domain' => $row->referrer_domain,
                'page_views' => (int) $row->page_views,
                'unique_visitors' => (int) $row->unique_visitors,
            ])
            ->all();
    }
}
