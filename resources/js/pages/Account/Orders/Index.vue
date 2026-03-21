<script setup lang="ts">
import Pagination from '@/components/Pagination.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    orders: {
        data: Array<{
            id: number;
            order_number: string;
            status: string;
            total_amount: number;
            currency: string;
            item_count: number;
            created_at: string | null;
        }>;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
}>();

const money = (value: number, currency = 'NGN') =>
    new Intl.NumberFormat('en-NG', { style: 'currency', currency }).format(Number(value || 0));
</script>

<template>
    <Head title="My Orders" />

    <div class="space-y-6 bg-slate-50 p-6">
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <h1 class="text-3xl font-semibold tracking-tight text-slate-900">My Orders</h1>
            <p class="mt-2 text-sm text-slate-500">Every order placed through your customer account, with secure access to your own history only.</p>
        </section>

        <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div v-if="orders.data.length" class="divide-y divide-slate-200">
                <Link
                    v-for="order in orders.data"
                    :key="order.id"
                    :href="`/account/orders/${order.id}`"
                    class="grid gap-3 px-6 py-5 transition hover:bg-slate-50 md:grid-cols-[1.5fr_1fr_1fr_1fr]"
                >
                    <div>
                        <p class="font-semibold text-slate-900">{{ order.order_number }}</p>
                        <p class="mt-1 text-sm text-slate-500">{{ order.created_at ? new Date(order.created_at).toLocaleString() : 'Recently placed' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Status</p>
                        <p class="mt-1 text-sm font-medium text-slate-900">{{ order.status }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Items</p>
                        <p class="mt-1 text-sm font-medium text-slate-900">{{ order.item_count }}</p>
                    </div>
                    <div class="text-left md:text-right">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Total</p>
                        <p class="mt-1 text-sm font-semibold text-slate-900">{{ money(order.total_amount, order.currency) }}</p>
                    </div>
                </Link>
            </div>

            <div v-else class="px-6 py-16 text-center">
                <h2 class="text-lg font-semibold text-slate-900">You have not placed an order yet</h2>
                <p class="mt-2 text-sm text-slate-500">Your order history will appear here after checkout.</p>
                <Link href="/store" class="mt-4 inline-flex rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Shop now</Link>
            </div>
        </section>

        <Pagination :links="orders.links" />
    </div>
</template>
