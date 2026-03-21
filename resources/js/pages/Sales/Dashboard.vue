<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    stats: {
        sales_today: number;
        orders_today: number;
        customers_today: number;
    };
    recentSales: Array<{
        id: number;
        total_amount: number;
        customer_name: string;
        order_number: string | null;
        order_status: string | null;
        created_at: string | null;
    }>;
}>();

const money = (value: number) => new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN' }).format(Number(value || 0));
</script>

<template>
    <Head title="Sales Workspace" />

    <div class="space-y-6 bg-slate-50 p-6">
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <p class="text-sm font-medium uppercase tracking-[0.24em] text-emerald-600">Sales Workspace</p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-900">A focused desk for customer-facing sales work</h1>
            <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-600">Use POS, review order activity, and manage customer records without exposing catalog, inventory, or director-only operations.</p>
            <div class="mt-5 flex flex-wrap gap-3">
                <Link href="/sales/pos" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Open POS</Link>
                <Link href="/sales/orders" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">View orders</Link>
                <Link href="/sales/customers" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Customers</Link>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-3">
            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-slate-500">Sales today</p>
                <p class="mt-3 text-3xl font-semibold text-slate-900">{{ money(stats.sales_today) }}</p>
            </div>
            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-slate-500">POS orders today</p>
                <p class="mt-3 text-3xl font-semibold text-slate-900">{{ stats.orders_today }}</p>
            </div>
            <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm font-medium text-slate-500">Unique customers today</p>
                <p class="mt-3 text-3xl font-semibold text-slate-900">{{ stats.customers_today }}</p>
            </div>
        </section>

        <section class="rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-6 py-4">
                <h2 class="text-lg font-semibold text-slate-900">Recent sales activity</h2>
            </div>
            <div v-if="recentSales.length" class="divide-y divide-slate-200">
                <div v-for="sale in recentSales" :key="sale.id" class="grid gap-3 px-6 py-4 md:grid-cols-[1.2fr_1fr_1fr_1fr]">
                    <div>
                        <p class="font-semibold text-slate-900">{{ sale.customer_name }}</p>
                        <p class="mt-1 text-sm text-slate-500">{{ sale.created_at ? new Date(sale.created_at).toLocaleString() : 'Recently recorded' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Order</p>
                        <p class="mt-1 text-sm text-slate-900">{{ sale.order_number || 'Pending' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Status</p>
                        <p class="mt-1 text-sm text-slate-900">{{ sale.order_status || 'Draft' }}</p>
                    </div>
                    <div class="text-left md:text-right">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Amount</p>
                        <p class="mt-1 text-sm font-semibold text-slate-900">{{ money(sale.total_amount) }}</p>
                    </div>
                </div>
            </div>
            <div v-else class="px-6 py-14 text-center text-sm text-slate-500">No sales have been recorded yet for this workspace.</div>
        </section>
    </div>
</template>
