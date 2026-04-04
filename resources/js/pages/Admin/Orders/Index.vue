<script setup lang="ts">
import Pagination from '@/components/Pagination.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps<{
    filters: Record<string, string | number | null>;
    summary_cards: Array<{ key: string; label: string; value: number }>;
    filter_options: {
        payment_statuses: Array<{ value: string; label: string }>;
        order_statuses: Array<{ value: string; label: string }>;
        fulfillment_statuses: Array<{ value: string; label: string }>;
        channels: Array<{ value: string; label: string }>;
        sorts: Array<{ value: string; label: string }>;
    };
    orders: {
        data: Array<any>;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    bulk_actions: Array<{ value: string; label: string }>;
}>();

const filters = ref({
    search: props.filters.search || '',
    order_number: props.filters.order_number || '',
    customer: props.filters.customer || '',
    payment_status: props.filters.payment_status || '',
    order_status: props.filters.order_status || '',
    fulfillment_status: props.filters.fulfillment_status || '',
    channel: props.filters.channel || '',
    from: props.filters.from || '',
    to: props.filters.to || '',
    sort: props.filters.sort || 'newest',
    per_page: props.filters.per_page || 15,
});

const selectedIds = ref<number[]>([]);
const bulkForm = useForm({
    order_ids: [] as number[],
    action: '',
    note: '',
});

let filterTimeout: number | undefined;
watch(
    filters,
    () => {
        window.clearTimeout(filterTimeout);
        filterTimeout = window.setTimeout(() => {
            router.get(route('admin.orders.index'), filters.value, {
                preserveState: true,
                replace: true,
            });
        }, 350);
    },
    { deep: true }
);

watch(
    () => props.orders.data.map((order: any) => order.id),
    (currentIds) => {
        selectedIds.value = selectedIds.value.filter((id) => currentIds.includes(id));
    }
);

function money(value: number, currency = 'NGN') {
    return new Intl.NumberFormat('en-NG', { style: 'currency', currency }).format(Number(value || 0));
}

function badgeClass(status: string) {
    const normalized = String(status || '').toLowerCase();

    if (normalized.includes('cancel') || normalized.includes('fail')) return 'bg-rose-100 text-rose-700 dark:bg-rose-950/40 dark:text-rose-200';
    if (normalized.includes('deliver') || normalized.includes('complete') || normalized.includes('paid')) return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-200';
    if (normalized.includes('ship') || normalized.includes('ready')) return 'bg-blue-100 text-blue-700 dark:bg-blue-950/40 dark:text-blue-200';
    if (normalized.includes('process') || normalized.includes('pack') || normalized.includes('pending')) return 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-200';

    return 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200';
}

function allVisibleSelected() {
    return props.orders.data.length > 0 && props.orders.data.every((order: any) => selectedIds.value.includes(order.id));
}

function toggleAll() {
    selectedIds.value = allVisibleSelected() ? [] : props.orders.data.map((order: any) => order.id);
}

function runBulkAction() {
    if (!selectedIds.value.length || !bulkForm.action) {
        return;
    }

    bulkForm.order_ids = [...selectedIds.value];
    bulkForm.post(route('admin.orders.bulk'), {
        preserveScroll: true,
        onSuccess: () => {
            selectedIds.value = [];
            bulkForm.reset('action', 'note');
        },
    });
}
</script>

