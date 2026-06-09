<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DropshipFulfillment;
use App\Models\Vendor;
use App\Services\DropshippingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DropshippingController extends Controller
{
    public function __construct(protected DropshippingService $dropshippingService) {}

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(DropshipFulfillment::STATUSES)],
            'supplier_id' => ['nullable', 'integer', 'exists:vendors,id'],
            'order_number' => ['nullable', 'string', 'max:120'],
            'customer' => ['nullable', 'string', 'max:120'],
            'channel' => ['nullable', Rule::in(['online', 'pos'])],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
        ]);

        $fulfillments = $this->dropshippingService->listFulfillments($filters);

        return Inertia::render('Admin/Dropshipping/Index', [
            'filters' => $filters,
            'statuses' => DropshipFulfillment::STATUSES,
            'suppliers' => Vendor::query()->orderBy('name')->get(['id', 'name', 'active']),
            'fulfillments' => $fulfillments->through(fn (DropshipFulfillment $fulfillment) => $this->payload($fulfillment)),
        ]);
    }

    public function update(Request $request, DropshipFulfillment $fulfillment): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(DropshipFulfillment::STATUSES)],
            'supplier_id' => ['nullable', 'integer', 'exists:vendors,id'],
            'supplier_cost' => ['nullable', 'numeric', 'min:0'],
            'supplier_reference' => ['nullable', 'string', 'max:255'],
            'expected_delivery_at' => ['nullable', 'date'],
            'admin_note' => ['nullable', 'string'],
            'override' => ['nullable', 'boolean'],
        ]);

        $this->dropshippingService->updateStatus($fulfillment, (string) $data['status'], $data);

        return back()->with('success', 'Dropshipping fulfillment updated.');
    }

    protected function payload(DropshipFulfillment $fulfillment): array
    {
        $item = $fulfillment->orderItem;
        $variant = $item?->variant;
        $product = $variant?->product;
        $order = $fulfillment->order;
        $paidAmount = (float) ($order?->payments?->where('status', 'paid')->sum('amount') ?? 0);

        return [
            'id' => (int) $fulfillment->id,
            'status' => $fulfillment->status,
            'supplier_cost' => (float) ($fulfillment->supplier_cost ?? 0),
            'supplier_reference' => $fulfillment->supplier_reference,
            'expected_delivery_at' => optional($fulfillment->expected_delivery_at)?->toIso8601String(),
            'admin_note' => $fulfillment->admin_note,
            'supplier' => $fulfillment->supplier ? [
                'id' => (int) $fulfillment->supplier->id,
                'name' => $fulfillment->supplier->name,
                'active' => (bool) $fulfillment->supplier->active,
            ] : null,
            'order' => [
                'id' => (int) $order->id,
                'order_number' => $order->order_number,
                'channel' => $order->channel,
                'total_amount' => (float) $order->total_amount,
                'payment_status' => $paidAmount + 0.01 >= (float) $order->total_amount ? 'paid' : ($paidAmount > 0 ? 'partially_paid' : 'unpaid'),
                'customer' => $order->user ? [
                    'name' => $order->user->name,
                    'email' => $order->user->email,
                    'phone' => $order->user->phone,
                ] : null,
            ],
            'item' => [
                'id' => (int) $item->id,
                'quantity' => (int) $item->quantity,
                'price' => (float) $item->price,
                'expected_gross_profit' => $this->dropshippingService->calculateExpectedProfit($item),
                'product' => [
                    'name' => $product?->name,
                ],
                'variant' => [
                    'sku' => $variant?->sku,
                    'label' => $variant?->values?->map(fn ($value) => trim(($value->type?->name ? $value->type->name . ': ' : '') . $value->value))->implode(' / '),
                ],
            ],
        ];
    }
}
