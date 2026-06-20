<template>
    <div class="p-6 max-w-4xl mx-auto">
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-100 mb-6">
            New Stock Adjustment
        </h1>

        <form
            @submit.prevent="submit"
            class="bg-white dark:bg-gray-900 shadow rounded-lg p-6 space-y-5"
        >
            <!-- Product Variant -->
            <div ref="variantComboboxRef" class="relative">
                <label
                    for="product-variant"
                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                >
                    Product Variant
                </label>
                <div class="relative">
                    <Search
                        class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
                    />
                    <input
                        id="product-variant"
                        ref="variantInputRef"
                        v-model="variantQuery"
                        type="search"
                        autocomplete="off"
                        placeholder="Search or select a product variant"
                        role="combobox"
                        :aria-expanded="showVariantDropdown"
                        aria-controls="product-variant-options"
                        :aria-activedescendant="showVariantDropdown && highlightedVariantIndex >= 0 ? variantOptionId(filteredVariants[highlightedVariantIndex]) : undefined"
                        class="w-full rounded-md border border-gray-300 bg-white py-2 pl-9 pr-10 text-gray-900
                 dark:border-gray-700 dark:bg-gray-800 dark:text-white placeholder:text-gray-400
                 focus:border-blue-500 focus:ring-blue-500"
                        @focus="openVariantDropdown"
                        @input="handleVariantInput"
                        @keydown="handleVariantKeydown"
                    />
                    <button
                        type="button"
                        class="absolute right-2 top-1/2 flex h-7 w-7 -translate-y-1/2 items-center justify-center rounded text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-700 dark:hover:text-gray-200"
                        aria-label="Toggle product variant list"
                        @click="toggleVariantDropdown"
                    >
                        <ChevronDown
                            class="h-4 w-4 transition"
                            :class="{ 'rotate-180': showVariantDropdown }"
                        />
                    </button>
                </div>

                <div
                    v-if="showVariantDropdown"
                    id="product-variant-options"
                    class="absolute z-30 mt-2 max-h-72 w-full overflow-y-auto rounded-md border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-900"
                    role="listbox"
                    aria-labelledby="product-variant"
                >
                    <button
                        v-for="(variant, index) in filteredVariants"
                        :id="variantOptionId(variant)"
                        :key="variant.id"
                        type="button"
                        class="flex w-full items-start gap-3 px-3 py-2 text-left text-sm transition hover:bg-blue-50 dark:hover:bg-gray-800"
                        :class="[
                            Number(form.variant_id) === Number(variant.id) ? 'bg-blue-50 dark:bg-blue-900/30' : '',
                            highlightedVariantIndex === index ? 'bg-gray-100 dark:bg-gray-800' : '',
                        ]"
                        role="option"
                        :aria-selected="Number(form.variant_id) === Number(variant.id)"
                        @mouseenter="highlightedVariantIndex = index"
                        @click="selectVariant(variant)"
                    >
                        <Check
                            class="mt-0.5 h-4 w-4 shrink-0 text-blue-600"
                            :class="Number(form.variant_id) === Number(variant.id) ? 'opacity-100' : 'opacity-0'"
                        />
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-medium text-gray-900 dark:text-gray-100">
                                {{ variant.label }}
                            </span>
                            <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">
                                Current quantity: {{ variant.current_quantity ?? 0 }}
                            </span>
                        </span>
                    </button>

                    <div
                        v-if="filteredVariants.length === 0"
                        class="px-3 py-4 text-sm text-gray-500 dark:text-gray-400"
                    >
                        No product variants match your search.
                    </div>
                </div>
                <p v-if="form.errors.variant_id" class="text-red-500 text-sm mt-1">
                    {{ form.errors.variant_id }}
                </p>
            </div>

            <!-- Quantities -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label
                        class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                    >
                        Previous Quantity
                    </label>
                    <input disabled
                        type="number"
                        v-model="form.previous_quantity"
                        class="w-full py-2 px-2 border-gray-300 disabled dark:border-gray-700 bg-white dark:bg-gray-800
                   text-gray-900 dark:text-gray-100 rounded-md focus:ring-blue-500 focus:border-blue-500"
                    />
                </div>

                <div>
                    <label
                        class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                        title="Enter +5 if you found extra items or -5 if items are missing"
                    >
                        Stock Adjustment (+/–)
                    </label>
                    <input
                        type="number"
                        placeholder="change in qty, e.g., +5 or -10"
                        v-model="form.adjusted_quantity"
                        class="w-full py-2 px-2 border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800
                   text-gray-900 dark:text-gray-100 rounded-md focus:ring-blue-500 focus:border-blue-500"
                    />
                </div>
            </div>

            <!-- Reason -->
            <div class="grid gap-5 lg:grid-cols-2">
                <div>
                <label
                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                >
                    Reason
                </label>
                <select
                    v-model="form.reason"
                    class="w-full py-2 px-2 border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800
                 text-gray-900 dark:text-gray-100 rounded-md focus:ring-blue-500 focus:border-blue-500"
                >
                    <option value="manual_correction">Manual Correction</option>
                    <option value="damage">Damage</option>
                    <option value="loss">Loss</option>
                    <option value="count_discrepancy">Count Discrepancy</option>
                    <option value="other">Other</option>
                </select>
                </div>

                <div>
                    <label
                        class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                    >
                        Adjustment Type
                    </label>
                    <select
                        v-model="form.adjustment_type"
                        class="w-full py-2 px-2 border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800
                 text-gray-900 dark:text-gray-100 rounded-md focus:ring-blue-500 focus:border-blue-500"
                    >
                        <option
                            v-for="option in adjustment_type_options"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        {{ selectedAdjustmentTypeDescription }}
                    </p>
                    <p v-if="form.errors.adjustment_type" class="text-red-500 text-sm mt-1">
                        {{ form.errors.adjustment_type }}
                    </p>
                </div>
            </div>

            <!-- Note -->
            <div>
                <label
                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                >
                    Note (Optional)
                </label>
                <textarea
                    v-model="form.note"
                    rows="3"
                    class="w-full px-2 border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800
                 text-gray-900 dark:text-gray-100 rounded-md focus:ring-blue-500 focus:border-blue-500"
                ></textarea>
            </div>

            <!-- Buttons -->
            <div class="flex justify-end space-x-3">
                <Link
                    href="/admin/stock-adjustments"
                    class="px-4 py-2 rounded-lg border text-gray-700 dark:text-gray-200
                 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-800 transition"
                >
                    Cancel
                </Link>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700
                 disabled:opacity-60 transition"
                >
                    Submit for Approval
                </button>
            </div>
        </form>
    </div>
