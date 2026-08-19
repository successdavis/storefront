<script setup>
import StepBasics from '@/components/Admin/Products/Wizard/StepBasics.vue'
import StepExtras from '@/components/Admin/Products/Wizard/StepExtras.vue'
import StepInventory from '@/components/Admin/Products/Wizard/StepInventory.vue'
import StepPhotos from '@/components/Admin/Products/Wizard/StepPhotos.vue'
import StepReview from '@/components/Admin/Products/Wizard/StepReview.vue'
import PreviewCard from '@/components/Admin/Products/Wizard/PreviewCard.vue'
import { Head, useForm } from '@inertiajs/vue3'
import axios from 'axios'
import {
    ArrowLeft,
    ArrowRight,
    Check,
    ClipboardCheck,
    CloudOff,
    History,
    ImagePlus,
    Info,
    LoaderCircle,
    Sparkles,
    Tags,
    X,
} from 'lucide-vue-next'
import { computed, nextTick, provide, reactive, ref, watch } from 'vue'

const props = defineProps({
    categories: { type: Array, default: () => [] },
    brands: { type: Array, default: () => [] },
    suppliers: { type: Array, default: () => [] },
    variantTypes: { type: Array, default: () => [] },
    draft: { type: Object, default: null },
    product: { type: [Object, null], default: null }, // present = edit mode
})

/* Edit mode: the wizard doubles as the product editor. */
const p = props.product?.data ?? props.product ?? null
const isEdit = !!p

/* Prefill source: the edited product wins, then a restored auto-draft. */
const seed = p ?? props.draft ?? null

const editVariants = (p?.variants ?? []).map(row => ({ archived: false, ...row }))

const form = useForm({
    name: seed?.name ?? '',
    brand_id: seed?.brand_id ?? null,
    category_ids: [...(seed?.category_ids ?? [])],
    description: seed?.description ?? '',
    is_active: isEdit ? !!p.is_active : true,
    featured: seed?.featured ?? false,
    cash_on_delivery: seed?.cash_on_delivery ?? false,
    weight: seed?.weight ?? null,
    weight_unit: seed?.weight_unit ?? null,
    length: seed?.length ?? null,
    width: seed?.width ?? null,
    height: seed?.height ?? null,
    meta_title: seed?.meta_title ?? '',
    meta_description: seed?.meta_description ?? '',
    youtube_video_url: seed?.youtube_video_url ?? '',
    faqs: [...(seed?.faqs ?? [])],
    variants: editVariants,
    images: (p?.images ?? []).map((img, index) => ({
        id: img.id,
        path: img.path,
        file: null,
        alt: img.alt || '',
        is_primary: !!img.is_primary,
        sort_order: Number.isFinite(+img.sort_order) ? +img.sort_order : index,
        _preview: img.url || '',
    })),
})

/* Brand/category lists are mutable so inline quick-create can extend them. */
const brandOptions = ref([...(props.brands || [])])
const categoryOptions = ref([...(props.categories || [])])

function applyQuickBrand(brands, selectId = null) {
    brandOptions.value = brands
    if (selectId) form.brand_id = selectId
}

function applyQuickCategory(tree, selectId = null) {
    categoryOptions.value = tree
    if (selectId && !form.category_ids.map(String).includes(String(selectId))) {
        form.category_ids = [...form.category_ids, selectId]
    }
}

/* ---------------- Wizard state ---------------- */
const hasOptionVariants = editVariants.some(row => (row.value_ids || []).length > 0)
const mode = ref(isEdit && hasOptionVariants ? 'variants' : 'simple') // 'simple' | 'variants'

/* On edit, pre-select the option types the existing variants use. */
function typeNamesFromVariants(rows) {
    const valueIdToTypeName = new Map()
    for (const type of props.variantTypes || []) {
        for (const value of type.values || []) {
            valueIdToTypeName.set(String(value.id), type.name)
        }
    }
    const names = new Set()
    for (const row of rows) {
        for (const valueId of row.value_ids || []) {
            const name = valueIdToTypeName.get(String(valueId))
            if (name) names.add(name)
        }
    }
    return Array.from(names)
}

const selectedTypeNames = ref(isEdit && hasOptionVariants ? typeNamesFromVariants(editVariants) : [])

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

