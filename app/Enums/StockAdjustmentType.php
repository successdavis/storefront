<?php
declare(strict_types=1);

namespace App\Enums;

enum StockAdjustmentType: string
{
    case LOSS = 'loss';
    case CORRECTION = 'correction';
    case GAIN = 'gain';

    public function label(): string
    {
        return match ($this) {
            self::LOSS => 'Loss',
            self::CORRECTION => 'Correction',
            self::GAIN => 'Gain',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::LOSS => 'Use for damage, theft, expiry, missing stock, or real shrinkage.',
            self::CORRECTION => 'Use for opening balance fixes, data entry mistakes, setup issues, or administrative count corrections.',
            self::GAIN => 'Use when stock is genuinely found or an inventory overage should be recognized.',
        };
    }

    public function allowsQuantityDelta(int $delta): bool
    {
        return match ($this) {
            self::LOSS => $delta < 0,
            self::GAIN => $delta > 0,
            self::CORRECTION => $delta !== 0,
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->map(fn (self $type) => [
                'value' => $type->value,
                'label' => $type->label(),
                'description' => $type->description(),
            ])
            ->all();
    }
}
