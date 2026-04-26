<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import { computed, reactive, watch } from 'vue'

type CategoryOption = {
    id: number
    name: string
    active_products_count: number
}

type InventoryValuationRow = {
    variant_id: number
    variant_name: string
    sku: string | null
    on_hand: number
    average_cost: number
    asset_value: number
    asset_percent: number
    sale_price: number
    retail_value: number
    retail_percent: number
}

type InventoryValuationGroup = {
    category_name: string
    row_count: number
    totals: {
        on_hand: number
        asset_value: number
        asset_percent: number
        retail_value: number
        retail_percent: number
    }
    rows: InventoryValuationRow[]
}

const props = defineProps<{
    categories: CategoryOption[]
    report: {
        filters: {
            as_of: string
            category_id: number | null
        }
        summary: {
            as_of_label: string
            total_on_hand: number
            total_asset_value: number
            total_retail_value: number
            category_count: number
            variant_count: number
        }
        groups: InventoryValuationGroup[]
    }
}>()

const filters = reactive({
    as_of: props.report.filters.as_of || '',
    category_id: props.report.filters.category_id ?? '',
})

const exportUrl = computed(() => route('admin.accounting.reports.inventory-valuation.export', {
    as_of: filters.as_of || undefined,
    category_id: filters.category_id || undefined,
}))

watch(
    () => ({ ...filters }),
    (value) => {
        router.get(route('admin.accounting.reports.inventory-valuation'), {
            as_of: value.as_of || undefined,
            category_id: value.category_id || undefined,
        }, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        })
    },
)

function formatCurrency(value: number) {
    return new Intl.NumberFormat('en-NG', {
        style: 'currency',
        currency: 'NGN',
        minimumFractionDigits: 2,
    }).format(value)
}

function formatNumber(value: number) {
    return new Intl.NumberFormat('en-NG').format(value)
}

function formatPercent(value: number) {
    return `${value.toFixed(1)}%`
}
</script>

