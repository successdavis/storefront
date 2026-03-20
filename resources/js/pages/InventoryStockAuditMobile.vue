<script setup>
import axios from 'axios'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import { computed, onBeforeUnmount, ref, watch } from 'vue'

const props = defineProps({
    totalVariants: {
        type: Number,
        default: 0,
    },
    session: {
        type: Object,
        default: null,
    },
    sessionItems: {
        type: Array,
        default: () => [],
    },
    categories: {
        type: Array,
        default: () => [],
    },
})

const scopeType = ref(props.session?.scope_type || 'full')
const selectedCategoryId = ref(props.session?.category_id || null)
const page = usePage()

const form = useForm({
    session_id: props.session?.id || null,
    warehouse_id: props.session?.warehouse_id || null,
    note: '',
    scope_type: scopeType.value,
    category_id: selectedCategoryId.value,
    submit_anyway: false,
    source: 'mobile',
    counts: [],
})

const scannerReady = ref(false)
const scannerRunning = ref(false)
const scannerError = ref('')
const physicalInput = ref(0)
const currentVariant = ref(null)
const entries = ref(
    (props.sessionItems || []).map((item) => ({
        variant_id: Number(item.variant_id),
        sku: item.sku,
        display_name: item.display_name,
        barcode: item.barcode,
        system_quantity: Number(item.system_quantity || 0),
        physical_quantity: Number(item.physical_quantity || 0),
    })),
)
const lastScanValue = ref('')
const lastScanTime = ref(0)
const showMissingWarning = ref(false)
const scannerWasRunningBeforeWarning = ref(false)
let scanner = null

const isScopeReady = computed(() => {
    const query = String(page.url || '').split('?')[1] || ''
    const params = new URLSearchParams(query)
    return params.get('ready') === '1'
})

function loadScannerScript() {
    return new Promise((resolve, reject) => {
        if (window.Html5Qrcode) {
            resolve()
            return
        }

        const script = document.createElement('script')
        script.src = 'https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js'
        script.onload = () => resolve()
        script.onerror = () => reject(new Error('Scanner library failed to load.'))
        document.head.appendChild(script)
    })
}

async function initScanner() {
    try {
        scannerError.value = ''
        await loadScannerScript()
        scannerReady.value = true
    } catch (error) {
        scannerError.value = 'Scanner unavailable right now. Please try again.'
    }
}

async function startScanner() {
    if (!scannerReady.value || scannerRunning.value || currentVariant.value || showMissingWarning.value) {
        return
    }

    scanner = new window.Html5Qrcode('barcode-reader')

    try {
        await scanner.start(
            { facingMode: 'environment' },
            { fps: 10, qrbox: { width: 250, height: 120 } },
            async (decodedText) => {
                const now = Date.now()
                if (decodedText === lastScanValue.value && now - lastScanTime.value < 1500) {
                    return
                }

                lastScanValue.value = decodedText
                lastScanTime.value = now
                await handleScan(decodedText)
            },
        )
        scannerRunning.value = true
    } catch (error) {
        scannerError.value = 'Unable to access camera for scanning.'
    }
}

async function stopScanner() {
    if (!scanner || !scannerRunning.value) {
        return
    }

    try {
        await scanner.stop()
        await scanner.clear()
    } catch (error) {
        // no-op
    } finally {
        scannerRunning.value = false
        scanner = null
    }
}

async function handleScan(rawBarcode) {
    await stopScanner()
    await lookupBarcode(rawBarcode)

    if (!currentVariant.value) {
        await startScanner()
    }
}

async function lookupBarcode(rawBarcode) {
    const barcode = String(rawBarcode || '').trim()
    if (!barcode) {
        return
    }

    scannerError.value = ''

    try {
        const { data } = await axios.get('/admin/inventory/stock-audit/lookup', {
            params: {
                barcode,
                session_id: form.session_id,
            },
        })

        currentVariant.value = data
        physicalInput.value = Number(data.system_quantity || 0)
    } catch (error) {
        currentVariant.value = null
        scannerError.value = 'Barcode not found in this audit scope. Confirm label and try again.'
    }
}

