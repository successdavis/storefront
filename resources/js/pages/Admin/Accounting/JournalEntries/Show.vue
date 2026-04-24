<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'

const props = defineProps<{ entry: any }>()

function formatCurrency(value: number) {
    return new Intl.NumberFormat('en-NG', {
        style: 'currency',
        currency: 'NGN',
    }).format(value)
}
</script>

<template>
    <Head :title="entry.entry_number" />

    <div class="min-h-screen space-y-6 bg-slate-100 p-6 dark:bg-slate-950">
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="space-y-2">
                    <Link :href="route('admin.accounting.journal-entries.index')" class="text-sm font-medium text-sky-600 dark:text-sky-300">Back to journals</Link>
                    <h1 class="text-3xl font-semibold tracking-tight text-slate-950 dark:text-white">{{ entry.entry_number }}</h1>
                    <p class="text-sm text-slate-600 dark:text-slate-300">{{ entry.description }}</p>
                </div>
                <div class="grid gap-3 text-sm text-slate-600 dark:text-slate-300">
                    <div>Posting date: <span class="font-semibold text-slate-900 dark:text-slate-100">{{ entry.posting_date || '-' }}</span></div>
                    <div>Status: <span class="font-semibold text-slate-900 dark:text-slate-100">{{ entry.status }}</span></div>
                    <div>Source: <span class="font-semibold text-slate-900 dark:text-slate-100">{{ entry.source_type || '-' }} #{{ entry.source_id || '-' }}</span></div>
                </div>
            </div>
        </section>

        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400">Total debit</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-950 dark:text-white">{{ formatCurrency(entry.total_debit) }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400">Total credit</p>
                    <p class="mt-2 text-2xl font-semibold text-slate-950 dark:text-white">{{ formatCurrency(entry.total_credit) }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400">Posted by</p>
                    <p class="mt-2 text-lg font-semibold text-slate-950 dark:text-white">{{ entry.posted_by || '-' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400">Source event</p>
                    <p class="mt-2 text-lg font-semibold text-slate-950 dark:text-white">{{ entry.source_event || '-' }}</p>
                </div>
            </div>
        </section>

        <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead class="bg-slate-50 dark:bg-slate-950">
                        <tr class="text-left text-xs uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400">
                            <th class="px-6 py-4">Line</th>
                            <th class="px-6 py-4">Account</th>
                            <th class="px-6 py-4">Description</th>
                            <th class="px-6 py-4 text-right">Debit</th>
                            <th class="px-6 py-4 text-right">Credit</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-for="line in entry.lines" :key="line.id">
                            <td class="px-6 py-4 text-slate-700 dark:text-slate-300">{{ line.line_number }}</td>
                            <td class="px-6 py-4">
                                <div class="font-semibold text-slate-900 dark:text-slate-100">{{ line.account_code }} · {{ line.account_name }}</div>
                                <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ line.account_type }}</div>
                            </td>
                            <td class="px-6 py-4 text-slate-700 dark:text-slate-300">{{ line.description || '-' }}</td>
                            <td class="px-6 py-4 text-right text-slate-700 dark:text-slate-300">{{ formatCurrency(line.debit) }}</td>
                            <td class="px-6 py-4 text-right text-slate-700 dark:text-slate-300">{{ formatCurrency(line.credit) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
