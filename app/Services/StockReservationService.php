<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\ProductVariant;
use App\Models\StockReservation;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class StockReservationService
{
    public function reserveForSession(int $checkoutSessionId, array $items, CarbonInterface $expiresAt): void
    {
        DB::transaction(function () use ($checkoutSessionId, $items, $expiresAt) {
            $this->releaseExpiredReservationsInternal();

            $desired = collect($items)
                ->reduce(function (array $carry, array $line) {
                    $variantId = (int) ($line['variant_id'] ?? 0);
                    $qty = (int) ($line['quantity'] ?? 0);

                    if ($variantId <= 0 || $qty <= 0) {
                        return $carry;
                    }

                    $carry[$variantId] = ($carry[$variantId] ?? 0) + $qty;

                    return $carry;
                }, []);

            $existing = StockReservation::query()
                ->where('checkout_session_id', $checkoutSessionId)
                ->lockForUpdate()
                ->get()
                ->keyBy('variant_id');

            $variantIds = collect(array_keys($desired))
                ->merge($existing->keys())
                ->unique()
                ->values()
                ->all();

            if (empty($variantIds)) {
                return;
            }

            $variants = ProductVariant::query()
                ->whereIn('id', $variantIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($desired as $variantId => $desiredQty) {
                $reservation = $existing->get($variantId);
                $currentQty = (int) ($reservation?->status === 'active' ? $reservation->quantity : 0);
                $delta = $desiredQty - $currentQty;

                $variant = $variants->get($variantId);
                if (!$variant) {
                    throw new InsufficientStockException('Variant not found for reservation.', [
                        ['variant_id' => $variantId, 'requested' => $desiredQty, 'available' => 0],
                    ]);
                }

                if ($delta > 0) {
                    $available = max((int) $variant->quantity - (int) ($variant->reserved ?? 0), 0);

                    if ($available < $delta) {
                        throw new InsufficientStockException('One or more items are no longer available.', [
                            [
                                'variant_id' => (int) $variant->id,
                                'sku' => $variant->sku ?? null,
                                'requested' => $desiredQty,
                                'available' => $available + $currentQty,
                            ],
                        ]);
                    }

                    $variant->reserved = (int) ($variant->reserved ?? 0) + $delta;
                    $variant->save();
                } elseif ($delta < 0) {
                    $variant->reserved = max((int) ($variant->reserved ?? 0) + $delta, 0);
                    $variant->save();
                }

                if (!$reservation) {
                    StockReservation::query()->create([
                        'checkout_session_id' => $checkoutSessionId,
                        'variant_id' => $variantId,
                        'quantity' => $desiredQty,
                        'status' => 'active',
                        'expires_at' => $expiresAt,
                    ]);
                    continue;
                }

                $reservation->fill([
                    'quantity' => $desiredQty,
                    'status' => 'active',
                    'expires_at' => $expiresAt,
                    'consumed_at' => null,
                    'released_at' => null,
                    'release_reason' => null,
                ])->save();
            }

            foreach ($existing as $variantId => $reservation) {
                if (array_key_exists((int) $variantId, $desired)) {
                    continue;
                }

                if ($reservation->status !== 'active' || (int) $reservation->quantity <= 0) {
                    continue;
                }

                $variant = $variants->get((int) $variantId);
                if ($variant) {
                    $variant->reserved = max((int) ($variant->reserved ?? 0) - (int) $reservation->quantity, 0);
                    $variant->save();
                }

                $reservation->fill([
                    'status' => 'released',
                    'released_at' => now(),
                    'release_reason' => 'session_updated',
                ])->save();
            }
        }, 3);
    }

    public function consumeForSession(int $checkoutSessionId): void
    {
        DB::transaction(function () use ($checkoutSessionId) {
            $reservations = StockReservation::query()
                ->where('checkout_session_id', $checkoutSessionId)
                ->where('status', 'active')
                ->lockForUpdate()
                ->get();

            if ($reservations->isEmpty()) {
                return;
            }

            $variants = ProductVariant::query()
                ->whereIn('id', $reservations->pluck('variant_id')->all())
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($reservations as $reservation) {
                $variant = $variants->get((int) $reservation->variant_id);
                if ($variant) {
                    $variant->reserved = max((int) ($variant->reserved ?? 0) - (int) $reservation->quantity, 0);
                    $variant->save();
                }

                $reservation->fill([
                    'status' => 'consumed',
                    'consumed_at' => now(),
                    'release_reason' => null,
                ])->save();
            }
        }, 3);
    }

    public function releaseForSession(int $checkoutSessionId, string $reason = 'released'): int
    {
        return DB::transaction(function () use ($checkoutSessionId, $reason) {
            $reservations = StockReservation::query()
                ->where('checkout_session_id', $checkoutSessionId)
                ->where('status', 'active')
                ->lockForUpdate()
                ->get();

            if ($reservations->isEmpty()) {
                return 0;
            }

            $variants = ProductVariant::query()
                ->whereIn('id', $reservations->pluck('variant_id')->all())
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($reservations as $reservation) {
                $variant = $variants->get((int) $reservation->variant_id);
                if ($variant) {
                    $variant->reserved = max((int) ($variant->reserved ?? 0) - (int) $reservation->quantity, 0);
                    $variant->save();
                }

                $reservation->fill([
                    'status' => 'released',
                    'released_at' => now(),
                    'release_reason' => $reason,
                ])->save();
            }

            return $reservations->count();
        }, 3);
    }

    public function releaseExpiredReservations(int $limit = 200): int
    {
        return DB::transaction(function () use ($limit) {
            $ids = StockReservation::query()
                ->where('status', 'active')
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', now())
                ->orderBy('id')
                ->limit($limit)
                ->lockForUpdate()
                ->pluck('id')
                ->all();

            if (empty($ids)) {
                return 0;
            }

            $reservations = StockReservation::query()->whereIn('id', $ids)->get();

            $variants = ProductVariant::query()
                ->whereIn('id', $reservations->pluck('variant_id')->all())
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($reservations as $reservation) {
                $variant = $variants->get((int) $reservation->variant_id);
                if ($variant) {
                    $variant->reserved = max((int) ($variant->reserved ?? 0) - (int) $reservation->quantity, 0);
                    $variant->save();
                }

                $reservation->fill([
                    'status' => 'released',
                    'released_at' => now(),
                    'release_reason' => 'expired',
                ])->save();
            }

            return $reservations->count();
        }, 3);
    }

    protected function releaseExpiredReservationsInternal(): void
    {
        $reservations = StockReservation::query()
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->lockForUpdate()
            ->get();

        if ($reservations->isEmpty()) {
            return;
        }

        $variants = ProductVariant::query()
            ->whereIn('id', $reservations->pluck('variant_id')->all())
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($reservations as $reservation) {
            $variant = $variants->get((int) $reservation->variant_id);
            if ($variant) {
                $variant->reserved = max((int) ($variant->reserved ?? 0) - (int) $reservation->quantity, 0);
                $variant->save();
            }

            $reservation->fill([
                'status' => 'released',
                'released_at' => now(),
                'release_reason' => 'expired',
            ])->save();
        }
    }
}
