<?php

namespace Tests\Unit;

use App\Services\DeliveryDateFormatter;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class DeliveryDateFormatterTest extends TestCase
{
    public function test_it_formats_same_month_ranges_compactly(): void
    {
        $formatter = app(DeliveryDateFormatter::class);

        $window = $formatter->formatWindow(
            CarbonImmutable::parse('2026-04-03'),
            CarbonImmutable::parse('2026-04-05'),
        );

        $this->assertNotNull($window);
        $this->assertSame('3–5 Apr', $window['label']);
        $this->assertSame('Delivery: 3–5 Apr', $formatter->buildCheckoutMessage('delivery', $window));
    }

    public function test_it_formats_cross_month_ranges_cleanly(): void
    {
        $formatter = app(DeliveryDateFormatter::class);

        $window = $formatter->formatWindow(
            CarbonImmutable::parse('2026-03-29'),
            CarbonImmutable::parse('2026-04-02'),
        );

        $this->assertNotNull($window);
        $this->assertSame('29 Mar – 2 Apr', $window['label']);
    }
}
