<script setup lang="ts">
import Pagination from '@/components/Pagination.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps<{
    filters: Record<string, string | number | boolean | null>;
    summary_cards: Array<{ key: string; label: string; value: number }>;
    filter_options: {
        statuses: Array<{ value: string; label: string }>;
        verification: Array<{ value: string; label: string }>;
        order_presence: Array<{ value: string; label: string }>;
        dormant_days: Array<{ value: string; label: string }>;
        sorts: Array<{ value: string; label: string }>;
        high_value_threshold: number;
    };
    bulk_actions: Array<{ value: string; label: string }>;
    customers: {
        data: Array<any>;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
}>();

const filters = reactive({
    search: props.filters.search || '',
    status: props.filters.status || '',
    email_verified: props.filters.email_verified || '',
    has_orders: props.filters.has_orders || '',
    registered_from: props.filters.registered_from || '',
    registered_to: props.filters.registered_to || '',
    last_login_from: props.filters.last_login_from || '',
    last_login_to: props.filters.last_login_to || '',
    dormant_days: props.filters.dormant_days || '',
    high_value: Boolean(props.filters.high_value || false),
    sort: props.filters.sort || 'newest',
    per_page: props.filters.per_page || 15,
});

const selectedIds = ref<number[]>([]);
const bulkForm = useForm({
    customer_ids: [] as number[],
    action: '',
});

let filterTimeout: number | undefined;

