<?php

namespace App\Domain\Inventory\Alerts;

class InventoryAlertMailContext
{
    protected static int $immediateMailSuppressionDepth = 0;

    public static function withoutImmediateMail(callable $callback): mixed
    {
        self::$immediateMailSuppressionDepth++;

        try {
            return $callback();
        } finally {
            self::$immediateMailSuppressionDepth--;
        }
    }

    public static function immediateMailSuppressed(): bool
    {
        return self::$immediateMailSuppressionDepth > 0;
    }
}
