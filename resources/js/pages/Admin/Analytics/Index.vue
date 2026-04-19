<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import {
    Activity,
    BarChart3,
    Download,
    Globe2,
    MonitorSmartphone,
    RefreshCcw,
    Route,
    Settings2,
    Users,
} from 'lucide-vue-next'
import VChart from 'vue-echarts'
import { computed, reactive, watch } from 'vue'

type SummaryCard = { key: string; label: string; value: number }
type SelectOption = { value: string; label: string }

type Report = {
    filters: { range: string; from: string; to: string; trend: string }
    date_presets: SelectOption[]
    trend_options: SelectOption[]
    summary_cards: SummaryCard[]
    trend_chart: { labels: string[]; series: Array<Record<string, any>> }
    top_pages: Array<Record<string, any>>
    countries: Array<Record<string, any>>
    regions: Array<Record<string, any>>
    devices: Array<{ device_type: string; page_views: number; unique_visitors: number }>
    referrers: Array<{ referrer_domain: string; page_views: number; unique_visitors: number }>
}

const props = defineProps<{
    report: Report
    permissions: { can_manage: boolean }
}>()

const filters = reactive({
    range: props.report.filters.range,
    from: props.report.filters.from,
    to: props.report.filters.to,
    trend: props.report.filters.trend,
})

const summaryIcons: Record<string, any> = {
    page_views: Activity,
    unique_visitors: Users,
    new_visitors: RefreshCcw,
    returning_visitors: Route,
    authenticated_visitors: MonitorSmartphone,
    guest_visitors: Globe2,
}

watch(
    () => ({ ...filters }),
    (value) => {
        router.get('/admin/analytics', value, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        })
    },
    { deep: true },
)

const trendOptions = computed(() => ({
    tooltip: { trigger: 'axis' },
    legend: {
        textStyle: { color: '#cbd5e1' },
    },
    grid: {
        left: 24,
        right: 24,
        top: 40,
        bottom: 24,
        containLabel: true,
    },
    xAxis: {
        type: 'category',
        data: props.report.trend_chart.labels,
        axisLabel: { color: '#94a3b8' },
        axisLine: { lineStyle: { color: '#334155' } },
    },
    yAxis: {
        type: 'value',
        axisLabel: { color: '#94a3b8' },
        splitLine: { lineStyle: { color: '#1e293b' } },
    },
    series: props.report.trend_chart.series.map((series, index) => ({
        ...series,
        lineStyle: { width: 3 },
        itemStyle: { color: index === 0 ? '#38bdf8' : '#22c55e' },
        areaStyle: { opacity: 0.08 },
    })),
}))

const deviceOptions = computed(() => ({
    tooltip: { trigger: 'item' },
    legend: {
        bottom: 0,
        textStyle: { color: '#cbd5e1' },
    },
    series: [
        {
            type: 'pie',
            radius: ['44%', '72%'],
            label: { color: '#e2e8f0' },
            data: props.report.devices.map((device) => ({
                name: formatLabel(device.device_type),
                value: device.page_views,
            })),
        },
    ],
}))

const exportBaseQuery = computed(() => {
    const params = new URLSearchParams()

    params.set('range', filters.range)
    params.set('trend', filters.trend)

    if (filters.range === 'custom') {
        params.set('from', filters.from)
        params.set('to', filters.to)
    }

    return params.toString()
})

function exportUrl(type: string) {
    const query = exportBaseQuery.value

    return `/admin/analytics/export?type=${encodeURIComponent(type)}${query ? `&${query}` : ''}`
}

function formatNumber(value: number) {
    return new Intl.NumberFormat('en-US').format(value)
}

function formatLabel(value: string) {
    return value.replace(/_/g, ' ').replace(/\b\w/g, (letter) => letter.toUpperCase())
}
</script>

