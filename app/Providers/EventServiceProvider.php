<?php

namespace App\Providers;

use App\Events\OrderLifecycleChanged;
use App\Listeners\SendOrderLifecycleNotifications;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        OrderLifecycleChanged::class => [
            SendOrderLifecycleNotifications::class,
        ],
    ];
}
