<?php

use App\Services\Accounting\HistoricalAccountingSyncService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('accounting:sync-history', function (HistoricalAccountingSyncService $syncService) {
    $result = $syncService->sync();

    $this->info('Historical accounting synchronization complete.');
    foreach ($result['synced'] as $key => $count) {
        $this->line(str_replace('_', ' ', ucfirst($key)).": {$count}");
    }
    $this->newLine();
    $this->info("Total synchronized workflows: {$result['total_posted']}");
})->purpose('Synchronize historical commerce and inventory records into accounting journals.');

Schedule::command('inventory:scan')->everyFiveMinutes();
Schedule::command('analytics:storefront-aggregate')->everyFifteenMinutes();
Schedule::command('analytics:storefront-prune')->dailyAt('02:30');