async function closeVariantDialog() {
    currentVariant.value = null
    physicalInput.value = 0
    await startScanner()
}

async function addToBatch() {
    if (!currentVariant.value) {
        return
    }

    const payload = {
        variant_id: Number(currentVariant.value.id),
        sku: currentVariant.value.sku,
        display_name: currentVariant.value.display_name,
        barcode: currentVariant.value.barcode,
        system_quantity: Number(currentVariant.value.system_quantity || 0),
        physical_quantity: Number(physicalInput.value || 0),
    }

    try {
        await axios.post('/admin/inventory/stock-audit/items', {
            session_id: form.session_id,
            variant_id: payload.variant_id,
            physical_quantity: payload.physical_quantity,
        })
    } catch (error) {
        scannerError.value = 'Could not save the scanned item. Please retry.'
        return
    }

    const existingIndex = entries.value.findIndex((entry) => entry.variant_id === payload.variant_id)
    if (existingIndex >= 0) {
        entries.value[existingIndex] = payload
    } else {
        entries.value.unshift(payload)
    }

    await closeVariantDialog()
}

const discrepancyCount = computed(() => {
    return entries.value.filter((entry) => entry.physical_quantity !== entry.system_quantity).length
})

const auditedCount = computed(() => entries.value.length)

const leftCount = computed(() => {
    return Math.max(Number(props.totalVariants || 0) - auditedCount.value, 0)
})

const progressPercent = computed(() => {
    const total = Number(props.totalVariants || 0)
    if (total <= 0) {
        return 0
    }

    return Math.min(100, Math.round((auditedCount.value / total) * 100))
})

const missingCount = computed(() => {
    return Math.max(Number(props.totalVariants || 0) - entries.value.length, 0)
})

const selectedCategoryName = computed(() => {
    if (scopeType.value !== 'category' || !selectedCategoryId.value) {
        return null
    }

    const selected = (props.categories || []).find(
        (category) => Number(category.id) === Number(selectedCategoryId.value),
    )

    return selected?.name || null
})

function applyScope() {
    router.get(
        '/admin/inventory/stock-audit/mobile',
        {
            scope_type: scopeType.value,
            category_id: scopeType.value === 'category' ? selectedCategoryId.value : null,
            ready: 1,
        },
        {
            preserveScroll: true,
            replace: true,
        },
    )
}

function changeScope() {
    router.get(
        '/admin/inventory/stock-audit/mobile',
        {
            scope_type: scopeType.value,
            category_id: scopeType.value === 'category' ? selectedCategoryId.value : null,
        },
        {
            preserveScroll: true,
            replace: true,
        },
    )
}

async function submitBatch() {
    if (!entries.value.length) {
        return
    }

    form.submit_anyway = false

    if (missingCount.value > 0) {
        scannerWasRunningBeforeWarning.value = scannerRunning.value
        await stopScanner()
        showMissingWarning.value = true
        return
    }

    performSubmit()
}

function performSubmit() {
    form.session_id = props.session?.id || form.session_id
    form.scope_type = scopeType.value
    form.category_id = scopeType.value === 'category' ? selectedCategoryId.value : null
    form.source = 'mobile'
    form.counts = entries.value.map((entry) => ({
        variant_id: entry.variant_id,
        physical_quantity: Number(entry.physical_quantity),
    }))

    form.post('/admin/inventory/stock-audit', {
        preserveScroll: true,
        onSuccess: () => {
            entries.value = []
            showMissingWarning.value = false
            form.submit_anyway = false
        },
    })
}

function continueSubmitAnyway() {
    form.submit_anyway = true
    showMissingWarning.value = false
    performSubmit()
}

async function cancelSubmitWarning() {
    showMissingWarning.value = false
    form.submit_anyway = false

    if (scannerWasRunningBeforeWarning.value) {
        await startScanner()
    }
}

onBeforeUnmount(async () => {
    await stopScanner()
})

