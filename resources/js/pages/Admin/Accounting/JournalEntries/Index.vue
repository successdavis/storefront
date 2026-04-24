<script setup lang="ts">
import { Button } from '@/components/ui/button'
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog'
import Pagination from '@/components/Pagination.vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { reactive, ref, watch } from 'vue'

const props = defineProps<{
    filters: Record<string, any>
    entries: { data: any[]; links: any[] }
    account_options: Array<{ id: number; label: string }>
}>()

const filters = reactive({
    search: props.filters?.search || '',
    status: props.filters?.status || '',
    from: props.filters?.from || '',
    to: props.filters?.to || '',
})

const showJournalModal = ref(false)

watch(
    () => ({ ...filters }),
    (value) => {
        router.get(route('admin.accounting.journal-entries.index'), value, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        })
    },
)

const form = useForm({
    entry_date: new Date().toISOString().slice(0, 10),
    description: '',
    currency: 'NGN',
    status: 'posted',
    lines: [
        { account_id: '', debit: '', credit: '', description: '' },
        { account_id: '', debit: '', credit: '', description: '' },
    ],
})

function addLine() {
    form.lines.push({ account_id: '', debit: '', credit: '', description: '' })
}

function removeLine(index: number) {
    if (form.lines.length <= 2) return
    form.lines.splice(index, 1)
}

function submit() {
    form.transform((data) => ({
        ...data,
        lines: data.lines.map((line) => ({
            account_id: Number(line.account_id),
            debit: line.debit === '' ? 0 : Number(line.debit),
            credit: line.credit === '' ? 0 : Number(line.credit),
            description: line.description || null,
        })),
    })).post(route('admin.accounting.journal-entries.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset()
            form.entry_date = new Date().toISOString().slice(0, 10)
            form.currency = 'NGN'
            form.status = 'posted'
            form.lines = [
                { account_id: '', debit: '', credit: '', description: '' },
                { account_id: '', debit: '', credit: '', description: '' },
            ]
            showJournalModal.value = false
        },
    })
}

function formatCurrency(value: number) {
    return new Intl.NumberFormat('en-NG', {
        style: 'currency',
        currency: 'NGN',
    }).format(value)
}
</script>