</template>
<script setup>
import { useForm, Link } from '@inertiajs/vue3'
import { Check, ChevronDown, Search } from 'lucide-vue-next'
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'

const props = defineProps({
    variants: Array,
    adjustment_type_options: Array,
})

const form = useForm({
    warehouse_id: null,
    variant_id: '',
    previous_quantity: '',
    adjusted_quantity: '',
    adjustment_type: 'correction',
    reason: 'manual_correction',
    employee_id: null,
    note: '',
    reference: '',
})

const variantComboboxRef = ref(null)
const variantInputRef = ref(null)
const variantQuery = ref('')
const showVariantDropdown = ref(false)
const highlightedVariantIndex = ref(-1)

const selectedAdjustmentTypeDescription = computed(() => (
    props.adjustment_type_options.find(option => option.value === form.adjustment_type)?.description
    || ''
))

const selectedVariant = computed(() => (
    props.variants.find(variant => Number(variant.id) === Number(form.variant_id))
))

const filteredVariants = computed(() => {
    const term = variantQuery.value.trim().toLowerCase()
    const selectedLabel = selectedVariant.value?.label?.toLowerCase()

    if (!term || term === selectedLabel) {
        return props.variants
    }

    return props.variants.filter(variant => (
        `${variant.label} ${variant.current_quantity ?? ''}`.toLowerCase().includes(term)
    ))
})

