<?php

namespace App\Listeners;

use App\Events\OrderLifecycleChanged;
use App\Notifications\OrderPlacedNotification;
use App\Notifications\OrderShippedNotification;
use App\Services\OrderManagementService;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendOrderLifecycleNotifications implements ShouldQueue
{
    public function handle(OrderLifecycleChanged $event): void
    {
        $order = $event->order;
        $user = $order->user;

        if (!$user) {
            return;
        }

        if ($event->event === 'placed') {
            $user->notify(new OrderPlacedNotification($order));
            return;
        }

        if (
            $event->event === 'status_changed'
            && $event->statusType === OrderManagementService::HISTORY_TYPE_FULFILLMENT
            && in_array($event->newStatus, ['shipped', 'ready'], true)
        ) {
            $user->notify(new OrderShippedNotification($order));
        }
    }
}
