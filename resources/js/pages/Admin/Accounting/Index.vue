<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { computed, reactive, watch } from 'vue'
import { BookOpen, History, Landmark, ReceiptText, TrendingDown, TrendingUp } from 'lucide-vue-next'

const props = defineProps<{
    filters: { from?: string | null; to?: string | null }
    can_sync_history?: boolean
    history_sync?: { pending?: Record<string, number> }
    summary_cards: Array<{ key: string; label: string; value: number }>
    recent_entries: Array<{
        id: number
        entry_number: string
        description: string
        posting_date: string | null
        status: string
        total_debit: number
        total_credit: number
    }>
}>()

const filters = reactive({
    from: props.filters?.from || '',
    to: props.filters?.to || '',
})

const syncForm = useForm({})
const canSyncHistory = computed(() => Boolean(props.can_sync_history))
const pendingHistoryCount = computed(() => Object.values(props.history_sync?.pending || {}).reduce((sum, value) => sum + Number(value || 0), 0))

watch(
    () => ({ ...filters }),
    (value) => {
        router.get(route('admin.accounting.index'), value, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        })
    },
)

const cardIcons: Record<string, any> = {
    accounts: Landmark,
    journals: ReceiptText,
    revenue: TrendingUp,
    operating_expense: TrendingDown,
}

function formatCurrency(value: number) {
    return new Intl.NumberFormat('en-NG', {
        style: 'currency',
        currency: 'NGN',
        minimumFractionDigits: 2,
    }).format(value)
}

function syncHistory() {
    syncForm.post(route('admin.accounting.sync-history'), {
        preserveScroll: true,
    })
}
</script>

