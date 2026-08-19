<script setup>
import { Check, ImageOff, Lightbulb, Minus } from 'lucide-vue-next'
import { computed, inject } from 'vue'

const props = defineProps({
    currentStep: { type: Number, default: 0 },
})

const wizard = inject('productWizard')
const { form, isEdit, brands, activeVariants } = wizard

const brandName = computed(() => brands.value.find(brand => brand.id === form.brand_id)?.name || '')

const previewImage = computed(() => {
    const primary = form.images.find(img => img.is_primary) || form.images[0]
    return primary?._preview || ''
})

const priceDisplay = computed(() => {
    const prices = activeVariants.value
        .map(row => row.regular_price)
        .filter(value => value !== null && value !== '' && value !== undefined)
        .map(Number)
        .filter(price => Number.isFinite(price) && price >= 0)
    if (!prices.length) return null
    const min = Math.min(...prices)
    const max = Math.max(...prices)
    const fmt = value => value.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
    return min === max ? fmt(min) : `${fmt(min)} – ${fmt(max)}`
})

const totalStock = computed(() => activeVariants.value.reduce((sum, row) => sum + (parseInt(row.quantity, 10) || 0), 0))

const checklist = computed(() => [
    { label: 'Named', done: !!String(form.name || '').trim() },
    { label: 'Brand & category', done: !!form.brand_id && (form.category_ids || []).length > 0 },
    { label: 'Described', done: !!String(form.description || '').trim() },
    { label: 'Priced', done: priceDisplay.value !== null },
    { label: 'Photo added', done: form.images.length > 0 },
    { label: 'Search-friendly', done: !!String(form.meta_title || '').trim() || !!String(form.meta_description || '').trim() },
])

const readyCount = computed(() => checklist.value.filter(item => item.done).length)
const readyPercent = computed(() => Math.round((readyCount.value / checklist.value.length) * 100))

const tips = [
    'Shoppers scan names first — “Brand + model + key spec” beats vague titles every time.',
    'Cost price never shows to customers; it powers your margin and profit reports.',
    'Square photos on a plain background look best in the store grid.',
    'A good search title and description can double clicks from Google — free traffic.',
    'You can always come back: everything here is editable after the product is created.',
]

const tip = computed(() => tips[Math.min(props.currentStep, tips.length - 1)])
</script>

<template>
    <div class="space-y-4">
        <!-- Storefront-style live preview -->
        <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <p class="border-b border-gray-100 px-4 py-2.5 text-[11px] font-semibold uppercase tracking-[0.18em] text-gray-400 dark:border-gray-800 dark:text-gray-500">
                Live preview
            </p>
            <div class="aspect-square w-full bg-gray-100 dark:bg-gray-950">
                <img v-if="previewImage" :src="previewImage" alt="Product preview" class="h-full w-full object-cover" />
                <div v-else class="flex h-full w-full flex-col items-center justify-center gap-2 text-gray-300 dark:text-gray-700">
                    <ImageOff class="h-10 w-10" aria-hidden="true" />
                    <span class="text-xs">Photos appear here</span>
                </div>
            </div>
            <div class="space-y-1.5 p-4">
                <p v-if="brandName" class="text-[11px] font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">{{ brandName }}</p>
                <p class="line-clamp-2 text-sm font-semibold leading-snug" :class="form.name ? '' : 'text-gray-400 dark:text-gray-600'">
                    {{ form.name || 'Your product name' }}
                </p>
                <div class="flex items-center justify-between pt-1">
                    <p class="text-base font-bold" :class="priceDisplay ? 'text-gray-900 dark:text-gray-100' : 'text-gray-300 dark:text-gray-700'">
                        {{ priceDisplay ?? '0.00' }}
                    </p>
                    <span
                        class="rounded-full px-2 py-0.5 text-[11px] font-medium"
                        :class="form.is_active
                            ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300'
                            : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300'"
                    >
                        {{ form.is_active ? (isEdit ? 'Published' : 'Will publish') : 'Draft' }}
                    </span>
                </div>
                <p v-if="totalStock" class="text-xs text-gray-500 dark:text-gray-400">{{ totalStock }} in stock</p>
            </div>
        </div>

        <!-- Readiness checklist -->
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex items-center justify-between">
                <p class="text-sm font-semibold">Ready to sell</p>
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ readyPercent }}%</span>
            </div>
            <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                <div
                    class="h-full rounded-full bg-blue-600 transition-all duration-500 dark:bg-blue-500"
                    :style="{ width: `${readyPercent}%` }"
                />
            </div>
            <ul class="mt-3 space-y-1.5">
                <li v-for="item in checklist" :key="item.label" class="flex items-center gap-2 text-sm">
                    <span
                        class="flex h-4 w-4 items-center justify-center rounded-full"
                        :class="item.done
                            ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-400'
                            : 'bg-gray-100 text-gray-300 dark:bg-gray-800 dark:text-gray-600'"
                    >
                        <Check v-if="item.done" class="h-3 w-3" aria-hidden="true" />
                        <Minus v-else class="h-3 w-3" aria-hidden="true" />
                    </span>
                    <span :class="item.done ? 'text-gray-700 dark:text-gray-200' : 'text-gray-400 dark:text-gray-500'">
                        {{ item.label }}
                    </span>
                </li>
            </ul>
        </div>

        <!-- Contextual tip -->
        <div class="flex gap-3 rounded-lg border border-blue-200 bg-blue-50/70 p-4 text-sm text-blue-900 dark:border-blue-900 dark:bg-blue-950/30 dark:text-blue-200">
            <Lightbulb class="h-4 w-4 shrink-0" aria-hidden="true" />
            <p class="text-xs leading-relaxed">{{ tip }}</p>
        </div>
    </div>
</template>
