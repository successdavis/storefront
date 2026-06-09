<?php

namespace App\Services;

use App\Models\DropshipFulfillment;
use App\Models\OrderItem;
use App\Models\Vendor;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DropshippingService
{
    public function createFulfillmentForOrderItem(OrderItem $item): ?DropshipFulfillment
    {
        if (! $item->isDropshipping()) {
            return null;
        }

        return DropshipFulfillment::query()->firstOrCreate(
            ['order_item_id' => $item->id],
            [
                'order_id' => $item->order_id,
                'supplier_id' => $item->supplier_id,
                'supplier_cost' => $item->supplier_cost,
                'status' => $item->dropship_status ?: DropshipFulfillment::STATUS_PENDING,
                'supplier_reference' => $item->supplier_reference,
                'expected_delivery_at' => $item->supplier_expected_delivery_at,
                'admin_note' => $item->dropship_admin_note,
                'meta' => $item->dropship_meta,
            ]
        );
    }

    public function listFulfillments(array $filters = []): LengthAwarePaginator
    {
        $query = DropshipFulfillment::query()
            ->with([
                'supplier:id,name,email,phone,active',
                'order.user:id,name,email,phone',
                'order.payments:id,payable_type,payable_id,status,amount',
                'orderItem.variant.product.images',
                'orderItem.variant.values.type',
            ])
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['supplier_id'] ?? null, fn (Builder $query, $supplierId) => $query->where('supplier_id', (int) $supplierId))
            ->when($filters['order_number'] ?? null, function (Builder $query, string $orderNumber) {
                $query->whereHas('order', fn (Builder $order) => $order->where('order_number', 'like', "%{$orderNumber}%"));
            })
            ->when($filters['customer'] ?? null, function (Builder $query, string $customer) {
                $query->whereHas('order.user', function (Builder $user) use ($customer) {
                    $user->where('name', 'like', "%{$customer}%")
                        ->orWhere('email', 'like', "%{$customer}%")
                        ->orWhere('phone', 'like', "%{$customer}%");
                });
            })
            ->when($filters['channel'] ?? null, fn (Builder $query, string $channel) => $query->whereHas('order', fn (Builder $order) => $order->where('channel', $channel)))
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->whereDate('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->whereDate('created_at', '<=', $to))
            ->orderByRaw("case when status in ('pending_supplier_order', 'ordered_from_supplier', 'supplier_confirmed') then 0 else 1 end")
            ->orderBy('expected_delivery_at')
            ->latest('id');

        return $query->paginate((int) ($filters['per_page'] ?? 20))->withQueryString();
    }

    public function updateStatus(DropshipFulfillment $fulfillment, string $status, array $payload = []): DropshipFulfillment
    {
        if (! in_array($status, DropshipFulfillment::STATUSES, true)) {
            throw ValidationException::withMessages(['status' => 'Unsupported dropshipping status.']);
        }

        if (! $this->canTransition($fulfillment->status, $status, (bool) ($payload['override'] ?? false))) {
            throw ValidationException::withMessages(['status' => 'This dropshipping status transition is not allowed.']);
        }

        return DB::transaction(function () use ($fulfillment, $status, $payload) {
            $fulfillment = DropshipFulfillment::query()->lockForUpdate()->findOrFail($fulfillment->id);

            $timestampColumn = match ($status) {
                DropshipFulfillment::STATUS_ORDERED => 'ordered_at',
                DropshipFulfillment::STATUS_CONFIRMED => 'confirmed_at',
                DropshipFulfillment::STATUS_RECEIVED => 'received_at',
                DropshipFulfillment::STATUS_SHIPPED => 'shipped_to_customer_at',
                DropshipFulfillment::STATUS_DELIVERED => 'delivered_at',
                DropshipFulfillment::STATUS_CANCELLED => 'cancelled_at',
                default => null,
            };

            $fulfillment->fill([
                'status' => $status,
                'supplier_id' => array_key_exists('supplier_id', $payload) ? $payload['supplier_id'] : $fulfillment->supplier_id,
                'supplier_cost' => array_key_exists('supplier_cost', $payload) ? $payload['supplier_cost'] : $fulfillment->supplier_cost,
                'supplier_reference' => array_key_exists('supplier_reference', $payload) ? $payload['supplier_reference'] : $fulfillment->supplier_reference,
                'expected_delivery_at' => array_key_exists('expected_delivery_at', $payload) ? $payload['expected_delivery_at'] : $fulfillment->expected_delivery_at,
                'admin_note' => array_key_exists('admin_note', $payload) ? $payload['admin_note'] : $fulfillment->admin_note,
            ]);

            if ($timestampColumn && ! $fulfillment->{$timestampColumn}) {
                $fulfillment->{$timestampColumn} = now();
            }

            $fulfillment->save();
            $this->syncOrderItem($fulfillment);

            return $fulfillment->fresh(['order', 'orderItem', 'supplier']);
        });
    }

    public function changeSupplier(DropshipFulfillment $fulfillment, ?int $supplierId, ?float $supplierCost = null): DropshipFulfillment
    {
        if ($supplierId && ! Vendor::query()->whereKey($supplierId)->exists()) {
            throw ValidationException::withMessages(['supplier_id' => 'The selected supplier does not exist.']);
        }

        return $this->updateStatus($fulfillment, $fulfillment->status, [
            'supplier_id' => $supplierId,
            'supplier_cost' => $supplierCost ?? $fulfillment->supplier_cost,
            'override' => true,
        ]);
    }

    public function calculateExpectedProfit(OrderItem $item): float
    {
        return round(((float) $item->price - (float) ($item->supplier_cost ?? 0)) * (int) $item->quantity, 2);
    }

    public function customerStatusLabel(?string $status): string
    {
        return match ($status) {
            DropshipFulfillment::STATUS_ORDERED => 'Processing',
            DropshipFulfillment::STATUS_CONFIRMED => 'Confirmed',
            DropshipFulfillment::STATUS_RECEIVED => 'Preparing for Delivery',
            DropshipFulfillment::STATUS_SHIPPED => 'Shipped',
            DropshipFulfillment::STATUS_DELIVERED => 'Delivered',
            DropshipFulfillment::STATUS_CANCELLED => 'Cancelled',
            DropshipFulfillment::STATUS_UNAVAILABLE => 'Awaiting Update',
            default => 'Processing',
        };
    }

    protected function canTransition(?string $from, string $to, bool $override = false): bool
    {
        if ($override || $from === $to) {
            return true;
        }

        if (in_array($to, [DropshipFulfillment::STATUS_CANCELLED], true)) {
            return in_array($from, DropshipFulfillment::ACTIVE_STATUSES, true);
        }

        $allowed = [
            DropshipFulfillment::STATUS_PENDING => [DropshipFulfillment::STATUS_ORDERED, DropshipFulfillment::STATUS_UNAVAILABLE],
            DropshipFulfillment::STATUS_ORDERED => [DropshipFulfillment::STATUS_CONFIRMED],
            DropshipFulfillment::STATUS_CONFIRMED => [DropshipFulfillment::STATUS_RECEIVED],
            DropshipFulfillment::STATUS_RECEIVED => [DropshipFulfillment::STATUS_SHIPPED],
            DropshipFulfillment::STATUS_SHIPPED => [DropshipFulfillment::STATUS_DELIVERED],
        ];

        return in_array($to, $allowed[$from] ?? [], true);
    }

    protected function syncOrderItem(DropshipFulfillment $fulfillment): void
    {
        $fulfillment->orderItem()->update([
            'supplier_id' => $fulfillment->supplier_id,
            'supplier_cost' => $fulfillment->supplier_cost,
            'dropship_status' => $fulfillment->status,
            'supplier_reference' => $fulfillment->supplier_reference,
            'supplier_ordered_at' => $fulfillment->ordered_at,
            'supplier_confirmed_at' => $fulfillment->confirmed_at,
            'supplier_expected_delivery_at' => $fulfillment->expected_delivery_at,
            'supplier_received_at' => $fulfillment->received_at,
            'dropship_admin_note' => $fulfillment->admin_note,
            'dropship_meta' => $fulfillment->meta,
        ]);
    }
}