<template>
    <Head title="Accounting Overview" />

    <div class="min-h-screen space-y-6 bg-slate-100 p-6 dark:bg-slate-950">
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div class="space-y-2">
                    <p class="text-sm font-medium uppercase tracking-[0.24em] text-sky-500">Accounting</p>
                    <h1 class="text-3xl font-semibold tracking-tight text-slate-950 dark:text-white">Accounting overview</h1>
                    <p class="max-w-3xl text-sm text-slate-600 dark:text-slate-300">
                        Enterprise accounting foundation for journals, expenses, ledgers, and financial reporting across storefront, POS, inventory, and procurement.
                    </p>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] lg:items-end">
                    <label class="space-y-2 text-sm text-slate-600 dark:text-slate-300">
                        <span>From</span>
                        <input v-model="filters.from" type="date" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100" />
                    </label>
                    <label class="space-y-2 text-sm text-slate-600 dark:text-slate-300">
                        <span>To</span>
                        <input v-model="filters.to" type="date" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100" />
                    </label>
                    <button
                        v-if="canSyncHistory"
                        type="button"
                        class="inline-flex items-center justify-center gap-2 rounded-2xl bg-sky-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-sky-500 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="syncForm.processing"
                        @click="syncHistory"
                    >
                        <History class="h-4 w-4" />
                        {{ syncForm.processing ? 'Syncing history…' : 'Sync historical activity' }}
                    </button>
                </div>
            </div>
        </section>

        <section v-if="pendingHistoryCount" class="rounded-3xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-100">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div class="space-y-2">
                    <h2 class="text-base font-semibold">Historical transactions need accounting sync</h2>
                    <p>
                        Existing opening balances, prior sales, item receipts, vendor bills, vendor payments, expenses, and stock adjustments do not enter the new accounting reports until they are synchronized into journals.
                    </p>
                </div>
                <div class="rounded-2xl bg-white/70 px-4 py-3 text-xs uppercase tracking-[0.24em] text-amber-700 dark:bg-slate-950/40 dark:text-amber-200">
                    {{ pendingHistoryCount }} historical workflows detected
                </div>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <article v-for="card in summary_cards" :key="card.key" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.28em] text-slate-500 dark:text-slate-400">{{ card.label }}</p>
                        <p class="mt-3 text-3xl font-semibold tracking-tight text-slate-950 dark:text-white">
                            {{ ['revenue', 'operating_expense'].includes(card.key) ? formatCurrency(card.value) : card.value }}
                        </p>
                    </div>
                    <component :is="cardIcons[card.key] || BookOpen" class="h-10 w-10 rounded-2xl bg-sky-50 p-2.5 text-sky-500 dark:bg-sky-500/10" />
                </div>
            </article>
        </section>

        <section class="grid gap-6 xl:grid-cols-[minmax(0,1.4fr)_minmax(280px,0.8fr)]">
            <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4 dark:border-slate-800">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-950 dark:text-white">Recent journal entries</h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400">Latest posted accounting events from the commerce platform.</p>
                    </div>
                    <Link :href="route('admin.accounting.journal-entries.index')" class="rounded-2xl border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 transition hover:border-sky-300 hover:text-sky-600 dark:border-slate-700 dark:text-slate-200 dark:hover:border-sky-500 dark:hover:text-sky-300">
                        View all
                    </Link>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                        <thead class="bg-slate-50 dark:bg-slate-950">
                            <tr class="text-left text-xs uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400">
                                <th class="px-6 py-4">Entry</th>
                                <th class="px-6 py-4">Description</th>
                                <th class="px-6 py-4">Date</th>
                                <th class="px-6 py-4 text-right">Debit</th>
                                <th class="px-6 py-4 text-right">Credit</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="entry in recent_entries" :key="entry.id">
                                <td class="px-6 py-4">
                                    <Link :href="route('admin.accounting.journal-entries.show', entry.id)" class="font-semibold text-slate-900 hover:text-sky-600 dark:text-slate-100 dark:hover:text-sky-300">
                                        {{ entry.entry_number }}
                                    </Link>
                                </td>
                                <td class="px-6 py-4 text-slate-700 dark:text-slate-300">{{ entry.description }}</td>
                                <td class="px-6 py-4 text-slate-700 dark:text-slate-300">{{ entry.posting_date || '-' }}</td>
                                <td class="px-6 py-4 text-right text-slate-700 dark:text-slate-300">{{ formatCurrency(entry.total_debit) }}</td>
                                <td class="px-6 py-4 text-right text-slate-700 dark:text-slate-300">{{ formatCurrency(entry.total_credit) }}</td>
                            </tr>
                            <tr v-if="!recent_entries.length">
                                <td colspan="5" class="px-6 py-16 text-center text-sm text-slate-500 dark:text-slate-400">No journal entries have been posted yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="grid gap-4">
                <Link :href="route('admin.accounting.accounts.index')" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-sky-300 dark:border-slate-800 dark:bg-slate-900 dark:hover:border-sky-500">
                    <h3 class="text-lg font-semibold text-slate-950 dark:text-white">Chart of accounts</h3>
                    <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Manage system and manual accounts, activate or deactivate them, and keep the chart structured for reporting.</p>
                </Link>

                <Link :href="route('admin.accounting.expenses.index')" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-sky-300 dark:border-slate-800 dark:bg-slate-900 dark:hover:border-sky-500">
                    <h3 class="text-lg font-semibold text-slate-950 dark:text-white">Expenses</h3>
                    <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Record manual expenses with journal-backed posting into the general ledger.</p>
                </Link>

                <Link :href="route('admin.accounting.reports.trial-balance')" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-sky-300 dark:border-slate-800 dark:bg-slate-900 dark:hover:border-sky-500">
                    <h3 class="text-lg font-semibold text-slate-950 dark:text-white">Trial balance and reports</h3>
                    <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Move from account balances into trial balance, general ledger, profit and loss, and balance sheet views.</p>
                </Link>

                <Link :href="route('admin.accounting.charts')" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-sky-300 dark:border-slate-800 dark:bg-slate-900 dark:hover:border-sky-500">
                    <h3 class="text-lg font-semibold text-slate-950 dark:text-white">Charts and trends</h3>
                    <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">See monthly cash flow, expenses, sales, profit, and liquidity balances in one finance dashboard.</p>
                </Link>
            </div>
        </section>
    </div>
</template>
