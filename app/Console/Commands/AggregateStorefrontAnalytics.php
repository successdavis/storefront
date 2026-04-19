<?php

namespace App\Console\Commands;

use App\Services\Analytics\StorefrontAnalyticsAggregationService;
use Illuminate\Console\Command;

class AggregateStorefrontAnalytics extends Command
{
    protected $signature = 'analytics:storefront-aggregate {--days=}';

    protected $description = 'Refresh storefront analytics aggregate tables.';

    public function handle(StorefrontAnalyticsAggregationService $service): int
    {
        $service->refreshRecentWindow($this->option('days') ? (int) $this->option('days') : null);

        $this->info('Storefront analytics aggregates refreshed.');

        return self::SUCCESS;
    }
}
