<?php

namespace App\Support;

final class RoleNames
{
    public const DIRECTOR = 'director';
    public const SALES_REPRESENTATIVE = 'sales_representative';
    public const CUSTOMER = 'customer';

    /**
     * @return list<string>
     */
    public static function staff(): array
    {
        return [
            self::DIRECTOR,
            self::SALES_REPRESENTATIVE,
        ];
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::DIRECTOR,
            self::SALES_REPRESENTATIVE,
            self::CUSTOMER,
        ];
    }
}
