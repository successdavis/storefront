<?php

namespace App\Console\Commands;

use App\Services\Analytics\StorefrontAnalyticsAggregationService;
use Illuminate\Console\Command;

class PruneStorefrontAnalytics extends Command
{
    protected $signature = 'analytics:storefront-prune';

    protected $description = 'Prune old raw storefront analytics page views.';

    public function handle(StorefrontAnalyticsAggregationService $service): int
    {
        $deleted = $service->pruneRaw();

        $this->info(sprintf('Pruned %d storefront analytics page view(s).', $deleted));

        return self::SUCCESS;
    }
}
