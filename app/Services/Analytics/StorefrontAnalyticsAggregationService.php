<?php

namespace App\Services\Analytics;

use App\Models\StorefrontAnalyticsDailyDevice;
use App\Models\StorefrontAnalyticsDailyGeo;
use App\Models\StorefrontAnalyticsDailyPage;
use App\Models\StorefrontAnalyticsDailyReferrer;
use App\Models\StorefrontAnalyticsDailyTotal;
use App\Models\StorefrontAnalyticsDailyVisitor;
use App\Models\StorefrontAnalyticsPageView;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class StorefrontAnalyticsAggregationService
{
    protected const FRESHNESS_CACHE_KEY = 'storefront_analytics.last_aggregated_at';

    public function __construct(
        protected StorefrontAnalyticsSettings $settings,
    ) {}

    public function refreshRecentWindow(?int $days = null): void
    {
        $days ??= $this->settings->aggregationRefreshWindowDays();
        $start = now()->subDays(max($days - 1, 0))->startOfDay();

        $this->rebuildRange($start, now()->endOfDay());
    }

    public function rebuildRange(CarbonInterface $start, CarbonInterface $end): void
    {
        $startDate = $start->toDateString();
        $endDate = $end->toDateString();

        DB::transaction(function () use ($startDate, $endDate) {
            StorefrontAnalyticsDailyVisitor::query()->whereBetween('date', [$startDate, $endDate])->delete();
            StorefrontAnalyticsDailyTotal::query()->whereBetween('date', [$startDate, $endDate])->delete();
            StorefrontAnalyticsDailyPage::query()->whereBetween('date', [$startDate, $endDate])->delete();
            StorefrontAnalyticsDailyGeo::query()->whereBetween('date', [$startDate, $endDate])->delete();
            StorefrontAnalyticsDailyDevice::query()->whereBetween('date', [$startDate, $endDate])->delete();
            StorefrontAnalyticsDailyReferrer::query()->whereBetween('date', [$startDate, $endDate])->delete();

            $dailyVisitors = DB::table('storefront_analytics_page_views')
                ->whereBetween('occurred_on', [$startDate, $endDate])
                ->selectRaw('occurred_on as date, visitor_key, MAX(is_authenticated) as is_authenticated, MAX(is_new_visitor) as is_new_visitor')
                ->groupBy('occurred_on', 'visitor_key')
                ->get();

            if ($dailyVisitors->isNotEmpty()) {
                StorefrontAnalyticsDailyVisitor::query()->insert(
                    $dailyVisitors->map(fn ($row) => [
                        'date' => $row->date,
                        'visitor_key' => $row->visitor_key,
                        'is_authenticated' => (bool) $row->is_authenticated,
                        'is_new_visitor' => (bool) $row->is_new_visitor,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ])->all()
                );
            }

            $pageViewTotals = DB::table('storefront_analytics_page_views')
                ->whereBetween('occurred_on', [$startDate, $endDate])
                ->selectRaw('occurred_on as date')
                ->selectRaw('COUNT(*) as page_views')
                ->selectRaw('SUM(CASE WHEN is_authenticated = 0 THEN 1 ELSE 0 END) as guest_page_views')
                ->selectRaw('SUM(CASE WHEN is_authenticated = 1 THEN 1 ELSE 0 END) as authenticated_page_views')
                ->groupBy('occurred_on')
                ->get();

            $dailyVisitorTotals = DB::table('storefront_analytics_daily_visitors')
                ->whereBetween('date', [$startDate, $endDate])
                ->selectRaw('date')
                ->selectRaw('COUNT(*) as unique_visitors')
                ->selectRaw('SUM(CASE WHEN is_new_visitor = 1 THEN 1 ELSE 0 END) as new_visitors')
                ->selectRaw('SUM(CASE WHEN is_authenticated = 0 THEN 1 ELSE 0 END) as guest_visitors')
                ->selectRaw('SUM(CASE WHEN is_authenticated = 1 THEN 1 ELSE 0 END) as authenticated_visitors')
                ->groupBy('date')
                ->get();

            if ($pageViewTotals->isNotEmpty()) {
                $visitorTotalsByDate = $dailyVisitorTotals->keyBy('date');

                StorefrontAnalyticsDailyTotal::query()->insert(
                    $pageViewTotals->map(function ($row) use ($visitorTotalsByDate) {
                        $visitors = $visitorTotalsByDate->get($row->date);

                        return [
                            'date' => $row->date,
                            'page_views' => (int) $row->page_views,
                            'unique_visitors' => (int) ($visitors->unique_visitors ?? 0),
                            'new_visitors' => (int) ($visitors->new_visitors ?? 0),
                            'returning_visitors' => max((int) (($visitors->unique_visitors ?? 0) - ($visitors->new_visitors ?? 0)), 0),
                            'guest_page_views' => (int) $row->guest_page_views,
                            'authenticated_page_views' => (int) $row->authenticated_page_views,
                            'guest_visitors' => (int) ($visitors->guest_visitors ?? 0),
                            'authenticated_visitors' => (int) ($visitors->authenticated_visitors ?? 0),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    })->all()
                );
            }

            $this->insertGroupedAggregate(
                modelClass: StorefrontAnalyticsDailyPage::class,
                query: DB::table('storefront_analytics_page_views')
                    ->whereBetween('occurred_on', [$startDate, $endDate])
                    ->selectRaw('occurred_on as date, page_path, MAX(page_title) as page_title, MAX(component) as component, COUNT(*) as page_views, COUNT(DISTINCT visitor_key) as unique_visitors')
                    ->groupBy('occurred_on', 'page_path'),
                mapper: fn ($row) => [
                    'date' => $row->date,
                    'page_path' => $row->page_path,
                    'page_title' => $row->page_title,
                    'component' => $row->component,
                    'page_views' => (int) $row->page_views,
                    'unique_visitors' => (int) $row->unique_visitors,
                ],
            );

            $this->insertGroupedAggregate(
                modelClass: StorefrontAnalyticsDailyGeo::class,
                query: DB::table('storefront_analytics_page_views')
                    ->whereBetween('occurred_on', [$startDate, $endDate])
                    ->selectRaw('occurred_on as date, country_code, MAX(country_name) as country_name, region_name, COUNT(*) as page_views, COUNT(DISTINCT visitor_key) as unique_visitors')
                    ->groupBy('occurred_on', 'country_code', 'region_name'),
                mapper: fn ($row) => [
                    'date' => $row->date,
                    'country_code' => $row->country_code,
                    'country_name' => $row->country_name,
                    'region_name' => $row->region_name,
                    'page_views' => (int) $row->page_views,
                    'unique_visitors' => (int) $row->unique_visitors,
                ],
            );

            $this->insertGroupedAggregate(
                modelClass: StorefrontAnalyticsDailyDevice::class,
                query: DB::table('storefront_analytics_page_views')
                    ->whereBetween('occurred_on', [$startDate, $endDate])
                    ->selectRaw('occurred_on as date, device_type, COUNT(*) as page_views, COUNT(DISTINCT visitor_key) as unique_visitors')
                    ->groupBy('occurred_on', 'device_type'),
                mapper: fn ($row) => [
                    'date' => $row->date,
                    'device_type' => $row->device_type ?: 'unknown',
                    'page_views' => (int) $row->page_views,
                    'unique_visitors' => (int) $row->unique_visitors,
                ],
            );

            $this->insertGroupedAggregate(
                modelClass: StorefrontAnalyticsDailyReferrer::class,
                query: DB::table('storefront_analytics_page_views')
                    ->whereBetween('occurred_on', [$startDate, $endDate])
                    ->whereNotNull('referrer_domain')
                    ->selectRaw('occurred_on as date, referrer_domain, COUNT(*) as page_views, COUNT(DISTINCT visitor_key) as unique_visitors')
                    ->groupBy('occurred_on', 'referrer_domain'),
                mapper: fn ($row) => [
                    'date' => $row->date,
                    'referrer_domain' => $row->referrer_domain,
                    'page_views' => (int) $row->page_views,
                    'unique_visitors' => (int) $row->unique_visitors,
                ],
            );
        });

        Cache::forever(self::FRESHNESS_CACHE_KEY, now()->toIso8601String());
    }

    public function ensureFresh(?int $days = null): void
    {
        $days ??= $this->settings->aggregationRefreshWindowDays();
        $lastAggregatedAt = Cache::get(self::FRESHNESS_CACHE_KEY);

        if ($lastAggregatedAt && now()->diffInMinutes(Carbon::parse($lastAggregatedAt)) < 15) {
            return;
        }

        Cache::lock('storefront_analytics.aggregate_lock', 30)->block(5, function () use ($days) {
            $this->refreshRecentWindow($days);
        });
    }

    public function pruneRaw(): int
    {
        $cutoff = now()->subDays($this->settings->rawRetentionDays())->startOfDay();

        return StorefrontAnalyticsPageView::query()
            ->where('occurred_at', '<', $cutoff)
            ->delete();
    }

    /**
     * @param class-string<\Illuminate\Database\Eloquent\Model> $modelClass
     * @param callable(object):array<string, mixed> $mapper
     */
    protected function insertGroupedAggregate(string $modelClass, $query, callable $mapper): void
    {
        $rows = $query->get();

        if ($rows->isEmpty()) {
            return;
        }

        $modelClass::query()->insert(
            $rows->map(function ($row) use ($mapper) {
                return [
                    ...$mapper($row),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            })->all()
        );
    }
}
