<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{
    filters: Record<string, any>;
    statuses: string[];
    suppliers: Array<{ id: number; name: string; active: boolean }>;
    fulfillments: any;
}>();

const selected = ref<any | null>(null);
const form = useForm({
    status: '',
    supplier_id: null as number | null,
    supplier_cost: '',
    supplier_reference: '',
    expected_delivery_at: '',
    admin_note: '',
    override: false,
});

const money = (value: number, currency = 'NGN') =>
    new Intl.NumberFormat('en-NG', { style: 'currency', currency }).format(Number(value || 0));

const filters = ref({ ...(props.filters ?? {}) });

function statusLabel(value: string) {
    return String(value || '')
        .split('_')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
}

function badgeClass(status: string) {
    if (['delivered', 'received', 'supplier_confirmed'].includes(status)) return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-200';
    if (['ordered_from_supplier', 'shipped_to_customer'].includes(status)) return 'bg-blue-100 text-blue-700 dark:bg-blue-950/40 dark:text-blue-200';
    if (['cancelled', 'unavailable'].includes(status)) return 'bg-rose-100 text-rose-700 dark:bg-rose-950/40 dark:text-rose-200';
    return 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-200';
}

function applyFilters() {
    router.get(route('admin.dropshipping.index'), filters.value, { preserveState: true, replace: true });
}

function openUpdate(row: any) {
    selected.value = row;
    form.status = row.status;
    form.supplier_id = row.supplier?.id ?? null;
    form.supplier_cost = row.supplier_cost || '';
    form.supplier_reference = row.supplier_reference || '';
    form.expected_delivery_at = row.expected_delivery_at ? row.expected_delivery_at.slice(0, 10) : '';
    form.admin_note = row.admin_note || '';
    form.override = false;
}

function submit() {
    if (!selected.value) return;
    form.patch(route('admin.dropshipping.update', selected.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            selected.value = null;
            form.reset();
        },
    });
}

const activeCount = computed(() =>
    (props.fulfillments.data ?? []).filter((row: any) => !['delivered', 'cancelled'].includes(row.status)).length,
);
</script>

