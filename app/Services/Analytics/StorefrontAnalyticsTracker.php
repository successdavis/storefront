<?php

namespace App\Services\Analytics;

use App\Models\StorefrontAnalyticsPageView;
use App\Models\StorefrontAnalyticsVisitor;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StorefrontAnalyticsTracker
{
    public function __construct(
        protected StorefrontAnalyticsSettings $settings,
    ) {}

    /**
     * @param list<array<string, mixed>> $events
     */
    public function trackBatch(Request $request, array $events): int
    {
        if (!$this->settings->enabled()) {
            return 0;
        }

        $normalizedEvents = collect($events)
            ->map(fn (array $event) => $this->normalizeEvent($request, $event))
            ->filter()
            ->values();

        if ($normalizedEvents->isEmpty()) {
            return 0;
        }

        $existingVisitors = StorefrontAnalyticsVisitor::query()
            ->whereIn('visitor_key', $normalizedEvents->pluck('visitor_key')->unique()->all())
            ->get()
            ->keyBy('visitor_key');

        $pageViewRows = [];

        DB::transaction(function () use ($normalizedEvents, $existingVisitors, &$pageViewRows) {
            $grouped = $normalizedEvents->groupBy('visitor_key');

            foreach ($grouped as $visitorKey => $visitorEvents) {
                /** @var Collection<int, array<string, mixed>> $visitorEvents */
                $first = $visitorEvents->first();
                $last = $visitorEvents->last();
                $visitor = $existingVisitors->get($visitorKey);
                $isNewVisitor = !$visitor;

                if (!$visitor) {
                    $visitor = StorefrontAnalyticsVisitor::query()->create([
                        'visitor_key' => $visitorKey,
                        'first_user_id' => $first['user_id'],
                        'last_user_id' => $last['user_id'],
                        'first_page_path' => $first['page_path'],
                        'last_page_path' => $last['page_path'],
                        'first_referrer_domain' => $first['referrer_domain'],
                        'last_referrer_domain' => $last['referrer_domain'],
                        'first_country_code' => $first['country_code'],
                        'first_country_name' => $first['country_name'],
                        'first_region_name' => $first['region_name'],
                        'last_country_code' => $last['country_code'],
                        'last_country_name' => $last['country_name'],
                        'last_region_name' => $last['region_name'],
                        'first_device_type' => $first['device_type'],
                        'last_device_type' => $last['device_type'],
                        'total_page_views' => $visitorEvents->count(),
                        'first_seen_at' => $first['occurred_at'],
                        'last_seen_at' => $last['occurred_at'],
                    ]);
                } else {
                    $visitor->forceFill([
                        'last_user_id' => $last['user_id'] ?? $visitor->last_user_id,
                        'last_page_path' => $last['page_path'],
                        'last_referrer_domain' => $last['referrer_domain'],
                        'last_country_code' => $last['country_code'],
                        'last_country_name' => $last['country_name'],
                        'last_region_name' => $last['region_name'],
                        'last_device_type' => $last['device_type'],
                        'last_seen_at' => $last['occurred_at'],
                        'total_page_views' => (int) $visitor->total_page_views + $visitorEvents->count(),
                    ])->save();
                }

                foreach ($visitorEvents as $event) {
                    $pageViewRows[] = [
                        'visitor_key' => $event['visitor_key'],
                        'user_id' => $event['user_id'],
                        'occurred_on' => $event['occurred_on'],
                        'occurred_at' => $event['occurred_at'],
                        'page_path' => $event['page_path'],
                        'page_title' => $event['page_title'],
                        'component' => $event['component'],
                        'country_code' => $event['country_code'],
                        'country_name' => $event['country_name'],
                        'region_name' => $event['region_name'],
                        'device_type' => $event['device_type'],
                        'referrer_domain' => $event['referrer_domain'],
                        'is_authenticated' => $event['is_authenticated'],
                        'is_new_visitor' => $isNewVisitor,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            if (!empty($pageViewRows)) {
                StorefrontAnalyticsPageView::query()->insert($pageViewRows);
            }
        });

        return count($pageViewRows);
    }

    /**
     * @param array<string, mixed> $event
     * @return array<string, mixed>|null
     */
    protected function normalizeEvent(Request $request, array $event): ?array
    {
        $visitorKey = trim((string) ($event['visitor_key'] ?? ''));
        $component = trim((string) ($event['component'] ?? ''));
        $pagePath = $this->normalizePath((string) ($event['page_path'] ?? ''));

        if (!preg_match('/^[a-zA-Z0-9\-_]{16,64}$/', $visitorKey)) {
            return null;
        }

        if (!$this->shouldTrack($pagePath, $component, $request->user() !== null)) {
            return null;
        }

        if ($this->isBot($request)) {
            return null;
        }

        $occurredAt = $this->resolveOccurredAt($event['occurred_at'] ?? null);
        $geo = $this->resolveGeography($request, is_array($event['location'] ?? null) ? $event['location'] : []);
        $referrer = $this->resolveReferrerDomain($request, $event['referrer'] ?? null);

        return [
            'visitor_key' => $visitorKey,
            'user_id' => $request->user()?->id,
            'occurred_on' => $occurredAt->toDateString(),
            'occurred_at' => $occurredAt->toDateTimeString(),
            'page_path' => $pagePath,
            'page_title' => Str::limit(trim((string) ($event['page_title'] ?? '')), 255, ''),
            'component' => Str::limit($component, 160, ''),
            'country_code' => $geo['country_code'] ?? null,
            'country_name' => $geo['country_name'] ?? null,
            'region_name' => $geo['region_name'] ?? null,
            'device_type' => $this->resolveDeviceType($request->userAgent()),
            'referrer_domain' => $referrer,
            'is_authenticated' => $request->user() !== null,
        ];
    }

    protected function shouldTrack(string $pagePath, string $component, bool $isAuthenticated): bool
    {
        if ($pagePath === '' || $component === '') {
            return false;
        }

        if ($isAuthenticated && !$this->settings->trackAuthenticatedPages()) {
            return false;
        }

        if (in_array($pagePath, $this->settings->excludedExactPaths(), true)) {
            return false;
        }

        foreach ($this->settings->excludedPathPrefixes() as $prefix) {
            $prefix = trim((string) $prefix, '/');

            if ($prefix !== '' && Str::startsWith(ltrim($pagePath, '/'), $prefix)) {
                return false;
            }
        }

        foreach ($this->settings->allowedComponentPrefixes() as $allowedPrefix) {
            if ($component === $allowedPrefix || Str::startsWith($component, $allowedPrefix)) {
                return true;
            }
        }

        return false;
    }

    protected function normalizePath(string $pagePath): string
    {
        $path = trim($pagePath);

        if ($path === '') {
            return '';
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            $parsed = parse_url($path, PHP_URL_PATH);
            $path = is_string($parsed) ? $parsed : '/';
        }

        $path = '/'.ltrim($path, '/');

        return $path !== '/' ? rtrim($path, '/') : $path;
    }

    protected function resolveOccurredAt(mixed $value): CarbonImmutable
    {
        try {
            return CarbonImmutable::parse($value ?: now())->timezone(config('app.timezone'));
        } catch (\Throwable) {
            return now()->toImmutable();
        }
    }

    /**
     * @param array<string, mixed> $location
     * @return array<string, string|null>
     */
    protected function resolveGeography(Request $request, array $location): array
    {
        $countryCode = $this->nullableString($location['country_code'] ?? null);
        $countryName = $this->nullableString($location['country_name'] ?? null);
        $regionName = $this->nullableString($location['state_name'] ?? $location['region_name'] ?? null);

        if ($countryCode || $countryName || $regionName) {
            return [
                'country_code' => $countryCode ? strtoupper($countryCode) : null,
                'country_name' => $countryName,
                'region_name' => $regionName,
            ];
        }

        $headers = $this->settings->geoHeaders();
        $countryCode = $this->firstHeaderValue($request, $headers['country_code'] ?? []);
        $regionName = $this->firstHeaderValue($request, $headers['region_name'] ?? []);

        return [
            'country_code' => $countryCode ? strtoupper($countryCode) : null,
            'country_name' => null,
            'region_name' => $regionName,
        ];
    }

    /**
     * @param array<int, string> $headers
     */
    protected function firstHeaderValue(Request $request, array $headers): ?string
    {
        foreach ($headers as $header) {
            $value = $this->nullableString($request->header($header));

            if ($value) {
                return Str::limit($value, 120, '');
            }
        }

        return null;
    }

    protected function resolveReferrerDomain(Request $request, mixed $referrer): ?string
    {
        if (!$this->settings->captureReferrers()) {
            return null;
        }

        $candidate = $this->nullableString($referrer) ?? $this->nullableString($request->headers->get('referer'));

        if (!$candidate) {
            return null;
        }

        $host = parse_url($candidate, PHP_URL_HOST);
        $host = is_string($host) ? strtolower($host) : null;

        if (!$host || $host === strtolower($request->getHost())) {
            return null;
        }

        return Str::limit($host, 160, '');
    }

    protected function resolveDeviceType(?string $userAgent): string
    {
        $userAgent = strtolower((string) $userAgent);

        if ($userAgent === '') {
            return 'unknown';
        }

        if (str_contains($userAgent, 'ipad') || str_contains($userAgent, 'tablet')) {
            return 'tablet';
        }

        if (str_contains($userAgent, 'mobi') || str_contains($userAgent, 'android')) {
            return 'mobile';
        }

        return 'desktop';
    }

    protected function isBot(Request $request): bool
    {
        $userAgent = strtolower((string) $request->userAgent());

        if ($userAgent === '') {
            return true;
        }

        return (bool) preg_match('/bot|crawl|spider|slurp|headless|preview|facebookexternalhit|pingdom|monitor/i', $userAgent);
    }

    protected function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
