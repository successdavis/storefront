<script setup>
import SearchFilterSidebar from '@/components/Storefront/SearchFilterSidebar.vue'
import ProductCard from '@/components/Storefront/ProductCard.vue'
import SeoHead from '@/components/Storefront/SeoHead.vue'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet'
import StorefrontLayout from '@/layouts/StorefrontLayout.vue'
import { Link, router } from '@inertiajs/vue3'
import { FilterX, LoaderCircle, SearchX, SlidersHorizontal, X } from 'lucide-vue-next'
import { computed, ref, watch } from 'vue'

defineOptions({ layout: StorefrontLayout })

const props = defineProps({
    pageTitle: {
        type: String,
        default: 'Search Results',
    },
    filters: {
        type: Object,
        required: true,
    },
    results: {
        type: Object,
        required: true,
    },
    summary: {
        type: Object,
        default: () => ({}),
    },
    availableSorts: {
        type: Array,
        default: () => [],
    },
    priceRange: {
        type: Object,
        default: () => ({ min: 0, max: 0 }),
    },
    toggleFilters: {
        type: Array,
        default: () => [],
    },
    filterGroups: {
        type: Array,
        default: () => [],
    },
    activeFilters: {
        type: Array,
        default: () => [],
    },
    seo: {
        type: Object,
        default: () => ({}),
    },
    structuredData: {
        type: Array,
        default: () => [],
    },
})

const isUpdating = ref(false)
const isMobileFiltersOpen = ref(false)
const minPriceDraft = ref(props.filters?.min_price ?? '')
const maxPriceDraft = ref(props.filters?.max_price ?? '')

watch(
    () => props.filters,
    (value) => {
        minPriceDraft.value = value?.min_price ?? ''
        maxPriceDraft.value = value?.max_price ?? ''
    },
    { deep: true }
)

const hasResults = computed(() => Array.isArray(props.results?.data) && props.results.data.length > 0)
const defaultSort = computed(() => (props.filters?.q ? 'relevance' : 'featured'))

function paramsFromFilters() {
    const params = {}

    if (props.filters?.q) {
        params.q = props.filters.q
    }

    if (props.filters?.sort && props.filters.sort !== defaultSort.value) {
        params.sort = props.filters.sort
    }

    if (props.filters?.category?.length) {
        params.category = props.filters.category.join(',')
    }

    if (props.filters?.brand?.length) {
        params.brand = props.filters.brand.join(',')
    }

    if (props.filters?.min_price !== null && props.filters?.min_price !== '') {
        params.min_price = props.filters.min_price
    }

    if (props.filters?.max_price !== null && props.filters?.max_price !== '') {
        params.max_price = props.filters.max_price
    }

    if (props.filters?.in_stock) {
        params.in_stock = 1
    }

    if (props.filters?.on_sale) {
        params.on_sale = 1
    }

    if ((props.filters?.per_page || 24) !== 24) {
        params.per_page = props.filters.per_page
    }

    Object.entries(props.filters?.attributes || {}).forEach(([key, values]) => {
        if (Array.isArray(values) && values.length) {
            params[key] = values.join(',')
        }
    })

    return params
}

function visitWithParams(nextParams) {
    isUpdating.value = true

    router.get(route('store.search'), nextParams, {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => {
            isUpdating.value = false
            isMobileFiltersOpen.value = false
        },
    })
}

function toggleValue(list, value) {
    return list.includes(value)
        ? list.filter((item) => item !== value)
        : [...list, value]
}

function toggleOption(groupKey, value) {
    const params = paramsFromFilters()

    if (groupKey === 'category' || groupKey === 'brand') {
        const currentValues = Array.isArray(props.filters?.[groupKey]) ? props.filters[groupKey] : []
        const nextValues = toggleValue(currentValues, value)

        if (nextValues.length) {
            params[groupKey] = nextValues.join(',')
        } else {
            delete params[groupKey]
        }
    } else {
        const currentValues = Array.isArray(props.filters?.attributes?.[groupKey]) ? props.filters.attributes[groupKey] : []
        const nextValues = toggleValue(currentValues, value)

        if (nextValues.length) {
            params[groupKey] = nextValues.join(',')
        } else {
            delete params[groupKey]
        }
    }

    delete params.page
    visitWithParams(params)
}

function toggleFlag(key) {
    const params = paramsFromFilters()

    if (props.filters?.[key]) {
        delete params[key]
    } else {
        params[key] = 1
    }

    delete params.page
    visitWithParams(params)
}

function applySort(value) {
    const params = paramsFromFilters()

    if (!value || value === defaultSort.value) {
        delete params.sort
    } else {
        params.sort = value
    }

    delete params.page
    visitWithParams(params)
}

function applyPrice() {
    const params = paramsFromFilters()

    if (String(minPriceDraft.value).trim() === '') {
        delete params.min_price
    } else {
        params.min_price = minPriceDraft.value
    }

    if (String(maxPriceDraft.value).trim() === '') {
        delete params.max_price
    } else {
        params.max_price = maxPriceDraft.value
    }

    delete params.page
    visitWithParams(params)
}

