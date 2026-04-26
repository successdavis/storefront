<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import { useSidebar } from '@/components/ui/sidebar'
import { ChevronDown, TrendingDown, TrendingUp } from 'lucide-vue-next'
import VChart from 'vue-echarts'
import { computed, onMounted, reactive, watch } from 'vue'

type SelectOption = { value: string; label: string }
type SummaryCard = { key: string; label: string; value: number }
type BalanceRow = { id: number; code: string; name: string; subtype: string; balance: number }
type ExpenseSegment = { id: number; name: string; amount: number }
type ProfitLossRow = { key: string; label: string; amount: number; change_percent: number | null }

const props = defineProps<{
    report: {
        filters: { period: string; from: string; to: string; balance_as_of: string }
        date_presets: SelectOption[]
        summary_cards: SummaryCard[]
        cash_flow_chart: { labels: string[]; inflow: number[]; outflow: number[]; net: number[] }
        expense_chart: {
            filters: { period: string; from: string; to: string }
            period_options: SelectOption[]
            total: number
            subtitle: string
            segments: ExpenseSegment[]
        }
        profit_loss_chart: {
            filters: { period: string; from: string; to: string }
            period_options: SelectOption[]
            period_label: string
            net_profit: number
            is_profit: boolean
            rows: ProfitLossRow[]
        }
        sales_profit_chart: { labels: string[]; sales: number[]; profit: number[] }
        liquidity_chart: { labels: string[]; balances: number[] }
        bank_balances: BalanceRow[]
        cash_balances: BalanceRow[]
    }
}>()

const { setOpen, setOpenMobile } = useSidebar()

const filters = reactive({
    period: props.report.filters.period || 'this_year',
    from: props.report.filters.from || '',
    to: props.report.filters.to || '',
})
const expenseWidget = reactive({
    period: props.report.expense_chart.filters.period || 'last_6_months',
})
const profitLossWidget = reactive({
    period: props.report.profit_loss_chart.filters.period || 'selected_range',
})

watch(
    () => ({ ...filters, expensePeriod: expenseWidget.period, profitLossPeriod: profitLossWidget.period }),
    (value) => {
        router.get(route('admin.accounting.charts'), {
            period: value.period,
            from: value.from,
            to: value.to,
            expense_period: value.expensePeriod,
            profit_loss_period: value.profitLossPeriod,
        }, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        })
    },
    { deep: true },
)

const expensePalette = ['#14b8a6', '#38bdf8', '#818cf8', '#8b5cf6', '#06b6d4', '#22c55e']

const expenseSegments = computed(() =>
    props.report.expense_chart.segments.map((segment, index) => ({
        ...segment,
        color: expensePalette[index % expensePalette.length],
    })),
)
const profitLossRows = computed(() => {
    const palette: Record<string, string> = {
        revenue: '#22c55e',
        cost_of_goods_sold: '#f59e0b',
        operating_expenses: '#14b8a6',
    }
    const maxAmount = Math.max(...props.report.profit_loss_chart.rows.map((row) => row.amount), 0)

    return props.report.profit_loss_chart.rows.map((row) => ({
        ...row,
        color: palette[row.key] ?? '#60a5fa',
        widthPercent: maxAmount > 0 ? `${Math.max((row.amount / maxAmount) * 100, 8)}%` : '8%',
    }))
})

const cashFlowOptions = computed(() => ({
    tooltip: { trigger: 'axis' },
    legend: {
        bottom: 0,
        itemWidth: 14,
        itemHeight: 10,
        textStyle: { color: '#cbd5e1', fontSize: 12 },
    },
    grid: { left: 24, right: 24, top: 40, bottom: 56, containLabel: true },
    xAxis: {
        type: 'category',
        data: props.report.cash_flow_chart.labels,
        axisLabel: { color: '#94a3b8' },
        axisLine: { lineStyle: { color: '#334155' } },
    },
    yAxis: {
        type: 'value',
        axisLabel: { color: '#94a3b8' },
        splitLine: { lineStyle: { color: '#1e293b' } },
    },
    series: [
        {
            name: 'Inflow',
            type: 'bar',
            itemStyle: { color: '#22c55e' },
            data: props.report.cash_flow_chart.inflow,
        },
        {
            name: 'Outflow',
            type: 'bar',
            itemStyle: { color: '#f97316' },
            data: props.report.cash_flow_chart.outflow,
        },
        {
            name: 'Net',
            type: 'line',
            smooth: true,
            lineStyle: { width: 3, color: '#38bdf8' },
            itemStyle: { color: '#38bdf8' },
            areaStyle: { opacity: 0.08 },
            data: props.report.cash_flow_chart.net,
        },
    ],
}))

