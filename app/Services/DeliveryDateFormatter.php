<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class DeliveryDateFormatter
{
    public function formatWindow(?CarbonInterface $earliest, ?CarbonInterface $latest): ?array
    {
        if (!$earliest || !$latest) {
            return null;
        }

        $start = CarbonImmutable::instance($earliest);
        $end = CarbonImmutable::instance($latest);

        if ($end->lt($start)) {
            [$start, $end] = [$end, $start];
        }

        $singleDay = $start->isSameDay($end);
        $today = CarbonImmutable::today($start->timezone);

        return [
            'earliest_date' => $start->toDateString(),
            'latest_date' => $end->toDateString(),
            'is_exact' => $singleDay,
            'is_today' => $singleDay && $start->isSameDay($today),
            'is_tomorrow' => $singleDay && $start->isSameDay($today->addDay()),
            'label' => $this->formatRangeLabel($start, $end),
        ];
    }

    public function buildStorefrontMessage(string $methodType, ?string $destinationLabel, ?array $window): ?string
    {
        if (!$window) {
            return null;
        }

        $prefix = strtolower($methodType) === 'pickup' ? 'Ready for pickup' : 'Deliver';
        $destination = $destinationLabel ? ' to '.$destinationLabel : '';

        if ($window['is_today'] ?? false) {
            return strtolower($methodType) === 'pickup'
                ? 'Ready for pickup today'
                : "{$prefix}{$destination} today";
        }

        if ($window['is_tomorrow'] ?? false) {
            return strtolower($methodType) === 'pickup'
                ? 'Ready for pickup tomorrow'
                : "{$prefix}{$destination} tomorrow";
        }

        return strtolower($methodType) === 'pickup'
            ? 'Ready for pickup '.$window['label']
            : "{$prefix}{$destination} ".$window['label'];
    }

    public function buildCheckoutMessage(string $methodType, ?array $window): ?string
    {
        if (!$window) {
            return null;
        }

        $prefix = strtolower($methodType) === 'pickup' ? 'Pickup ready:' : 'Delivery:';

        if ($window['is_today'] ?? false) {
            return "{$prefix} Today";
        }

        if ($window['is_tomorrow'] ?? false) {
            return "{$prefix} Tomorrow";
        }

        return "{$prefix} {$window['label']}";
    }

    protected function formatRangeLabel(CarbonImmutable $start, CarbonImmutable $end): string
    {
        $separator = "\u{2013}";

        if ($start->isSameDay($end)) {
            return $start->format('j M');
        }

        if ($start->isSameMonth($end) && $start->isSameYear($end)) {
            return $start->format('j').$separator.$end->format('j M');
        }

        return $start->format('j M').' '.$separator.' '.$end->format('j M');
    }
}
