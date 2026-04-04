<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderShippedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected Order $order) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->order->loadMissing([
            'shipment.method',
            'shipment.pickup.location',
        ]);

        $shipment = $this->order->shipment;
        $isPickup = $shipment?->type === 'pickup';

        $mail = (new MailMessage())
            ->subject(($isPickup ? 'Pickup Update: ' : 'Shipment Update: ') . $this->order->order_number)
            ->greeting('Hello ' . ($notifiable->name ?? 'there') . ',')
            ->line($isPickup
                ? 'Your order is ready for pickup.'
                : 'Your order has been shipped and is on the way.')
            ->line('Order number: ' . $this->order->order_number);

        if ($shipment?->method?->name) {
            $mail->line(($isPickup ? 'Pickup method: ' : 'Shipping method: ') . $shipment->method->name);
        }

        if ($shipment?->courier_name) {
            $mail->line('Courier: ' . $shipment->courier_name);
        }

        if ($shipment?->tracking_number) {
            $mail->line('Tracking number: ' . $shipment->tracking_number);
        }

        if ($shipment?->tracking_url) {
            $mail->action($isPickup ? 'View pickup details' : 'Track order', $shipment->tracking_url);
        } else {
            $mail->action('View order', route('account.orders.show', $this->order));
        }

        return $mail->line($isPickup
            ? 'Please bring your order reference when you come to collect it.'
            : 'You can review the latest order status any time from your account dashboard.');
    }
}
