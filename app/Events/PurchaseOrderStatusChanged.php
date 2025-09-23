<?php
declare(strict_types=1);

namespace App\Events;

use App\Models\PurchaseOrder;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PurchaseOrderStatusChanged
{
    use Dispatchable, SerializesModels;

    public PurchaseOrder $purchaseOrder;
    public string $oldStatus;
    public string $newStatus;
    public ?string $note;

    public function __construct(PurchaseOrder $purchaseOrder, string $oldStatus, string $newStatus, ?string $note = null)
    {
        $this->purchaseOrder = $purchaseOrder;
        $this->oldStatus = $oldStatus;
        $this->newStatus = $newStatus;
        $this->note = $note;
    }
}
