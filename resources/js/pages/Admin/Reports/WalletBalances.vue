<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import { useCurrencyFormatter } from '@/pages/Admin/Pos/composables/useCurrencyFormatter'

interface BranchOption {
    id: number
    name: string
    code: string
}

interface ReportRow {
    key: string
    label: string
    active_accounts: number
    total_balance: number
}

const props = defineProps<{
    branches: BranchOption[]
    filters: {
        branch_id?: number | null
    }
    report: {
        summary: {
            selected_branch: BranchOption | null
            total_active_accounts: number
            total_balance: number
        }
        rows: ReportRow[]
    }
}>()

const branchId = ref<number | null>(props.filters?.branch_id ?? null)
const { formatCurrency } = useCurrencyFormatter()

watch(
    () => props.filters?.branch_id,
    (value) => {
        branchId.value = value ?? null
    }
)

const exportPdfUrl = computed(() => route('admin.reports.wallet-balances.export-pdf', {
    branch_id: branchId.value || undefined,
}))

function applyFilters() {
    router.get(route('admin.reports.wallet-balances.index'), {
        branch_id: branchId.value || undefined,
    }, {
        preserveState: true,
        replace: true,
    })
}

function resetFilters() {
    branchId.value = null
    applyFilters()
}

function exportCategoryUrl(walletType: string) {
    return route('admin.reports.wallet-balances.export-category-accounts', {
        wallet_type: walletType,
        branch_id: branchId.value || undefined,
    })
}
</script>

<template>
    <Head title="Wallet Balances" />

    <div class="space-y-6 px-5 py-4">
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold text-slate-900 dark:text-slate-100">Wallet Balances Report</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500 dark:text-slate-400">
                        Track active wallet accounts by category, monitor the current balance each category holds, filter the report by branch, and export either the summary PDF or category account balances in an Excel-friendly file.
                    </p>
                </div>

                <a
                    :href="exportPdfUrl"
                    target="_blank"
                    rel="noopener"
                    class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-300"
                >
                    Export PDF
                </a>
            </div>

            <div class="mt-6 grid gap-4 lg:grid-cols-[1.4fr_auto_auto]">
                <label class="text-sm">
                    <span class="mb-2 block text-xs font-medium uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Branch</span>
                    <select v-model.number="branchId" class="h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                        <option :value="null">All branches</option>
                        <option v-for="branch in branches" :key="branch.id" :value="branch.id">
                            {{ branch.name }} ({{ branch.code }})
                        </option>
                    </select>
                </label>

                <button
                    type="button"
                    class="h-11 rounded-xl bg-slate-900 px-4 text-sm font-semibold text-white transition hover:bg-slate-700 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-300"
                    @click="applyFilters"
                >
                    Apply Filter
                </button>

                <button
                    type="button"
                    class="h-11 rounded-xl border border-slate-300 px-4 text-sm font-semibold text-slate-700 transition hover:border-slate-500 dark:border-slate-600 dark:text-slate-200 dark:hover:border-slate-400"
                    @click="resetFilters"
                >
                    Reset
                </button>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <article
                v-for="row in report.rows"
                :key="row.key"
                class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900"
            >
                <p class="text-xs font-medium uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">{{ row.label }}</p>
                <p class="mt-4 text-3xl font-semibold text-slate-900 dark:text-slate-100">{{ row.active_accounts }}</p>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Active accounts</p>
                <p class="mt-4 text-lg font-semibold text-emerald-600 dark:text-emerald-400">{{ formatCurrency(row.total_balance) }}</p>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Current balance held</p>
                <a
                    :href="exportCategoryUrl(row.key)"
                    class="mt-5 inline-flex rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-500 dark:border-slate-600 dark:text-slate-200 dark:hover:border-slate-400"
                >
                    Export Accounts
                </a>
            </article>
        </section>

        <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-xs font-medium uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Scope</p>
                    <h2 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100">
                        {{ report.summary.selected_branch ? report.summary.selected_branch.name : 'All branches' }}
                    </h2>
                </div>

                <div class="flex flex-wrap gap-3 text-sm text-slate-500 dark:text-slate-400">
                    <span class="rounded-full bg-slate-100 px-3 py-1.5 dark:bg-slate-800 dark:text-slate-200">
                        {{ report.summary.total_active_accounts }} active accounts
                    </span>
                    <span class="rounded-full bg-slate-100 px-3 py-1.5 dark:bg-slate-800 dark:text-slate-200">
                        {{ formatCurrency(report.summary.total_balance) }} total balance
                    </span>
                </div>
            </div>

            <div class="mt-5 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead class="bg-slate-50 dark:bg-slate-950">
                        <tr class="text-left text-xs uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">
                            <th class="px-5 py-4">Wallet Category</th>
                            <th class="px-5 py-4">Active Accounts</th>
                            <th class="px-5 py-4 text-right">Current Balance</th>
                            <th class="px-5 py-4 text-right">Export</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white dark:divide-slate-800 dark:bg-slate-900">
                        <tr v-for="row in report.rows" :key="`${row.key}-table`">
                            <td class="px-5 py-4 font-semibold text-slate-900 dark:text-slate-100">{{ row.label }}</td>
                            <td class="px-5 py-4 text-slate-700 dark:text-slate-300">{{ row.active_accounts }}</td>
                            <td class="px-5 py-4 text-right font-semibold text-slate-900 dark:text-slate-100">{{ formatCurrency(row.total_balance) }}</td>
                            <td class="px-5 py-4 text-right">
                                <a
                                    :href="exportCategoryUrl(row.key)"
                                    class="inline-flex rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-semibold text-slate-700 transition hover:border-slate-500 dark:border-slate-600 dark:text-slate-200 dark:hover:border-slate-400"
                                >
                                    Excel Export
                                </a>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
