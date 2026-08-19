<script setup>
import StepBasics from '@/components/Admin/Products/Wizard/StepBasics.vue'
import StepExtras from '@/components/Admin/Products/Wizard/StepExtras.vue'
import StepInventory from '@/components/Admin/Products/Wizard/StepInventory.vue'
import StepPhotos from '@/components/Admin/Products/Wizard/StepPhotos.vue'
import StepReview from '@/components/Admin/Products/Wizard/StepReview.vue'
import PreviewCard from '@/components/Admin/Products/Wizard/PreviewCard.vue'
import { Head, useForm } from '@inertiajs/vue3'
import {
    ArrowLeft,
    ArrowRight,
    Check,
    ClipboardCheck,
    ImagePlus,
    Info,
    Sparkles,
    Tags,
} from 'lucide-vue-next'
import { computed, nextTick, provide, reactive, ref, watch } from 'vue'

const props = defineProps({
    categories: { type: Array, default: () => [] },
    brands: { type: Array, default: () => [] },
    suppliers: { type: Array, default: () => [] },
    variantTypes: { type: Array, default: () => [] },
})

/* ---------------- Form ---------------- */
const form = useForm({
    name: '',
    brand_id: null,
    category_ids: [],
    description: '',
    is_active: true,
    featured: false,
    cash_on_delivery: false,
    weight: null,
    weight_unit: null,
    length: null,
    width: null,
    height: null,
    meta_title: '',
    meta_description: '',
    youtube_video_url: '',
    faqs: [],
    variants: [],
    images: [],
})

/* ---------------- Wizard state ---------------- */
const mode = ref('simple') // 'simple' | 'variants'
const selectedTypeNames = ref([])

function makeSimpleRow() {
    return {
        id: null,
        archived: false,
        sku: '',
        quantity: 0,
        barcode: '',
        last_purchase_price: null,
        regular_price: null,
        weight: null,
        length: null,
        width: null,
        height: null,
        replenishment_status: 'reorderable',
        replenishment_note: '',
        fulfillment_type: 'stocked',
        is_dropshippable: false,
        default_supplier_id: null,
        supplier_cost: null,
        supplier_lead_time_days: null,
        show_as_available_when_dropshipping: true,
        dropshipping_note: '',
        value_ids: [],
        images: [],
    }
}

const simpleRow = reactive(makeSimpleRow())

function simpleRowAsVariant() {
    return { ...simpleRow, value_ids: [], images: [...(simpleRow.images || [])] }
}

// Keep form.variants mirroring the simple row while in simple mode.
watch(
    simpleRow,
    () => {
        if (mode.value === 'simple') {
            form.variants = [simpleRowAsVariant()]
        }
    },
    { deep: true, immediate: true },
)

// Switching modes swaps form.variants without losing what was typed in the other mode.
let stashedMatrixRows = []
watch(mode, (next) => {
    if (next === 'simple') {
        stashedMatrixRows = form.variants
        form.variants = [simpleRowAsVariant()]
    } else {
        form.variants = Array.isArray(stashedMatrixRows) && stashedMatrixRows.some(row => (row.value_ids || []).length)
            ? stashedMatrixRows
            : []
    }
})

/* ---------------- Steps ---------------- */
const steps = [
    { key: 'basics', title: 'The basics', short: 'Basics', hint: 'Name, brand & category', icon: Info, optional: false },
    { key: 'inventory', title: 'Pricing & stock', short: 'Pricing', hint: 'Options, prices, quantities', icon: Tags, optional: false },
    { key: 'photos', title: 'Photos', short: 'Photos', hint: 'Product gallery', icon: ImagePlus, optional: true },
    { key: 'extras', title: 'Details & SEO', short: 'Details', hint: 'Optional extras', icon: Sparkles, optional: true },
    { key: 'review', title: 'Review & publish', short: 'Review', hint: 'Check and publish', icon: ClipboardCheck, optional: false },
]

const currentStep = ref(0)
const maxVisitedStep = ref(0)
const attempted = reactive({}) // stepIndex -> true after a failed Continue
const stepPanel = ref(null)

const activeVariants = computed(() => (form.variants || []).filter(row => !row.archived))

