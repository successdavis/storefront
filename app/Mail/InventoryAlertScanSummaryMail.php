<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Collection;

class InventoryAlertScanSummaryMail extends Mailable
{
    public function __construct(
        public Collection $alerts,
        public string $scanCompletedAt,
    ) {}

    public function envelope(): Envelope
    {
        $count = $this->alerts->count();
        $criticalCount = $this->alerts->where('severity', 'critical')->count();

        $subject = sprintf(
            'Inventory scan summary: %d alert%s',
            $count,
            $count === 1 ? '' : 's'
        );

        if ($criticalCount > 0) {
            $subject .= sprintf(' (%d critical)', $criticalCount);
        }

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.inventory-alert-scan-summary',
            with: [
                'alerts' => $this->alerts,
                'groupedAlerts' => $this->alerts->groupBy('type'),
                'scanCompletedAt' => $this->scanCompletedAt,
            ]
        );
    }
}
