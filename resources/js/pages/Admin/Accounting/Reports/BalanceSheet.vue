<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { reactive, watch } from 'vue'

const props = defineProps<{ report: any }>()

const filters = reactive({
    as_of: props.report.filters?.as_of || '',
})

watch(
    () => ({ ...filters }),
    (value) => {
        router.get(route('admin.accounting.reports.balance-sheet'), value, {
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
    }).format(value)
}

function formatSignedCurrency(value: number, negative = false) {
    const formatted = formatCurrency(Math.abs(value))

    return negative ? `-${formatted}` : formatted
}
</script>

<template>
    <Head title="Balance Sheet" />

    <div class="min-h-screen space-y-6 bg-slate-100 p-6 dark:bg-slate-950">
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-sm font-medium uppercase tracking-[0.24em] text-sky-500">Accounting reports</p>
                    <h1 class="text-3xl font-semibold tracking-tight text-slate-950 dark:text-white">Balance sheet</h1>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Shows cumulative balances as at the selected date, not just movement within a month.</p>
                </div>
                <div class="grid gap-3 sm:grid-cols-1">
                    <div class="space-y-2">
                        <label class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400">As at</label>
                        <input v-model="filters.as_of" type="date" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100" />
                    </div>
                </div>
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-3">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-xl font-semibold text-slate-950 dark:text-white">Assets</h2>
                <div class="mt-4 space-y-3">
                    <div v-for="row in report.assets" :key="row.code" class="flex items-center justify-between gap-4 rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-800">
                        <div>
                            <span class="text-slate-800 dark:text-slate-200">{{ row.name }}</span>
                            <p v-if="row.derived" class="mt-1 text-xs uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Derived</p>
                        </div>
                        <span class="font-semibold text-slate-900 dark:text-slate-100">{{ formatSignedCurrency(row.amount, row.is_negative) }}</span>
                    </div>
                </div>
                <div class="mt-4 border-t border-slate-200 pt-4 text-right font-semibold text-slate-900 dark:border-slate-800 dark:text-slate-100">
                    {{ formatSignedCurrency(report.totals.assets, report.totals.assets < 0) }}
                </div>
            </div>

            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-xl font-semibold text-slate-950 dark:text-white">Liabilities</h2>
                <div class="mt-4 space-y-3">
                    <div v-for="row in report.liabilities" :key="row.code" class="flex items-center justify-between gap-4 rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-800">
                        <div>
                            <span class="text-slate-800 dark:text-slate-200">{{ row.name }}</span>
                            <p v-if="row.derived" class="mt-1 text-xs uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Derived</p>
                        </div>
                        <span class="font-semibold text-slate-900 dark:text-slate-100">{{ formatSignedCurrency(row.amount, row.is_negative) }}</span>
                    </div>
                </div>
                <div class="mt-4 border-t border-slate-200 pt-4 text-right font-semibold text-slate-900 dark:border-slate-800 dark:text-slate-100">
                    {{ formatSignedCurrency(report.totals.liabilities, report.totals.liabilities < 0) }}
                </div>
            </div>

            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-xl font-semibold text-slate-950 dark:text-white">Equity</h2>
                <div class="mt-4 space-y-3">
                    <div v-for="row in report.equity" :key="row.code" class="flex items-center justify-between gap-4 rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-800">
                        <div>
                            <span class="text-slate-800 dark:text-slate-200">{{ row.name }}</span>
                            <p v-if="row.derived" class="mt-1 text-xs uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Derived</p>
                        </div>
                        <span class="font-semibold text-slate-900 dark:text-slate-100">{{ formatSignedCurrency(row.amount, row.is_negative) }}</span>
                    </div>
                </div>
                <div class="mt-4 border-t border-slate-200 pt-4 text-right font-semibold text-slate-900 dark:border-slate-800 dark:text-slate-100">
                    {{ formatSignedCurrency(report.totals.equity, report.totals.equity < 0) }}
                </div>
            </div>
        </section>
    </div>
</template>