/* Client-side validation per step. Returns a list of messages; empty = valid. */
function validateStep(index) {
    const problems = []

    if (index === 0) {
        if (!String(form.name || '').trim()) problems.push('Give the product a name.')
        if (!form.brand_id) problems.push('Pick a brand.')
        if (!(form.category_ids || []).length) problems.push('Choose at least one category so customers can find it.')
        if (!String(form.description || '').trim()) problems.push('Write a short description.')
    }

    if (index === 1) {
        const rows = activeVariants.value
        if (!rows.length) {
            problems.push(mode.value === 'variants'
                ? 'Select at least one option value to generate a variant.'
                : 'Fill in the price details.')
        }
        rows.forEach((row, i) => {
            const label = mode.value === 'variants' ? `Variant ${i + 1}` : 'This product'
            if (row.regular_price === null || row.regular_price === '' || Number(row.regular_price) < 0) {
                problems.push(`${label}: enter a selling price.`)
            }
            if (row.last_purchase_price === null || row.last_purchase_price === '' || Number(row.last_purchase_price) < 0) {
                problems.push(`${label}: enter the cost (purchase) price.`)
            }
        })
    }

    if (index === 3) {
        const url = String(form.youtube_video_url || '').trim()
        if (url && !/^https?:\/\//i.test(url)) {
            problems.push('The YouTube link must be a full URL (starting with http:// or https://).')
        }
        ;(form.faqs || []).forEach((faq, i) => {
            if (!String(faq.question || '').trim() || !String(faq.answer || '').trim()) {
                problems.push(`FAQ ${i + 1} needs both a question and an answer.`)
            }
        })
    }

    return problems
}

const currentProblems = computed(() => (attempted[currentStep.value] ? validateStep(currentStep.value) : []))

const stepCompletion = computed(() => steps.map((step, index) => {
    if (index >= 4) return false
    if (index > maxVisitedStep.value) return false
    return validateStep(index).length === 0 && (index < currentStep.value || index < maxVisitedStep.value)
}))

function goToStep(index) {
    if (index < 0 || index >= steps.length) return
    if (index > maxVisitedStep.value + 1) return
    currentStep.value = index
    maxVisitedStep.value = Math.max(maxVisitedStep.value, index)
    nextTick(() => {
        stepPanel.value?.scrollIntoView?.({ behavior: 'smooth', block: 'start' })
    })
}

function goNext() {
    const problems = validateStep(currentStep.value)
    if (problems.length) {
        attempted[currentStep.value] = true
        return
    }
    attempted[currentStep.value] = false
    goToStep(currentStep.value + 1)
}

function goBack() {
    goToStep(currentStep.value - 1)
}

/* ---------------- Server error mapping ---------------- */
const stepFieldMap = [
    ['name', 'brand_id', 'category_ids', 'description'],
    ['variants'],
    ['images'],
    ['meta_title', 'meta_description', 'youtube_video_url', 'weight', 'weight_unit', 'length', 'width', 'height', 'faqs', 'featured', 'cash_on_delivery'],
    ['is_active', 'slug'],
]

function stepForErrorKey(key) {
    const index = stepFieldMap.findIndex(fields => fields.some(field => key === field || key.startsWith(field + '.')))
    return index === -1 ? 0 : index
}

function serverErr(key) {
    if (!form.errors) return ''
    if (form.errors[key]) return form.errors[key]
    const prefix = key + '.'
    const match = Object.keys(form.errors).find(k => k.startsWith(prefix))
    return match ? form.errors[match] : ''
}

const serverErrorsByStep = computed(() => {
    const grouped = {}
    for (const [key, message] of Object.entries(form.errors || {})) {
        const step = stepForErrorKey(key)
        if (!grouped[step]) grouped[step] = []
        grouped[step].push({ key, message })
    }
    return grouped
})

function serverErrorCount(index) {
    return (serverErrorsByStep.value[index] || []).length
}

/* ---------------- Submit ---------------- */
const toNumberOrNull = value => (value === null || value === '' || value === undefined ? null : Number(value))

