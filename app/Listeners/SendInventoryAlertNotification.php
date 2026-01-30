<?php

namespace App\Listeners;

use App\Events\InventoryAlertRaised;
use App\Mail\InventoryAlertMail;
use App\Models\Setting;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;

class SendInventoryAlertNotification
{
    public function handle(InventoryAlertRaised $event)
    {
        if ($event->alert->severity === 'critical') {
            Mail::to(Setting::get('admin_email'))
                ->send(new InventoryAlertMail($event->alert));
        }
    }
}