<template>
    <Head title="Inventory Valuation Summary" />

    <div class="min-h-screen space-y-6 bg-slate-100 p-6 dark:bg-slate-950">
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
                <div class="flex-1 text-center xl:text-left">
                    <p class="text-sm font-medium uppercase tracking-[0.24em] text-sky-500">Accounting reports</p>
                    <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-950 dark:text-white">Inventory Valuation Summary</h1>
                    <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">
                        Weighted-average inventory value as of {{ report.summary.as_of_label }}.
                    </p>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 xl:min-w-[24rem]">
                    <label class="space-y-2 text-sm text-slate-600 dark:text-slate-300">
                        <span>As of date</span>
                        <input
                            v-model="filters.as_of"
                            type="date"
                            class="w-full rounded-2xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"
                        />
                    </label>

                    <label class="space-y-2 text-sm text-slate-600 dark:text-slate-300">
                        <span>Category</span>
                        <select
                            v-model="filters.category_id"
                            class="w-full rounded-2xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"
                        >
                            <option value="">All categories</option>
                            <option v-for="category in categories" :key="category.id" :value="category.id">
                                {{ category.name }} ({{ category.active_products_count }})
                            </option>
                        </select>
                    </label>
                </div>
            </div>

            <div class="mt-5 flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap gap-3 text-sm text-slate-500 dark:text-slate-400">
                    <span class="rounded-full bg-slate-100 px-3 py-1.5 dark:bg-slate-800 dark:text-slate-200">
                        {{ formatNumber(report.summary.total_on_hand) }} units on hand
                    </span>
                    <span class="rounded-full bg-slate-100 px-3 py-1.5 dark:bg-slate-800 dark:text-slate-200">
                        {{ report.summary.category_count }} categories
                    </span>
                    <span class="rounded-full bg-slate-100 px-3 py-1.5 dark:bg-slate-800 dark:text-slate-200">
                        {{ report.summary.variant_count }} stocked variants
                    </span>
                </div>

                <div class="flex items-center gap-3">
                    <a
                        :href="exportUrl"
                        target="_blank"
                        rel="noopener"
                        class="rounded-2xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-300"
                    >
                        Export PDF
                    </a>

                    <Link
                        :href="route('admin.accounting.index')"
                        class="text-sm font-medium text-sky-600 transition hover:text-sky-500 dark:text-sky-300 dark:hover:text-sky-200"
                    >
                        Back to accounting overview
                    </Link>
                </div>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-3">
            <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400">Total Asset Value</p>
                <p class="mt-3 text-3xl font-semibold text-slate-950 dark:text-white">{{ formatCurrency(report.summary.total_asset_value) }}</p>
            </article>

            <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400">Total Retail Value</p>
                <p class="mt-3 text-3xl font-semibold text-slate-950 dark:text-white">{{ formatCurrency(report.summary.total_retail_value) }}</p>
            </article>

            <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400">Units On Hand</p>
                <p class="mt-3 text-3xl font-semibold text-slate-950 dark:text-white">{{ formatNumber(report.summary.total_on_hand) }}</p>
            </article>
        </section>

        <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="overflow-x-auto">
                <table class="min-w-[980px] w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead class="bg-slate-50 dark:bg-slate-950">
                        <tr class="text-left text-xs uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400">
                            <th class="px-5 py-4">Variant</th>
                            <th class="px-4 py-4 text-right">On Hand</th>
                            <th class="px-4 py-4 text-right">Avg Cost</th>
                            <th class="px-4 py-4 text-right">Asset Value</th>
                            <th class="px-4 py-4 text-right">% of Total Asset</th>
                            <th class="px-4 py-4 text-right">Sale Price</th>
                            <th class="px-4 py-4 text-right">Retail Value</th>
                            <th class="px-4 py-4 text-right">% of Total Retail</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <template v-for="group in report.groups" :key="group.category_name">
                            <tr class="bg-slate-100/80 dark:bg-slate-800/60">
                                <td colspan="8" class="px-5 py-3 text-sm font-semibold uppercase tracking-[0.18em] text-slate-700 dark:text-slate-200">
                                    {{ group.category_name }}
                                </td>
                            </tr>

                            <tr
                                v-for="row in group.rows"
                                :key="row.variant_id"
                                class="align-top"
                            >
                                <td class="px-5 py-3">
                                    <div class="font-medium text-slate-900 dark:text-slate-100">{{ row.variant_name }}</div>
                                    <p v-if="row.sku" class="mt-1 text-xs text-slate-500 dark:text-slate-400">SKU: {{ row.sku }}</p>
                                </td>
                                <td class="px-4 py-3 text-right font-medium text-slate-900 dark:text-slate-100">{{ formatNumber(row.on_hand) }}</td>
                                <td class="px-4 py-3 text-right text-slate-700 dark:text-slate-200">{{ formatCurrency(row.average_cost) }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-slate-900 dark:text-slate-100">{{ formatCurrency(row.asset_value) }}</td>
                                <td class="px-4 py-3 text-right text-slate-700 dark:text-slate-200">{{ formatPercent(row.asset_percent) }}</td>
                                <td class="px-4 py-3 text-right text-slate-700 dark:text-slate-200">{{ formatCurrency(row.sale_price) }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-slate-900 dark:text-slate-100">{{ formatCurrency(row.retail_value) }}</td>
                                <td class="px-4 py-3 text-right text-slate-700 dark:text-slate-200">{{ formatPercent(row.retail_percent) }}</td>
                            </tr>

                            <tr class="bg-slate-50 dark:bg-slate-950/70">
                                <td class="px-5 py-3 font-semibold text-slate-900 dark:text-slate-100">Total {{ group.category_name }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-slate-900 dark:text-slate-100">{{ formatNumber(group.totals.on_hand) }}</td>
                                <td class="px-4 py-3"></td>
                                <td class="px-4 py-3 text-right font-semibold text-slate-900 dark:text-slate-100">{{ formatCurrency(group.totals.asset_value) }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-slate-900 dark:text-slate-100">{{ formatPercent(group.totals.asset_percent) }}</td>
                                <td class="px-4 py-3"></td>
                                <td class="px-4 py-3 text-right font-semibold text-slate-900 dark:text-slate-100">{{ formatCurrency(group.totals.retail_value) }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-slate-900 dark:text-slate-100">{{ formatPercent(group.totals.retail_percent) }}</td>
                            </tr>
                        </template>

                        <tr v-if="!report.groups.length">
                            <td colspan="8" class="px-6 py-12 text-center text-sm text-slate-500 dark:text-slate-400">
                                No stocked inventory matched the selected filters.
                            </td>
                        </tr>
                    </tbody>

                    <tfoot v-if="report.groups.length" class="bg-slate-100 dark:bg-slate-800">
                        <tr>
                            <td class="px-5 py-4 text-base font-semibold text-slate-950 dark:text-white">TOTAL</td>
                            <td class="px-4 py-4 text-right text-base font-semibold text-slate-950 dark:text-white">{{ formatNumber(report.summary.total_on_hand) }}</td>
                            <td class="px-4 py-4"></td>
                            <td class="px-4 py-4 text-right text-base font-semibold text-slate-950 dark:text-white">{{ formatCurrency(report.summary.total_asset_value) }}</td>
                            <td class="px-4 py-4 text-right text-base font-semibold text-slate-950 dark:text-white">100.0%</td>
                            <td class="px-4 py-4"></td>
                            <td class="px-4 py-4 text-right text-base font-semibold text-slate-950 dark:text-white">{{ formatCurrency(report.summary.total_retail_value) }}</td>
                            <td class="px-4 py-4 text-right text-base font-semibold text-slate-950 dark:text-white">100.0%</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>
    </div>
</template>
