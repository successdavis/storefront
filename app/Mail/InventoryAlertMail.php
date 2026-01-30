<?php
namespace App\Mail;

use App\Models\InventoryAlert;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Content;

class InventoryAlertMail extends Mailable
{
    public function __construct(public InventoryAlert $alert) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Inventory Alert: {$this->alert->type}"
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.inventory-alert',
            with: [
                'alert' => $this->alert,
            ]
        );
    }
}