watch(
    isScopeReady,
    async (ready) => {
        if (ready) {
            await initScanner()
            return
        }

        await stopScanner()
        scannerReady.value = false
    },
    { immediate: true },
)
</script>

<template>
    <Head title="Mobile Stock Audit" />

    <div class="mx-auto w-full max-w-3xl space-y-5 px-4 py-4 text-gray-900 dark:text-gray-100">
        <div>
            <h1 class="text-2xl font-bold">Mobile Stock Audit</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Scan variant barcodes within the active session scope and submit once complete.
            </p>
        </div>

        <div v-if="!isScopeReady" class="space-y-4 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <div>
                <h2 class="text-lg font-semibold">Step 1: Select Audit Scope</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Apply scope and category before moving to the scanning screen.
                </p>
            </div>

            <div class="flex flex-wrap items-end gap-3">
                <label class="w-full text-sm sm:w-auto sm:min-w-48">
                    <span class="mb-1 block text-xs text-gray-500">Audit Scope</span>
                    <select
                        v-model="scopeType"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 dark:border-gray-700 dark:bg-gray-900"
                    >
                    <option value="full">Full Inventory</option>
                    <option value="category">Category Only</option>
                </select>
            </label>
                <label v-if="scopeType === 'category'" class="w-full text-sm sm:w-auto sm:min-w-48">
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
                <button
                    type="button"
                    class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500 disabled:opacity-60"
                    :disabled="scopeType === 'category' && !selectedCategoryId"
                    @click="applyScope"
                >
                    Apply Scope and Continue
                </button>
            </div>
            <p v-if="session" class="text-xs text-gray-500 dark:text-gray-400">
                Session #{{ session.id }} ({{ session.status }})
            </p>
        </div>

        <div v-if="isScopeReady" class="space-y-3 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <div class="flex items-center justify-between">
                <div class="text-sm">
                    <p>
                        Scope:
                        <strong>{{ scopeType === 'category' ? 'Category' : 'Full Inventory' }}</strong>
                    </p>
                    <p v-if="scopeType === 'category'" class="text-xs text-gray-500">
                        Category: {{ selectedCategoryName || 'N/A' }}
                    </p>
                </div>
                <button
                    type="button"
                    class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium hover:bg-gray-100 dark:border-gray-700 dark:hover:bg-gray-800"
                    @click="changeScope"
                >
                    Change Scope
                </button>
            </div>
