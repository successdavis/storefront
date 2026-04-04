<?php

namespace App\Notifications;

use App\Models\Order;
use App\Services\OrderManagementService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderPlacedNotification extends Notification implements ShouldQueue
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
            'items.variant.product',
            'payments',
            'shipment.method',
            'shipment.pickup.location',
            'shipment.addresses.state',
            'shipment.addresses.country',
        ]);

        $paymentStatus = app(OrderManagementService::class)->resolvePaymentStatus($this->order);
        $mail = (new MailMessage())
            ->subject('Order Confirmation: ' . $this->order->order_number)
            ->greeting('Hello ' . ($notifiable->name ?? 'there') . ',')
            ->line('We have received your order and it is now in our system.')
            ->line('Order number: ' . $this->order->order_number)
            ->line('Order total: ' . $this->money((float) $this->order->total_amount, (string) $this->order->currency))
            ->line('Payment status: ' . $this->label($paymentStatus));

        foreach ($this->order->items as $item) {
            $productName = $item->variant?->product?->name ?? ('Item #' . $item->id);
            $mail->line($productName . ' x' . (int) $item->quantity);
        }

        if ($this->order->shipment) {
            $deliveryLine = $this->order->shipment->type === 'pickup'
                ? 'Pickup method: ' . ($this->order->shipment->pickup?->location?->name ?? ($this->order->shipment->method?->name ?? 'Pickup'))
                : 'Shipping method: ' . ($this->order->shipment->method?->name ?? 'Delivery');

            $mail->line($deliveryLine);
        }

        return $mail
            ->action('View order', route('account.orders.show', $this->order))
            ->line('We will send another update as the order moves through fulfillment.');
    }

    protected function label(string $value): string
    {
        return str($value)->replace('_', ' ')->headline()->value();
    }

    protected function money(float $amount, string $currency): string
    {
        return strtoupper($currency) . ' ' . number_format($amount, 2);
    }
}
