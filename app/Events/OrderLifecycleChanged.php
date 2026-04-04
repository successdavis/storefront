<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderLifecycleChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Order $order,
        public string $event,
        public string $statusType,
        public ?string $previousStatus = null,
        public ?string $newStatus = null,
        public ?int $actorId = null,
        public array $meta = [],
    ) {}
}