function clearAllFilters() {
    const params = {}

    if (props.filters?.q) {
        params.q = props.filters.q
    }

    visitWithParams(params)
}

function clearChip(chip) {
    const params = paramsFromFilters()

    if (chip.type === 'category' || chip.type === 'brand') {
        const current = Array.isArray(props.filters?.[chip.key]) ? props.filters[chip.key] : []
        const next = current.filter((item) => item !== chip.value)

        if (next.length) {
            params[chip.key] = next.join(',')
        } else {
            delete params[chip.key]
        }
    } else if (chip.type === 'attribute') {
        const current = Array.isArray(props.filters?.attributes?.[chip.key]) ? props.filters.attributes[chip.key] : []
        const next = current.filter((item) => item !== chip.value)

        if (next.length) {
            params[chip.key] = next.join(',')
        } else {
            delete params[chip.key]
        }
    } else if (chip.type === 'toggle' || chip.type === 'min_price' || chip.type === 'max_price') {
        delete params[chip.key]
    }

    delete params.page
    visitWithParams(params)
}

function visitPage(url) {
    if (!url) {
        return
    }

    isUpdating.value = true
    router.visit(url, {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => {
            isUpdating.value = false
        },
    })
}
</script>

<template>
    <SeoHead :seo="seo" :structured-data="structuredData" />

    <section class="rounded-[2rem] border border-amber-100/80 bg-white/90 p-6 shadow-xl shadow-amber-100/40 backdrop-blur dark:border-slate-800 dark:bg-slate-950/90 dark:shadow-black/20">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div class="space-y-3">
                <p class="text-xs font-semibold uppercase tracking-[0.28em] text-amber-600 dark:text-amber-400">
                    Smart Search
                </p>
                <div>
                    <h1 class="text-3xl font-bold tracking-tight text-slate-900 dark:text-slate-100 sm:text-4xl">
                        {{ pageTitle }}
                    </h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500 dark:text-slate-400">
                        Refine by category, brand, price, availability, and context-aware product attributes pulled from the matched catalog.
                    </p>
                </div>
            </div>

            <div class="grid gap-3 sm:grid-cols-[auto_minmax(220px,260px)]">
                <Sheet v-model:open="isMobileFiltersOpen">
                    <SheetTrigger as-child>
                        <button
                            type="button"
                            class="inline-flex h-11 items-center justify-center gap-2 rounded-2xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:border-slate-300 hover:text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 lg:hidden"
                        >
                            <SlidersHorizontal class="size-4" />
                            Filters
                        </button>
                    </SheetTrigger>

                    <SheetContent side="left" class="w-full overflow-y-auto border-r border-slate-200 bg-slate-50 p-0 dark:border-slate-800 dark:bg-slate-950 sm:max-w-md">
                        <SheetHeader class="border-b border-slate-200 px-5 py-4 text-left dark:border-slate-800">
                            <SheetTitle class="text-slate-900 dark:text-slate-100">Refine Search</SheetTitle>
                        </SheetHeader>

                        <div class="p-5">
                            <SearchFilterSidebar
                                :price-range="priceRange"
                                :toggle-filters="toggleFilters"
                                :filter-groups="filterGroups"
                                :min-price-draft="minPriceDraft"
                                :max-price-draft="maxPriceDraft"
                                @update:min-price-draft="minPriceDraft = $event"
                                @update:max-price-draft="maxPriceDraft = $event"
                                @apply-price="applyPrice"
                                @toggle-option="toggleOption"
                                @toggle-flag="toggleFlag"
                                @clear-all="clearAllFilters"
                            />
                        </div>
                    </SheetContent>
                </Sheet>

                <Select :model-value="filters.sort || defaultSort" @update:model-value="applySort">
                    <SelectTrigger class="h-11 w-full rounded-2xl border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900">
                        <SelectValue placeholder="Sort results" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="sortOption in availableSorts"
                            :key="sortOption.value"
                            :value="sortOption.value"
                        >
                            {{ sortOption.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
        </div>
    </section>

    <section class="mt-6 grid gap-6 lg:grid-cols-[320px_minmax(0,1fr)]">
        <aside class="hidden lg:block">
            <div class="sticky top-6">
                <SearchFilterSidebar
                    :price-range="priceRange"
                    :toggle-filters="toggleFilters"
                    :filter-groups="filterGroups"
                    :min-price-draft="minPriceDraft"
                    :max-price-draft="maxPriceDraft"
                    @update:min-price-draft="minPriceDraft = $event"
                    @update:max-price-draft="maxPriceDraft = $event"
                    @apply-price="applyPrice"
                    @toggle-option="toggleOption"
                    @toggle-flag="toggleFlag"
                    @clear-all="clearAllFilters"
                />
            </div>
        </aside>

        <div class="space-y-5">
            <section class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-950">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">
                            {{ summary.total || 0 }} result<span v-if="(summary.total || 0) !== 1">s</span>
                        </p>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                            <template v-if="summary.query">
                                Showing the strongest matches for <span class="font-semibold text-slate-700 dark:text-slate-200">"{{ summary.query }}"</span>.
                            </template>
                            <template v-else>
                                Browse the full search catalog and narrow it from the sidebar.
                            </template>
                        </p>
                    </div>

                    <div
                        v-if="isUpdating"
                        class="inline-flex items-center gap-2 rounded-full bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-700 dark:bg-amber-500/10 dark:text-amber-300"
                    >
                        <LoaderCircle class="size-3.5 animate-spin" />
                        Updating results...
                    </div>
                </div>

                <div v-if="activeFilters.length" class="mt-4 flex flex-wrap gap-2">
                    <button
                        v-for="chip in activeFilters"
                        :key="`${chip.type}-${chip.key}-${chip.value}`"
                        type="button"
                        class="inline-flex items-center gap-2 rounded-full border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-700 transition hover:border-amber-300 hover:bg-amber-100 dark:border-amber-400/20 dark:bg-amber-500/10 dark:text-amber-300 dark:hover:border-amber-400/30 dark:hover:bg-amber-500/15"
                        @click="clearChip(chip)"
                    >
                        {{ chip.label }}
                        <X class="size-3.5" />
                    </button>

                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 transition hover:border-slate-300 hover:text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-slate-500 dark:hover:text-slate-100"
                        @click="clearAllFilters"
                    >
                        <FilterX class="size-3.5" />
                        Clear all
                    </button>
                </div>
            </section>

            <section class="relative">
                <div
                    :class="[
                        'transition-opacity duration-200',
                        isUpdating ? 'pointer-events-none opacity-30' : 'opacity-100',
                    ]"
                >
                    <div
                        v-if="hasResults"
                        class="grid grid-cols-2 gap-4 xl:grid-cols-3 2xl:grid-cols-4"
                    >
                        <ProductCard
                            v-for="product in results.data"
                            :key="product.id"
                            :product="product"
                        />
                    </div>

                    <div
                        v-else
                        class="rounded-[2rem] border border-dashed border-slate-300 bg-white px-6 py-14 text-center dark:border-slate-700 dark:bg-slate-950"
                    >
                        <div class="mx-auto flex size-14 items-center justify-center rounded-3xl bg-slate-100 text-slate-500 dark:bg-slate-900 dark:text-slate-400">
                            <SearchX class="size-6" />
                        </div>
                        <h2 class="mt-5 text-xl font-semibold text-slate-900 dark:text-slate-100">
                            No products matched this search
                        </h2>
                        <p class="mx-auto mt-2 max-w-xl text-sm leading-6 text-slate-500 dark:text-slate-400">
                            Try broadening the keywords, adjusting the price range, or clearing a few selected filters.
                        </p>
                        <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
                            <button
                                type="button"
                                class="inline-flex h-11 items-center justify-center rounded-2xl bg-slate-900 px-5 text-sm font-semibold text-white transition hover:bg-slate-700 dark:bg-amber-500 dark:text-slate-950 dark:hover:bg-amber-400"
                                @click="clearAllFilters"
                            >
                                Clear filters
                            </button>
                            <Link
                                :href="route('store.home')"
                                class="inline-flex h-11 items-center justify-center rounded-2xl border border-slate-200 px-5 text-sm font-semibold text-slate-700 transition hover:border-slate-300 hover:text-slate-900 dark:border-slate-700 dark:text-slate-200 dark:hover:border-slate-500"
                            >
                                Browse home
                            </Link>
                        </div>
                    </div>
                </div>

                <div
                    v-if="isUpdating"
                    class="absolute inset-0 grid grid-cols-2 gap-4 xl:grid-cols-3 2xl:grid-cols-4"
                >
                    <div
                        v-for="index in 8"
                        :key="index"
                        class="overflow-hidden rounded-[1.5rem] border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950"
                    >
                        <div class="aspect-[4/3] animate-pulse rounded-2xl bg-slate-100 dark:bg-slate-900" />
                        <div class="mt-4 space-y-2">
                            <div class="h-4 w-2/3 animate-pulse rounded bg-slate-100 dark:bg-slate-900" />
                            <div class="h-4 w-full animate-pulse rounded bg-slate-100 dark:bg-slate-900" />
                            <div class="h-4 w-1/3 animate-pulse rounded bg-slate-100 dark:bg-slate-900" />
                        </div>
                    </div>
                </div>
            </section>

            <div
                v-if="hasResults && results.links"
                class="flex flex-wrap items-center gap-2 rounded-[2rem] border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950"
            >
                <button
                    v-for="link in results.links"
                    :key="link.label + String(link.url)"
                    type="button"
                    :disabled="!link.url"
                    v-html="link.label"
                    :class="[
                        'inline-flex h-10 min-w-10 items-center justify-center rounded-xl border px-3 text-sm font-medium transition',
                        link.active
                            ? 'border-slate-900 bg-slate-900 text-white dark:border-amber-500 dark:bg-amber-500 dark:text-slate-950'
                            : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-slate-500 dark:hover:text-slate-100',
                        !link.url ? 'cursor-not-allowed opacity-40' : '',
                    ]"
                    @click="link.url && visitPage(link.url)"
                />
            </div>
        </div>
    </section>
</template>
