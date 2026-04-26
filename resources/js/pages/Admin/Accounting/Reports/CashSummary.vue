<script setup lang="ts">
import Pagination from '@/components/Pagination.vue'
import { Head, router } from '@inertiajs/vue3'
import { reactive, watch } from 'vue'

const props = defineProps<{ report: any }>()

const filters = reactive({
    date: props.report.filters?.date || '',
})

watch(
    () => ({ ...filters }),
    (value) => {
        router.get(route('admin.accounting.reports.cash-summary'), {
            date: value.date || undefined,
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
    }).format(value)
}

function labelClass(key: string) {
    if (key === 'cash') return 'text-emerald-600 dark:text-emerald-300'
    if (key === 'bank') return 'text-sky-600 dark:text-sky-300'
    if (key === 'credit_sales') return 'text-amber-600 dark:text-amber-300'
    if (key === 'debt_recovered') return 'text-violet-600 dark:text-violet-300'

    return 'text-slate-900 dark:text-slate-100'
}
</script>

<template>
    <Head title="Daily Cash Summary" />

    <div class="min-h-screen space-y-6 bg-slate-100 p-6 dark:bg-slate-950">
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-sm font-medium uppercase tracking-[0.24em] text-sky-500">Accounting reports</p>
                    <h1 class="text-3xl font-semibold tracking-tight text-slate-950 dark:text-white">Daily cash summary</h1>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Track cash, bank, wallet, credit sales, and debt recovery for a specific trading date.</p>
                </div>
                <input
                    v-model="filters.date"
                    type="date"
                    class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"
                />
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <article
                v-for="card in report.summary_cards"
                :key="card.key"
                class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
            >
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400">{{ card.label }}</p>
                <p class="mt-3 text-2xl font-semibold" :class="labelClass(card.key)">{{ formatCurrency(card.value) }}</p>
            </article>
        </section>

        <section class="grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-xl font-semibold text-slate-950 dark:text-white">Payment method breakdown</h2>
                <div class="mt-6 space-y-3">
                    <div
                        v-for="row in report.payment_method_breakdown"
                        :key="row.method"
                        class="flex items-center justify-between gap-4 rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-800"
                    >
                        <div>
                            <p class="font-semibold text-slate-900 dark:text-slate-100">{{ row.label }}</p>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ row.count }} transaction(s)</p>
                        </div>
                        <p class="font-semibold text-slate-900 dark:text-slate-100">{{ formatCurrency(row.amount) }}</p>
                    </div>
                    <div v-if="report.payment_method_breakdown.length === 0" class="rounded-2xl border border-dashed border-slate-300 px-6 py-8 text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">
                        No paid receipts were posted for this date.
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="text-xl font-semibold text-slate-950 dark:text-white">Cashier breakdown</h2>
                    <div class="mt-6 space-y-3">
                        <div
                            v-for="row in report.employee_breakdown"
                            :key="String(row.id ?? row.name)"
                            class="flex items-center justify-between gap-4 rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-800"
                        >
                            <div>
                                <p class="font-semibold text-slate-900 dark:text-slate-100">{{ row.name }}</p>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ row.transactions }} transaction(s)</p>
                            </div>
                            <p class="font-semibold text-slate-900 dark:text-slate-100">{{ formatCurrency(row.amount) }}</p>
                        </div>
                    </div>
                </section>

                <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="text-xl font-semibold text-slate-950 dark:text-white">Terminal breakdown</h2>
                    <div class="mt-6 space-y-3">
                        <div
                            v-for="row in report.terminal_breakdown"
                            :key="String(row.id ?? row.name)"
                            class="flex items-center justify-between gap-4 rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-800"
                        >
                            <div>
                                <p class="font-semibold text-slate-900 dark:text-slate-100">{{ row.name }}</p>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ row.transactions }} transaction(s)</p>
                            </div>
                            <p class="font-semibold text-slate-900 dark:text-slate-100">{{ formatCurrency(row.amount) }}</p>
                        </div>
                    </div>
                </section>
            </div>
        </section>

        <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead class="bg-slate-50 dark:bg-slate-950">
                        <tr class="text-left text-xs uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400">
                            <th class="px-6 py-4">Source</th>
                            <th class="px-6 py-4">Customer</th>
                            <th class="px-6 py-4">Method</th>
                            <th class="px-6 py-4">Cashier</th>
                            <th class="px-6 py-4">Terminal</th>
                            <th class="px-6 py-4">Paid at</th>
                            <th class="px-6 py-4 text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-for="row in report.receipts" :key="`${row.source_type}-${row.id}`">
                            <td class="px-6 py-4 font-medium text-slate-900 dark:text-slate-100">
                                {{ row.reference }}
                                <p class="mt-1 text-xs font-normal text-slate-500 dark:text-slate-400">{{ row.source_type === 'invoice' ? 'Debt recovery' : 'Sale receipt' }}</p>
                            </td>
                            <td class="px-6 py-4 text-slate-600 dark:text-slate-300">{{ row.customer_name || 'Walk In Customer' }}</td>
                            <td class="px-6 py-4 text-slate-600 dark:text-slate-300">{{ row.method }}</td>
                            <td class="px-6 py-4 text-slate-600 dark:text-slate-300">{{ row.employee_name || 'Unassigned' }}</td>
                            <td class="px-6 py-4 text-slate-600 dark:text-slate-300">{{ row.pos_terminal_name || 'Unassigned' }}</td>
                            <td class="px-6 py-4 text-slate-600 dark:text-slate-300">{{ row.paid_at ? new Date(row.paid_at).toLocaleString() : '-' }}</td>
                            <td class="px-6 py-4 text-right font-semibold text-slate-900 dark:text-slate-100">{{ formatCurrency(row.amount) }}</td>
                        </tr>
                        <tr v-if="report.receipts.length === 0">
                            <td colspan="7" class="px-6 py-10 text-center text-sm text-slate-500 dark:text-slate-400">No paid receipts were posted for this date.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
