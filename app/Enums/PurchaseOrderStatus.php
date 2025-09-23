<?php
declare(strict_types=1);

namespace App\Enums;

enum PurchaseOrderStatus: string
{
    case DRAFT = 'draft';
    case SENT = 'sent';
    case PARTIALLY_RECEIVED = 'partially_received';
    case RECEIVED = 'received';
    case CLOSED = 'closed';
    case CANCELLED = 'cancelled';

    /**
     * Allowed forward transitions map.
     */
    public static function allowedTransitions(): array
    {
        return [
            self::DRAFT->value => [
                self::SENT->value,
                self::CANCELLED->value,
            ],
            self::SENT->value => [
                self::PARTIALLY_RECEIVED->value,
                self::RECEIVED->value,
                self::CANCELLED->value,
            ],
            self::PARTIALLY_RECEIVED->value => [
                self::RECEIVED->value,
                self::CLOSED->value,
            ],
            self::RECEIVED->value => [
                self::CLOSED->value,
            ],
            self::CLOSED->value => [],
            self::CANCELLED->value => [],
        ];
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to->value, self::allowedTransitions()[$this->value] ?? [], true);
    }
}