watch(filteredVariants, async () => {
    if (!showVariantDropdown.value) {
        return
    }

    highlightedVariantIndex.value = filteredVariants.value.length ? 0 : -1
    await scrollHighlightedVariantIntoView()
})

watch(highlightedVariantIndex, scrollHighlightedVariantIntoView)

// Watch for variant change and auto-fill previous_quantity
watch(() => form.variant_id, (newId) => {
    const selected = props.variants.find(v => Number(v.id) === Number(newId))
    form.previous_quantity = selected ? selected.current_quantity : ''
})

function variantOptionId(variant) {
    return variant ? `product-variant-option-${variant.id}` : undefined
}

function openVariantDropdown() {
    showVariantDropdown.value = true
    highlightedVariantIndex.value = selectedVariant.value
        ? Math.max(filteredVariants.value.findIndex(variant => Number(variant.id) === Number(selectedVariant.value.id)), 0)
        : (filteredVariants.value.length ? 0 : -1)
}

function toggleVariantDropdown() {
    showVariantDropdown.value = !showVariantDropdown.value

    if (showVariantDropdown.value) {
        openVariantDropdown()
        nextTick(() => variantInputRef.value?.focus())
    }
}

function closeVariantDropdown() {
    showVariantDropdown.value = false
    highlightedVariantIndex.value = -1
}

function handleVariantInput() {
    showVariantDropdown.value = true

    if (selectedVariant.value && variantQuery.value !== selectedVariant.value.label) {
        form.variant_id = ''
    }
}

function handleVariantKeydown(event) {
    if (!showVariantDropdown.value && ['ArrowDown', 'ArrowUp'].includes(event.key)) {
        event.preventDefault()
        openVariantDropdown()
        return
    }

    if (event.key === 'Escape') {
        closeVariantDropdown()
        return
    }

    if (!showVariantDropdown.value) {
        return
    }

    if (event.key === 'Enter') {
        event.preventDefault()
        const variant = filteredVariants.value[highlightedVariantIndex.value]

        if (variant) {
            selectVariant(variant)
        }

        return
    }

    if (filteredVariants.value.length === 0) {
        return
    }

    if (event.key === 'ArrowDown') {
        event.preventDefault()
        highlightedVariantIndex.value = highlightedVariantIndex.value >= filteredVariants.value.length - 1
            ? 0
            : highlightedVariantIndex.value + 1
    }

    if (event.key === 'ArrowUp') {
        event.preventDefault()
        highlightedVariantIndex.value = highlightedVariantIndex.value <= 0
            ? filteredVariants.value.length - 1
            : highlightedVariantIndex.value - 1
    }
}

function selectVariant(variant) {
    form.variant_id = variant.id
    variantQuery.value = variant.label
    closeVariantDropdown()
    nextTick(() => variantInputRef.value?.focus())
}

async function scrollHighlightedVariantIntoView() {
    await nextTick()

    const variant = filteredVariants.value[highlightedVariantIndex.value]
    if (!variantComboboxRef.value || !variant) {
        return
    }

    variantComboboxRef.value
        .querySelector(`#${variantOptionId(variant)}`)
        ?.scrollIntoView({ block: 'nearest' })
}

function handleOutsideClick(event) {
    if (!variantComboboxRef.value?.contains(event.target)) {
        closeVariantDropdown()
    }
}

onMounted(() => {
    document.addEventListener('mousedown', handleOutsideClick)
})

onBeforeUnmount(() => {
    document.removeEventListener('mousedown', handleOutsideClick)
})

const submit = () => {
    form.post('/admin/stock-adjustments', { preserveScroll: true })
}
</script>
