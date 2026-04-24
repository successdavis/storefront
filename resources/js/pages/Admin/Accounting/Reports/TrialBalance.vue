<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { reactive, watch } from 'vue'

const props = defineProps<{ report: any }>()

const filters = reactive({
    from: props.report.filters?.from || '',
    to: props.report.filters?.to || '',
})

watch(
    () => ({ ...filters }),
    (value) => {
        router.get(route('admin.accounting.reports.trial-balance'), value, {
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
</script>

<template>
    <Head title="Trial Balance" />

    <div class="min-h-screen space-y-6 bg-slate-100 p-6 dark:bg-slate-950">
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-sm font-medium uppercase tracking-[0.24em] text-sky-500">Accounting reports</p>
                    <h1 class="text-3xl font-semibold tracking-tight text-slate-950 dark:text-white">Trial balance</h1>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <input v-model="filters.from" type="date" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100" />
                    <input v-model="filters.to" type="date" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100" />
                </div>
            </div>
        </section>

        <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead class="bg-slate-50 dark:bg-slate-950">
                        <tr class="text-left text-xs uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400">
                            <th class="px-6 py-4">Code</th>
                            <th class="px-6 py-4">Account</th>
                            <th class="px-6 py-4">Type</th>
                            <th class="px-6 py-4 text-right">Debit</th>
                            <th class="px-6 py-4 text-right">Credit</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-for="row in report.rows" :key="row.id">
                            <td class="px-6 py-4 font-semibold text-slate-900 dark:text-slate-100">{{ row.code }}</td>
                            <td class="px-6 py-4 text-slate-700 dark:text-slate-300">{{ row.name }}</td>
                            <td class="px-6 py-4 text-slate-700 dark:text-slate-300">{{ row.type }}</td>
                            <td class="px-6 py-4 text-right text-slate-700 dark:text-slate-300">{{ formatCurrency(row.debit) }}</td>
                            <td class="px-6 py-4 text-right text-slate-700 dark:text-slate-300">{{ formatCurrency(row.credit) }}</td>
                        </tr>
                    </tbody>
                    <tfoot class="bg-slate-50 dark:bg-slate-950">
                        <tr class="text-sm font-semibold text-slate-900 dark:text-slate-100">
                            <td colspan="3" class="px-6 py-4 text-right">Totals</td>
                            <td class="px-6 py-4 text-right">{{ formatCurrency(report.totals.debit) }}</td>
                            <td class="px-6 py-4 text-right">{{ formatCurrency(report.totals.credit) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>
    </div>
</template>