<template>
    <Head title="Storefront Analytics" />

    <div class="min-h-screen space-y-6 bg-slate-100 p-6 dark:bg-slate-950">
        <div class="flex flex-col gap-4 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900 lg:flex-row lg:items-end lg:justify-between">
            <div class="space-y-2">
                <p class="text-sm font-medium uppercase tracking-[0.24em] text-sky-500">Storefront analytics</p>
                <h1 class="text-3xl font-semibold tracking-tight text-slate-950 dark:text-white">Traffic, geography, and visitor behavior</h1>
                <p class="max-w-3xl text-sm text-slate-600 dark:text-slate-300">
                    Lightweight storefront page tracking, aggregated for fast admin reporting and built to stay out of product, cart, and checkout flows.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <a
                    :href="exportUrl('summary')"
                    class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:border-sky-300 hover:text-sky-600 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200 dark:hover:border-sky-500/50 dark:hover:text-sky-300"
                >
                    <Download class="h-4 w-4" />
                    Export summary
                </a>

                <Link
                    v-if="permissions.can_manage"
                    href="/admin/analytics/settings"
                    class="inline-flex items-center gap-2 rounded-2xl bg-slate-950 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800 dark:bg-white dark:text-slate-950 dark:hover:bg-slate-200"
                >
                    <Settings2 class="h-4 w-4" />
                    Analytics settings
                </Link>
            </div>
        </div>

        <div class="grid gap-3 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:grid-cols-2 xl:grid-cols-4">
            <label class="space-y-2 text-sm text-slate-600 dark:text-slate-300">
                <span>Date range</span>
                <select v-model="filters.range" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-sky-400 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                    <option v-for="option in report.date_presets" :key="option.value" :value="option.value">{{ option.label }}</option>
                </select>
            </label>

            <label class="space-y-2 text-sm text-slate-600 dark:text-slate-300">
                <span>Trend view</span>
                <select v-model="filters.trend" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-sky-400 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                    <option v-for="option in report.trend_options" :key="option.value" :value="option.value">{{ option.label }}</option>
                </select>
            </label>

            <label v-if="filters.range === 'custom'" class="space-y-2 text-sm text-slate-600 dark:text-slate-300">
                <span>From</span>
                <input v-model="filters.from" type="date" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-sky-400 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100" />
            </label>

            <label v-if="filters.range === 'custom'" class="space-y-2 text-sm text-slate-600 dark:text-slate-300">
                <span>To</span>
                <input v-model="filters.to" type="date" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-sky-400 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100" />
            </label>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <div v-for="card in report.summary_cards" :key="card.key" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500 dark:text-slate-400">{{ card.label }}</p>
                        <p class="mt-3 text-3xl font-semibold tracking-tight text-slate-950 dark:text-white">{{ formatNumber(card.value) }}</p>
                    </div>
                    <component :is="summaryIcons[card.key] || BarChart3" class="h-10 w-10 rounded-2xl bg-sky-50 p-2.5 text-sky-500 dark:bg-sky-500/10" />
                </div>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1.7fr)_minmax(320px,0.9fr)]">
            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-950 dark:text-white">Traffic trend</h2>
                        <p class="text-sm text-slate-600 dark:text-slate-300">Daily, weekly, or monthly storefront traffic over the selected date range.</p>
                    </div>

                    <a
                        :href="exportUrl('trend')"
                        class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:border-sky-300 hover:text-sky-600 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200 dark:hover:border-sky-500/50 dark:hover:text-sky-300"
                    >
                        <Download class="h-4 w-4" />
                        Export
                    </a>
                </div>

                <div class="h-[360px] overflow-hidden">
                    <v-chart class="h-full w-full" :option="trendOptions" autoresize />
                </div>
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-950 dark:text-white">Devices</h2>
                        <p class="text-sm text-slate-600 dark:text-slate-300">Traffic split by device class.</p>
                    </div>

                    <a
                        :href="exportUrl('devices')"
                        class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:border-sky-300 hover:text-sky-600 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200 dark:hover:border-sky-500/50 dark:hover:text-sky-300"
                    >
                        <Download class="h-4 w-4" />
                        Export
                    </a>
                </div>

                <div class="h-[280px] overflow-hidden">
                    <v-chart class="h-full w-full" :option="deviceOptions" autoresize />
                </div>
            </section>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-950 dark:text-white">Most visited pages</h2>
                        <p class="text-sm text-slate-600 dark:text-slate-300">Pages getting the most attention during the selected range.</p>
                    </div>

                    <a
                        :href="exportUrl('pages')"
                        class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:border-sky-300 hover:text-sky-600 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200 dark:hover:border-sky-500/50 dark:hover:text-sky-300"
                    >
                        <Download class="h-4 w-4" />
                        Export
                    </a>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                        <thead>
                            <tr class="text-left text-xs uppercase tracking-[0.24em] text-slate-500 dark:text-slate-400">
                                <th class="pb-3 pr-4">Page</th>
                                <th class="pb-3 pr-4">Views</th>
                                <th class="pb-3">Visitors</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="page in report.top_pages" :key="page.page_path">
                                <td class="py-3 pr-4">
                                    <p class="font-medium text-slate-950 dark:text-white">{{ page.page_title }}</p>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ page.page_path }}</p>
                                </td>
                                <td class="py-3 pr-4 text-slate-700 dark:text-slate-200">{{ formatNumber(page.page_views) }}</td>
                                <td class="py-3 text-slate-700 dark:text-slate-200">{{ formatNumber(page.unique_visitors) }}</td>
                            </tr>
                            <tr v-if="!report.top_pages.length">
                                <td colspan="3" class="py-8 text-center text-sm text-slate-500 dark:text-slate-400">No storefront page traffic has been recorded for this range yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-950 dark:text-white">Referrers</h2>
                        <p class="text-sm text-slate-600 dark:text-slate-300">External domains sending visitors into the storefront.</p>
                    </div>

                    <a
                        :href="exportUrl('referrers')"
                        class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:border-sky-300 hover:text-sky-600 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200 dark:hover:border-sky-500/50 dark:hover:text-sky-300"
                    >
                        <Download class="h-4 w-4" />
                        Export
                    </a>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                        <thead>
                            <tr class="text-left text-xs uppercase tracking-[0.24em] text-slate-500 dark:text-slate-400">
                                <th class="pb-3 pr-4">Source</th>
                                <th class="pb-3 pr-4">Views</th>
                                <th class="pb-3">Visitors</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="source in report.referrers" :key="source.referrer_domain">
                                <td class="py-3 pr-4 font-medium text-slate-950 dark:text-white">{{ source.referrer_domain }}</td>
                                <td class="py-3 pr-4 text-slate-700 dark:text-slate-200">{{ formatNumber(source.page_views) }}</td>
                                <td class="py-3 text-slate-700 dark:text-slate-200">{{ formatNumber(source.unique_visitors) }}</td>
                            </tr>
                            <tr v-if="!report.referrers.length">
                                <td colspan="3" class="py-8 text-center text-sm text-slate-500 dark:text-slate-400">No external referrer traffic was captured in this range.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-950 dark:text-white">Countries</h2>
                        <p class="text-sm text-slate-600 dark:text-slate-300">Traffic distribution by visitor country.</p>
                    </div>

                    <a
                        :href="exportUrl('countries')"
                        class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:border-sky-300 hover:text-sky-600 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200 dark:hover:border-sky-500/50 dark:hover:text-sky-300"
                    >
                        <Download class="h-4 w-4" />
                        Export
                    </a>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                        <thead>
                            <tr class="text-left text-xs uppercase tracking-[0.24em] text-slate-500 dark:text-slate-400">
                                <th class="pb-3 pr-4">Country</th>
                                <th class="pb-3 pr-4">Views</th>
                                <th class="pb-3">Visitors</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="country in report.countries" :key="`${country.country_code}-${country.label}`">
                                <td class="py-3 pr-4 font-medium text-slate-950 dark:text-white">{{ country.label }}</td>
                                <td class="py-3 pr-4 text-slate-700 dark:text-slate-200">{{ formatNumber(country.page_views) }}</td>
                                <td class="py-3 text-slate-700 dark:text-slate-200">{{ formatNumber(country.unique_visitors) }}</td>
                            </tr>
                            <tr v-if="!report.countries.length">
                                <td colspan="3" class="py-8 text-center text-sm text-slate-500 dark:text-slate-400">No country-level traffic is available for this range yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-950 dark:text-white">States / Regions</h2>
                        <p class="text-sm text-slate-600 dark:text-slate-300">Regional visitor breakdown where location is known.</p>
                    </div>

                    <a
                        :href="exportUrl('regions')"
                        class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:border-sky-300 hover:text-sky-600 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200 dark:hover:border-sky-500/50 dark:hover:text-sky-300"
                    >
                        <Download class="h-4 w-4" />
                        Export
                    </a>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                        <thead>
                            <tr class="text-left text-xs uppercase tracking-[0.24em] text-slate-500 dark:text-slate-400">
                                <th class="pb-3 pr-4">Region</th>
                                <th class="pb-3 pr-4">Views</th>
                                <th class="pb-3">Visitors</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="region in report.regions" :key="region.label">
                                <td class="py-3 pr-4 font-medium text-slate-950 dark:text-white">{{ region.label }}</td>
                                <td class="py-3 pr-4 text-slate-700 dark:text-slate-200">{{ formatNumber(region.page_views) }}</td>
                                <td class="py-3 text-slate-700 dark:text-slate-200">{{ formatNumber(region.unique_visitors) }}</td>
                            </tr>
                            <tr v-if="!report.regions.length">
                                <td colspan="3" class="py-8 text-center text-sm text-slate-500 dark:text-slate-400">No state or region data is available for this range yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</template>
