<script setup>
import axios from 'axios'
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
    resumableSessions: {
        type: Array,
        default: () => [],
    },
    resumeShortcut: {
        type: Object,
        default: null,
    },
    routes: {
        type: Object,
        required: true,
    },
    defaultAuditNote: {
        type: String,
        default: '',
    },
})

const search = ref('')
const scopeType = ref(props.session?.scope_type || 'full')
const selectedCategoryId = ref(props.session?.category_id || null)
const sessionState = ref(props.session)
const autoSaveState = ref('idle')
const lastSavedAt = ref(props.session?.last_activity_at || null)
const persistingVariantId = ref(null)
const saveError = ref('')

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

const autoSaveLabel = computed(() => {
    if (autoSaveState.value === 'saving') {
        return 'Saving...'
    }

    if (autoSaveState.value === 'saved') {
        return lastSavedAt.value
            ? `Saved at ${new Date(lastSavedAt.value).toLocaleTimeString()}`
            : 'Saved'
    }

    if (autoSaveState.value === 'error') {
        return 'Save failed'
    }

    return 'Not saved yet'
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
        if (variant.locked_by_other_session) {
            return false
        }

        const physical = Number(physicalByVariant.value[variant.id] ?? 0)
        return physical !== Number(variant.system_quantity)
    }).length
})

const countableVariantCount = computed(() => {
    return props.variants.filter((variant) => !variant.locked_by_other_session).length
})

function getVariance(variant) {
    const physical = Number(physicalByVariant.value[variant.id] ?? 0)
    return physical - Number(variant.system_quantity)
}

function submitAudit() {
    form.session_id = sessionState.value?.id || null
    form.scope_type = scopeType.value
    form.category_id = scopeType.value === 'category' ? Number(selectedCategoryId.value || 0) || null : null
    form.source = 'manual'
    form.submit_anyway = false
    form.counts = props.variants
        .filter((variant) => !variant.locked_by_other_session)
        .map((variant) => ({
            variant_id: variant.id,
            physical_quantity: Number(physicalByVariant.value[variant.id] ?? 0),
        }))

    form.post(props.routes.store, {
        preserveScroll: true,
    })
}

function updateSessionStateFromResponse(session) {
    if (!session) {
        return
    }

    sessionState.value = session
    if (session.last_activity_at) {
        lastSavedAt.value = session.last_activity_at
    }
}

async function persistVariant(variantId) {
    if (!sessionState.value?.id) {
        return
    }

    autoSaveState.value = 'saving'
    saveError.value = ''
    persistingVariantId.value = variantId

    try {
        const { data } = await axios.post(props.routes.upsert_item, {
            session_id: sessionState.value.id,
            variant_id: variantId,
            physical_quantity: Number(physicalByVariant.value[variantId] ?? 0),
        })

        updateSessionStateFromResponse(data.session)
        autoSaveState.value = 'saved'
    } catch (error) {
        autoSaveState.value = 'error'
        saveError.value = 'Auto-save failed. Please retry the field update.'
    } finally {
        persistingVariantId.value = null
    }
}

function applyScope() {
    router.get(
        props.routes.index,
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

function openResumeSessions() {
    router.get(props.routes.sessions)
}

function resumeSession(sessionId, mode = 'manual') {
    if (mode === 'mobile') {
        router.get(props.routes.mobile, { session_id: sessionId, ready: 1 })
        return
    }

    router.get(props.routes.index, { session_id: sessionId })
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

        <div
            v-if="resumeShortcut || resumableSessions.length"
            class="rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-700 dark:bg-amber-900/30"
        >
            <p class="text-sm">
                You have unfinished audit sessions. Resume one to continue instead of starting over.
            </p>
            <div class="mt-3 flex flex-wrap gap-2">
                <button
                    v-if="resumeShortcut"
                    type="button"
                    class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-500"
                    @click="resumeSession(resumeShortcut.id)"
                >
                    Resume Last Unfinished
                </button>
                <button
                    type="button"
                    class="rounded-lg border border-amber-400 px-4 py-2 text-sm font-medium text-amber-800 hover:bg-amber-100 dark:border-amber-600 dark:text-amber-200 dark:hover:bg-amber-900/40"
                    @click="openResumeSessions"
                >
                    View All In-Progress Sessions
                </button>
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
            v-if="sessionState"
            class="grid gap-4 rounded-lg border border-gray-200 bg-white p-4 text-sm dark:border-gray-700 dark:bg-gray-900 md:grid-cols-3"
        >
            <p>
                Session:
                <strong>#{{ sessionState.id }}</strong>
            </p>
            <p>
                Audited:
                <strong>{{ sessionState.total_scanned_items }}</strong>
                /
                <strong>{{ sessionState.total_expected_items }}</strong>
            </p>
            <p>
                Coverage:
                <strong>{{ Number(sessionState.coverage_percentage || 0).toFixed(2) }}%</strong>
            </p>
            <p class="md:col-span-3">
                <span class="font-medium">{{ autoSaveLabel }}</span>
                <span v-if="persistingVariantId" class="text-xs text-gray-500"> (Variant #{{ persistingVariantId }})</span>
            </p>
            <p v-if="saveError" class="md:col-span-3 text-xs text-red-600">
                {{ saveError }}
            </p>
        </div>

        <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <p class="text-sm">
                {{ discrepancyCount }} potential discrepancy{{ discrepancyCount === 1 ? '' : 'ies' }}
            </p>

            <button
                type="button"
                class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-60"
                :disabled="form.processing || countableVariantCount === 0"
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
                            <div>
                                <p>{{ variant.display_name }}</p>
                                <p v-if="variant.locked_by_other_session" class="mt-1 text-xs text-amber-600">
                                    {{ variant.lock_message }}
                                </p>
                                <p v-else-if="variant.conflict_reason" class="mt-1 text-xs text-red-600">
                                    {{ variant.conflict_reason }}
                                </p>
                            </div>
                        </td>
                        <td class="px-4 py-3 font-mono text-xs">{{ variant.sku }}</td>
                        <td class="px-4 py-3">{{ variant.system_quantity }}</td>
                        <td class="px-4 py-3">
                            <input
                                v-model.number="physicalByVariant[variant.id]"
                                type="number"
                                min="0"
                                class="w-28 rounded border border-gray-300 px-2 py-1 dark:border-gray-700 dark:bg-gray-900"
                                :disabled="variant.locked_by_other_session"
                                @change="persistVariant(variant.id)"
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
