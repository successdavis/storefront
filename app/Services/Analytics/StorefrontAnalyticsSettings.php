<?php

namespace App\Services\Analytics;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class StorefrontAnalyticsSettings
{
    protected const CACHE_KEY = 'storefront_analytics.settings';

    protected const CACHE_TTL_SECONDS = 300;

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function () {
            return [
                'enabled' => $this->boolValue('analytics.storefront.enabled', config('analytics.storefront.enabled', true)),
                'capture_referrers' => $this->boolValue('analytics.storefront.capture_referrers', config('analytics.storefront.capture_referrers', true)),
                'track_authenticated_pages' => $this->boolValue('analytics.storefront.track_authenticated_pages', config('analytics.storefront.track_authenticated_pages', true)),
                'raw_retention_days' => $this->intValue('analytics.storefront.raw_retention_days', config('analytics.storefront.raw_retention_days', 180), 30, 365),
                'aggregation_refresh_window_days' => $this->intValue('analytics.storefront.aggregation_refresh_window_days', config('analytics.storefront.aggregation_refresh_window_days', 7), 1, 90),
                'frontend_batch_size' => $this->intValue('analytics.storefront.frontend_batch_size', config('analytics.storefront.frontend_batch_size', 5), 1, 20),
                'frontend_flush_interval_ms' => $this->intValue('analytics.storefront.frontend_flush_interval_ms', config('analytics.storefront.frontend_flush_interval_ms', 2000), 500, 10000),
                'throttle_per_minute' => $this->intValue('analytics.storefront.throttle_per_minute', config('analytics.storefront.throttle_per_minute', 180), 30, 1000),
                'allowed_component_prefixes' => array_values(config('analytics.storefront.allowed_component_prefixes', [])),
                'excluded_path_prefixes' => array_values(config('analytics.storefront.excluded_path_prefixes', [])),
                'excluded_exact_paths' => array_values(config('analytics.storefront.excluded_exact_paths', [])),
                'geo_headers' => config('analytics.storefront.geo_headers', []),
            ];
        });
    }

    public function enabled(): bool
    {
        return (bool) $this->all()['enabled'];
    }

    public function captureReferrers(): bool
    {
        return (bool) $this->all()['capture_referrers'];
    }

    public function trackAuthenticatedPages(): bool
    {
        return (bool) $this->all()['track_authenticated_pages'];
    }

    public function rawRetentionDays(): int
    {
        return (int) $this->all()['raw_retention_days'];
    }

    public function aggregationRefreshWindowDays(): int
    {
        return (int) $this->all()['aggregation_refresh_window_days'];
    }

    public function frontendBatchSize(): int
    {
        return (int) $this->all()['frontend_batch_size'];
    }

    public function frontendFlushIntervalMs(): int
    {
        return (int) $this->all()['frontend_flush_interval_ms'];
    }

    public function throttlePerMinute(): int
    {
        return (int) $this->all()['throttle_per_minute'];
    }

    /**
     * @return list<string>
     */
    public function allowedComponentPrefixes(): array
    {
        return $this->all()['allowed_component_prefixes'];
    }

    /**
     * @return list<string>
     */
    public function excludedPathPrefixes(): array
    {
        return $this->all()['excluded_path_prefixes'];
    }

    /**
     * @return list<string>
     */
    public function excludedExactPaths(): array
    {
        return $this->all()['excluded_exact_paths'];
    }

    /**
     * @return array<string, mixed>
     */
    public function geoHeaders(): array
    {
        return $this->all()['geo_headers'];
    }

    /**
     * @return array<string, mixed>
     */
    public function frontendConfig(): array
    {
        return [
            'enabled' => $this->enabled(),
            'track_authenticated_pages' => $this->trackAuthenticatedPages(),
            'batch_size' => $this->frontendBatchSize(),
            'flush_interval_ms' => $this->frontendFlushIntervalMs(),
            'allowed_component_prefixes' => $this->allowedComponentPrefixes(),
        ];
    }

    /**
     * @param array<string, mixed> $values
     */
    public function update(array $values): void
    {
        $map = [
            'enabled' => 'analytics.storefront.enabled',
            'capture_referrers' => 'analytics.storefront.capture_referrers',
            'track_authenticated_pages' => 'analytics.storefront.track_authenticated_pages',
            'raw_retention_days' => 'analytics.storefront.raw_retention_days',
            'aggregation_refresh_window_days' => 'analytics.storefront.aggregation_refresh_window_days',
        ];

        foreach ($map as $inputKey => $settingKey) {
            if (!array_key_exists($inputKey, $values)) {
                continue;
            }

            Setting::set($settingKey, is_bool($values[$inputKey]) ? ($values[$inputKey] ? '1' : '0') : (string) $values[$inputKey]);
        }

        Cache::forget(self::CACHE_KEY);
    }

    protected function boolValue(string $key, bool $default): bool
    {
        return filter_var(Setting::get($key, $default ? '1' : '0'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE)
            ?? $default;
    }

    protected function intValue(string $key, int $default, int $min, int $max): int
    {
        $value = (int) Setting::get($key, $default);

        return max($min, min($max, $value));
    }
}
