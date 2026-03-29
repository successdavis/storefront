<?php

namespace App\Services;

use App\Models\CustomerAddress;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class CustomerAddressService
{
    public function paginate(User $user, int $perPage = 10): LengthAwarePaginator
    {
        return CustomerAddress::query()
            ->where('user_id', $user->id)
            ->with(['country:id,name', 'state:id,name', 'lga:id,name'])
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function listForCheckout(User $user): array
    {
        return CustomerAddress::query()
            ->where('user_id', $user->id)
            ->with(['country:id,name', 'state:id,name', 'lga:id,name'])
            ->orderByDesc('is_default')
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (CustomerAddress $address) => $this->toCheckoutPayload($address))
            ->values()
            ->all();
    }

    public function findForUser(User $user, int $addressId): ?CustomerAddress
    {
        return CustomerAddress::query()
            ->where('user_id', $user->id)
            ->with(['country:id,name', 'state:id,name', 'lga:id,name'])
            ->find($addressId);
    }

    public function defaultForUser(User $user): ?CustomerAddress
    {
        return CustomerAddress::query()
            ->where('user_id', $user->id)
            ->with(['country:id,name', 'state:id,name', 'lga:id,name'])
            ->orderByDesc('is_default')
            ->orderByDesc('updated_at')
            ->first();
    }

    public function store(User $user, array $data): CustomerAddress
    {
        return DB::transaction(function () use ($user, $data) {
            if (!empty($data['is_default'])) {
                CustomerAddress::query()->where('user_id', $user->id)->update(['is_default' => false]);
            }

            return CustomerAddress::query()->create([
                ...$data,
                'user_id' => $user->id,
            ]);
        });
    }

    public function update(User $user, CustomerAddress $address, array $data): CustomerAddress
    {
        $this->assertOwnedByUser($user, $address);

        return DB::transaction(function () use ($user, $address, $data) {
            if (!empty($data['is_default'])) {
                CustomerAddress::query()->where('user_id', $user->id)->whereKeyNot($address->id)->update(['is_default' => false]);
            }

            $address->update($data);

            return $address->fresh(['country:id,name', 'state:id,name', 'lga:id,name']);
        });
    }

    public function delete(User $user, CustomerAddress $address): void
    {
        $this->assertOwnedByUser($user, $address);
        $address->delete();
    }

    public function rememberCheckoutAddress(User $user, array $selection): ?CustomerAddress
    {
        if (!empty($selection['address_id'])) {
            return $this->findForUser($user, (int) $selection['address_id']);
        }

        $line1 = trim((string) ($selection['line1'] ?? ''));
        if ($line1 === '') {
            return null;
        }

        $line2 = $this->normalizeNullableString($selection['line2'] ?? null);
        $phone = $this->normalizeNullableString($selection['phone'] ?? null);
        $recipientName = $this->normalizeNullableString($selection['recipient_name'] ?? null) ?: $user->name;
        $email = $this->normalizeNullableString($selection['email'] ?? null) ?: $user->email;
        $stateId = !empty($selection['state_id']) ? (int) $selection['state_id'] : null;
        $lgaId = !empty($selection['lga_id']) ? (int) $selection['lga_id'] : null;
        $countryId = !empty($selection['country_id']) ? (int) $selection['country_id'] : null;
        $postalCode = $this->normalizeNullableString($selection['postal_code'] ?? null);

        $existing = CustomerAddress::query()
            ->where('user_id', $user->id)
            ->where('line1', $line1)
            ->where('line2', $line2)
            ->where('phone', $phone)
            ->where('recipient_name', $recipientName)
            ->where('state_id', $stateId)
            ->where('lga_id', $lgaId)
            ->first();

        if ($existing) {
            return $existing->fresh(['country:id,name', 'state:id,name', 'lga:id,name']);
        }

        $existingCount = CustomerAddress::query()->where('user_id', $user->id)->count();

        return $this->store($user, [
            'label' => $existingCount === 0 ? 'Primary Address' : 'Checkout Address '.($existingCount + 1),
            'recipient_name' => $recipientName,
            'phone' => $phone,
            'email' => $email,
            'line1' => $line1,
            'line2' => $line2,
            'country_id' => $countryId,
            'state_id' => $stateId,
            'lga_id' => $lgaId,
            'postal_code' => $postalCode,
            'is_default' => $existingCount === 0,
        ]);
    }

    public function toCheckoutPayload(CustomerAddress $address): array
    {
        return [
            'id' => (int) $address->id,
            'label' => $address->label,
            'recipient_name' => $address->recipient_name,
            'phone' => $address->phone,
            'email' => $address->email,
            'line1' => $address->line1,
            'line2' => $address->line2,
            'postal_code' => $address->postal_code,
            'country_id' => $address->country_id ? (int) $address->country_id : null,
            'state_id' => $address->state_id ? (int) $address->state_id : null,
            'lga_id' => $address->lga_id ? (int) $address->lga_id : null,
            'is_default' => (bool) $address->is_default,
            'country' => $address->country ? ['id' => (int) $address->country->id, 'name' => $address->country->name] : null,
            'state' => $address->state ? ['id' => (int) $address->state->id, 'name' => $address->state->name] : null,
            'lga' => $address->lga ? ['id' => (int) $address->lga->id, 'name' => $address->lga->name] : null,
        ];
    }

    protected function assertOwnedByUser(User $user, CustomerAddress $address): void
    {
        if ((int) $address->user_id !== (int) $user->id) {
            abort(403);
        }
    }

    protected function normalizeNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }
}