function buildPayload(data) {
    const variants = (data.variants || [])
        .filter(row => !row.archived)
        .map(row => ({
            sku: String(row.sku || '').trim() || null,
            quantity: row.fulfillment_type === 'dropshipping' ? 0 : Math.max(0, parseInt(row.quantity, 10) || 0),
            barcode: String(row.barcode || '').trim() || null,
            last_purchase_price: toNumberOrNull(row.last_purchase_price),
            regular_price: toNumberOrNull(row.regular_price),
            weight: toNumberOrNull(row.weight),
            length: toNumberOrNull(row.length),
            width: toNumberOrNull(row.width),
            height: toNumberOrNull(row.height),
            replenishment_status: row.replenishment_status || 'reorderable',
            replenishment_note: row.replenishment_note || '',
            fulfillment_type: row.fulfillment_type || 'stocked',
            default_supplier_id: row.default_supplier_id ?? null,
            supplier_cost: toNumberOrNull(row.supplier_cost),
            supplier_lead_time_days: toNumberOrNull(row.supplier_lead_time_days),
            show_as_available_when_dropshipping: !!row.show_as_available_when_dropshipping,
            dropshipping_note: row.dropshipping_note || '',
            value_ids: row.value_ids || [],
            images: (row.images || []).filter(file => file instanceof File),
        }))

    const images = (data.images || [])
        .filter(row => row.file instanceof File)
        .map((row, index) => ({
            file: row.file,
            alt: row.alt || '',
            is_primary: row.is_primary ? 1 : 0,
            sort_order: index,
        }))

    return { ...data, variants, images }
}

function submit() {
    // Re-check the required steps so nothing broken slips through from an earlier edit.
    for (const index of [0, 1, 3]) {
        if (validateStep(index).length) {
            attempted[index] = true
            goToStep(index)
            return
        }
    }

    form.transform(buildPayload).post(route('admin.products.store'), {
        forceFormData: true,
        preserveScroll: true,
        onError: () => {
            const erroredSteps = Object.keys(serverErrorsByStep.value).map(Number).sort((a, b) => a - b)
            if (erroredSteps.length) {
                goToStep(erroredSteps[0])
            }
        },
    })
}

/* ---------------- Shared context for steps ---------------- */
provide('productWizard', {
    form,
    mode,
    simpleRow,
    selectedTypeNames,
    brands: computed(() => props.brands),
    categories: computed(() => props.categories),
    suppliers: computed(() => props.suppliers),
    variantTypes: computed(() => props.variantTypes),
    activeVariants,
    serverErr,
    goToStep,
    submit,
})
</script>