<template>
    <Head title="Orders" />

    <div class="space-y-6 px-5 py-4">
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold text-slate-900 dark:text-slate-100">Orders</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500 dark:text-slate-400">
                        Monitor storefront orders, payment state, and fulfillment progress from one workspace.
                    </p>
                </div>
            </div>

            <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div v-for="card in summary_cards" :key="card.key" class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 dark:border-slate-800 dark:bg-slate-950">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">{{ card.label }}</p>
                    <p class="mt-3 text-3xl font-semibold text-slate-900 dark:text-slate-100">{{ card.value }}</p>
                </div>
            </div>

            <div class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                <input v-model="filters.search" type="search" placeholder="Search order, customer, reference" class="h-11 rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 placeholder:text-slate-400 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100 dark:placeholder:text-slate-500">
                <input v-model="filters.customer" type="search" placeholder="Customer name, email, phone" class="h-11 rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 placeholder:text-slate-400 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100 dark:placeholder:text-slate-500">
                <select v-model="filters.payment_status" class="h-11 rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100"><option value="">All payment states</option><option v-for="option in filter_options.payment_statuses" :key="option.value" :value="option.value">{{ option.label }}</option></select>
                <select v-model="filters.order_status" class="h-11 rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100"><option value="">All order states</option><option v-for="option in filter_options.order_statuses" :key="option.value" :value="option.value">{{ option.label }}</option></select>
                <select v-model="filters.fulfillment_status" class="h-11 rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100"><option value="">All fulfillment states</option><option v-for="option in filter_options.fulfillment_statuses" :key="option.value" :value="option.value">{{ option.label }}</option></select>
                <input v-model="filters.from" type="date" class="h-11 rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                <input v-model="filters.to" type="date" class="h-11 rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                <select v-model="filters.channel" class="h-11 rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100"><option value="">All channels</option><option v-for="option in filter_options.channels" :key="option.value" :value="option.value">{{ option.label }}</option></select>
                <select v-model="filters.sort" class="h-11 rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100"><option v-for="option in filter_options.sorts" :key="option.value" :value="option.value">Sort: {{ option.label }}</option></select>
                <select v-model="filters.per_page" class="h-11 rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100"><option :value="15">15 per page</option><option :value="25">25 per page</option><option :value="50">50 per page</option></select>
            </div>
        </section>

        <section class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="flex flex-wrap items-center gap-3">
                <select v-model="bulkForm.action" class="h-10 rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100"><option value="">Bulk actions</option><option v-for="action in bulk_actions" :key="action.value" :value="action.value">{{ action.label }}</option></select>
                <input v-model="bulkForm.note" type="text" placeholder="Optional internal note" class="h-10 min-w-[220px] flex-1 rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 placeholder:text-slate-400 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100 dark:placeholder:text-slate-500">
                <button type="button" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700 disabled:opacity-40 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-300" :disabled="!selectedIds.length || !bulkForm.action || bulkForm.processing" @click="runBulkAction">Apply to {{ selectedIds.length }} selected</button>
            </div>
        </section>

        <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead class="bg-slate-50 dark:bg-slate-950">
                        <tr class="text-left text-xs uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">
                            <th class="px-5 py-4"><input type="checkbox" :checked="allVisibleSelected()" @change="toggleAll"></th>
                            <th class="px-5 py-4">Order</th>
                            <th class="px-5 py-4">Customer</th>
                            <th class="px-5 py-4">Payment</th>
                            <th class="px-5 py-4">Fulfillment</th>
                            <th class="px-5 py-4">Total</th>
                            <th class="px-5 py-4">Channel</th>
                            <th class="px-5 py-4">Placed</th>
                            <th class="px-5 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white dark:divide-slate-800 dark:bg-slate-900">
                        <tr v-for="order in orders.data" :key="order.id" class="align-top">
                            <td class="px-5 py-4"><input v-model="selectedIds" type="checkbox" :value="order.id"></td>
                            <td class="px-5 py-4"><p class="font-semibold text-slate-900 dark:text-slate-100">{{ order.order_number }}</p><div class="mt-2 flex flex-wrap gap-2"><span :class="['inline-flex rounded-full px-2.5 py-1 text-xs font-semibold', badgeClass(order.status_label)]">{{ order.status_label }}</span></div></td>
                            <td class="px-5 py-4 text-slate-600 dark:text-slate-300"><p class="font-medium text-slate-900 dark:text-slate-100">{{ order.customer?.name || 'Walk-in customer' }}</p><p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ order.customer?.email || 'No email' }}</p></td>
                            <td class="px-5 py-4"><span :class="['inline-flex rounded-full px-2.5 py-1 text-xs font-semibold', badgeClass(order.payment_status_label)]">{{ order.payment_status_label }}</span><p class="mt-2 text-xs text-slate-500 dark:text-slate-400">{{ order.payment_method || 'No method recorded' }}</p></td>
                            <td class="px-5 py-4"><span :class="['inline-flex rounded-full px-2.5 py-1 text-xs font-semibold', badgeClass(order.fulfillment_status_label)]">{{ order.fulfillment_status_label }}</span><p v-if="order.tracking_number" class="mt-2 text-xs text-slate-500 dark:text-slate-400">Tracking: {{ order.tracking_number }}</p></td>
                            <td class="px-5 py-4 font-semibold text-slate-900 dark:text-slate-100">{{ money(order.total_amount, order.currency) }}</td>
                            <td class="px-5 py-4 text-slate-600 dark:text-slate-300">{{ order.channel }}</td>
                            <td class="px-5 py-4 text-slate-600 dark:text-slate-300">{{ order.created_at ? new Date(order.created_at).toLocaleDateString() : '-' }}</td>
                            <td class="px-5 py-4 text-right"><Link :href="route('admin.orders.show', order.id)" class="inline-flex rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white transition hover:bg-slate-700 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-300">Manage</Link></td>
                        </tr>
                        <tr v-if="orders.data.length === 0"><td colspan="9" class="px-5 py-14 text-center text-sm text-slate-500 dark:text-slate-400">No orders matched the current filters.</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <Pagination :links="orders.links" />
    </div>
</template>