const expenseOptions = computed(() => ({
    tooltip: {
        trigger: 'item',
        valueFormatter: (value: number) => formatCurrency(value),
        backgroundColor: '#0f172a',
        borderColor: '#1e293b',
        textStyle: { color: '#e2e8f0' },
    },
    series: [
        {
            name: 'Operating Expenses',
            type: 'pie',
            radius: ['62%', '84%'],
            center: ['50%', '50%'],
            avoidLabelOverlap: true,
            label: { show: false },
            labelLine: { show: false },
            itemStyle: {
                borderColor: '#0f172a',
                borderWidth: 4,
                borderRadius: 8,
            },
            data: expenseSegments.value.map((segment) => ({
                value: segment.amount,
                name: segment.name,
                itemStyle: { color: segment.color },
            })),
        },
    ],
}))

const salesProfitOptions = computed(() => ({
    tooltip: { trigger: 'axis' },
    legend: {
        bottom: 0,
        itemWidth: 14,
        itemHeight: 10,
        textStyle: { color: '#cbd5e1', fontSize: 12 },
    },
    grid: { left: 24, right: 24, top: 40, bottom: 56, containLabel: true },
    xAxis: {
        type: 'category',
        data: props.report.sales_profit_chart.labels,
        axisLabel: { color: '#94a3b8' },
        axisLine: { lineStyle: { color: '#334155' } },
    },
    yAxis: {
        type: 'value',
        axisLabel: { color: '#94a3b8' },
        splitLine: { lineStyle: { color: '#1e293b' } },
    },
    series: [
        {
            name: 'Sales Revenue',
            type: 'bar',
            itemStyle: { color: '#6366f1' },
            data: props.report.sales_profit_chart.sales,
        },
        {
            name: 'Profit',
            type: 'line',
            smooth: true,
            lineStyle: { width: 3, color: '#22c55e' },
            itemStyle: { color: '#22c55e' },
            areaStyle: { opacity: 0.08 },
            data: props.report.sales_profit_chart.profit,
        },
    ],
}))

const liquidityOptions = computed(() => ({
    tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
    grid: { left: 24, right: 24, top: 24, bottom: 24, containLabel: true },
    xAxis: {
        type: 'value',
        axisLabel: { color: '#94a3b8' },
        splitLine: { lineStyle: { color: '#1e293b' } },
    },
    yAxis: {
        type: 'category',
        data: props.report.liquidity_chart.labels,
        axisLabel: { color: '#cbd5e1' },
        axisLine: { lineStyle: { color: '#334155' } },
    },
    series: [
        {
            name: 'Balance',
            type: 'bar',
            itemStyle: { color: '#0ea5e9' },
            data: props.report.liquidity_chart.balances,
        },
    ],
}))

function formatCurrency(value: number) {
    return new Intl.NumberFormat('en-NG', {
        style: 'currency',
        currency: 'NGN',
        minimumFractionDigits: 2,
    }).format(value)
}

function formatCompactCurrency(value: number) {
    const compact = new Intl.NumberFormat('en-NG', {
        notation: 'compact',
        compactDisplay: 'short',
        maximumFractionDigits: 1,
    }).format(value)

    return `₦${compact.replace(/^NGN/i, '').trim()}`
}

function formatCompactSignedCurrency(value: number) {
    const amount = formatCompactCurrency(Math.abs(value))

    return value < 0 ? `-${amount}` : amount
}

function formatChangePercent(value: number | null) {
    if (value === null) {
        return 'New'
    }

    if (value === 0) {
        return '0.0%'
    }

    return `${value > 0 ? '+' : ''}${value.toFixed(1)}%`
}

function onDateChange() {
    if (filters.period !== 'custom') {
        filters.period = 'custom'
    }
}

onMounted(() => {
    setOpen(false)
    setOpenMobile(false)
})
</script>

