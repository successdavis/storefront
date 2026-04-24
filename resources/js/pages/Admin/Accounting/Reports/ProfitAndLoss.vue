<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { reactive } from 'vue'

const props = defineProps<{ report: any }>()

const periodOptions = [
    { value: 'this_month', label: 'This month' },
    { value: 'last_month', label: 'Last month' },
    { value: 'this_year', label: 'This year' },
    { value: 'last_year', label: 'Last year' },
    { value: 'all_time', label: 'All time' },
    { value: 'custom', label: 'Custom range' },
]

const filters = reactive({
    period: props.report.filters?.period || 'this_month',
    from: props.report.filters?.from || '',
    to: props.report.filters?.to || '',
})

function formatDateForInput(value: Date) {
    return value.toISOString().slice(0, 10)
}

function resolvePeriodRange(period: string) {
    const today = new Date()
    const year = today.getFullYear()
    const month = today.getMonth()

    switch (period) {
        case 'last_month': {
            const from = new Date(year, month - 1, 1)
            const to = new Date(year, month, 0)
            return { from: formatDateForInput(from), to: formatDateForInput(to) }
        }
        case 'this_year':
            return {
                from: formatDateForInput(new Date(year, 0, 1)),
                to: formatDateForInput(today),
            }
        case 'last_year':
            return {
                from: formatDateForInput(new Date(year - 1, 0, 1)),
                to: formatDateForInput(new Date(year - 1, 11, 31)),
            }
        case 'all_time':
            return {
                from: '',
                to: '',
            }
        case 'custom':
            return {
                from: filters.from,
                to: filters.to,
            }
        default:
            return {
                from: formatDateForInput(new Date(year, month, 1)),
                to: formatDateForInput(today),
            }
    }
}

function submitFilters() {
    router.get(
        route('admin.accounting.reports.profit-loss'),
        {
            period: filters.period,
            from: filters.from || undefined,
            to: filters.to || undefined,
        },
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        },
    )
}

function onPeriodChange() {
    if (filters.period === 'all_time') {
        filters.from = ''
        filters.to = ''
    } else if (filters.period !== 'custom') {
        const range = resolvePeriodRange(filters.period)
        filters.from = range.from
        filters.to = range.to
    }

    submitFilters()
}

function onDateRangeChange() {
    if (filters.period !== 'custom') {
        filters.period = 'custom'
    }

    submitFilters()
}

function formatCurrency(value: number) {
    return new Intl.NumberFormat('en-NG', {
        style: 'currency',
        currency: 'NGN',
    }).format(value)
}
</script>

<template>
    <Head title="Profit and Loss" />

    <div class="min-h-screen space-y-6 bg-slate-100 p-6 dark:bg-slate-950">
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-sm font-medium uppercase tracking-[0.24em] text-sky-500">Accounting reports</p>
                    <h1 class="text-3xl font-semibold tracking-tight text-slate-950 dark:text-white">Profit and loss</h1>
                </div>
                <div class="grid gap-3 sm:grid-cols-3">
                    <select
                        v-model="filters.period"
                        class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"
                        @change="onPeriodChange"
                    >
                        <option v-for="option in periodOptions" :key="option.value" :value="option.value">
                            {{ option.label }}
                        </option>
                    </select>
                    <input
                        v-model="filters.from"
                        type="date"
                        class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"
                        @change="onDateRangeChange"
                    />
                    <input
                        v-model="filters.to"
                        type="date"
                        class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"
                        @change="onDateRangeChange"
                    />
                </div>
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-2">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-xl font-semibold text-slate-950 dark:text-white">Income</h2>
                <div class="mt-4 space-y-3">
                    <div v-for="row in report.income" :key="row.code" class="flex items-center justify-between gap-4 rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-800">
                        <div>
                            <div class="font-semibold text-slate-900 dark:text-slate-100">{{ row.name }}</div>
                            <div class="text-xs text-slate-500 dark:text-slate-400">{{ row.code }}</div>
                        </div>
                        <div class="font-semibold text-slate-900 dark:text-slate-100">{{ formatCurrency(row.amount) }}</div>
                    </div>
                </div>
            </div>

            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-xl font-semibold text-slate-950 dark:text-white">Expenses</h2>
                <div class="mt-4 space-y-3">
                    <div v-for="row in report.expenses" :key="row.code" class="flex items-center justify-between gap-4 rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-800">
                        <div>
                            <div class="font-semibold text-slate-900 dark:text-slate-100">{{ row.name }}</div>
                            <div class="text-xs text-slate-500 dark:text-slate-400">{{ row.code }}</div>
                        </div>
                        <div class="font-semibold text-slate-900 dark:text-slate-100">{{ formatCurrency(row.amount) }}</div>
                    </div>
                </div>
            </div>
        </section>

        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400">Net result</p>
            <p class="mt-3 text-3xl font-semibold text-slate-950 dark:text-white">{{ formatCurrency(report.net_profit) }}</p>
        </section>
    </div>
</template>
