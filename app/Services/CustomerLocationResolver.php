<?php

namespace App\Services;

use App\Models\Country;
use App\Models\State;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class CustomerLocationResolver
{
    protected const SESSION_KEY = 'storefront_inferred_location';

    public function resolveForRequest(?Request $request): ?array
    {
        if (!$request) {
            return $this->defaultLocation();
        }

        $sessionLocation = $request->session()->get(self::SESSION_KEY);
        if (is_array($sessionLocation)) {
            return $sessionLocation;
        }

        $location = $this->lookupFromRequest($request) ?? $this->defaultLocation();

        if ($location) {
            $request->session()->put(self::SESSION_KEY, $location);
        }

        return $location;
    }

    protected function lookupFromRequest(Request $request): ?array
    {
        if (!config('services.geolocation.enabled', true)) {
            return null;
        }

        $ip = trim((string) $request->ip());
        if ($ip === '' || !$this->isPublicIp($ip)) {
            return null;
        }

        $cacheKey = 'geoip:'.sha1($ip.'|'.config('app.key'));

        return Cache::remember($cacheKey, now()->addHours(12), function () use ($ip) {
            $endpoint = (string) config('services.geolocation.endpoint', '');
            if ($endpoint === '') {
                return null;
            }

            try {
                $response = Http::acceptJson()
                    ->timeout((int) config('services.geolocation.timeout', 2))
                    ->get(str_replace('{ip}', urlencode($ip), $endpoint));

                if (!$response->successful()) {
                    return null;
                }

                return $this->mapProviderPayload($response->json() ?: []);
            } catch (\Throwable) {
                return null;
            }
        });
    }

    protected function mapProviderPayload(array $payload): ?array
    {
        $countryName = $this->nullableString(
            $payload['country_name']
            ?? $payload['country']
            ?? null
        );
        $countryCode = strtoupper((string) ($payload['country_code'] ?? $payload['countryCode'] ?? ''));
        $stateName = $this->nullableString(
            $payload['state_prov']
            ?? $payload['regionName']
            ?? $payload['region']
            ?? $payload['state']
            ?? null
        );
        $cityName = $this->nullableString($payload['city'] ?? $payload['town'] ?? null);

        if (!$countryName && !$countryCode && !$stateName && !$cityName) {
            return null;
        }

        $country = $this->resolveCountry($countryName, $countryCode);
        $state = $this->resolveState($country?->id, $stateName);

        return [
            'source' => 'ip',
            'is_inferred' => true,
            'country_id' => $country?->id ? (int) $country->id : null,
            'state_id' => $state?->id ? (int) $state->id : null,
            'lga_id' => null,
            'country_name' => $country?->name ?? $countryName,
            'state_name' => $this->cleanStateName($state?->name ?? $stateName),
            'city_name' => $cityName,
            'country_code' => $country?->iso2 ?? ($countryCode !== '' ? $countryCode : null),
            'destination_label' => $cityName ?: $this->cleanStateName($state?->name ?? $stateName) ?: ($country?->name ?? $countryName),
        ];
    }

    protected function defaultLocation(): ?array
    {
        $countryCode = strtoupper((string) config('services.geolocation.default_country_code', 'NG'));
        $stateName = $this->nullableString(config('services.geolocation.default_state'));
        $cityName = $this->nullableString(config('services.geolocation.default_city'));

        $country = $this->resolveCountry(null, $countryCode);
        $state = $this->resolveState($country?->id, $stateName);

        if (!$country && !$state && !$cityName) {
            return null;
        }

        return [
            'source' => 'default',
            'is_inferred' => true,
            'country_id' => $country?->id ? (int) $country->id : null,
            'state_id' => $state?->id ? (int) $state->id : null,
            'lga_id' => null,
            'country_name' => $country?->name,
            'state_name' => $this->cleanStateName($state?->name ?? $stateName),
            'city_name' => $cityName,
            'country_code' => $country?->iso2 ?? ($countryCode !== '' ? $countryCode : null),
            'destination_label' => $cityName ?: $this->cleanStateName($state?->name ?? $stateName) ?: $country?->name,
        ];
    }

    protected function resolveCountry(?string $countryName, ?string $countryCode): ?Country
    {
        return Country::query()
            ->when($countryCode, fn ($query) => $query->where('iso2', strtoupper($countryCode)))
            ->when(!$countryCode && $countryName, fn ($query) => $query->where('name', $countryName))
            ->first();
    }

    protected function resolveState(?int $countryId, ?string $stateName): ?State
    {
        if (!$stateName) {
            return null;
        }

        $normalized = $this->normalizeName($stateName);

        return State::query()
            ->when($countryId, fn ($query) => $query->where('country_id', $countryId))
            ->get(['id', 'country_id', 'name'])
            ->first(function (State $state) use ($normalized) {
                return $this->normalizeName($state->name) === $normalized
                    || $this->normalizeName(Str::replaceLast(' state', '', Str::lower($state->name))) === $normalized;
            });
    }

    protected function cleanStateName(?string $stateName): ?string
    {
        $stateName = $this->nullableString($stateName);
        if (!$stateName) {
            return null;
        }

        return preg_replace('/\s+state$/i', '', $stateName) ?: $stateName;
    }

    protected function normalizeName(string $value): string
    {
        $value = Str::lower(trim($value));
        $value = preg_replace('/\s+state$/', '', $value) ?: $value;

        return preg_replace('/\s+/', ' ', $value) ?: $value;
    }

    protected function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    protected function isPublicIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }
}
