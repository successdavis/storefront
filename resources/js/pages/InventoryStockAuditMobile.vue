<script setup>
import axios from 'axios'
import { Head, useForm } from '@inertiajs/vue3'
import { computed, onBeforeUnmount, ref } from 'vue'

const props = defineProps({
    totalVariants: {
        type: Number,
        default: 0,
    },
})

const form = useForm({
    warehouse_id: null,
    note: '',
    counts: [],
})

const scannerReady = ref(false)
const scannerRunning = ref(false)
const scannerError = ref('')
const physicalInput = ref(0)
const currentVariant = ref(null)
const entries = ref([])
const lastScanValue = ref('')
const lastScanTime = ref(0)
let scanner = null

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
    if (!scannerReady.value || scannerRunning.value) {
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
            params: { barcode },
        })

        currentVariant.value = data
        physicalInput.value = Number(data.system_quantity || 0)
    } catch (error) {
        currentVariant.value = null
        scannerError.value = 'Barcode not found. Confirm the label and try again.'
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

function submitBatch() {
    if (!entries.value.length) {
        return
    }

    form.counts = entries.value.map((entry) => ({
        variant_id: entry.variant_id,
        physical_quantity: Number(entry.physical_quantity),
    }))

    form.post('/admin/inventory/stock-audit', {
        preserveScroll: true,
        onSuccess: () => {
            entries.value = []
        },
    })
}

onBeforeUnmount(async () => {
    await stopScanner()
})

initScanner()
</script>

<template>
    <Head title="Mobile Stock Audit" />

    <div class="mx-auto w-full max-w-3xl space-y-5 px-4 py-4 text-gray-900 dark:text-gray-100">
        <div>
            <h1 class="text-2xl font-bold">Mobile Stock Audit</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Scan variant barcode, count physically, save to batch, then submit.
            </p>
        </div>

        <div class="space-y-2 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <div class="flex items-center justify-between text-sm">
                <p>
                    Audited:
                    <strong>{{ auditedCount }}</strong>
                    of
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
            <p class="text-xs text-gray-500 dark:text-gray-400">{{ progressPercent }}% complete</p>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
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

        <div v-if="currentVariant" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 px-4">
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

        <div class="space-y-3 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
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