<template>
    <Head title="Journal Entries" />

    <div class="min-h-screen space-y-6 bg-slate-100 p-6 dark:bg-slate-950">
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                <div class="space-y-2">
                    <p class="text-sm font-medium uppercase tracking-[0.24em] text-sky-500">Accounting</p>
                    <h1 class="text-3xl font-semibold tracking-tight text-slate-950 dark:text-white">Journal entries</h1>
                    <p class="max-w-3xl text-sm text-slate-600 dark:text-slate-300">
                        Review posted journals and create tightly controlled manual entries when you need a deliberate accounting adjustment.
                    </p>
                </div>

                <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_180px_180px_180px_auto]">
                    <input v-model="filters.search" type="search" placeholder="Search entries" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100" />
                    <select v-model="filters.status" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                        <option value="">All statuses</option>
                        <option value="draft">Draft</option>
                        <option value="posted">Posted</option>
                        <option value="reversed">Reversed</option>
                    </select>
                    <input v-model="filters.from" type="date" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100" />
                    <input v-model="filters.to" type="date" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100" />
                    <Dialog v-model:open="showJournalModal">
                        <DialogTrigger as-child>
                            <Button class="rounded-2xl px-4 py-3 text-sm font-semibold">Manual journal entry</Button>
                        </DialogTrigger>
                        <DialogContent class="sm:max-w-3xl">
                            <form class="space-y-6" @submit.prevent="submit">
                                <DialogHeader class="space-y-2">
                                    <DialogTitle>Manual journal entry</DialogTitle>
                                    <DialogDescription>Use this for deliberate accounting corrections only. Balancing is enforced before posting.</DialogDescription>
                                </DialogHeader>

                                <div class="grid gap-4 md:grid-cols-2">
                                    <input v-model="form.entry_date" type="date" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100" />
                                    <input v-model="form.description" type="text" placeholder="Entry description" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100" />
                                </div>

                                <div class="space-y-3">
                                    <div v-for="(line, index) in form.lines" :key="index" class="rounded-2xl border border-slate-200 p-4 dark:border-slate-800">
                                        <div class="grid gap-3">
                                            <select v-model="line.account_id" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                                                <option value="">Select account</option>
                                                <option v-for="account in account_options" :key="account.id" :value="String(account.id)">{{ account.label }}</option>
                                            </select>
                                            <div class="grid gap-3 sm:grid-cols-2">
                                                <input v-model="line.debit" type="number" min="0" step="0.01" placeholder="Debit" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100" />
                                                <input v-model="line.credit" type="number" min="0" step="0.01" placeholder="Credit" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100" />
                                            </div>
                                            <div class="flex items-center gap-3">
                                                <input v-model="line.description" type="text" placeholder="Line description" class="flex-1 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100" />
                                                <button type="button" class="rounded-2xl border border-slate-200 px-3 py-2 text-xs font-medium text-slate-700 dark:border-slate-700 dark:text-slate-200" @click="removeLine(index)">Remove</button>
                                            </div>
                                        </div>
                                    </div>
                                    <button type="button" class="rounded-2xl border border-dashed border-slate-300 px-4 py-3 text-sm font-medium text-slate-700 transition hover:border-sky-300 hover:text-sky-600 dark:border-slate-700 dark:text-slate-200 dark:hover:border-sky-500 dark:hover:text-sky-300" @click="addLine">
                                        Add line
                                    </button>
                                </div>

                                <div v-if="Object.keys(form.errors).length" class="rounded-2xl border border-rose-300 bg-rose-50 p-4 text-sm text-rose-700 dark:border-rose-900/60 dark:bg-rose-950/30 dark:text-rose-300">
                                    <div v-for="(error, key) in form.errors" :key="key">{{ error }}</div>
                                </div>

                                <DialogFooter class="gap-2">
                                    <Button type="button" variant="secondary" @click="showJournalModal = false">Cancel</Button>
                                    <Button type="submit" :disabled="form.processing">Post journal entry</Button>
                                </DialogFooter>
                            </form>
                        </DialogContent>
                    </Dialog>
                </div>
            </div>
        </section>

        <section>
            <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                        <thead class="bg-slate-50 dark:bg-slate-950">
                            <tr class="text-left text-xs uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400">
                                <th class="px-6 py-4">Entry</th>
                                <th class="px-6 py-4">Description</th>
                                <th class="px-6 py-4">Date</th>
                                <th class="px-6 py-4">Status</th>
                                <th class="px-6 py-4 text-right">Debit</th>
                                <th class="px-6 py-4 text-right">Credit</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="entry in entries.data" :key="entry.id">
                                <td class="px-6 py-4">
                                    <Link :href="route('admin.accounting.journal-entries.show', entry.id)" class="font-semibold text-slate-900 hover:text-sky-600 dark:text-slate-100 dark:hover:text-sky-300">
                                        {{ entry.entry_number }}
                                    </Link>
                                    <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ entry.source_event || 'manual' }}</div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-slate-800 dark:text-slate-200">{{ entry.description }}</div>
                                    <div v-if="entry.lines_preview?.length" class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                        {{ entry.lines_preview[0].account }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-slate-700 dark:text-slate-300">{{ entry.posting_date || '-' }}</td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" :class="entry.status === 'posted' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300' : entry.status === 'reversed' ? 'bg-rose-100 text-rose-800 dark:bg-rose-950/40 dark:text-rose-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300'">
                                        {{ entry.status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right text-slate-700 dark:text-slate-300">{{ formatCurrency(entry.total_debit) }}</td>
                                <td class="px-6 py-4 text-right text-slate-700 dark:text-slate-300">{{ formatCurrency(entry.total_credit) }}</td>
                            </tr>
                            <tr v-if="!entries.data.length">
                                <td colspan="6" class="px-6 py-16 text-center text-sm text-slate-500 dark:text-slate-400">No journal entries match the current filters.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="px-6 py-4">
                    <Pagination :links="entries.links" />
                </div>
            </div>
        </section>
    </div>
</template>
