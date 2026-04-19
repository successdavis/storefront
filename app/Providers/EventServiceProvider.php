<?php

namespace App\Providers;

use App\Events\OrderLifecycleChanged;
use App\Listeners\RecordSuccessfulLogin;
use App\Listeners\SendOrderLifecycleNotifications;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        Login::class => [
            RecordSuccessfulLogin::class,
        ],
        OrderLifecycleChanged::class => [
            SendOrderLifecycleNotifications::class,
        ],
    ];
}
