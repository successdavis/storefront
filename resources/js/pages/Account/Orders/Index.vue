<script setup lang="ts">
import OrderProgressTracker from '@/components/Orders/OrderProgressTracker.vue';
import Pagination from '@/components/Pagination.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps<{
    orders: {
        data: Array<{
            id: number;
            order_number: string;
            status_label: string;
            payment_status_label: string;
            fulfillment_status_label: string;
            total_amount: number;
            currency: string;
            item_count: number;
            created_at: string | null;
            tracker: {
                steps: Array<{ key: string; label: string; status: string; timestamp?: string | null }>;
                state?: { kind: string; label: string; description: string } | null;
            };
        }>;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
}>();

const money = (value: number, currency = 'NGN') =>
    new Intl.NumberFormat('en-NG', { style: 'currency', currency }).format(Number(value || 0));

function formatDate(value: string | null) {
    return value ? new Date(value).toLocaleString() : 'Recently placed';
}

function badgeClass(status: string) {
    const normalized = status.toLowerCase();

    if (normalized.includes('cancel')) return 'bg-rose-100 text-rose-700 dark:bg-rose-950/40 dark:text-rose-200';
    if (normalized.includes('deliver') || normalized.includes('complete')) return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-200';
    if (normalized.includes('ship') || normalized.includes('ready')) return 'bg-blue-100 text-blue-700 dark:bg-blue-950/40 dark:text-blue-200';
    if (normalized.includes('process') || normalized.includes('pack') || normalized.includes('paid')) return 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-200';

    return 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200';
}
</script>

<template>
    <Head title="My Orders" />

    <div class="space-y-6 bg-slate-50 p-6 dark:bg-slate-950">
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h1 class="text-3xl font-semibold tracking-tight text-slate-900 dark:text-slate-100">My Orders</h1>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                Track your payment and fulfillment progress from a single view.
            </p>
        </section>

        <section class="space-y-4">
            <Link
                v-for="order in orders.data"
                :key="order.id"
                :href="route('account.orders.show', order.id)"
                class="block rounded-3xl border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-0.5 hover:border-slate-300 dark:border-slate-800 dark:bg-slate-900 dark:hover:border-slate-700"
            >
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">Order</p>
                        <h2 class="mt-2 text-xl font-semibold text-slate-900 dark:text-slate-100">{{ order.order_number }}</h2>
                        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ formatDate(order.created_at) }}</p>
                    </div>
                    <div class="text-left sm:text-right">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">Total</p>
                        <p class="mt-2 text-xl font-semibold text-slate-900 dark:text-slate-100">{{ money(order.total_amount, order.currency) }}</p>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ order.item_count }} item(s)</p>
                    </div>
                </div>

                <div class="mt-5 flex flex-wrap gap-2">
                    <span :class="['inline-flex rounded-full px-3 py-1 text-xs font-semibold', badgeClass(order.status_label)]">{{ order.status_label }}</span>
                    <span :class="['inline-flex rounded-full px-3 py-1 text-xs font-semibold', badgeClass(order.payment_status_label)]">Payment: {{ order.payment_status_label }}</span>
                    <span :class="['inline-flex rounded-full px-3 py-1 text-xs font-semibold', badgeClass(order.fulfillment_status_label)]">Fulfillment: {{ order.fulfillment_status_label }}</span>
                </div>

                <div class="mt-6 rounded-3xl bg-slate-50 p-4 dark:bg-slate-950">
                    <OrderProgressTracker :tracker="order.tracker" />
                </div>
            </Link>

            <div v-if="orders.data.length === 0" class="rounded-3xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center dark:border-slate-700 dark:bg-slate-900">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">You have not placed an order yet</h2>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Your order history will appear here after checkout.</p>
                <Link href="/store" class="mt-4 inline-flex rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white dark:bg-slate-100 dark:text-slate-900">Shop now</Link>
            </div>
        </section>

        <Pagination :links="orders.links" />
    </div>
</template>
