<script setup>
import { Head, router, useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

const props = defineProps({
    variants: {
        type: Array,
        required: true,
    },
    warehouses: {
        type: Array,
        default: () => [],
    },
    categories: {
        type: Array,
        default: () => [],
    },
    session: {
        type: Object,
        default: null,
    },
    defaultAuditNote: {
        type: String,
        default: '',
    },
})

const search = ref('')
const scopeType = ref(props.session?.scope_type || 'full')
const selectedCategoryId = ref(props.session?.category_id || null)

const form = useForm({
    session_id: props.session?.id || null,
    warehouse_id: props.session?.warehouse_id || null,
    note: props.defaultAuditNote,
    scope_type: scopeType.value,
    category_id: selectedCategoryId.value,
    submit_anyway: false,
    source: 'manual',
    counts: props.variants.map((variant) => ({
        variant_id: variant.id,
        physical_quantity: Number(variant.physical_quantity ?? variant.system_quantity),
    })),
})

const physicalByVariant = ref(
    props.variants.reduce((carry, variant) => {
        carry[variant.id] = Number(variant.physical_quantity ?? variant.system_quantity)
        return carry
    }, {}),
)

const visibleVariants = computed(() => {
    const query = search.value.trim().toLowerCase()

    if (!query) {
        return props.variants
    }

    return props.variants.filter((variant) => {
        return (
            variant.display_name.toLowerCase().includes(query) ||
            String(variant.sku || '')
                .toLowerCase()
                .includes(query) ||
            String(variant.barcode || '')
                .toLowerCase()
                .includes(query)
        )
    })
})

const discrepancyCount = computed(() => {
    return props.variants.filter((variant) => {
        const physical = Number(physicalByVariant.value[variant.id] ?? 0)
        return physical !== Number(variant.system_quantity)
    }).length
})

function getVariance(variant) {
    const physical = Number(physicalByVariant.value[variant.id] ?? 0)
    return physical - Number(variant.system_quantity)
}

function submitAudit() {
    form.session_id = props.session?.id || null
    form.scope_type = scopeType.value
    form.category_id = scopeType.value === 'category' ? Number(selectedCategoryId.value || 0) || null : null
    form.source = 'manual'
    form.submit_anyway = false
    form.counts = props.variants.map((variant) => ({
        variant_id: variant.id,
        physical_quantity: Number(physicalByVariant.value[variant.id] ?? 0),
    }))

    form.post('/admin/inventory/stock-audit', {
        preserveScroll: true,
    })
}

function applyScope() {
    router.get(
        '/admin/inventory/stock-audit',
        {
            scope_type: scopeType.value,
            category_id: scopeType.value === 'category' ? selectedCategoryId.value : null,
            warehouse_id: form.warehouse_id,
        },
        {
            preserveScroll: true,
            replace: true,
        },
    )
}
</script>

<template>
    <Head title="Stock Audit" />

    <div class="space-y-6 px-5 py-4 text-gray-900 dark:text-gray-100">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold">Stock Audit</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Compare physical count against system quantity and flag discrepancies.
                </p>
            </div>

            <div class="w-full max-w-md">
                <input
                    v-model="search"
                    type="text"
                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-900"
                    placeholder="Search variant, SKU, or barcode"
                />
            </div>
        </div>

        <div class="grid gap-4 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900 md:grid-cols-3">
            <label class="text-sm">
                <span class="mb-1 block text-xs text-gray-500">Audit Scope</span>
                <select
                    v-model="scopeType"
                    class="w-full rounded-md border border-gray-300 px-3 py-2 dark:border-gray-700 dark:bg-gray-900"
                >
                    <option value="full">Full Inventory</option>
                    <option value="category">Category Only</option>
                </select>
            </label>

            <label v-if="scopeType === 'category'" class="text-sm">
                <span class="mb-1 block text-xs text-gray-500">Category</span>
                <select
                    v-model.number="selectedCategoryId"
                    class="w-full rounded-md border border-gray-300 px-3 py-2 dark:border-gray-700 dark:bg-gray-900"
                >
                    <option :value="null">Select category</option>
                    <option v-for="category in categories" :key="category.id" :value="category.id">
                        {{ category.name }}
                    </option>
                </select>
            </label>

            <label class="text-sm">
                <span class="mb-1 block text-xs text-gray-500">Warehouse (Optional)</span>
                <select
                    v-model.number="form.warehouse_id"
                    class="w-full rounded-md border border-gray-300 px-3 py-2 dark:border-gray-700 dark:bg-gray-900"
                >
                    <option :value="null">Select warehouse</option>
                    <option v-for="warehouse in warehouses" :key="warehouse.id" :value="warehouse.id">
                        {{ warehouse.name }}
                    </option>
                </select>
            </label>

            <label class="text-sm md:col-span-2">
                <span class="mb-1 block text-xs text-gray-500">Audit Note (Optional)</span>
                <input
                    v-model="form.note"
                    type="text"
                    class="w-full rounded-md border border-gray-300 px-3 py-2 dark:border-gray-700 dark:bg-gray-900"
                    placeholder="Physical stock check for week 12"
                />
            </label>

            <div class="md:col-span-3">
                <button
                    type="button"
                    class="rounded-lg border border-indigo-300 px-4 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-50 dark:border-indigo-700 dark:text-indigo-300 dark:hover:bg-indigo-900/30"
                    :disabled="scopeType === 'category' && !selectedCategoryId"
                    @click="applyScope"
                >
                    Apply Scope
                </button>
            </div>
        </div>

        <div
            v-if="session"
            class="grid gap-4 rounded-lg border border-gray-200 bg-white p-4 text-sm dark:border-gray-700 dark:bg-gray-900 md:grid-cols-3"
        >
            <p>
                Session:
                <strong>#{{ session.id }}</strong>
            </p>
            <p>
                Audited:
                <strong>{{ session.total_scanned_items }}</strong>
                /
                <strong>{{ session.total_expected_items }}</strong>
            </p>
            <p>
                Coverage:
                <strong>{{ Number(session.coverage_percentage || 0).toFixed(2) }}%</strong>
            </p>
        </div>

        <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <p class="text-sm">
                {{ discrepancyCount }} potential discrepancy{{ discrepancyCount === 1 ? '' : 'ies' }}
            </p>

            <button
                type="button"
                class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-60"
                :disabled="form.processing"
                @click="submitAudit"
            >
                {{ form.processing ? 'Submitting...' : 'Submit Audit' }}
            </button>
        </div>

        <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-4 py-3 text-left">Variant</th>
                        <th class="px-4 py-3 text-left">SKU</th>
                        <th class="px-4 py-3 text-left">System Qty</th>
                        <th class="px-4 py-3 text-left">Physical Count</th>
                        <th class="px-4 py-3 text-left">Variance</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    <tr
                        v-for="variant in visibleVariants"
                        :key="variant.id"
                        class="hover:bg-gray-50 dark:hover:bg-gray-800/70"
                    >
                        <td class="px-4 py-3">
                            {{ variant.display_name }}
                        </td>
                        <td class="px-4 py-3 font-mono text-xs">{{ variant.sku }}</td>
                        <td class="px-4 py-3">{{ variant.system_quantity }}</td>
                        <td class="px-4 py-3">
                            <input
                                v-model.number="physicalByVariant[variant.id]"
                                type="number"
                                min="0"
                                class="w-28 rounded border border-gray-300 px-2 py-1 dark:border-gray-700 dark:bg-gray-900"
                            />
                        </td>
                        <td
                            class="px-4 py-3 font-semibold"
                            :class="{
                                'text-green-600': getVariance(variant) > 0,
                                'text-red-600': getVariance(variant) < 0,
                            }"
                        >
                            {{ getVariance(variant) > 0 ? '+' : '' }}{{ getVariance(variant) }}
                        </td>
                    </tr>
                    <tr v-if="visibleVariants.length === 0">
                        <td class="px-4 py-6 text-center text-sm text-gray-500" colspan="5">
                            No variants match your search.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
