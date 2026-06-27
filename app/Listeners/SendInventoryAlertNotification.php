<?php

namespace App\Listeners;

use App\Domain\Inventory\Alerts\InventoryAlertMailContext;
use App\Events\InventoryAlertRaised;
use App\Mail\InventoryAlertMail;
use App\Models\Setting;
use Illuminate\Support\Facades\Mail;

class SendInventoryAlertNotification
{
    public function handle(InventoryAlertRaised $event)
    {
        if (InventoryAlertMailContext::immediateMailSuppressed()) {
            return;
        }

        if ($event->alert->severity === 'critical') {
            $recipient = Setting::get('admin_email');

            if ($recipient) {
                Mail::to($recipient)->send(new InventoryAlertMail($event->alert));
            }
        }
    }
}
