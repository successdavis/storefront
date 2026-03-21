<script setup lang="ts">
import Pagination from '@/components/Pagination.vue';
import { Head } from '@inertiajs/vue3';

defineProps<{
    sales: {
        data: Array<{
            id: number;
            customer: { name: string; email: string | null };
            order: { id: number; order_number: string | null; status: string | null; total_amount: number; currency: string; created_at: string | null };
        }>;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
}>();

const money = (value: number, currency = 'NGN') => new Intl.NumberFormat('en-NG', { style: 'currency', currency }).format(Number(value || 0));
</script>

<template>
    <Head title="Sales Orders" />

    <div class="space-y-6 bg-slate-50 p-6">
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <h1 class="text-3xl font-semibold tracking-tight text-slate-900">Sales orders</h1>
            <p class="mt-2 text-sm text-slate-500">Operational order visibility for the sales team only.</p>
        </section>

        <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div v-if="sales.data.length" class="divide-y divide-slate-200">
                <div v-for="sale in sales.data" :key="sale.id" class="grid gap-3 px-6 py-4 md:grid-cols-[1.2fr_1fr_1fr_1fr]">
                    <div>
                        <p class="font-semibold text-slate-900">{{ sale.customer.name }}</p>
                        <p class="mt-1 text-sm text-slate-500">{{ sale.customer.email || 'No email on file' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Order number</p>
                        <p class="mt-1 text-sm text-slate-900">{{ sale.order.order_number || 'Pending' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Status</p>
                        <p class="mt-1 text-sm text-slate-900">{{ sale.order.status || 'Draft' }}</p>
                    </div>
                    <div class="text-left md:text-right">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Total</p>
                        <p class="mt-1 text-sm font-semibold text-slate-900">{{ money(sale.order.total_amount, sale.order.currency) }}</p>
                    </div>
                </div>
            </div>
            <div v-else class="px-6 py-14 text-center text-sm text-slate-500">No sales orders are available yet.</div>
        </section>

        <Pagination :links="sales.links" />
    </div>
</template>
