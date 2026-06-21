<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import { useCurrencyFormatter } from '@/pages/Admin/Pos/composables/useCurrencyFormatter'

type CategoryOption = {
    id: number
    name: string
    active_products_count: number
}

type PreviewRow = {
    variant_id: number
    product_name: string
    variant_name: string
    sku: string | null
    quantity_available: number
    original_price: number
    final_price: number
    sales_price: number
    has_active_discount: boolean
    discount_label: string | null
    discount_display_label: string | null
    image_url: string | null
}

const props = defineProps<{
    categories: CategoryOption[]
    filters: {
        category_id?: number | null
        in_stock_only?: boolean
        sort?: string
    }
    report: {
        summary: {
            selected_category: { id: number; name: string } | null
            total_rows: number
            in_stock_only: boolean
            sort: string
            sort_label: string
        }
        preview: {
            data: PreviewRow[]
            links: Array<{ url: string | null; label: string; active: boolean }>
            total: number
            from: number | null
            to: number | null
        } | null
    }
}>()

const categoryId = ref<number | null>(props.filters?.category_id ?? null)
const inStockOnly = ref(Boolean(props.filters?.in_stock_only))
const sort = ref(props.filters?.sort ?? 'default')
const { formatCurrency } = useCurrencyFormatter()

const sortLabel = computed(() => props.report.summary.sort_label || 'Default')

const exportUrl = computed(() => {
    if (!categoryId.value) {
        return null
    }

    return route('admin.reports.category-price-list.export', {
        category_id: categoryId.value,
        in_stock_only: inStockOnly.value ? 1 : undefined,
        sort: sort.value || undefined,
    })
})

function runPreview(page?: string | null) {
    router.get(route('admin.reports.category-price-list.index'), {
        category_id: categoryId.value || undefined,
        in_stock_only: inStockOnly.value ? 1 : undefined,
        sort: sort.value || undefined,
        page: page || undefined,
    }, {
        preserveState: true,
        replace: true,
    })
}

function clearFilters() {
    categoryId.value = null
    inStockOnly.value = false
    sort.value = 'default'
    runPreview(null)
}

function paginationLabel(label: string) {
    return label.replace('&laquo;', '«').replace('&raquo;', '»')
}

function visitPreviewPage(url: string | null) {
    if (!url) {
        return
    }

    router.visit(url, {
        preserveState: false,
        preserveScroll: true,
        replace: true,
    })
}

watch(sort, () => {
    if (categoryId.value) {
        runPreview()
    }
})
</script>

