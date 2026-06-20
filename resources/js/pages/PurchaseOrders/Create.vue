<template>
    <div class="mx-auto max-w-6xl p-6 text-gray-900 dark:text-gray-100">
        <header class="mb-6">
            <h1 class="text-2xl font-semibold">Create Purchase Order</h1>
            <p class="text-sm text-muted-foreground dark:text-gray-400">
                Create a new purchase order and add line items.
            </p>
        </header>

        <form @submit.prevent="submit" class="space-y-6" novalidate>
            <!-- Header: vendor / warehouse / dates -->
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div>
                    <label class="text-sm font-medium">Vendor</label>
                    <SearchableSelect
                        v-model="form.vendor_id"
                        class="mt-1"
                        :options="vendors"
                        label-key="name"
                        placeholder="Search or select vendor"
                        empty-label="No vendors match your search."
                        action-label="Add New Vendor"
                        @action="openVendorModal"
                    />

                    <p v-if="form.errors.vendor_id" class="mt-1 text-sm text-red-500">
                        {{ form.errors.vendor_id }}
                    </p>
                </div>

                <div>
                    <label class="text-sm font-medium">Warehouse</label>
                    <select
                        v-model="form.warehouse_id"
                        class="mt-1 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                    >
                        <option value="" disabled>Select warehouse</option>
                        <option v-for="w in warehouses" :key="w.id" :value="w.id">
                            {{ w.name }}
                        </option>
                    </select>
                    <p v-if="form.errors.warehouse_id" class="mt-1 text-sm text-red-500">
                        {{ form.errors.warehouse_id }}
                    </p>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="text-sm font-medium">Order Date</label>
                        <input
                            type="date"
                            v-model="form.order_date"
                            class="mt-1 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                        />
                        <p v-if="form.errors.order_date" class="mt-1 text-sm text-red-500">
                            {{ form.errors.order_date }}
                        </p>
                    </div>

                    <div>
                        <label class="text-sm font-medium">Expected Date</label>
                        <input
                            type="date"
                            v-model="form.expected_date"
                            class="mt-1 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                        />
                        <p v-if="form.errors.expected_date" class="mt-1 text-sm text-red-500">
                            {{ form.errors.expected_date }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Note -->
            <div>
                <label class="text-sm font-medium">Note (optional)</label>
                <textarea
                    v-model="form.note"
                    rows="3"
                    placeholder="Notes or instructions to vendor..."
                    class="mt-1 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                ></textarea>
                <p v-if="form.errors.note" class="mt-1 text-sm text-red-500">
                    {{ form.errors.note }}
                </p>
            </div>

            <!-- Line Items -->
            <section class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-lg font-medium">Line Items</h2>

                    <div class="flex gap-2">
                        <button
                            type="button"
                            @click="addRow"
                            class="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-3 py-2 hover:bg-gray-100 dark:border-gray-600 dark:bg-gray-800 dark:hover:bg-gray-700"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16M4 12h16" />
                            </svg>
                            Add row
                        </button>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full table-auto">
                        <thead class="text-left text-sm text-gray-600 dark:text-gray-300">
                        <tr>
                            <th class="px-3 py-2">#</th>
                            <th class="px-3 py-2">Product</th>
                            <th class="px-3 py-2">Qty Ordered</th>
                            <th class="px-3 py-2">Unit Cost</th>
                            <th class="px-3 py-2">Line Total</th>
                            <th class="px-3 py-2">Actions</th>
                        </tr>
                        </thead>

                        <tbody>
                        <tr
                            v-for="(item, idx) in form.items"
                            :key="item._uid"
                            :class="idx % 2 === 0 ? 'bg-white dark:bg-gray-900' : 'bg-gray-50 dark:bg-gray-800'"
                        >
                            <td class="px-3 py-2 align-top">{{ idx + 1 }}</td>

                            <td class="min-w-[260px] px-3 py-2 align-top">
                                <SearchableSelect
                                    v-model="item.product_variant_id"
                                    :options="productVariants"
                                    label-key="name"
                                    meta-key="sku"
                                    meta-prefix="SKU: "
                                    :search-keys="['name', 'sku']"
                                    placeholder="Search or select product"
                                    empty-label="No products match your search."
                                    dropdown-width="content"
                                    portal
                                    @select="(variant) => onVariantSelect(idx, variant)"
                                />

                                <p v-if="lineError(idx, 'product_variant_id')" class="mt-1 text-xs text-red-500">
                                    {{ lineError(idx, 'product_variant_id') }}
                                </p>
                            </td>

                            <td class="px-3 py-2 align-top">
                                <input
                                    type="number"
                                    min="1"
                                    v-model.number="item.quantity_ordered"
                                    @input="recalcLine(idx)"
                                    class="w-28 rounded-lg border border-gray-300 bg-white px-3 py-2 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                                />
                                <p v-if="lineError(idx, 'quantity_ordered')" class="mt-1 text-xs text-red-500">
                                    {{ lineError(idx, 'quantity_ordered') }}
                                </p>
                            </td>

                            <td class="px-3 py-2 align-top">
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    v-model.number="item.unit_cost"
                                    @input="recalcLine(idx)"
                                    class="w-36 rounded-lg border border-gray-300 bg-white px-3 py-2 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                                />
                                <p v-if="lineError(idx, 'unit_cost')" class="mt-1 text-xs text-red-500">
                                    {{ lineError(idx, 'unit_cost') }}
                                </p>
                            </td>

                            <td class="px-3 py-2 align-top">
                                <div class="font-medium">
                                    {{ formatCurrency(lineTotal(item)) }}
                                </div>
                            </td>

                            <td class="px-3 py-2 align-top">
                                <div class="flex gap-2">
                                    <button
                                        type="button"
                                        @click="duplicateRow(idx)"
                                        class="rounded-lg border border-gray-300 px-2 py-1 hover:bg-gray-100 dark:border-gray-600 dark:bg-gray-800 dark:hover:bg-gray-700"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" stroke="currentColor">
                                            <rect x="9" y="9" width="13" height="13" rx="2" ry="2" />
                                            <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" />
                                        </svg>
                                    </button>

                                    <button
                                        type="button"
                                        @click="removeRow(idx)"
                                        class="rounded-lg border border-red-600 px-2 py-1 text-red-600 hover:bg-red-50 dark:border-red-500 dark:hover:bg-red-900/40"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" stroke="currentColor">
                                            <path d="M3 6h18M8 6v12a2 2 0 0 0 2 2h4a2 2 0 0 0 2-2V6" />
                                            <path d="M10 11v6M14 11v6" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="itemErrorsExist" class="mt-3 text-sm text-red-500">
                    Some line items have errors. Fix them above.
                </div>

                <div class="mt-4 flex items-center justify-end gap-6">
                    <div class="text-sm">Subtotal:</div>
                    <div class="text-lg font-semibold">{{ formatCurrency(subtotal) }}</div>
                </div>
            </section>

            <!-- Form Actions -->
            <div class="flex items-center justify-between gap-4">
                <div class="text-sm text-gray-600 dark:text-gray-400">
                    After saving, you can send the PO to the vendor or edit it while it's still a draft.
                </div>

                <div class="flex items-center gap-3">
                    <Link
                        :href="route('admin.purchase-orders.index')"
                        class="inline-flex items-center gap-2 rounded-lg border border-gray-400 bg-red-300 px-4 py-2 hover:bg-gray-100 dark:border-gray-600 dark:bg-red-600 dark:text-white dark:hover:bg-gray-700"
                    >
                        Cancel
                    </Link>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="inline-flex items-center gap-2 rounded-lg bg-blue-900 px-4 py-2 text-white hover:bg-blue-700 disabled:opacity-50"
                    >
                        <svg v-if="form.processing" class="h-4 w-4 animate-spin" viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" stroke-linecap="round" stroke-dasharray="31.4 31.4"/>
                        </svg>
                        Save Purchase Order
                    </button>
                </div>
            </div>
        </form>

        <!-- Modal -->
        <div v-if="showVendorModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
            <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-lg dark:bg-gray-900">
                <h2 class="mb-4 text-lg font-semibold">Add New Vendor</h2>
                <VendorForm :show-cancel="true" @cancel="closeVendorModal" @created="vendorCreated" />
            </div>
        </div>
    </div>
</template>


<script setup>

import { ref, computed, watch, onBeforeUnmount } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import VendorForm from '@/components/VendorForm.vue'
import SearchableSelect from '@/components/SearchableSelect.vue'

// Props injected by Inertia
const props = defineProps({
    vendors: { type: Array, required: true },
    warehouses: { type: Array, required: true },
    productVariants: { type: Array, required: true },
});

// utilities
const uid = (() => {
    let i = 0;
    return () => ++i;
})();

// initial empty item
function emptyItem() {
    return {
        _uid: uid(),
        product_variant_id: '',
        quantity_ordered: 1,
        quantity_received: 0,
        unit_cost: 0.0,
    };
}

const form = useForm({
    vendor_id: props.vendors.length ? props.vendors[0].id : '',
    warehouse_id: props.warehouses.length ? props.warehouses[0].id : '',
    order_date: new Date().toISOString().slice(0, 10),
    expected_date: '',
    note: '',
    items: [emptyItem()],
});

// local refs for convenience
const vendors = ref([...props.vendors]);
const warehouses = props.warehouses;
const productVariants = props.productVariants;

const showVendorModal = ref(false);

function openVendorModal() {
    form.vendor_id = '__new';
    showVendorModal.value = true;
}

function closeVendorModal() {
    showVendorModal.value = false;
    if (form.vendor_id === '__new') form.vendor_id = '';
}
function vendorCreated(newVendor) {
    vendors.value.push(newVendor);
    form.vendor_id = newVendor.id;
    showVendorModal.value = false;
}

// computed totals
const lineTotal = (item) => {
    const qty = Number(item.quantity_ordered || 0);
    const cost = Number(item.unit_cost || 0);
    return Math.round(qty * cost * 100) / 100;
};

const subtotal = computed(() => {
    return form.items.reduce((sum, it) => sum + lineTotal(it), 0);
});

// when subtotal changes, keep it read-only on server-side; we still submit items and total is recalculated server-side too
watch(subtotal, (val) => {
    // Keep local cache if needed
});

// form helper actions
function addRow() {
    form.items.push(emptyItem());
    // focus ideally moved to the new select - left to implement depending on UI lib
}

function removeRow(index) {
    if (form.items.length <= 1) {
        // clear values instead of removing last row to keep UI stable
        Object.assign(form.items[0], emptyItem());
        return;
    }
    form.items.splice(index, 1);
}

function duplicateRow(index) {
    const item = form.items[index];
    const copy = {
        _uid: uid(),
        product_variant_id: item.product_variant_id,
        quantity_ordered: item.quantity_ordered,
        quantity_received: 0,
        unit_cost: item.unit_cost,
    };
    form.items.splice(index + 1, 0, copy);
}

function recalcLine(index) {
    // ensure minimums and proper numeric types
    const it = form.items[index];
    it.quantity_ordered = Math.max(1, Number(it.quantity_ordered || 0));
    it.unit_cost = Math.max(0, Number(it.unit_cost || 0));
}

function lineError(index, field) {
    // server returns errors in dot format like items.0.quantity_ordered
    // we map them here for inline display
    const key = `items.${index}.${field}`;
    return form.errors[key] || null;
}

const itemErrorsExist = computed(() => {
    // detect any errors in items.* keys
    return Object.keys(form.errors).some((k) => k.startsWith('items.'));
});

// currency formatting (simple, can be swapped for Intl)
function formatCurrency(value) {
    const num = Number(value || 0);
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: 'USD',
        maximumFractionDigits: 2,
    }).format(num);
}

