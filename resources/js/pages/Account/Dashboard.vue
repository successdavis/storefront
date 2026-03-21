<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    stats: {
        orders: number;
        wishlist: number;
        saved_for_later: number;
        addresses: number;
    };
    recentOrders: Array<{
        id: number;
        order_number: string;
        status: string;
        total_amount: number;
        currency: string;
        created_at: string | null;
    }>;
}>();

const cards = [
    { key: 'orders', title: 'Orders', href: '/account/orders' },
    { key: 'wishlist', title: 'Wishlist', href: '/account/wishlist' },
    { key: 'saved_for_later', title: 'Saved for Later', href: '/account/saved-for-later' },
    { key: 'addresses', title: 'Addresses', href: '/account/addresses' },
];

const money = (value: number, currency = 'NGN') =>
    new Intl.NumberFormat('en-NG', { style: 'currency', currency }).format(Number(value || 0));
</script>

<template>
    <Head title="My Account" />

    <div class="space-y-8 bg-slate-50 p-6">
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-sm font-medium uppercase tracking-[0.24em] text-amber-600">Customer Account</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-900">Account overview built for repeat shoppers</h1>
            <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-600">
                Review recent orders, keep products handy in your wishlist and saved list, and manage the addresses you use most often.
            </p>
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <Link
                v-for="card in cards"
                :key="card.key"
                :href="card.href"
                class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-slate-300"
            >
                <p class="text-sm font-medium text-slate-500">{{ card.title }}</p>
                <p class="mt-3 text-3xl font-semibold text-slate-900">{{ stats[card.key as keyof typeof stats] }}</p>
            </Link>
        </section>

        <section class="rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">Recent orders</h2>
                    <p class="text-sm text-slate-500">Track the latest activity from your customer account.</p>
                </div>
                <Link href="/account/orders" class="text-sm font-semibold text-slate-700 hover:text-slate-900">View all</Link>
            </div>

            <div v-if="recentOrders.length" class="divide-y divide-slate-200">
                <Link
                    v-for="order in recentOrders"
                    :key="order.id"
                    :href="`/account/orders/${order.id}`"
                    class="flex flex-wrap items-center justify-between gap-4 px-6 py-4 transition hover:bg-slate-50"
                >
                    <div>
                        <p class="font-semibold text-slate-900">{{ order.order_number }}</p>
                        <p class="mt-1 text-sm text-slate-500">{{ order.created_at ? new Date(order.created_at).toLocaleString() : 'Recently placed' }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-medium uppercase tracking-wide text-slate-500">{{ order.status }}</p>
                        <p class="mt-1 text-base font-semibold text-slate-900">{{ money(order.total_amount, order.currency) }}</p>
                    </div>
                </Link>
            </div>

            <div v-else class="px-6 py-12 text-center">
                <h3 class="text-lg font-semibold text-slate-900">No orders yet</h3>
                <p class="mt-2 text-sm text-slate-500">When you complete your first purchase, it will appear here.</p>
                <Link href="/store" class="mt-4 inline-flex rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Explore products</Link>
            </div>
        </section>
    </div>
</template>
