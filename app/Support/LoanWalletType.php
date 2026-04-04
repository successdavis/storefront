<?php

namespace App\Support;

final class LoanWalletType
{
    public const SAVING = 'saving';
    public const DAILY_SAVING = 'daily_saving';
    public const EASY_FLEX = 'easy_flex';
    public const CONTRIBUTION = 'contribution';

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::SAVING => 'Saving',
            self::DAILY_SAVING => 'Daily Saving',
            self::EASY_FLEX => 'Easy Flex',
            self::CONTRIBUTION => 'Contribution',
        ];
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::labels());
    }

    public static function label(string $key): string
    {
        return self::labels()[$key] ?? $key;
    }

    public static function caseExpression(string $column = 'account_type'): string
    {
        return <<<SQL
CASE
    WHEN LOWER(REPLACE(TRIM({$column}), ' ', '')) = 'saving' THEN 'saving'
    WHEN LOWER(REPLACE(TRIM({$column}), ' ', '')) = 'dailysaving' THEN 'daily_saving'
    WHEN LOWER(REPLACE(TRIM({$column}), ' ', '')) = 'easyflex' THEN 'easy_flex'
    WHEN LOWER(REPLACE(TRIM({$column}), ' ', '')) = 'contribution' THEN 'contribution'
    ELSE NULL
END
SQL;
    }
}