<template>
    <Head title="Accounting Charts" />

    <div class="min-h-screen space-y-5 bg-slate-100 p-5 dark:bg-slate-950">
        <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div class="space-y-1.5">
                    <p class="text-sm font-medium uppercase tracking-[0.24em] text-sky-500">Accounting Charts</p>
                    <div class="flex flex-wrap items-center gap-2.5">
                        <h1 class="text-2xl font-semibold tracking-tight text-slate-950 dark:text-white">Finance performance and liquidity</h1>
                        <Link :href="route('admin.accounting.index')" class="text-sm font-medium text-sky-600 transition hover:text-sky-500 dark:text-sky-300 dark:hover:text-sky-200">
                            Back to accounting overview
                        </Link>
                    </div>
                    <p class="max-w-3xl text-sm text-slate-600 dark:text-slate-300">
                        Monthly cash flow, expenses, profit, sales performance, and current cash and bank balances from posted journals.
                    </p>
                </div>

                <div class="grid gap-2.5 sm:grid-cols-3">
                    <label class="space-y-2 text-sm text-slate-600 dark:text-slate-300">
                        <span>Period</span>
                        <select v-model="filters.period" class="w-full rounded-2xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                            <option v-for="option in report.date_presets" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </select>
                    </label>
                    <label class="space-y-2 text-sm text-slate-600 dark:text-slate-300">
                        <span>From</span>
                        <input v-model="filters.from" type="date" class="w-full rounded-2xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100" @change="onDateChange" />
                    </label>
                    <label class="space-y-2 text-sm text-slate-600 dark:text-slate-300">
                        <span>To</span>
                        <input v-model="filters.to" type="date" class="w-full rounded-2xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100" @change="onDateChange" />
                    </label>
                </div>
            </div>
        </section>

        <section class="grid gap-4 2xl:grid-cols-3">
            <article class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="mb-3">
                    <h2 class="text-base font-semibold text-slate-950 dark:text-white">Cash flow by month</h2>
                    <p class="text-sm text-slate-600 dark:text-slate-300">Monthly external cash movement across cash and bank accounts from posted journals, excluding internal deposits and opening balances.</p>
                </div>
                <div class="h-[300px] overflow-hidden">
                    <v-chart class="h-full w-full" :option="cashFlowOptions" autoresize />
                </div>
            </article>

            <article class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div class="space-y-2">
                        <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500 dark:text-slate-400">Expenses</p>
                        <div>
                            <p class="text-2xl font-semibold tracking-tight text-slate-950 dark:text-white">
                                {{ formatCompactCurrency(report.expense_chart.total) }}
                            </p>
                            <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ report.expense_chart.subtitle }}</p>
                        </div>
                    </div>

                    <label class="relative inline-flex shrink-0 items-center">
                        <select v-model="expenseWidget.period" class="min-w-[8.75rem] appearance-none rounded-2xl border border-slate-200 bg-slate-50 px-3.5 py-2 pr-9 text-sm font-medium text-slate-700 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200">
                            <option v-for="option in report.expense_chart.period_options" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </select>
                        <ChevronDown class="pointer-events-none absolute right-3 h-4 w-4 text-slate-400" />
                    </label>
                </div>

                <div class="mt-4 grid gap-4 lg:grid-cols-[minmax(180px,0.82fr)_minmax(0,1.18fr)] lg:items-center">
                    <div class="mx-auto h-[180px] w-full max-w-[220px] overflow-hidden">
                        <v-chart class="h-full w-full" :option="expenseOptions" autoresize />
                    </div>

                    <div class="space-y-2.5">
                        <div v-for="segment in expenseSegments" :key="`${segment.id}-${segment.name}`" class="flex items-start justify-between gap-3 rounded-2xl border border-slate-200/80 bg-slate-50/70 px-3 py-2.5 dark:border-slate-800 dark:bg-slate-950/50">
                            <div class="flex min-w-0 items-start gap-3">
                                <span class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full" :style="{ backgroundColor: segment.color }" />
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-slate-950 dark:text-white">{{ formatCurrency(segment.amount) }}</p>
                                    <p class="truncate text-xs text-slate-600 dark:text-slate-300">{{ segment.name }}</p>
                                </div>
                            </div>
                        </div>

                        <p v-if="!expenseSegments.length" class="rounded-2xl border border-dashed border-slate-200 px-4 py-8 text-center text-sm text-slate-500 dark:border-slate-800 dark:text-slate-400">
                            No operating expenses were recognized in the selected expense period.
                        </p>
                    </div>
                </div>
            </article>

            <article class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="mb-3">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="space-y-2">
                            <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500 dark:text-slate-400">Profit and Loss</p>
                            <div>
                                <p class="text-2xl font-semibold tracking-tight text-slate-950 dark:text-white">
                                    {{ formatCompactSignedCurrency(report.profit_loss_chart.net_profit) }}
                                </p>
                                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                                    {{ report.profit_loss_chart.is_profit ? 'Net income' : 'Net loss' }} for {{ report.profit_loss_chart.period_label }}
                                </p>
                            </div>
                        </div>

                        <label class="relative inline-flex shrink-0 items-center">
                            <select v-model="profitLossWidget.period" class="min-w-[8.75rem] appearance-none rounded-2xl border border-slate-200 bg-slate-50 px-3.5 py-2 pr-9 text-sm font-medium text-slate-700 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200">
                                <option v-for="option in report.profit_loss_chart.period_options" :key="option.value" :value="option.value">{{ option.label }}</option>
                            </select>
                            <ChevronDown class="pointer-events-none absolute right-3 h-4 w-4 text-slate-400" />
                        </label>
                    </div>
                </div>

                <div class="mt-4 space-y-3">
                    <div v-for="row in profitLossRows" :key="row.key" class="grid gap-2.5 rounded-2xl border border-slate-200/80 bg-slate-50/60 px-3 py-2.5 dark:border-slate-800 dark:bg-slate-950/40 lg:grid-cols-[140px_minmax(0,1fr)] lg:items-center">
                        <div class="space-y-1">
                            <p class="text-sm font-semibold text-slate-950 dark:text-white">{{ formatCurrency(row.amount) }}</p>
                            <p class="text-xs text-slate-600 dark:text-slate-300">{{ row.label }}</p>
                        </div>

                        <div class="space-y-2">
                            <div class="h-2.5 w-full overflow-hidden rounded-full bg-slate-200 dark:bg-slate-800">
                                <div class="h-full rounded-full transition-all duration-300" :style="{ width: row.widthPercent, backgroundColor: row.color }" />
                            </div>
                            <div class="flex justify-end text-xs font-medium" :class="row.change_percent !== null && row.change_percent < 0 ? 'text-amber-400' : 'text-slate-500 dark:text-slate-400'">
                                <span class="inline-flex items-center gap-1">
                                    <component :is="row.change_percent !== null && row.change_percent < 0 ? TrendingDown : TrendingUp" class="h-3.5 w-3.5" />
                                    {{ formatChangePercent(row.change_percent) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </article>
        </section>

        <section class="grid gap-5 xl:grid-cols-2">
            <article class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="mb-3">
                    <h2 class="text-base font-semibold text-slate-950 dark:text-white">Sales and profit</h2>
                    <p class="text-sm text-slate-600 dark:text-slate-300">Monthly sales revenue compared with net profit to show margin movement over time.</p>
                </div>
                <div class="h-[320px] overflow-hidden">
                    <v-chart class="h-full w-full" :option="salesProfitOptions" autoresize />
                </div>
            </article>

            <article class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="mb-3">
                    <h2 class="text-base font-semibold text-slate-950 dark:text-white">Bank and cash balances</h2>
                    <p class="text-sm text-slate-600 dark:text-slate-300">
                        Cumulative balances as at {{ report.filters.balance_as_of }} across active bank and cash accounts.
                    </p>
                </div>
                <div class="h-[320px] overflow-hidden">
                    <v-chart class="h-full w-full" :option="liquidityOptions" autoresize />
                </div>
            </article>
        </section>

        <section class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
            <article class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="mb-3">
                    <h2 class="text-base font-semibold text-slate-950 dark:text-white">Bank accounts</h2>
                        <p class="text-sm text-slate-600 dark:text-slate-300">Each bank account balance as at the selected closing date.</p>
                </div>
                <div class="space-y-2.5">
                    <div v-for="account in report.bank_balances" :key="account.id" class="rounded-2xl border border-slate-200 px-3 py-2.5 dark:border-slate-800">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-slate-950 dark:text-white">{{ account.name }}</p>
                                <p class="text-xs uppercase tracking-[0.24em] text-slate-500 dark:text-slate-400">{{ account.code }}</p>
                            </div>
                            <p class="text-sm font-semibold text-slate-950 dark:text-white">{{ formatCurrency(account.balance) }}</p>
                        </div>
                    </div>
                    <p v-if="!report.bank_balances.length" class="rounded-2xl border border-dashed border-slate-200 px-4 py-8 text-center text-sm text-slate-500 dark:border-slate-800 dark:text-slate-400">
                        No active bank accounts are mapped in the chart of accounts yet.
                    </p>
                </div>
            </article>

            <article class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="mb-3">
                    <h2 class="text-base font-semibold text-slate-950 dark:text-white">Cash balances</h2>
                    <p class="text-sm text-slate-600 dark:text-slate-300">Cash-on-hand balances as at the selected closing date.</p>
                </div>
                <div class="space-y-2.5">
                    <div v-for="account in report.cash_balances" :key="account.id" class="rounded-2xl border border-slate-200 px-3 py-2.5 dark:border-slate-800">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-slate-950 dark:text-white">{{ account.name }}</p>
                                <p class="text-xs uppercase tracking-[0.24em] text-slate-500 dark:text-slate-400">{{ account.code }}</p>
                            </div>
                            <p class="text-sm font-semibold text-slate-950 dark:text-white">{{ formatCurrency(account.balance) }}</p>
                        </div>
                    </div>
                    <p v-if="!report.cash_balances.length" class="rounded-2xl border border-dashed border-slate-200 px-4 py-8 text-center text-sm text-slate-500 dark:border-slate-800 dark:text-slate-400">
                        No cash accounts are currently active in the chart of accounts.
                    </p>
                </div>
            </article>
        </section>
    </div>
</template>
