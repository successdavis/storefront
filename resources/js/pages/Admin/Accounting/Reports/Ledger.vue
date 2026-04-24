<script setup lang="ts">
import Pagination from '@/components/Pagination.vue'
import { Head, router } from '@inertiajs/vue3'
import { reactive, watch } from 'vue'

const props = defineProps<{
    filters: Record<string, any>
    account_options: Array<{ id: number; label: string }>
    statement: any | null
}>()

const filters = reactive({
    account_id: props.filters?.account_id ? String(props.filters.account_id) : '',
    from: props.filters?.from || '',
    to: props.filters?.to || '',
})

watch(
    () => ({ ...filters }),
    (value) => {
        router.get(route('admin.accounting.reports.ledger'), {
            account_id: value.account_id || undefined,
            from: value.from || undefined,
            to: value.to || undefined,
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
</script>

<template>
    <Head title="General Ledger" />

    <div class="min-h-screen space-y-6 bg-slate-100 p-6 dark:bg-slate-950">
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="space-y-4">
                <div>
                    <p class="text-sm font-medium uppercase tracking-[0.24em] text-sky-500">Accounting reports</p>
                    <h1 class="text-3xl font-semibold tracking-tight text-slate-950 dark:text-white">General ledger</h1>
                </div>

                <div class="grid gap-3 md:grid-cols-3">
                    <select v-model="filters.account_id" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                        <option value="">Select account</option>
                        <option v-for="account in account_options" :key="account.id" :value="String(account.id)">{{ account.label }}</option>
                    </select>
                    <input v-model="filters.from" type="date" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100" />
                    <input v-model="filters.to" type="date" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100" />
                </div>
            </div>
        </section>

        <section v-if="statement" class="space-y-6">
            <div class="grid gap-4 md:grid-cols-3">
                <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400">Account</p>
                    <p class="mt-2 text-xl font-semibold text-slate-950 dark:text-white">{{ statement.account.code }} · {{ statement.account.name }}</p>
                </article>
                <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400">Opening balance</p>
                    <p class="mt-2 text-xl font-semibold text-slate-950 dark:text-white">{{ formatCurrency(statement.opening_balance.amount) }} {{ statement.opening_balance.side }}</p>
                </article>
                <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400">Closing balance</p>
                    <p class="mt-2 text-xl font-semibold text-slate-950 dark:text-white">{{ formatCurrency(statement.closing_balance.amount) }} {{ statement.closing_balance.side }}</p>
                </article>
            </div>

            <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                        <thead class="bg-slate-50 dark:bg-slate-950">
                            <tr class="text-left text-xs uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400">
                                <th class="px-6 py-4">Date</th>
                                <th class="px-6 py-4">Entry</th>
                                <th class="px-6 py-4">Description</th>
                                <th class="px-6 py-4 text-right">Debit</th>
                                <th class="px-6 py-4 text-right">Credit</th>
                                <th class="px-6 py-4 text-right">Running</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="movement in statement.movements.data" :key="movement.id">
                                <td class="px-6 py-4 text-slate-700 dark:text-slate-300">{{ movement.posting_date }}</td>
                                <td class="px-6 py-4 text-slate-700 dark:text-slate-300">{{ movement.entry_number }}</td>
                                <td class="px-6 py-4 text-slate-700 dark:text-slate-300">{{ movement.description }}</td>
                                <td class="px-6 py-4 text-right text-slate-700 dark:text-slate-300">{{ formatCurrency(movement.debit) }}</td>
                                <td class="px-6 py-4 text-right text-slate-700 dark:text-slate-300">{{ formatCurrency(movement.credit) }}</td>
                                <td class="px-6 py-4 text-right text-slate-700 dark:text-slate-300">{{ formatCurrency(movement.running_balance.amount) }} {{ movement.running_balance.side }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="px-6 py-4">
                    <Pagination :links="statement.movements.links" />
                </div>
            </div>
        </section>
    </div>
</template>