<template>
    <Head title="Create product" />

    <div class="min-h-screen bg-gray-100 text-gray-900 dark:bg-gray-950 dark:text-gray-100">
        <!-- Top bar -->
        <header class="border-b border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-4 sm:px-6">
                <div class="flex items-center gap-3">
                    <a
                        :href="route('admin.products.index')"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-300 text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                        title="Back to products"
                        aria-label="Back to products"
                    >
                        <ArrowLeft class="h-4 w-4" aria-hidden="true" />
                    </a>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-blue-600 dark:text-blue-300">New product</p>
                        <h1 class="text-lg font-semibold leading-tight">{{ form.name || 'Create a product' }}</h1>
                    </div>
                </div>
                <p class="hidden text-sm text-gray-500 dark:text-gray-400 sm:block">
                    Step {{ currentStep + 1 }} of {{ steps.length }} · {{ steps[currentStep].title }}
                </p>
            </div>
        </header>

        <!-- Stepper -->
        <nav class="border-b border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900" aria-label="Progress">
            <ol class="mx-auto flex max-w-7xl items-stretch overflow-x-auto px-4 sm:px-6">
                <li v-for="(step, index) in steps" :key="step.key" class="flex flex-1 items-center">
                    <button
                        type="button"
                        class="group flex min-w-0 flex-1 items-center gap-3 border-b-2 px-2 py-3 text-left transition disabled:cursor-not-allowed"
                        :class="index === currentStep
                            ? 'border-blue-600 dark:border-blue-400'
                            : 'border-transparent hover:border-gray-300 dark:hover:border-gray-700'"
                        :disabled="index > maxVisitedStep + 1"
                        :aria-current="index === currentStep ? 'step' : undefined"
                        @click="goToStep(index)"
                    >
                        <span
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-semibold transition"
                            :class="serverErrorCount(index)
                                ? 'bg-rose-100 text-rose-700 dark:bg-rose-950/50 dark:text-rose-300'
                                : stepCompletion[index]
                                    ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300'
                                    : index === currentStep
                                        ? 'bg-blue-600 text-white dark:bg-blue-500'
                                        : 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400'"
                        >
                            <template v-if="serverErrorCount(index)">{{ serverErrorCount(index) }}</template>
                            <Check v-else-if="stepCompletion[index]" class="h-4 w-4" aria-hidden="true" />
                            <component :is="step.icon" v-else class="h-4 w-4" aria-hidden="true" />
                        </span>
                        <span class="min-w-0">
                            <span
                                class="block truncate text-sm font-medium"
                                :class="index === currentStep ? 'text-blue-700 dark:text-blue-300' : 'text-gray-700 dark:text-gray-200'"
                            >
                                {{ step.short }}
                                <span v-if="step.optional" class="ml-1 text-[10px] font-normal uppercase tracking-wide text-gray-400">optional</span>
                            </span>
                            <span class="hidden truncate text-xs text-gray-500 dark:text-gray-400 lg:block">{{ step.hint }}</span>
                        </span>
                    </button>
                </li>
            </ol>
        </nav>

        <!-- Body -->
        <main class="mx-auto max-w-7xl px-4 py-6 sm:px-6">
            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
                <div ref="stepPanel" class="min-w-0 space-y-4">
                    <!-- Client validation summary -->
                    <div
                        v-if="currentProblems.length"
                        class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-700 dark:bg-amber-950/30 dark:text-amber-200"
                        role="alert"
                    >
                        <p class="font-medium">Almost there — a few things need attention:</p>
                        <ul class="mt-1 list-inside list-disc space-y-0.5">
                            <li v-for="(problem, i) in currentProblems" :key="i">{{ problem }}</li>
                        </ul>
                    </div>

                    <!-- Server validation summary -->
                    <div
                        v-if="serverErrorCount(currentStep)"
                        class="rounded-lg border border-rose-300 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-800 dark:bg-rose-950/30 dark:text-rose-300"
                        role="alert"
                    >
                        <p class="font-medium">The server rejected some of these fields:</p>
                        <ul class="mt-1 list-inside list-disc space-y-0.5">
                            <li v-for="entry in serverErrorsByStep[currentStep]" :key="entry.key">{{ entry.message }}</li>
                        </ul>
                    </div>

                    <StepBasics v-show="currentStep === 0" />
                    <StepInventory v-show="currentStep === 1" />
                    <StepPhotos v-show="currentStep === 2" />
                    <StepExtras v-show="currentStep === 3" />
                    <StepReview v-if="currentStep === 4" :steps="steps" :validate-step="validateStep" />

                    <!-- Footer nav -->
                    <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-3 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <button
                            v-if="currentStep > 0"
                            type="button"
                            class="inline-flex items-center gap-2 rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800"
                            @click="goBack"
                        >
                            <ArrowLeft class="h-4 w-4" aria-hidden="true" />
                            Back
                        </button>
                        <span v-else />

                        <div class="flex items-center gap-3">
                            <p v-if="steps[currentStep].optional" class="hidden text-xs text-gray-500 dark:text-gray-400 sm:block">
                                This step is optional — you can continue without it.
                            </p>
                            <button
                                v-if="currentStep < steps.length - 1"
                                type="button"
                                class="inline-flex items-center gap-2 rounded-md bg-blue-600 px-5 py-2 text-sm font-semibold text-white transition hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-400"
                                @click="goNext"
                            >
                                Continue
                                <ArrowRight class="h-4 w-4" aria-hidden="true" />
                            </button>
                            <button
                                v-else
                                type="button"
                                class="inline-flex items-center gap-2 rounded-md bg-emerald-600 px-5 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-emerald-500 dark:hover:bg-emerald-400"
                                :disabled="form.processing"
                                @click="submit"
                            >
                                <Check class="h-4 w-4" aria-hidden="true" />
                                {{ form.processing ? 'Creating…' : (form.is_active ? 'Create & publish' : 'Create draft') }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Live preview rail -->
                <aside class="hidden lg:block">
                    <div class="sticky top-6 space-y-4">
                        <PreviewCard :current-step="currentStep" />
                    </div>
                </aside>
            </div>
        </main>
    </div>
</template>
