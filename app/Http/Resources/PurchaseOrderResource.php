<?php
namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderResource extends JsonResource
{
    public function toArray($request): array
    {
        // $this is a PurchaseOrder model
        return [
            'id' => $this->id,
            'po_number' => $this->po_number,
            'order_date' => $this->order_date?->toDateString(),
            'expected_date' => $this->expected_date?->toDateString(),
            'status' => $this->status,
            'total_amount' => number_format((float)$this->total_amount, 2, '.', ''),
            'note' => $this->note,
            'vendor' => $this->whenLoaded('vendor', function () {
                return [
                    'id' => $this->vendor->id,
                    'name' => $this->vendor->name,
                    'email' => $this->vendor->email,
                    'phone' => $this->vendor->phone,
                    'address' => $this->vendor->address,
                ];
            }),
            'warehouse' => $this->whenLoaded('warehouse', function () {
                return [
                    'id' => $this->warehouse->id,
                    'name' => $this->warehouse->name ?? null,
                ];
            }),
            'items' => $this->whenLoaded('items', function () {
                return $this->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'product_variant' => [
                            'id' => $item->productVariant?->id,
                            'sku' => $item->productVariant?->sku ?? null,
                            'title' => $this->variantDisplayName($item->productVariant),
                            'product' => $item->productVariant?->product?->only(['id','name']) ?? null,
                        ],
                        'quantity_ordered' => (int) $item->quantity_ordered,
                        'quantity_received' => (int) $item->quantity_received,
                        'remaining_quantity' => (int) max(0, $item->quantity_ordered - $item->quantity_received),
                        'unit_cost' => number_format((float)$item->unit_cost, 2, '.', ''),
                        'line_total' => number_format((float)$item->line_total, 2, '.', ''),
                    ];
                })->values();
            }),
            'item_receipts' => $this->whenLoaded('itemReceipts', function () {
                return $this->itemReceipts->map(function ($receipt) {
                    return [
                        'id' => $receipt->id,
                        'receipt_number' => $receipt->receipt_number,
                        'received_date' => $receipt->received_date
                            ? Carbon::parse($receipt->received_date)->format('d M Y')
                            : null,
                        'status' => $receipt->status,
                        'items' => $receipt->relationLoaded('items')
                            ? $receipt->items->map(fn($ri) => [
                                'id' => $ri->id,
                                'sku' => $ri->productVariant?->sku ?? null,
                                'title' => $this->variantDisplayName($ri->productVariant),
                                'product_variant_id' => $ri->product_variant_id,
                                'quantity_received' => (int) $ri->quantity_received,
                                'unit_cost' => number_format((float)$ri->unit_cost, 2, '.', ''),
                                'line_total' => number_format((float)$ri->line_total, 2, '.', ''),
                            ])
                            : null,
                    ];
                })->values();
            }),
            'vendor_bills' => $this->whenLoaded('vendorBills', function () {
                return $this->vendorBills->map(function ($bill) {
                    // Load payments
                    $payments = $bill->relationLoaded('payments')
                        ? $bill->payments
                        : $bill->payments()->get();

                    $paid = (float) $payments->sum('amount');

                    // Load bill items
                    $items = $bill->relationLoaded('items')
                        ? $bill->items
                        : $bill->items()->get();

                    return [
                        'id' => $bill->id,
                        'bill_number' => $bill->bill_number,
                        'bill_date' => $bill->bill_date
                            ? Carbon::parse($bill->bill_date)->format('d M Y')
                            : null,
                        'due_date'  => $bill->due_date
                            ? Carbon::parse($bill->due_date)->format('d M Y')
                            : null,
                        'status' => $bill->status,
                        'total_amount' => number_format((float) $bill->total_amount, 2, '.', ''),
                        'paid_amount' => number_format($paid, 2, '.', ''),
                        'outstanding' => number_format(max(0, (float) $bill->total_amount - $paid), 2, '.', ''),

                        // 👇 include payments array
                        'payments' => $payments->map(function ($p) {
                            return [
                                'id' => $p->id,
                                'payment_date' => $p->paid_at
                                    ? Carbon::parse($p->paid_at)->format('d M Y')
                                    : null,
                                'method' => $p->method,
                                'note' => $p->note,
                                'amount' => number_format((float) $p->amount, 2, '.', ''),
                            ];
                        })->values(),

                        // 👇 include bill items array
                        'items' => $items->map(function ($it) {
                            return [
                                'id' => $it->id,
                                'product_id' => $it->product_id,
                                'description' => $it->description,
                                'quantity' => (float) $it->quantity,
                                'unit_price' => number_format((float) $it->unit_cost, 2, '.', ''),
                                'total' => number_format((float) $it->unit_cost * $it->quantity, 2, '.', ''),
                            ];
                        })->values(),
                    ];
                })->values();
            }),

            // Helpful computed values
            'totals' => [
                'items_sum' => number_format(optional($this->items)->sum('line_total') ?? 0.0, 2, '.', ''),
                'bills_sum' => number_format(optional($this->vendorBills)->sum('total_amount') ?? 0.0, 2, '.', ''),
                'outstanding' => number_format($this->outstandingAmount(), 2, '.', ''),
            ],
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }

    private function variantDisplayName($variant): ?string
    {
        if (! $variant) {
            return null;
        }

        $productName = $variant->product?->name;
        $valueLabel = $variant->relationLoaded('values')
            ? $variant->values->pluck('value')->filter()->implode(', ')
            : '';

        $display = trim((string) $productName . ($valueLabel !== '' ? " {$valueLabel}" : ''));

        return $display !== '' ? $display : ($variant->sku ?? null);
    }
}