<template>
    <Head title="Category Price List" />

    <div class="space-y-6 px-5 py-4">
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold text-slate-900 dark:text-slate-100">Category Price List</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500 dark:text-slate-400">
                        Generate a print-friendly PDF price list for sales reps using the current product image, stock availability, and live selling price logic already used by the app.
                    </p>
                </div>

                <a
                    v-if="exportUrl"
                    :href="exportUrl"
                    target="_blank"
                    rel="noopener"
                    class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-300"
                >
                    Export PDF
                </a>
                <button
                    v-else
                    type="button"
                    disabled
                    class="cursor-not-allowed rounded-xl bg-slate-300 px-4 py-2 text-sm font-semibold text-white dark:bg-slate-700 dark:text-slate-300"
                >
                    Export PDF
                </button>
            </div>

            <div class="mt-6 grid gap-4 lg:grid-cols-[1.4fr_0.9fr_0.9fr_auto]">
                <label class="text-sm">
                    <span class="mb-2 block text-xs font-medium uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Category</span>
                    <select v-model.number="categoryId" class="h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                        <option :value="null">Select a category</option>
                        <option v-for="category in categories" :key="category.id" :value="category.id">
                            {{ category.name }} ({{ category.active_products_count }})
                        </option>
                    </select>
                </label>

                <label class="text-sm">
                    <span class="mb-2 block text-xs font-medium uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Sort</span>
                    <select v-model="sort" class="h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                        <option value="default">Default</option>
                        <option value="price_asc">Price: Low to High</option>
                        <option value="price_desc">Price: High to Low</option>
                    </select>
                </label>

                <label class="flex items-center gap-3 rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700 dark:border-slate-700 dark:text-slate-200">
                    <input v-model="inStockOnly" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-slate-900 focus:ring-slate-500 dark:border-slate-600 dark:bg-slate-950">
                    <span>Only show in-stock variants</span>
                </label>

                <div class="flex flex-wrap items-end gap-2">
                    <button
                        type="button"
                        :disabled="!categoryId"
                        class="h-11 rounded-xl bg-slate-900 px-4 text-sm font-semibold text-white transition hover:bg-slate-700 disabled:cursor-not-allowed disabled:bg-slate-300 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-300 dark:disabled:bg-slate-700 dark:disabled:text-slate-300"
                        @click="runPreview()"
                    >
                        Preview
                    </button>
                    <button
                        type="button"
                        class="h-11 rounded-xl border border-slate-300 px-4 text-sm font-semibold text-slate-700 transition hover:border-slate-500 dark:border-slate-600 dark:text-slate-200 dark:hover:border-slate-400"
                        @click="clearFilters"
                    >
                        Reset
                    </button>
                </div>
            </div>
        </section>

        <section
            v-if="report.summary.selected_category"
            class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900"
        >
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-xs font-medium uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Selected Category</p>
                    <h2 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100">{{ report.summary.selected_category.name }}</h2>
                </div>

                <div class="flex flex-wrap gap-3 text-sm text-slate-500 dark:text-slate-400">
                    <span class="rounded-full bg-slate-100 px-3 py-1.5 dark:bg-slate-800 dark:text-slate-200">
                        {{ report.summary.total_rows }} row{{ report.summary.total_rows === 1 ? '' : 's' }} to export
                    </span>
                    <span class="rounded-full bg-slate-100 px-3 py-1.5 dark:bg-slate-800 dark:text-slate-200">
                        Sort: {{ sortLabel }}
                    </span>
                    <span class="rounded-full bg-slate-100 px-3 py-1.5 dark:bg-slate-800 dark:text-slate-200">
                        {{ report.summary.in_stock_only ? 'In-stock only' : 'All active variants' }}
                    </span>
                </div>
            </div>
        </section>

        <section
            v-if="report.preview && report.preview.data.length"
            class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900"
        >
            <div class="border-b border-slate-200 px-5 py-4 text-sm text-slate-500 dark:border-slate-800 dark:text-slate-400">
                Previewing
                <span class="font-semibold text-slate-900 dark:text-slate-100">{{ report.preview.from }}</span>
                to
                <span class="font-semibold text-slate-900 dark:text-slate-100">{{ report.preview.to }}</span>
                of
                <span class="font-semibold text-slate-900 dark:text-slate-100">{{ report.preview.total }}</span>
                export rows.
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead class="bg-slate-50 dark:bg-slate-950">
                        <tr class="text-left text-xs uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">
                            <th class="px-5 py-4">Product Picture</th>
                            <th class="px-5 py-4">Product Name</th>
                            <th class="px-5 py-4">Product Variant</th>
                            <th class="px-5 py-4">Quantity Available</th>
                            <th class="px-5 py-4 text-right">Sales Price</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white dark:divide-slate-800 dark:bg-slate-900">
                        <tr v-for="row in report.preview.data" :key="row.variant_id" class="align-top">
                            <td class="px-5 py-4">
                                <img
                                    v-if="row.image_url"
                                    :src="row.image_url"
                                    :alt="row.product_name"
                                    class="h-14 w-14 rounded-xl border border-slate-200 object-cover dark:border-slate-700"
                                >
                                <div
                                    v-else
                                    class="flex h-14 w-14 items-center justify-center rounded-xl border border-dashed border-slate-300 bg-slate-100 text-[10px] font-medium uppercase tracking-[0.12em] text-slate-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-400"
                                >
                                    No image
                                </div>
                            </td>
                            <td class="px-5 py-4">
                                <div class="font-semibold text-slate-900 dark:text-slate-100">{{ row.product_name }}</div>
                            </td>
                            <td class="px-5 py-4">
                                <div class="font-medium text-slate-900 dark:text-slate-100">{{ row.variant_name }}</div>
                                <p v-if="row.sku" class="mt-1 text-xs text-slate-500 dark:text-slate-400">SKU: {{ row.sku }}</p>
                            </td>
                            <td class="px-5 py-4">
                                <span class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-sm font-semibold text-slate-800 dark:bg-slate-800 dark:text-slate-100">
                                    {{ row.quantity_available }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-right">
                                <div class="flex flex-col items-end gap-1">
                                    <span
                                        v-if="row.has_active_discount && row.discount_display_label"
                                        class="inline-flex rounded-full bg-rose-100 px-2.5 py-1 text-[11px] font-semibold text-rose-700 dark:bg-rose-500/15 dark:text-rose-200"
                                    >
                                        {{ row.discount_display_label }}
                                    </span>
                                    <div
                                        v-if="row.has_active_discount && row.original_price > row.final_price"
                                        class="text-xs text-slate-400 line-through dark:text-slate-500"
                                    >
                                        {{ formatCurrency(row.original_price) }}
                                    </div>
                                    <div class="font-semibold text-slate-900 dark:text-slate-100">
                                        {{ formatCurrency(row.final_price) }}
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="report.preview.links?.length" class="flex flex-wrap gap-2 border-t border-slate-200 px-5 py-4 dark:border-slate-800">
                <button
                    v-for="link in report.preview.links"
                    :key="`${link.label}-${link.url}`"
                    type="button"
                    :disabled="!link.url"
                    :class="[
                        'rounded-lg border px-3 py-1.5 text-sm transition',
                        link.active ? 'border-slate-900 bg-slate-900 text-white dark:border-slate-100 dark:bg-slate-100 dark:text-slate-900' : 'border-slate-300 bg-white text-slate-700 hover:border-slate-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200 dark:hover:border-slate-500',
                        !link.url ? 'cursor-not-allowed opacity-40' : '',
                    ]"
                    @click="visitPreviewPage(link.url)"
                >
                    {{ paginationLabel(link.label) }}
                </button>
            </div>
        </section>

        <section
            v-else-if="report.summary.selected_category"
            class="rounded-3xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center shadow-sm dark:border-slate-700 dark:bg-slate-900"
        >
            <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">No products to export</h2>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                The selected category does not currently have any active product variants that match your filters.
            </p>
        </section>

        <section
            v-else
            class="rounded-3xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center shadow-sm dark:border-slate-700 dark:bg-slate-900"
        >
            <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Select a category to begin</h2>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                Choose a category, preview the rows that will be exported, then open the PDF in a new tab for printing.
            </p>
        </section>
    </div>
</template>
