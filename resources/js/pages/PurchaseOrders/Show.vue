<template>
    <div class="container mx-auto py-8">
        <Head title="'PO ' + purchaseOrder.po_number" />

        <div class="mb-6 flex items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold">
                    Purchase Order — {{ purchaseOrder.data.po_number }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    Vendor:
                    <span class="font-medium">{{
                        purchaseOrder.data.vendor?.name ?? '—'
                    }}</span>
                    • Warehouse:
                    <span class="font-medium">{{
                        purchaseOrder.data.warehouse?.name ?? '—'
                    }}</span>
                </p>
                <p class="mt-1 text-sm text-muted-foreground">
                    Ordered: {{ formatDate(purchaseOrder.data.order_date) }}
                    <span v-if="purchaseOrder.expected_date">
                        • Expected:
                        {{ formatDate(purchaseOrder.data.expected_date) }}</span
                    >
                </p>
            </div>

            <div class="flex items-center gap-3">
                <div class="mr-4 text-right">
                    <div class="text-sm text-muted-foreground">Status</div>
                    <div>
                        <span
                            :class="statusBadgeClass(purchaseOrder.data.status)"
                            class="inline-flex items-center rounded-full px-3 py-1 text-sm font-medium"
                        >
                            {{ purchaseOrder.data.status.replace('_', ' ') }}
                        </span>
                    </div>
                </div>

                <div class="text-right">
                    <div class="text-sm text-muted-foreground">Total</div>
                    <div class="text-lg font-semibold">
                        {{ formatCurrency(purchaseOrder.data.total_amount) }}
                    </div>
                </div>

                <Link
                    :href="
                        route('admin.purchase-orders.show', purchaseOrder.data.id)
                    "
                    class="hidden"
                />

                <div class="flex gap-2">
                    <Button @click="printPage">Print</Button>
                    <Link
                        :href="
                            route(
                                'admin.purchase-orders.edit',
                                purchaseOrder.data.id,
                            )
                        "
                    >
                        <Button variant="outline">Edit</Button>
                    </Link>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- Items list / main -->
            <section class="space-y-4 lg:col-span-2">
                <div class="rounded-lg bg-white p-4 shadow sm:p-6">
                    <h2 class="mb-3 text-lg font-medium">Items</h2>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th
                                        class="px-4 py-2 text-left text-sm font-medium text-gray-500"
                                    >
                                        SKU / Title
                                    </th>
                                    <th
                                        class="px-4 py-2 text-right text-sm font-medium text-gray-500"
                                    >
                                        Ordered
                                    </th>
                                    <th
                                        class="px-4 py-2 text-right text-sm font-medium text-gray-500"
                                    >
                                        Received
                                    </th>
                                    <th
                                        class="px-4 py-2 text-right text-sm font-medium text-gray-500"
                                    >
                                        Remaining
                                    </th>
                                    <th
                                        class="px-4 py-2 text-right text-sm font-medium text-gray-500"
                                    >
                                        Unit
                                    </th>
                                    <th
                                        class="px-4 py-2 text-right text-sm font-medium text-gray-500"
                                    >
                                        Line total
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                <tr
                                    v-for="item in purchaseOrder.data.items"
                                    :key="item.id"
                                >
                                    <td class="px-4 py-3">
                                        <div class="text-sm font-medium">
                                            {{
                                                item.product_variant?.title ||
                                                '—'
                                            }}
                                        </div>
                                        <div
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{
                                                item.product_variant?.sku || '—'
                                            }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-right text-sm">
                                        {{ item.quantity_ordered }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-sm">
                                        {{ item.quantity_received }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-sm">
                                        {{ item.remaining_quantity }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-sm">
                                        {{ formatCurrency(item.unit_cost) }}
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right text-sm font-medium"
                                    >
                                        {{ formatCurrency(item.line_total) }}
                                    </td>
                                </tr>
                                <tr
                                    v-if="
                                        (purchaseOrder.items || []).length === 0
                                    "
                                >
                                    <td
                                        colspan="6"
                                        class="px-4 py-6 text-center text-sm text-muted-foreground"
                                    >
                                        No items on this PO.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="rounded-lg bg-white p-4 shadow sm:p-6">
                    <h2 class="mb-3 text-lg font-medium">
                        Shipments (Item Receipts)
                    </h2>
                    <div
                        v-if="
                            purchaseOrder.data.item_receipts &&
                            purchaseOrder.data.item_receipts.length
                        "
                    >
                        <ul class="space-y-3">
                            <li
                                v-for="r in purchaseOrder.data.item_receipts"
                                :key="r.id"
                                class="rounded border p-3"
                            >
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="font-medium">
                                            {{ r.receipt_number }}
                                        </div>
                                        <div
                                            class="text-sm text-muted-foreground"
                                        >
                                            {{ formatDate(r.received_date) }}
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div
                                            class="text-sm text-muted-foreground"
                                        >
                                            {{ r.status }}
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-3">
                                    <table class="w-full text-sm">
                                        <thead>
                                            <tr
                                                class="text-xs text-muted-foreground"
                                            >
                                                <th class="text-left">SKU</th>
                                                <th class="text-right">Qty</th>
                                                <th class="text-right">
                                                    Line total
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr
                                                v-for="ri in r.items"
                                                :key="ri.id"
                                            >
                                                <td class="py-1 text-sm">
                                                    {{ ri.product_variant_id }}
                                                </td>
                                                <td class="py-1 text-right">
                                                    {{ ri.quantity_received }}
                                                </td>
                                                <td class="py-1 text-right">
                                                    {{
                                                        formatCurrency(
                                                            ri.line_total,
                                                        )
                                                    }}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </li>
                        </ul>
                    </div>
                    <div v-else class="text-sm text-muted-foreground">
                        No shipments recorded yet.
                    </div>
                </div>

                <div class="rounded-lg bg-white p-4 shadow sm:p-6">
                    <h2 class="mb-3 text-lg font-medium">Vendor Bills</h2>
                    <div
                        v-if="
                            purchaseOrder.data.vendor_bills &&
                            purchaseOrder.data.vendor_bills.length
                        "
                    >
                        <div class="space-y-3">
                            <div
                                v-for="bill in purchaseOrder.data.vendor_bills"
                                :key="bill.id"
                                class="flex items-center justify-between rounded border p-3"
                            >
                                <div>
                                    <div class="font-medium">
                                        {{ bill.bill_number }}
                                    </div>
                                    <div class="text-sm text-muted-foreground">
                                        Date: {{ formatDate(bill.bill_date) }}
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="text-sm">
                                        Total:
                                        {{ formatCurrency(bill.total_amount) }}
                                    </div>
                                    <div class="text-sm text-muted-foreground">
                                        Outstanding:
                                        {{ formatCurrency(bill.outstanding) }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-sm text-muted-foreground">
                        No vendor bills yet.
                    </div>
                </div>
            </section>

            <!-- Right column: summary / actions -->
            <aside class="space-y-4">
                <div class="rounded-lg bg-white p-4 shadow sm:p-6">
                    <h3 class="text-base font-medium">Summary</h3>
                    <dl class="mt-3 space-y-2 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-muted-foreground">Items total</dt>
                            <dd class="font-medium">
                                {{
                                    formatCurrency(
                                        purchaseOrder.data.totals.items_sum,
                                    )
                                }}
                            </dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-muted-foreground">Bills total</dt>
                            <dd class="font-medium">
                                {{
                                    formatCurrency(
                                        purchaseOrder.data.totals.bills_sum,
                                    )
                                }}
                            </dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-muted-foreground">Outstanding</dt>
                            <dd class="font-semibold text-red-600">
                                {{
                                    formatCurrency(
                                        purchaseOrder.data.totals.outstanding,
                                    )
                                }}
                            </dd>
                        </div>
                    </dl>

                    <div class="mt-4 space-y-2">
                        <Button
                            as="a"
                            class="w-full"
                            :href="
                                route('admin.item-receipts.create', {
                                    purchase_order: purchaseOrder.data.id,
                                })
                            "
                        >
                            Receive Items
                        </Button>
                        <Button
                            as="a"
                            variant="outline"
                            class="w-full"
                            :href="
                                route('admin.vendor-bills.create', {
                                    purchase_order: purchaseOrder.data.id,
                                })
                            "
                        >
                            Create Bill
                        </Button>
                    </div>
                </div>

                <div class="rounded-lg bg-white p-4 shadow sm:p-6">
                    <h3 class="text-base font-medium">Vendor</h3>
                    <div class="mt-2 text-sm">
                        <div class="font-medium">
                            {{ purchaseOrder.data.vendor?.name }}
                        </div>
                        <div class="text-sm text-muted-foreground">
                            {{ purchaseOrder.data.vendor?.phone }} ·
                            {{ purchaseOrder.data.vendor?.email }}
                        </div>
                        <div class="mt-2 text-sm">
                            {{ purchaseOrder.data.vendor?.address }}
                        </div>
                    </div>
                </div>

                <div class="rounded-lg bg-white p-4 shadow sm:p-6">
                    <h3 class="text-base font-medium">Notes</h3>
                    <div
                        class="mt-2 text-sm text-muted-foreground"
                        v-if="purchaseOrder.data.note"
                    >
                        {{ purchaseOrder.data.note }}
                    </div>
                    <div v-else class="text-sm text-muted-foreground">
                        No notes.
                    </div>
                </div>
            </aside>
        </div>
    </div>
</template>

<!--<template>-->
<!--    <p>{{purchaseOrder.data}}</p>-->
<!--</template>-->

<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { defineProps } from 'vue';

type PurchaseOrder = any; // you can replace `any` with a stricter interface if desired

const props = defineProps<{ purchaseOrder: PurchaseOrder }>();
const purchaseOrder = props.purchaseOrder;

function formatCurrency(value: number | string | null) {
    const v = Number(value ?? 0);
    // change currency to suit your store (NGN, USD, etc.)
    return new Intl.NumberFormat(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(v);
}

function formatDate(date?: string | null) {
    if (!date) return '—';
    try {
        const d = new Date(date);
        return d.toLocaleDateString();
    } catch {
        return date;
    }
}

function statusBadgeClass(status: string | undefined) {
    switch (status) {
        case 'draft':
            return 'bg-gray-100 text-gray-800';
        case 'sent':
            return 'bg-blue-100 text-blue-800';
        case 'partially_received':
            return 'bg-yellow-100 text-yellow-800';
        case 'received':
            return 'bg-green-100 text-green-800';
        case 'closed':
            return 'bg-slate-100 text-slate-800';
        case 'cancelled':
            return 'bg-red-100 text-red-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}

function printPage() {
    window.print();
}
</script>

<style scoped>
/* small visual helpers if your shadcn-like classes are not present */
.text-muted-foreground {
    color: rgba(100, 116, 139, 1);
}
</style>
