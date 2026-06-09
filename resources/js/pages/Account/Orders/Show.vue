<script setup lang="ts">
import OrderProgressTracker from '@/components/Orders/OrderProgressTracker.vue';
import OrderTimeline from '@/components/Orders/OrderTimeline.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps<{
    order: any;
}>();

const money = (value: number, currency = 'NGN') =>
    new Intl.NumberFormat('en-NG', { style: 'currency', currency }).format(Number(value || 0));

function badgeClass(status: string) {
    const normalized = String(status || '').toLowerCase();

    if (normalized.includes('cancel')) return 'bg-rose-100 text-rose-700 dark:bg-rose-950/40 dark:text-rose-200';
    if (normalized.includes('deliver') || normalized.includes('complete')) return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-200';
    if (normalized.includes('ship') || normalized.includes('ready')) return 'bg-blue-100 text-blue-700 dark:bg-blue-950/40 dark:text-blue-200';
    if (normalized.includes('process') || normalized.includes('pack') || normalized.includes('paid')) return 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-200';

    return 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200';
}
</script>

<template>
    <Head :title="order.order_number" />

    <div class="space-y-6 bg-slate-50 p-6 dark:bg-slate-950">
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <Link :href="route('account.orders.index')" class="text-sm font-medium text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">Back to orders</Link>
                    <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-900 dark:text-slate-100">{{ order.order_number }}</h1>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Placed {{ order.created_at ? new Date(order.created_at).toLocaleString() : 'recently' }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <span :class="['inline-flex rounded-full px-3 py-1 text-xs font-semibold', badgeClass(order.status_label)]">{{ order.status_label }}</span>
                    <span :class="['inline-flex rounded-full px-3 py-1 text-xs font-semibold', badgeClass(order.payment_status_label)]">Payment: {{ order.payment_status_label }}</span>
                    <span :class="['inline-flex rounded-full px-3 py-1 text-xs font-semibold', badgeClass(order.fulfillment_status_label)]">Fulfillment: {{ order.fulfillment_status_label }}</span>
                </div>
            </div>
        </section>

        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <OrderProgressTracker :tracker="order.tracker" />
        </section>

        <section class="grid gap-6 xl:grid-cols-[1.8fr_1fr]">
            <div class="space-y-6">
                <div class="rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="border-b border-slate-200 px-6 py-4 dark:border-slate-800">
                        <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Items</h2>
                    </div>
                    <div class="divide-y divide-slate-200 dark:divide-slate-800">
                        <div v-for="item in order.items" :key="item.id" class="flex flex-wrap gap-4 px-6 py-5">
                            <img v-if="item.product.image" :src="item.product.image" :alt="item.product.name ?? 'Product image'" class="h-20 w-20 rounded-2xl object-cover" />
                            <div v-else class="flex h-20 w-20 items-center justify-center rounded-2xl bg-slate-100 text-xs text-slate-500 dark:bg-slate-800 dark:text-slate-400">No image</div>
                            <div class="flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="font-semibold text-slate-900 dark:text-slate-100">{{ item.product.name }}</p>
                                    <span v-if="item.fulfillment_type === 'dropshipping'" :class="['inline-flex rounded-full px-2.5 py-1 text-xs font-semibold', badgeClass(item.customer_dropship_status_label)]">{{ item.customer_dropship_status_label }}</span>
                                </div>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ item.variant.label || item.variant.sku }}</p>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Quantity: {{ item.quantity }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm text-slate-500 dark:text-slate-400">{{ money(item.price, order.currency) }} each</p>
                                <p class="mt-1 text-base font-semibold text-slate-900 dark:text-slate-100">{{ money(item.subtotal, order.currency) }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Order timeline</h2>
                    <div class="mt-4">
                        <OrderTimeline :timeline="order.timeline" />
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Order Summary</h2>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div class="flex justify-between text-slate-600 dark:text-slate-300"><dt>Subtotal</dt><dd>{{ money(order.subtotal, order.currency) }}</dd></div>
                        <div class="flex justify-between text-slate-600 dark:text-slate-300"><dt>Shipping</dt><dd>{{ money(order.shipping_total, order.currency) }}</dd></div>
                        <div class="flex justify-between text-slate-600 dark:text-slate-300"><dt>Tax</dt><dd>{{ money(order.tax_total, order.currency) }}</dd></div>
                        <div class="flex justify-between text-slate-600 dark:text-slate-300"><dt>Discount</dt><dd>-{{ money(order.discount, order.currency) }}</dd></div>
                        <div class="flex justify-between border-t border-slate-200 pt-3 text-base font-semibold text-slate-900 dark:border-slate-800 dark:text-slate-100"><dt>Total</dt><dd>{{ money(order.total_amount, order.currency) }}</dd></div>
                    </dl>
                </div>

                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Payment Summary</h2>
                    <div class="mt-4 space-y-3 text-sm text-slate-600 dark:text-slate-300">
                        <div class="flex justify-between"><span>Status</span><span class="font-medium text-slate-900 dark:text-slate-100">{{ order.payment_summary.status_label }}</span></div>
                        <div class="flex justify-between"><span>Paid</span><span>{{ money(order.payment_summary.paid_amount, order.currency) }}</span></div>
                        <div class="flex justify-between"><span>Refunded</span><span>{{ money(order.payment_summary.refunded_amount, order.currency) }}</span></div>
                        <div class="flex justify-between"><span>Outstanding</span><span>{{ money(order.payment_summary.outstanding_amount, order.currency) }}</span></div>
                    </div>

                    <div v-if="order.payments.length" class="mt-5 space-y-3">
                        <div v-for="payment in order.payments" :key="payment.id" class="rounded-2xl bg-slate-50 p-4 dark:bg-slate-950">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ payment.method }}</p>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ payment.reference || 'No reference supplied' }}</p>
                                </div>
                                <span :class="['inline-flex rounded-full px-3 py-1 text-xs font-semibold', badgeClass(payment.status_label)]">{{ payment.status_label }}</span>
                            </div>
                            <p class="mt-3 text-sm text-slate-600 dark:text-slate-300">{{ money(payment.amount, payment.currency) }}</p>
                        </div>
                    </div>
                </div>

                <div v-if="order.shipment" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Shipment</h2>
                    <div class="mt-4 space-y-3 text-sm text-slate-600 dark:text-slate-300">
                        <div class="flex justify-between"><span>Type</span><span class="font-medium text-slate-900 dark:text-slate-100">{{ order.shipment.type_label }}</span></div>
                        <div class="flex justify-between"><span>Status</span><span class="font-medium text-slate-900 dark:text-slate-100">{{ order.shipment.status_label }}</span></div>
                        <div v-if="order.shipment.method" class="flex justify-between"><span>Method</span><span>{{ order.shipment.method }}</span></div>
                        <div v-if="order.shipment.courier_name" class="flex justify-between"><span>Courier</span><span>{{ order.shipment.courier_name }}</span></div>
                        <div v-if="order.shipment.tracking_number" class="flex justify-between"><span>Tracking</span><span>{{ order.shipment.tracking_number }}</span></div>
                    </div>

                    <div v-if="order.shipping_address || order.billing_address" class="mt-5 space-y-3">
                        <div v-if="order.shipping_address" class="rounded-2xl bg-slate-50 p-4 dark:bg-slate-950">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Shipping address</p>
                            <p class="mt-2 text-sm font-medium text-slate-900 dark:text-slate-100">{{ order.shipping_address.name }}</p>
                            <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ order.shipping_address.line1 }}<span v-if="order.shipping_address.line2">, {{ order.shipping_address.line2 }}</span></p>
                            <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ [order.shipping_address.lga, order.shipping_address.state, order.shipping_address.country].filter(Boolean).join(', ') }}</p>
                        </div>
                        <div v-if="order.shipment.pickup" class="rounded-2xl bg-slate-50 p-4 dark:bg-slate-950">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Pickup details</p>
                            <p class="mt-2 text-sm font-medium text-slate-900 dark:text-slate-100">{{ order.shipment.pickup.location?.name || 'Pickup location' }}</p>
                            <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Reference: {{ order.shipment.pickup.reference }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>