/* On edit of a simple product, the wizard edits its one existing variant in place. */
const simpleSeed = isEdit && !hasOptionVariants && editVariants.length ? editVariants[0] : null
const simpleRow = reactive({ ...makeSimpleRow(), ...(simpleSeed ?? {}) })

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
// Editing an existing product unlocks every step so any section is one click away.
const maxVisitedStep = ref(isEdit ? steps.length - 1 : 0)
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
            // Existing variants have their cost locked (it comes from purchase history).
            if (!row.id && (row.last_purchase_price === null || row.last_purchase_price === '' || Number(row.last_purchase_price) < 0)) {
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
    saveDraft() // flush any pending auto-save when moving between steps
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

/* ---------------- Auto-save as draft ---------------- */
const AUTOSAVE_DEBOUNCE_MS = 2500

const draftId = ref(props.draft?.id ?? null)
const draftStatus = ref(props.draft ? 'saved' : 'idle') // idle | saving | saved | error
const draftSavedAt = ref(null)
const finalizing = ref(false)
const showRestoredNotice = ref(!!props.draft)

let draftTimer = null
let draftInFlight = false
let draftQueued = false
let lastDraftSnapshot = ''

function draftPayload() {
    return {
        name: form.name,
        brand_id: form.brand_id,
        category_ids: [...(form.category_ids || [])],
        description: form.description,
        meta_title: form.meta_title || '',
        meta_description: form.meta_description || '',
        youtube_video_url: form.youtube_video_url || '',
        cash_on_delivery: !!form.cash_on_delivery,
        featured: !!form.featured,
        weight: form.weight,
        weight_unit: form.weight_unit,
        length: form.length,
        width: form.width,
        height: form.height,
        faqs: (form.faqs || [])
            .filter(faq => String(faq.question || '').trim() && String(faq.answer || '').trim())
            .map((faq, index) => ({
                question: faq.question,
                answer: faq.answer,
                is_active: faq.is_active !== false,
                position: index,
            })),
    }
}

if (props.draft) {
    lastDraftSnapshot = JSON.stringify(draftPayload())
}

function scheduleDraftSave() {
    if (isEdit || finalizing.value) return // editing saves explicitly, never via auto-draft
    if (draftTimer) clearTimeout(draftTimer)
    draftTimer = setTimeout(saveDraft, AUTOSAVE_DEBOUNCE_MS)
}

async function saveDraft() {
    if (draftTimer) {
        clearTimeout(draftTimer)
        draftTimer = null
    }
    if (isEdit || finalizing.value) return
    if (validateStep(0).length) return // a draft needs the basics filled in first
    if (draftInFlight) {
        draftQueued = true
        return
    }

    const payload = draftPayload()
    const snapshot = JSON.stringify(payload)
    if (snapshot === lastDraftSnapshot) return

    draftInFlight = true
    draftStatus.value = 'saving'
    try {
        if (!draftId.value) {
            const { data } = await axios.post('/admin/products/draft', payload)
            draftId.value = data.id
            // Survive refreshes: a reload of this URL restores the draft into the wizard.
            const url = new URL(window.location.href)
            url.searchParams.set('draft', String(data.id))
            window.history.replaceState(window.history.state, '', url)
        } else {
            await axios.patch(`/admin/products/${draftId.value}/draft`, payload)
        }
        lastDraftSnapshot = snapshot
        draftStatus.value = 'saved'
        draftSavedAt.value = new Date()
    } catch {
        draftStatus.value = 'error'
    } finally {
        draftInFlight = false
        if (draftQueued) {
            draftQueued = false
            scheduleDraftSave()
        }
    }
}

watch(draftPayload, scheduleDraftSave, { deep: true })

const draftSavedLabel = computed(() => {
    if (!draftSavedAt.value) return 'Draft saved'
    return `Draft saved ${draftSavedAt.value.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`
})

/* ---------------- Submit ---------------- */
const toNumberOrNull = value => (value === null || value === '' || value === undefined ? null : Number(value))

function buildPayload(data) {
    const variants = (data.variants || [])
        .filter(row => !row.archived)
        .map(row => ({
            ...(row.id ? { id: row.id } : {}),
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
            // New uploads travel as files; existing images as {id, path} rows so they survive the sync.
            images: (row.images || [])
                .map(img => {
                    if (img instanceof File) return img
                    if (img && typeof img === 'object' && (img.id || img.path)) {
                        return {
                            ...(img.id ? { id: img.id } : {}),
                            path: img.path || '',
                            alt: img.alt || '',
                            is_primary: !!img.is_primary,
                            sort_order: img.sort_order ?? 0,
                        }
                    }
                    return null
                })
                .filter(Boolean),
        }))

    const images = (data.images || [])
        .map((row, index) => {
            const base = { alt: row.alt || '', is_primary: row.is_primary ? 1 : 0, sort_order: index }
            if (row.file instanceof File) return { ...base, file: row.file }
            if (row.id) return { ...base, id: row.id, path: row.path || '' }
            return null
        })
        .filter(Boolean)

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

    // Stop auto-saving: from here the product is saved (or created fresh) in one request.
    finalizing.value = true
    if (draftTimer) {
        clearTimeout(draftTimer)
        draftTimer = null
    }

    const submitOptions = {
        forceFormData: true,
        preserveScroll: true,
        onError: () => {
            finalizing.value = false
            const erroredSteps = Object.keys(serverErrorsByStep.value).map(Number).sort((a, b) => a - b)
            if (erroredSteps.length) {
                goToStep(erroredSteps[0])
            }
        },
    }

    if (isEdit) {
        form.transform(buildPayload).put(route('admin.products.update', p.id), submitOptions)
        return
    }

    const target = draftId.value
        ? `/admin/products/${draftId.value}/finalize-draft`
        : route('admin.products.store')

    form.transform(buildPayload).post(target, submitOptions)
}

/* ---------------- Shared context for steps ---------------- */
provide('productWizard', {
    form,
    isEdit,
    mode,
    simpleRow,
    selectedTypeNames,
    brands: brandOptions,
    categories: categoryOptions,
    suppliers: computed(() => props.suppliers),
    variantTypes: computed(() => props.variantTypes),
    activeVariants,
    serverErr,
    goToStep,
    submit,
    applyQuickBrand,
    applyQuickCategory,
})
</script>

<template>
    <Head :title="isEdit ? `Edit ${form.name || 'product'}` : 'Create product'" />

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
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-blue-600 dark:text-blue-300">
                            {{ isEdit ? 'Edit product' : 'New product' }}
                        </p>
                        <h1 class="text-lg font-semibold leading-tight">{{ form.name || (isEdit ? 'Edit product' : 'Create a product') }}</h1>
                    </div>
                </div>
                <div class="hidden text-right sm:block">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Step {{ currentStep + 1 }} of {{ steps.length }} · {{ steps[currentStep].title }}
                    </p>
                    <p
                        v-if="draftStatus !== 'idle'"
                        class="mt-0.5 inline-flex items-center gap-1.5 text-xs"
                        :class="{
                            'text-gray-500 dark:text-gray-400': draftStatus === 'saving',
                            'text-emerald-600 dark:text-emerald-400': draftStatus === 'saved',
                            'text-amber-600 dark:text-amber-400': draftStatus === 'error',
                        }"
                        aria-live="polite"
                    >
                        <LoaderCircle v-if="draftStatus === 'saving'" class="h-3 w-3 animate-spin" aria-hidden="true" />
                        <Check v-else-if="draftStatus === 'saved'" class="h-3 w-3" aria-hidden="true" />
                        <CloudOff v-else class="h-3 w-3" aria-hidden="true" />
                        <template v-if="draftStatus === 'saving'">Saving draft…</template>
                        <template v-else-if="draftStatus === 'saved'">{{ draftSavedLabel }}</template>
                        <template v-else>Couldn't auto-save — will retry</template>
                    </p>
                </div>
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
                    <!-- Restored draft notice -->
                    <div
                        v-if="showRestoredNotice"
                        class="flex items-start gap-3 rounded-lg border border-blue-200 bg-blue-50/70 px-4 py-3 text-sm text-blue-900 dark:border-blue-900 dark:bg-blue-950/30 dark:text-blue-200"
                        role="status"
                    >
                        <History class="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
                        <p class="flex-1">
                            <span class="font-medium">Draft restored</span> — your earlier details are filled in.
                            Pricing and photos aren't part of auto-save, so add those before publishing.
                        </p>
                        <button
                            type="button"
                            class="rounded p-0.5 text-blue-500 transition hover:bg-blue-100 dark:hover:bg-blue-900/50"
                            aria-label="Dismiss"
                            @click="showRestoredNotice = false"
                        >
                            <X class="h-4 w-4" aria-hidden="true" />
                        </button>
                    </div>

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
                                <template v-if="isEdit">{{ form.processing ? 'Saving…' : 'Save changes' }}</template>
                                <template v-else>{{ form.processing ? 'Creating…' : (form.is_active ? 'Create & publish' : 'Create draft') }}</template>
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