<template>
    <Head title="Dropshipping" />

    <div class="space-y-6 p-6">
        <section class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-slate-900 dark:text-slate-100">Dropshipping</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Supplier fulfillment queue for online and POS orders.</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-900">
                <span class="font-semibold text-slate-900 dark:text-slate-100">{{ activeCount }}</span>
                <span class="text-slate-500 dark:text-slate-400"> active item(s)</span>
            </div>
        </section>

        <section class="grid gap-3 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900 md:grid-cols-6">
            <input v-model="filters.order_number" placeholder="Order number" class="rounded border border-slate-300 px-3 py-2 dark:border-slate-600 dark:bg-slate-950" />
            <input v-model="filters.customer" placeholder="Customer" class="rounded border border-slate-300 px-3 py-2 dark:border-slate-600 dark:bg-slate-950" />
            <select v-model="filters.status" class="rounded border border-slate-300 px-3 py-2 dark:border-slate-600 dark:bg-slate-950">
                <option value="">All statuses</option>
                <option v-for="status in statuses" :key="status" :value="status">{{ statusLabel(status) }}</option>
            </select>
            <select v-model="filters.supplier_id" class="rounded border border-slate-300 px-3 py-2 dark:border-slate-600 dark:bg-slate-950">
                <option value="">All suppliers</option>
                <option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">{{ supplier.name }}</option>
            </select>
            <select v-model="filters.channel" class="rounded border border-slate-300 px-3 py-2 dark:border-slate-600 dark:bg-slate-950">
                <option value="">All channels</option>
                <option value="online">Online</option>
                <option value="pos">POS</option>
            </select>
            <button class="rounded bg-slate-900 px-4 py-2 text-white dark:bg-slate-100 dark:text-slate-900" @click="applyFilters">Filter</button>
        </section>

        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500 dark:bg-slate-800 dark:text-slate-300">
                        <tr>
                            <th class="px-4 py-3">Order</th>
                            <th class="px-4 py-3">Customer</th>
                            <th class="px-4 py-3">Product</th>
                            <th class="px-4 py-3">Qty</th>
                            <th class="px-4 py-3">Selling</th>
                            <th class="px-4 py-3">Supplier Cost</th>
                            <th class="px-4 py-3">Profit</th>
                            <th class="px-4 py-3">Supplier</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Expected</th>
                            <th class="px-4 py-3">Payment</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                        <tr v-for="row in fulfillments.data" :key="row.id">
                            <td class="px-4 py-3">
                                <Link :href="route('admin.orders.show', row.order.id)" class="font-medium text-blue-600 dark:text-blue-300">{{ row.order.order_number }}</Link>
                                <div class="text-xs text-slate-500">{{ row.order.channel }}</div>
                            </td>
                            <td class="px-4 py-3">{{ row.order.customer?.name || 'Walk-in customer' }}</td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-slate-900 dark:text-slate-100">{{ row.item.product.name }}</div>
                                <div class="text-xs text-slate-500">{{ row.item.variant.label || row.item.variant.sku }}</div>
                            </td>
                            <td class="px-4 py-3">{{ row.item.quantity }}</td>
                            <td class="px-4 py-3">{{ money(row.item.price) }}</td>
                            <td class="px-4 py-3">{{ money(row.supplier_cost) }}</td>
                            <td class="px-4 py-3">{{ money(row.item.expected_gross_profit) }}</td>
                            <td class="px-4 py-3">{{ row.supplier?.name || 'Unassigned' }}</td>
                            <td class="px-4 py-3"><span :class="['rounded-full px-2.5 py-1 text-xs font-semibold', badgeClass(row.status)]">{{ statusLabel(row.status) }}</span></td>
                            <td class="px-4 py-3">{{ row.expected_delivery_at ? new Date(row.expected_delivery_at).toLocaleDateString() : '-' }}</td>
                            <td class="px-4 py-3">{{ statusLabel(row.order.payment_status) }}</td>
                            <td class="px-4 py-3"><button class="rounded border border-slate-300 px-3 py-1.5 dark:border-slate-600" @click="openUpdate(row)">Update</button></td>
                        </tr>
                        <tr v-if="!fulfillments.data.length">
                            <td colspan="12" class="px-4 py-10 text-center text-slate-500">No dropshipping fulfillments found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <div class="flex flex-wrap gap-2" v-if="fulfillments.links">
            <a v-for="link in fulfillments.links" :key="link.label" :href="link.url || '#'" v-html="link.label" :class="['rounded border px-3 py-1 text-sm', link.active ? 'bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900' : '']" @click.prevent="link.url && router.visit(link.url, { preserveState: true })" />
        </div>

        <div v-if="selected" class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-2xl rounded-lg bg-white p-5 shadow-xl dark:bg-slate-900">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Update Supplier Fulfillment</h2>
                        <p class="mt-1 text-sm text-slate-500">{{ selected.order.order_number }} · {{ selected.item.product.name }}</p>
                    </div>
                    <button class="rounded px-2 py-1 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800" @click="selected = null">x</button>
                </div>

                <div class="mt-5 grid gap-4 md:grid-cols-2">
                    <label class="space-y-1">
                        <span class="text-sm font-medium">Status</span>
                        <select v-model="form.status" class="w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-600 dark:bg-slate-950">
                            <option v-for="status in statuses" :key="status" :value="status">{{ statusLabel(status) }}</option>
                        </select>
                    </label>
                    <label class="space-y-1">
                        <span class="text-sm font-medium">Supplier</span>
                        <select v-model="form.supplier_id" class="w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-600 dark:bg-slate-950">
                            <option :value="null">Unassigned</option>
                            <option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">{{ supplier.name }}</option>
                        </select>
                    </label>
                    <input v-model="form.supplier_cost" type="number" min="0" step="0.01" placeholder="Supplier cost" class="rounded border border-slate-300 px-3 py-2 dark:border-slate-600 dark:bg-slate-950" />
                    <input v-model="form.supplier_reference" placeholder="Supplier reference" class="rounded border border-slate-300 px-3 py-2 dark:border-slate-600 dark:bg-slate-950" />
                    <input v-model="form.expected_delivery_at" type="date" class="rounded border border-slate-300 px-3 py-2 dark:border-slate-600 dark:bg-slate-950" />
                    <label class="flex items-center gap-2 text-sm"><input v-model="form.override" type="checkbox" /> Admin override transition</label>
                    <textarea v-model="form.admin_note" rows="3" placeholder="Admin note" class="md:col-span-2 rounded border border-slate-300 px-3 py-2 dark:border-slate-600 dark:bg-slate-950" />
                </div>

                <div class="mt-5 flex justify-end gap-2">
                    <button class="rounded border border-slate-300 px-4 py-2 dark:border-slate-600" @click="selected = null">Cancel</button>
                    <button class="rounded bg-slate-900 px-4 py-2 text-white disabled:opacity-50 dark:bg-slate-100 dark:text-slate-900" :disabled="form.processing" @click="submit">Save</button>
                </div>
            </div>
        </div>
    </div>
</template>
