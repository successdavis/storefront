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
import { Head, router, useForm } from '@inertiajs/vue3'
import { reactive, ref, watch } from 'vue'

const props = defineProps<{
    filters: Record<string, any>
    transfers: { data: any[]; links: any[] }
    cash_account_options: Array<{ id: number; label: string }>
    bank_account_options: Array<{ id: number; label: string }>
}>()

const filters = reactive({
    search: props.filters?.search || '',
})

const showTransferModal = ref(false)

watch(
    () => ({ ...filters }),
    (value) => {
        router.get(route('admin.accounting.cash-bank-transfers.index'), value, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        })
    },
)

const defaultCashAccountId = String(props.cash_account_options.find((row) => row.label.startsWith('1110'))?.id || props.cash_account_options[0]?.id || '')
const defaultBankAccountId = String(props.bank_account_options.find((row) => row.label.startsWith('1120'))?.id || props.bank_account_options[0]?.id || '')

const form = useForm({
    transfer_date: new Date().toISOString().slice(0, 10),
    amount: '',
    currency: 'NGN',
    cash_account_id: defaultCashAccountId,
    bank_account_id: defaultBankAccountId,
    reference: '',
    description: '',
    note: '',
})

function submit() {
    form.transform((data) => ({
        ...data,
        amount: Number(data.amount),
        cash_account_id: Number(data.cash_account_id),
        bank_account_id: Number(data.bank_account_id),
    })).post(route('admin.accounting.cash-bank-transfers.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset()
            form.transfer_date = new Date().toISOString().slice(0, 10)
            form.currency = 'NGN'
            form.cash_account_id = defaultCashAccountId
            form.bank_account_id = defaultBankAccountId
            showTransferModal.value = false
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
    <Head title="Cash to Bank Transfers" />

    <div class="min-h-screen space-y-6 bg-slate-100 p-6 dark:bg-slate-950">
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                <div class="space-y-2">
                    <p class="text-sm font-medium uppercase tracking-[0.24em] text-sky-500">Accounting</p>
                    <h1 class="text-3xl font-semibold tracking-tight text-slate-950 dark:text-white">Cash to bank transfers</h1>
                    <p class="max-w-3xl text-sm text-slate-600 dark:text-slate-300">
                        Record whenever cash collected in the till is physically deposited into the bank. Each transfer posts
                        <span class="font-semibold">debit bank account</span> and
                        <span class="font-semibold">credit cash on hand</span>
                        in one controlled journal.
                    </p>
                </div>
                <div class="flex flex-col gap-3 sm:flex-row">
                    <input v-model="filters.search" type="search" placeholder="Search transfers" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100" />
                    <Dialog v-model:open="showTransferModal">
                        <DialogTrigger as-child>
                            <Button class="rounded-2xl px-4 py-3 text-sm font-semibold">Record cash deposit</Button>
                        </DialogTrigger>
                        <DialogContent class="sm:max-w-2xl">
                            <form class="space-y-6" @submit.prevent="submit">
                                <DialogHeader class="space-y-2">
                                    <DialogTitle>Record cash deposit</DialogTitle>
                                    <DialogDescription>
                                        Use this when cash has left the till and has actually been deposited into the bank.
                                    </DialogDescription>
                                </DialogHeader>

                                <div class="grid gap-4 md:grid-cols-2">
                                    <input v-model="form.transfer_date" type="date" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100" />
                                    <input v-model="form.amount" type="number" min="0.01" step="0.01" placeholder="Amount deposited" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100" />
                                    <input v-model="form.currency" type="text" placeholder="Currency" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100" />
                                    <input v-model="form.reference" type="text" placeholder="Deposit slip / teller reference" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100" />
                                    <select v-model="form.cash_account_id" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                                        <option value="">Cash account</option>
                                        <option v-for="account in cash_account_options" :key="account.id" :value="String(account.id)">{{ account.label }}</option>
                                    </select>
                                    <select v-model="form.bank_account_id" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                                        <option value="">Bank account</option>
                                        <option v-for="account in bank_account_options" :key="account.id" :value="String(account.id)">{{ account.label }}</option>
                                    </select>
                                    <input v-model="form.description" type="text" placeholder="Transfer description" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 md:col-span-2" />
                                    <textarea v-model="form.note" rows="4" placeholder="Internal note" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 md:col-span-2" />
                                </div>

                                <div v-if="Object.keys(form.errors).length" class="rounded-2xl border border-rose-300 bg-rose-50 p-4 text-sm text-rose-700 dark:border-rose-900/60 dark:bg-rose-950/30 dark:text-rose-300">
                                    <div v-for="(error, key) in form.errors" :key="key">{{ error }}</div>
                                </div>

                                <DialogFooter class="gap-2">
                                    <Button type="button" variant="secondary" @click="showTransferModal = false">Cancel</Button>
                                    <Button type="submit" :disabled="form.processing">Record deposit</Button>
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
                                <th class="px-6 py-4">Transfer</th>
                                <th class="px-6 py-4">Date</th>
                                <th class="px-6 py-4">From cash</th>
                                <th class="px-6 py-4">To bank</th>
                                <th class="px-6 py-4 text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="transfer in transfers.data" :key="transfer.id">
                                <td class="px-6 py-4">
                                    <div class="font-semibold text-slate-900 dark:text-slate-100">{{ transfer.transfer_number }}</div>
                                    <div v-if="transfer.reference" class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ transfer.reference }}</div>
                                    <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ transfer.description }}</div>
                                </td>
                                <td class="px-6 py-4 text-slate-700 dark:text-slate-300">{{ transfer.transfer_date }}</td>
                                <td class="px-6 py-4 text-slate-700 dark:text-slate-300">{{ transfer.cash_account }}</td>
                                <td class="px-6 py-4 text-slate-700 dark:text-slate-300">{{ transfer.bank_account }}</td>
                                <td class="px-6 py-4 text-right text-slate-700 dark:text-slate-300">{{ formatCurrency(transfer.amount) }}</td>
                            </tr>
                            <tr v-if="!transfers.data.length">
                                <td colspan="5" class="px-6 py-16 text-center text-sm text-slate-500 dark:text-slate-400">No cash to bank transfers have been recorded yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="px-6 py-4">
                    <Pagination :links="transfers.links" />
                </div>
            </div>
        </section>
    </div>
</template>