<!--            <p v-if="session" class="text-xs text-gray-500 dark:text-gray-400">-->
<!--                Session #{{ session.id }} ({{ session.status }})-->
<!--            </p>-->
        </div>

        <div v-if="isScopeReady" class="space-y-2 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <div class="flex items-center justify-between text-sm">
                <p>
                    Audited:
                    <strong>{{ auditedCount }}</strong>
                    /
                    <strong>{{ totalVariants }}</strong>
                </p>
                <p class="text-gray-500 dark:text-gray-400">{{ leftCount }} left</p>
            </div>
            <div class="h-2 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-800">
                <div
                    class="h-full rounded-full bg-indigo-600 transition-all duration-300"
                    :style="{ width: `${progressPercent}%` }"
                />
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400">Coverage: {{ progressPercent }}%</p>
        </div>

        <div v-if="isScopeReady" class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <div id="barcode-reader" class="overflow-hidden rounded-md"></div>

            <div class="mt-3 flex flex-wrap gap-2">
                <button
                    type="button"
                    class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500 disabled:opacity-60"
                    :disabled="!scannerReady || scannerRunning"
                    @click="startScanner"
                >
                    Start Scanner
                </button>
                <button
                    type="button"
                    class="rounded-lg bg-gray-700 px-4 py-2 text-sm font-medium text-white hover:bg-gray-600 disabled:opacity-60"
                    :disabled="!scannerRunning"
                    @click="stopScanner"
                >
                    Stop Scanner
                </button>
            </div>

            <p v-if="scannerError" class="mt-3 rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-800">
                {{ scannerError }}
            </p>
        </div>

        <div v-if="isScopeReady && currentVariant" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 px-4">
            <div class="w-full max-w-lg space-y-4 rounded-xl border border-gray-200 bg-white p-5 shadow-xl dark:border-gray-700 dark:bg-gray-900">
                <div>
                    <h2 class="text-lg font-semibold">Scanned Variant</h2>
                    <p class="mt-1 text-sm">{{ currentVariant.display_name }}</p>
                    <p class="text-xs text-gray-500">SKU: {{ currentVariant.sku }} | Barcode: {{ currentVariant.barcode }}</p>
                </div>

                <p class="text-sm">System quantity: <strong>{{ currentVariant.system_quantity }}</strong></p>

                <label class="block text-sm">
                    <span class="mb-1 block text-xs text-gray-500">Physical count</span>
                    <input
                        v-model.number="physicalInput"
                        type="number"
                        min="0"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 dark:border-gray-700 dark:bg-gray-900"
                    />
                </label>

                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    <button
                        type="button"
                        class="rounded-lg bg-gray-200 px-4 py-2 text-sm font-medium text-gray-800 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-100 dark:hover:bg-gray-600"
                        @click="closeVariantDialog"
                    >
                        Back to Scan
                    </button>
                    <button
                        type="button"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500"
                        @click="addToBatch"
                    >
                        Save and Continue
                    </button>
                </div>
            </div>
        </div>

        <div v-if="isScopeReady && showMissingWarning" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/60 px-4">
            <div class="w-full max-w-md space-y-4 rounded-xl border border-amber-200 bg-white p-5 shadow-xl dark:border-amber-700 dark:bg-gray-900">
                <h2 class="text-lg font-semibold text-amber-700 dark:text-amber-300">Incomplete Audit Coverage</h2>
                <p class="text-sm">
                    You have not scanned <strong>{{ missingCount }}</strong> items. Do you want to submit anyway?
                </p>
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    <button
                        type="button"
                        class="rounded-lg bg-gray-200 px-4 py-2 text-sm font-medium text-gray-800 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-100 dark:hover:bg-gray-600"
                        @click="cancelSubmitWarning"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-500"
                        @click="continueSubmitAnyway"
                    >
                        Continue Submit
                    </button>
                </div>
            </div>
        </div>

        <div v-if="isScopeReady" class="space-y-3 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <div class="flex items-center justify-between">
                <h2 class="text-base font-semibold">Audit Batch</h2>
                <span class="text-xs text-gray-500">
                    {{ discrepancyCount }} discrepancy{{ discrepancyCount === 1 ? '' : 'ies' }}
                </span>
            </div>

            <div v-if="entries.length" class="space-y-2">
                <div
                    v-for="entry in entries"
                    :key="entry.variant_id"
                    class="rounded-md border border-gray-200 px-3 py-2 dark:border-gray-700"
                >
                    <p class="text-sm font-medium">{{ entry.display_name }}</p>
                    <p class="text-xs text-gray-500">SKU: {{ entry.sku }} | Barcode: {{ entry.barcode }}</p>
                    <p class="text-sm">
                        System: {{ entry.system_quantity }} |
                        Physical: {{ entry.physical_quantity }} |
                        Variance:
                        <span
                            :class="{
                                'text-green-600': entry.physical_quantity - entry.system_quantity > 0,
                                'text-red-600': entry.physical_quantity - entry.system_quantity < 0,
                            }"
                        >
                            {{ entry.physical_quantity - entry.system_quantity > 0 ? '+' : '' }}{{ entry.physical_quantity - entry.system_quantity }}
                        </span>
                    </p>
                </div>
            </div>

            <p v-else class="text-sm text-gray-500">No scanned items yet.</p>

            <button
                type="button"
                class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500 disabled:opacity-60"
                :disabled="!entries.length || form.processing"
                @click="submitBatch"
            >
                {{ form.processing ? 'Submitting...' : 'Submit Audit Batch' }}
            </button>
        </div>
    </div>
</template>
