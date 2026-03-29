<?php

namespace App\Services;

use App\Models\Country;
use App\Models\CustomerAddress;
use App\Models\Lga;
use App\Models\Order;
use App\Models\State;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class CustomerLocationResolver
{
    protected const SESSION_KEY = 'storefront_inferred_location';

    public function resolveForRequest(?Request $request): ?array
    {
        if (!$request) {
            return null;
        }

        $sessionLocation = $this->temporaryLocationFromSession($request);
        if (is_array($sessionLocation)) {
            return $sessionLocation;
        }

        $user = $request->user();
        if ($user instanceof User) {
            return $this->resolvePersistedUserLocation($user);
        }

        return null;
    }

    public function storeBrowserLocation(
        Request $request,
        float $latitude,
        float $longitude,
        ?float $accuracyMeters = null,
    ): ?array {
        $location = $this->resolveFromCoordinates($latitude, $longitude, $accuracyMeters);

        if (!$location) {
            $this->clearTemporaryLocation($request);

            return null;
        }

        $request->session()->put(self::SESSION_KEY, $location);

        return $location;
    }

    public function clearTemporaryLocation(?Request $request): void
    {
        if (!$request) {
            return;
        }

        $request->session()->forget(self::SESSION_KEY);
    }

    protected function temporaryLocationFromSession(Request $request): ?array
    {
        $location = $request->session()->get(self::SESSION_KEY);
        if (!is_array($location)) {
            return null;
        }

        if (($location['source'] ?? null) !== 'browser') {
            $request->session()->forget(self::SESSION_KEY);

            return null;
        }

        if ($this->temporaryLocationExpired($location)) {
            $request->session()->forget(self::SESSION_KEY);

            return null;
        }

        return $location;
    }

    protected function temporaryLocationExpired(array $location): bool
    {
        $capturedAt = $location['captured_at'] ?? null;
        if (!is_string($capturedAt) || trim($capturedAt) === '') {
            return true;
        }

        try {
            $captured = CarbonImmutable::parse($capturedAt);
        } catch (\Throwable) {
            return true;
        }

        $ttlMinutes = max((int) config('services.geolocation.browser_location_ttl_minutes', 180), 1);

        return $captured->addMinutes($ttlMinutes)->isPast();
    }

    protected function resolvePersistedUserLocation(User $user): ?array
    {
        return $this->mapCustomerAddress($this->defaultAddressForUser($user))
            ?? $this->latestOrderDestinationForUser($user);
    }

    protected function defaultAddressForUser(User $user): ?CustomerAddress
    {
        return CustomerAddress::query()
            ->where('user_id', $user->id)
            ->with(['country:id,name,iso2', 'state:id,name', 'lga:id,name'])
            ->where(function ($query) {
                $query->whereNotNull('state_id')
                    ->orWhereNotNull('lga_id');
            })
            ->orderByDesc('is_default')
            ->orderByDesc('updated_at')
            ->first();
    }

    protected function latestOrderDestinationForUser(User $user): ?array
    {
        $order = Order::query()
            ->where('user_id', $user->id)
            ->whereHas('shipment.addresses', function ($query) {
                $query->where('type', 'shipping')
                    ->where(function ($shippingAddress) {
                        $shippingAddress->whereNotNull('state_id')
                            ->orWhereNotNull('lga_id');
                    });
            })
            ->with([
                'shipment.addresses.country:id,name,iso2',
                'shipment.addresses.state:id,name',
                'shipment.addresses.lga:id,name',
            ])
            ->latest('id')
            ->first();

        $address = $order?->shipment?->addresses
            ?->first(fn ($candidate) => $candidate->type === 'shipping' && ($candidate->state_id || $candidate->lga_id));

        return $this->mapShipmentAddress($address);
    }

    protected function resolveFromCoordinates(float $latitude, float $longitude, ?float $accuracyMeters = null): ?array
    {
        if (!config('services.geolocation.enabled', true)) {
            return null;
        }

        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            return null;
        }

        $maxAccuracyMeters = (float) config('services.geolocation.browser_max_accuracy_meters', 25000);
        if ($accuracyMeters !== null && $accuracyMeters > 0 && $accuracyMeters > $maxAccuracyMeters) {
            return null;
        }

        $distanceThresholdKm = (float) config('services.geolocation.reverse_match_distance_km', 75);
        if ($distanceThresholdKm <= 0) {
            return null;
        }

        $matchedLga = $this->matchLgaByCoordinates($latitude, $longitude, $distanceThresholdKm);
        if ($matchedLga) {
            return $this->mapMatchedLga($matchedLga, $latitude, $longitude, $accuracyMeters);
        }

        return $this->reverseGeocodeCoordinates($latitude, $longitude, $accuracyMeters);
    }

    protected function matchLgaByCoordinates(float $latitude, float $longitude, float $distanceThresholdKm): ?Lga
    {
        $latitudeDelta = max($distanceThresholdKm / 111, 0.1);
        $longitudeFactor = cos(deg2rad($latitude));
        $longitudeDelta = $longitudeFactor > 0.001
            ? max($distanceThresholdKm / (111 * $longitudeFactor), 0.1)
            : 1.0;

        return Lga::query()
            ->with([
                'state:id,name,country_id',
                'state.country:id,name,iso2',
            ])
            ->where('is_active', true)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereBetween('latitude', [$latitude - $latitudeDelta, $latitude + $latitudeDelta])
            ->whereBetween('longitude', [$longitude - $longitudeDelta, $longitude + $longitudeDelta])
            ->get(['id', 'state_id', 'name', 'latitude', 'longitude'])
            ->map(function (Lga $lga) use ($latitude, $longitude) {
                $lga->distance_km = $this->distanceInKilometers(
                    $latitude,
                    $longitude,
                    (float) $lga->latitude,
                    (float) $lga->longitude,
                );

                return $lga;
            })
            ->sortBy('distance_km')
            ->first(fn (Lga $lga) => (float) ($lga->distance_km ?? INF) <= $distanceThresholdKm);
    }

    protected function mapMatchedLga(
        Lga $lga,
        float $latitude,
        float $longitude,
        ?float $accuracyMeters = null,
    ): array {
        return [
            'source' => 'browser',
            'is_inferred' => true,
            'country_id' => $lga->state?->country_id ? (int) $lga->state->country_id : null,
            'state_id' => $lga->state_id ? (int) $lga->state_id : null,
            'lga_id' => $lga->id ? (int) $lga->id : null,
            'country_name' => $lga->state?->country?->name,
            'state_name' => $this->cleanStateName($lga->state?->name),
            'city_name' => $lga->name,
            'country_code' => $lga->state?->country?->iso2,
            'destination_label' => $lga->name,
            'latitude' => round($latitude, 6),
            'longitude' => round($longitude, 6),
            'accuracy_meters' => $accuracyMeters !== null ? round($accuracyMeters, 2) : null,
            'captured_at' => now()->toIso8601String(),
        ];
    }

    protected function mapCustomerAddress(?CustomerAddress $address): ?array
    {
        if (!$address || (!$address->state_id && !$address->lga_id)) {
            return null;
        }

        return [
            'source' => 'saved_address',
            'is_inferred' => false,
            'country_id' => $address->country_id ? (int) $address->country_id : null,
            'state_id' => $address->state_id ? (int) $address->state_id : null,
            'lga_id' => $address->lga_id ? (int) $address->lga_id : null,
            'country_name' => $address->country?->name,
            'state_name' => $this->cleanStateName($address->state?->name),
            'city_name' => $address->lga?->name,
            'country_code' => $address->country?->iso2,
            'destination_label' => $address->lga?->name ?: $this->cleanStateName($address->state?->name),
        ];
    }

    protected function mapShipmentAddress(mixed $address): ?array
    {
        if (!$address || (!$address->state_id && !$address->lga_id)) {
            return null;
        }

        return [
            'source' => 'order_history',
            'is_inferred' => false,
            'country_id' => $address->country_id ? (int) $address->country_id : null,
            'state_id' => $address->state_id ? (int) $address->state_id : null,
            'lga_id' => $address->lga_id ? (int) $address->lga_id : null,
            'country_name' => $address->country?->name,
            'state_name' => $this->cleanStateName($address->state?->name),
            'city_name' => $address->lga?->name,
            'country_code' => $address->country?->iso2,
            'destination_label' => $address->lga?->name ?: $this->cleanStateName($address->state?->name),
        ];
    }

    protected function cleanStateName(?string $stateName): ?string
    {
        $stateName = $this->nullableString($stateName);
        if (!$stateName) {
            return null;
        }

        return preg_replace('/\s+state$/i', '', $stateName) ?: $stateName;
    }

    protected function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    protected function reverseGeocodeCoordinates(float $latitude, float $longitude, ?float $accuracyMeters = null): ?array
    {
        $endpoint = trim((string) config('services.geolocation.reverse_geocode_endpoint', ''));
        if ($endpoint === '') {
            return null;
        }

        try {
            $response = Http::acceptJson()
                ->timeout((int) config('services.geolocation.timeout', 2))
                ->withHeaders([
                    'User-Agent' => (string) config('app.name', 'Laravel Storefront').' delivery-estimate resolver',
                ])
                ->get(str_replace(
                    ['{lat}', '{lng}'],
                    [urlencode((string) $latitude), urlencode((string) $longitude)],
                    $endpoint
                ));

            if (!$response->successful()) {
                return null;
            }

            return $this->mapReverseGeocodePayload($response->json() ?: [], $latitude, $longitude, $accuracyMeters);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function mapReverseGeocodePayload(array $payload, float $latitude, float $longitude, ?float $accuracyMeters = null): ?array
    {
        $address = is_array($payload['address'] ?? null) ? $payload['address'] : $payload;

        $countryName = $this->nullableString($address['country'] ?? null);
        $countryCode = strtoupper((string) ($address['country_code'] ?? $address['countryCode'] ?? ''));
        $stateName = $this->nullableString($address['state'] ?? $address['region'] ?? null);
        $lgaName = $this->nullableString(
            $address['county']
            ?? $address['municipality']
            ?? $address['district']
            ?? $address['suburb']
            ?? null
        );
        $localityName = $this->nullableString(
            $address['city']
            ?? $address['town']
            ?? $address['village']
            ?? $address['hamlet']
            ?? $address['locality']
            ?? null
        );

        $country = $this->resolveCountry($countryName, $countryCode);
        $state = $this->resolveState($country?->id, $stateName);
        $lga = $this->resolveLga($state?->id, $lgaName ?: $localityName);

        if (!$state && !$lga) {
            return null;
        }

        return [
            'source' => 'browser',
            'is_inferred' => true,
            'country_id' => $country?->id ? (int) $country->id : null,
            'state_id' => $state?->id ? (int) $state->id : null,
            'lga_id' => $lga?->id ? (int) $lga->id : null,
            'country_name' => $country?->name ?? $countryName,
            'state_name' => $this->cleanStateName($state?->name ?? $stateName),
            'city_name' => $lga?->name ?? $localityName,
            'country_code' => $country?->iso2 ?? ($countryCode !== '' ? $countryCode : null),
            'destination_label' => $lga?->name ?? $localityName ?? $this->cleanStateName($state?->name ?? $stateName),
            'latitude' => round($latitude, 6),
            'longitude' => round($longitude, 6),
            'accuracy_meters' => $accuracyMeters !== null ? round($accuracyMeters, 2) : null,
            'captured_at' => now()->toIso8601String(),
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

    protected function resolveLga(?int $stateId, ?string $lgaName): ?Lga
    {
        if (!$stateId || !$lgaName) {
            return null;
        }

        $normalized = $this->normalizeName($lgaName);

        return Lga::query()
            ->where('state_id', $stateId)
            ->get(['id', 'state_id', 'name'])
            ->first(function (Lga $lga) use ($normalized) {
                $candidate = $this->normalizeName($lga->name);

                return $candidate === $normalized
                    || str_contains($candidate, $normalized)
                    || str_contains($normalized, $candidate);
            });
    }

    protected function normalizeName(string $value): string
    {
        $value = Str::lower(trim($value));
        $value = preg_replace('/\s+state$/', '', $value) ?: $value;
        $value = preg_replace('/\s+local government area$/', '', $value) ?: $value;

        return preg_replace('/\s+/', ' ', $value) ?: $value;
    }

    protected function distanceInKilometers(
        float $latitude,
        float $longitude,
        float $targetLatitude,
        float $targetLongitude,
    ): float {
        $earthRadius = 6371;

        $latitudeDelta = deg2rad($targetLatitude - $latitude);
        $longitudeDelta = deg2rad($targetLongitude - $longitude);
        $baseLatitude = deg2rad($latitude);
        $baseTargetLatitude = deg2rad($targetLatitude);

        $a = sin($latitudeDelta / 2) ** 2
            + cos($baseLatitude) * cos($baseTargetLatitude) * sin($longitudeDelta / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(max(1 - $a, 0)));

        return $earthRadius * $c;
    }
}
