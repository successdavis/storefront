<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    order: {
        id: number;
        order_number: string;
        status: string;
        subtotal: number;
        shipping_total: number;
        tax_total: number;
        discount: number;
        total_amount: number;
        currency: string;
        created_at: string | null;
        items: Array<{
            id: number;
            quantity: number;
            price: number;
            subtotal: number;
            product: { name: string | null; slug: string | null; image: string | null };
            variant: { sku: string | null; label: string | null };
        }>;
        payments: Array<{
            id: number;
            method: string;
            amount: number;
            currency: string;
            status: string;
            paid_at: string | null;
            reference: string | null;
        }>;
        shipment: null | {
            status: string;
            method: string | null;
            addresses: Array<{
                type: string;
                name: string | null;
                phone: string | null;
                line1: string | null;
                line2: string | null;
                state: string | null;
                lga: string | null;
                country: string | null;
            }>;
        };
    };
}>();

const money = (value: number, currency = 'NGN') =>
    new Intl.NumberFormat('en-NG', { style: 'currency', currency }).format(Number(value || 0));
</script>

<template>
    <Head :title="order.order_number" />

    <div class="space-y-6 bg-slate-50 p-6">
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <Link href="/account/orders" class="text-sm font-medium text-slate-500 hover:text-slate-700">Back to orders</Link>
                    <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-900">{{ order.order_number }}</h1>
                    <p class="mt-2 text-sm text-slate-500">{{ order.created_at ? new Date(order.created_at).toLocaleString() : 'Recently placed' }}</p>
                </div>
                <div class="rounded-2xl bg-slate-900 px-4 py-3 text-white">
                    <p class="text-xs uppercase tracking-[0.2em] text-slate-300">Status</p>
                    <p class="mt-1 text-base font-semibold">{{ order.status }}</p>
                </div>
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-[1.8fr_1fr]">
            <div class="space-y-6">
                <div class="rounded-3xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-6 py-4">
                        <h2 class="text-lg font-semibold text-slate-900">Items</h2>
                    </div>
                    <div class="divide-y divide-slate-200">
                        <div v-for="item in order.items" :key="item.id" class="flex flex-wrap gap-4 px-6 py-5">
                            <img v-if="item.product.image" :src="item.product.image" :alt="item.product.name ?? 'Product image'" class="h-20 w-20 rounded-2xl object-cover" />
                            <div v-else class="flex h-20 w-20 items-center justify-center rounded-2xl bg-slate-100 text-xs text-slate-500">No image</div>
                            <div class="flex-1">
                                <p class="font-semibold text-slate-900">{{ item.product.name }}</p>
                                <p class="mt-1 text-sm text-slate-500">{{ item.variant.label || item.variant.sku }}</p>
                                <p class="mt-1 text-sm text-slate-500">Quantity: {{ item.quantity }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm text-slate-500">{{ money(item.price, order.currency) }} each</p>
                                <p class="mt-1 text-base font-semibold text-slate-900">{{ money(item.subtotal, order.currency) }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rounded-3xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-6 py-4">
                        <h2 class="text-lg font-semibold text-slate-900">Payments</h2>
                    </div>
                    <div v-if="order.payments.length" class="divide-y divide-slate-200">
                        <div v-for="payment in order.payments" :key="payment.id" class="grid gap-3 px-6 py-4 md:grid-cols-[1fr_1fr_1fr]">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Method</p>
                                <p class="mt-1 text-sm font-medium text-slate-900">{{ payment.method }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Reference</p>
                                <p class="mt-1 text-sm text-slate-900">{{ payment.reference || 'Not supplied' }}</p>
                            </div>
                            <div class="text-left md:text-right">
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Amount</p>
                                <p class="mt-1 text-sm font-semibold text-slate-900">{{ money(payment.amount, payment.currency) }}</p>
                            </div>
                        </div>
                    </div>
                    <div v-else class="px-6 py-10 text-sm text-slate-500">No payment records are attached to this order yet.</div>
                </div>
            </div>

            <div class="space-y-6">
                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-slate-900">Order Summary</h2>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div class="flex justify-between text-slate-600"><dt>Subtotal</dt><dd>{{ money(order.subtotal, order.currency) }}</dd></div>
                        <div class="flex justify-between text-slate-600"><dt>Shipping</dt><dd>{{ money(order.shipping_total, order.currency) }}</dd></div>
                        <div class="flex justify-between text-slate-600"><dt>Tax</dt><dd>{{ money(order.tax_total, order.currency) }}</dd></div>
                        <div class="flex justify-between text-slate-600"><dt>Discount</dt><dd>-{{ money(order.discount, order.currency) }}</dd></div>
                        <div class="flex justify-between border-t border-slate-200 pt-3 text-base font-semibold text-slate-900"><dt>Total</dt><dd>{{ money(order.total_amount, order.currency) }}</dd></div>
                    </dl>
                </div>

                <div v-if="order.shipment" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-slate-900">Shipment</h2>
                    <p class="mt-3 text-sm text-slate-600">Method: <span class="font-medium text-slate-900">{{ order.shipment.method || 'Pending assignment' }}</span></p>
                    <p class="mt-2 text-sm text-slate-600">Status: <span class="font-medium text-slate-900">{{ order.shipment.status }}</span></p>

                    <div v-if="order.shipment.addresses.length" class="mt-4 space-y-3">
                        <div v-for="address in order.shipment.addresses" :key="`${address.type}-${address.line1}`" class="rounded-2xl bg-slate-50 p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ address.type }}</p>
                            <p class="mt-2 text-sm font-medium text-slate-900">{{ address.name }}</p>
                            <p class="mt-1 text-sm text-slate-600">{{ address.line1 }}<span v-if="address.line2">, {{ address.line2 }}</span></p>
                            <p class="mt-1 text-sm text-slate-600">{{ [address.lga, address.state, address.country].filter(Boolean).join(', ') }}</p>
                            <p v-if="address.phone" class="mt-1 text-sm text-slate-600">{{ address.phone }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>