watch(
    () => ({ ...filters }),
    (value) => {
        window.clearTimeout(filterTimeout);
        filterTimeout = window.setTimeout(() => {
            router.get(route('admin.customers.index'), buildFilterPayload(value), {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        }, 350);
    },
    { deep: true },
);

watch(
    () => props.filters,
    (incoming) => {
        filters.search = (incoming.search as string) || '';
        filters.status = (incoming.status as string) || '';
        filters.email_verified = (incoming.email_verified as string) || '';
        filters.has_orders = (incoming.has_orders as string) || '';
        filters.registered_from = (incoming.registered_from as string) || '';
        filters.registered_to = (incoming.registered_to as string) || '';
        filters.last_login_from = (incoming.last_login_from as string) || '';
        filters.last_login_to = (incoming.last_login_to as string) || '';
        filters.dormant_days = (incoming.dormant_days as string) || '';
        filters.high_value = Boolean(incoming.high_value || false);
        filters.sort = (incoming.sort as string) || 'newest';
        filters.per_page = Number(incoming.per_page || 15);
    },
    { deep: true },
);

watch(
    () => props.customers.data.map((customer: any) => customer.id),
    (currentIds) => {
        selectedIds.value = selectedIds.value.filter((id) => currentIds.includes(id));
    },
);

const allVisibleSelected = computed(() => (
    props.customers.data.length > 0
    && props.customers.data.every((customer: any) => selectedIds.value.includes(customer.id))
));

function toggleAll() {
    selectedIds.value = allVisibleSelected.value
        ? []
        : props.customers.data.map((customer: any) => customer.id);
}

function money(value: number, currency = 'NGN') {
    return new Intl.NumberFormat('en-NG', { style: 'currency', currency }).format(Number(value || 0));
}

function badgeClass(status: string) {
    const normalized = String(status || '').toLowerCase();

    if (normalized.includes('suspend')) return 'bg-rose-100 text-rose-700 dark:bg-rose-950/40 dark:text-rose-200';
    if (normalized.includes('inactive')) return 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-200';

    return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-200';
}

function segmentLabel(segment: string) {
    return String(segment || '').replaceAll('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase());
}

function runBulkAction() {
    if (!selectedIds.value.length || !bulkForm.action) {
        return;
    }

    bulkForm.customer_ids = [...selectedIds.value];
    bulkForm.post(route('admin.customers.bulk'), {
        preserveScroll: true,
        onSuccess: () => {
            selectedIds.value = [];
            bulkForm.reset('action');
        },
    });
}

function exportFiltered(selectedOnly = false) {
    const payload: Record<string, any> = {
        ...buildFilterPayload(filters),
    };

    if (selectedOnly && selectedIds.value.length) {
        payload.ids = selectedIds.value.join(',');
    }

    window.location.href = route('admin.customers.export', payload);
}

function buildFilterPayload(source: typeof filters) {
    return {
        search: source.search || undefined,
        status: source.status || undefined,
        email_verified: source.email_verified || undefined,
        has_orders: source.has_orders || undefined,
        registered_from: source.registered_from || undefined,
        registered_to: source.registered_to || undefined,
        last_login_from: source.last_login_from || undefined,
        last_login_to: source.last_login_to || undefined,
        dormant_days: source.dormant_days || undefined,
        high_value: source.high_value ? 1 : undefined,
        sort: source.sort || 'newest',
        per_page: source.per_page || 15,
    };
}
</script>

<template>
    <Head title="Customers" />

    <div class="space-y-6 px-5 py-4">
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold text-slate-900 dark:text-slate-100">Customers</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500 dark:text-slate-400">
                        Review customer lifecycle, order value, recent activity, and account health from one admin workspace.
                    </p>
                </div>

                <div class="flex flex-wrap gap-3">
                    <button
                        type="button"
                        class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-100 dark:hover:bg-slate-800"
                        @click="exportFiltered(false)"
                    >
                        Export CSV
                    </button>
                    <button
                        type="button"
                        class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700 disabled:opacity-40 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-300"
                        :disabled="!selectedIds.length"
                        @click="exportFiltered(true)"
                    >
                        Export Selected
                    </button>
                </div>
            </div>

            <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-6">
                <div
                    v-for="card in summary_cards"
                    :key="card.key"
                    class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 dark:border-slate-800 dark:bg-slate-950"
                >
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">{{ card.label }}</p>
                    <p class="mt-3 text-3xl font-semibold text-slate-900 dark:text-slate-100">{{ card.value }}</p>
                </div>
            </div>

            <div class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                <input v-model="filters.search" type="search" placeholder="Search customer, email, phone, order #" class="h-11 rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 placeholder:text-slate-400 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100 dark:placeholder:text-slate-500">
                <select v-model="filters.status" class="h-11 rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                    <option v-for="option in filter_options.statuses" :key="option.value" :value="option.value">{{ option.label }}</option>
                </select>
                <select v-model="filters.email_verified" class="h-11 rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                    <option v-for="option in filter_options.verification" :key="option.value" :value="option.value">{{ option.label }}</option>
                </select>
                <select v-model="filters.has_orders" class="h-11 rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                    <option v-for="option in filter_options.order_presence" :key="option.value" :value="option.value">{{ option.label }}</option>
                </select>
                <select v-model="filters.dormant_days" class="h-11 rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                    <option v-for="option in filter_options.dormant_days" :key="option.value" :value="option.value">{{ option.label }}</option>
                </select>
                <input v-model="filters.registered_from" type="date" class="h-11 rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                <input v-model="filters.registered_to" type="date" class="h-11 rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                <input v-model="filters.last_login_from" type="date" class="h-11 rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                <input v-model="filters.last_login_to" type="date" class="h-11 rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                <select v-model="filters.sort" class="h-11 rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                    <option v-for="option in filter_options.sorts" :key="option.value" :value="option.value">Sort: {{ option.label }}</option>
                </select>
                <label class="inline-flex items-center gap-3 rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-700 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                    <input v-model="filters.high_value" type="checkbox" class="rounded border-slate-300 text-slate-900 focus:ring-slate-500">
                    High value ({{ money(filter_options.high_value_threshold) }}+)
                </label>
                <select v-model="filters.per_page" class="h-11 rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                    <option :value="15">15 per page</option>
                    <option :value="25">25 per page</option>
                    <option :value="50">50 per page</option>
                    <option :value="100">100 per page</option>
                </select>
            </div>
        </section>

        <section class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="flex flex-wrap items-center gap-3">
                <select v-model="bulkForm.action" class="h-10 rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                    <option value="">Bulk actions</option>
                    <option v-for="action in bulk_actions" :key="action.value" :value="action.value">{{ action.label }}</option>
                </select>
                <button type="button" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700 disabled:opacity-40 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-300" :disabled="!selectedIds.length || !bulkForm.action || bulkForm.processing" @click="runBulkAction">
                    Apply to {{ selectedIds.length }} selected
                </button>
            </div>
        </section>

        <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead class="bg-slate-50 dark:bg-slate-950">
                        <tr class="text-left text-xs uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">
                            <th class="px-5 py-4"><input type="checkbox" :checked="allVisibleSelected" @change="toggleAll"></th>
                            <th class="px-5 py-4">Customer</th>
                            <th class="px-5 py-4">Status</th>
                            <th class="px-5 py-4 text-right">Orders</th>
                            <th class="px-5 py-4 text-right">Spend</th>
                            <th class="px-5 py-4">Segment</th>
                            <th class="px-5 py-4">Last Login</th>
                            <th class="px-5 py-4">Last Order</th>
                            <th class="px-5 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white dark:divide-slate-800 dark:bg-slate-900">
                        <tr v-for="customer in customers.data" :key="customer.id" class="align-top">
                            <td class="px-5 py-4"><input v-model="selectedIds" type="checkbox" :value="customer.id"></td>
                            <td class="px-5 py-4">
                                <p class="font-semibold text-slate-900 dark:text-slate-100">{{ customer.name }}</p>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ customer.email }}</p>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ customer.phone || 'No phone provided' }}</p>
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex flex-wrap gap-2">
                                    <span :class="['inline-flex rounded-full px-2.5 py-1 text-xs font-semibold', badgeClass(customer.status)]">{{ customer.status_label }}</span>
                                    <span v-if="customer.email_verified" class="inline-flex rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-200">Verified</span>
                                    <span v-else class="inline-flex rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700 dark:bg-amber-950/40 dark:text-amber-200">Unverified</span>
                                    <span v-if="customer.is_vip" class="inline-flex rounded-full bg-fuchsia-100 px-2.5 py-1 text-xs font-semibold text-fuchsia-700 dark:bg-fuchsia-950/40 dark:text-fuchsia-200">VIP</span>
                                    <span v-if="customer.is_risky" class="inline-flex rounded-full bg-rose-100 px-2.5 py-1 text-xs font-semibold text-rose-700 dark:bg-rose-950/40 dark:text-rose-200">Risk</span>
                                </div>
                            </td>
                            <td class="px-5 py-4 text-right font-semibold text-slate-900 dark:text-slate-100">{{ customer.total_orders }}</td>
                            <td class="px-5 py-4 text-right font-semibold text-slate-900 dark:text-slate-100">{{ money(customer.total_spend) }}</td>
                            <td class="px-5 py-4 text-slate-600 dark:text-slate-300">{{ segmentLabel(customer.segment) }}</td>
                            <td class="px-5 py-4 text-slate-600 dark:text-slate-300">{{ customer.last_login_at ? new Date(customer.last_login_at).toLocaleString() : 'Never' }}</td>
                            <td class="px-5 py-4 text-slate-600 dark:text-slate-300">{{ customer.last_order_at ? new Date(customer.last_order_at).toLocaleDateString() : 'No orders yet' }}</td>
                            <td class="px-5 py-4 text-right">
                                <Link :href="route('admin.customers.show', customer.route_key || customer.customer_slug || customer.id)" class="inline-flex rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white transition hover:bg-slate-700 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-300">
                                    View profile
                                </Link>
                            </td>
                        </tr>
                        <tr v-if="customers.data.length === 0">
                            <td colspan="9" class="px-5 py-14 text-center text-sm text-slate-500 dark:text-slate-400">No customers matched the current filters.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <Pagination :links="customers.links" />
    </div>
</template>