function onVariantSelect(index, variant) {
    const it = form.items[index];
    if (!it || !it.product_variant_id) return;

    if (!variant) return;

    const last = Number(variant.last_purchase_price ?? 0);

    // Only auto-fill if there's no user-entered cost yet (prevents overwriting)
    if (it.unit_cost === null || it.unit_cost === '' || Number(it.unit_cost) === 0) {
        it.unit_cost = last;
    }

    // keep existing guardrails
    recalcLine(index);
}

// submit
function submit() {
    // Basic front-end validations
    const errors = [];

    if (!form.vendor_id) errors.push('Choose a vendor');
    if (!form.warehouse_id) errors.push('Choose a warehouse');
    if (!form.order_date) errors.push('Select order date');

    // validate items client-side before sending
    form.items.forEach((it, idx) => {
        if (!it.product_variant_id) {
            form.errors[`items.${idx}.product_variant_id`] = 'Select a product';
        }
        if (!it.quantity_ordered || it.quantity_ordered < 1) {
            form.errors[`items.${idx}.quantity_ordered`] =
                'Quantity must be at least 1';
        }
        if (
            it.unit_cost === null ||
            it.unit_cost === '' ||
            Number(it.unit_cost) < 0
        ) {
            form.errors[`items.${idx}.unit_cost`] = 'Unit cost must be >= 0';
        }
    });

    if (errors.length) {
        // show a client-side toast or use Inertia flash — here we just stop
        return;
    }

    // prepare payload: remove _uid and quantity_received (server computes)
    const payloadItems = form.items.map((it) => ({
        product_variant_id: it.product_variant_id,
        quantity_ordered: Number(it.quantity_ordered),
        unit_cost: Number(it.unit_cost),
    }));

    form.processing = true;

    form.post('/admin/purchase-orders/store', {
        preserveState: true,
        onFinish: () => (form.processing = false),
        data: {
            vendor_id: form.vendor_id,
            warehouse_id: form.warehouse_id,
            order_date: form.order_date,
            expected_date: form.expected_date || null,
            note: form.note,
            items: payloadItems,
        },
    });
}

// unsaved changes guard
const beforeUnload = (e) => {
    if (form.isDirty) {
        e.preventDefault();
        e.returnValue = '';
    }
};

window.addEventListener('beforeunload', beforeUnload);
onBeforeUnmount(() => window.removeEventListener('beforeunload', beforeUnload));
</script>

<style scoped>
/* lightweight shadcn-like variables — adapt to your design system */
:root {
    --primary-600: #0066ff;
    --primary-700: #0051cc;
}

.bg-primary-600 {
    background-color: var(--primary-600);
}
.bg-primary-700 {
    background-color: var(--primary-700);
}
.text-muted-foreground {
    color: #6b7280;
}
</style>
