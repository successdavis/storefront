<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('inventory:scan')->everyFiveMinutes();
Schedule::command('analytics:storefront-aggregate')->everyFifteenMinutes();
Schedule::command('analytics:storefront-prune')->dailyAt('02:30');
