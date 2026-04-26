<script setup lang="ts">
import Pagination from '@/components/Pagination.vue'
import { Button } from '@/components/ui/button'
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog'
import { Head, router, useForm } from '@inertiajs/vue3'
import { computed, reactive, ref, watch } from 'vue'

const props = defineProps<{
    report: any
    customer_options: Array<{ id: number; name: string; email?: string | null; phone?: string | null }>
    payment_method_options: Array<{ value: string; label: string }>
}>()

const filters = reactive({
    search: props.report.filters?.search || '',
    status: props.report.filters?.status || '',
    customer_id: props.report.filters?.customer_id ? String(props.report.filters.customer_id) : '',
    from: props.report.filters?.from || '',
    to: props.report.filters?.to || '',
    as_of: props.report.filters?.as_of || '',
    per_page: String(props.report.filters?.per_page || 15),
})

watch(
    () => ({ ...filters }),
    (value) => {
        router.get(route('admin.accounting.reports.receivables'), {
            search: value.search || undefined,
            status: value.status || undefined,
            customer_id: value.customer_id || undefined,
            from: value.from || undefined,
            to: value.to || undefined,
            as_of: value.as_of || undefined,
            per_page: value.per_page || undefined,
        }, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        })
    },
)

const repaymentDialogOpen = ref(false)
const activeInvoice = ref<any | null>(null)
const repaymentForm = useForm({
    payment_lines: [createPaymentLine()],
})

const selectedCustomerLabel = computed(() => {
    if (!filters.customer_id) return 'All customers'

    return props.customer_options.find((customer) => String(customer.id) === filters.customer_id)?.name || 'Selected customer'
})

function createPaymentLine() {
    return {
        method: 'transfer',
        amount: '',
        transaction_reference: '',
    }
}

function addRepaymentLine() {
    repaymentForm.payment_lines.push(createPaymentLine())
}

function removeRepaymentLine(index: number) {
    if (repaymentForm.payment_lines.length === 1) {
        return
    }

    repaymentForm.payment_lines.splice(index, 1)
}

function openRepaymentModal(invoice: any) {
    activeInvoice.value = invoice
    repaymentForm.reset()
    repaymentForm.payment_lines = [createPaymentLine()]
    repaymentDialogOpen.value = true
}

function submitRepayment() {
    if (!activeInvoice.value) {
        return
    }

    repaymentForm
        .transform((data) => ({
            payment_lines: data.payment_lines
                .map((line) => ({
                    method: line.method,
                    amount: Number(line.amount || 0),
                    transaction_reference: line.transaction_reference || null,
                }))
                .filter((line) => line.amount > 0),
        }))
        .post(route('admin.accounting.customer-invoices.payments.store', activeInvoice.value.id), {
            preserveScroll: true,
            onSuccess: () => {
                repaymentDialogOpen.value = false
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
    <Head title="Receivables" />

    <div class="min-h-screen space-y-6 bg-slate-100 p-6 dark:bg-slate-950">
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col gap-4">
                <div>
                    <p class="text-sm font-medium uppercase tracking-[0.24em] text-sky-500">Accounting reports</p>
                    <h1 class="text-3xl font-semibold tracking-tight text-slate-950 dark:text-white">Receivables</h1>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Monitor open credit sales, aging, and debt recovery without reposting revenue.</p>
                </div>
                <div class="grid gap-3 md:grid-cols-3 xl:grid-cols-6">
                    <input v-model="filters.search" type="text" placeholder="Search invoice, order, or customer" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100" />
                    <select v-model="filters.status" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                        <option value="">All statuses</option>
                        <option value="unpaid">Unpaid</option>
                        <option value="partially_paid">Partially paid</option>
                        <option value="paid">Paid</option>
                        <option value="overdue">Overdue</option>
                    </select>
                    <select v-model="filters.customer_id" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                        <option value="">All customers</option>
                        <option v-for="customer in customer_options" :key="customer.id" :value="String(customer.id)">
                            {{ customer.name }}
                        </option>
                    </select>
                    <input v-model="filters.from" type="date" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100" />
                    <input v-model="filters.to" type="date" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100" />
                    <input v-model="filters.as_of" type="date" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100" />
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400">Currently viewing: {{ selectedCustomerLabel }}</p>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <article
                v-for="card in report.summary_cards"
                :key="card.key"
                class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
            >
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400">{{ card.label }}</p>
                <p class="mt-3 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ formatCurrency(card.value) }}</p>
            </article>
        </section>

        <section class="grid gap-6 xl:grid-cols-[0.9fr_1.1fr]">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-xl font-semibold text-slate-950 dark:text-white">Receivables aging</h2>
                <div class="mt-6 space-y-3">
                    <div
                        v-for="bucket in report.aging_buckets"
                        :key="bucket.key"
                        class="flex items-center justify-between gap-4 rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-800"
                    >
                        <p class="font-medium text-slate-900 dark:text-slate-100">{{ bucket.label }}</p>
                        <p class="font-semibold text-slate-900 dark:text-slate-100">{{ formatCurrency(bucket.amount) }}</p>
                    </div>
                </div>
            </div>

            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-xl font-semibold text-slate-950 dark:text-white">Recent debt recoveries</h2>
                <div class="mt-6 space-y-3">
                    <div
                        v-for="payment in report.recent_recoveries"
                        :key="payment.id"
                        class="rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-800"
                    >
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <p class="font-semibold text-slate-900 dark:text-slate-100">{{ payment.invoice_number }}</p>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ payment.customer_name || 'Customer' }} · {{ payment.method }}</p>
                            </div>
                            <p class="font-semibold text-slate-900 dark:text-slate-100">{{ formatCurrency(payment.amount) }}</p>
                        </div>
                        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">{{ payment.transaction_reference || 'No reference' }}</p>
                    </div>
                    <div v-if="report.recent_recoveries.length === 0" class="rounded-2xl border border-dashed border-slate-300 px-6 py-8 text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">
                        No receivable repayments have been recorded for the selected range.
                    </div>
                </div>
            </div>
        </section>

        <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead class="bg-slate-50 dark:bg-slate-950">
                        <tr class="text-left text-xs uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400">
                            <th class="px-6 py-4">Invoice</th>
                            <th class="px-6 py-4">Customer</th>
                            <th class="px-6 py-4">Status</th>
                            <th class="px-6 py-4">Due date</th>
                            <th class="px-6 py-4 text-right">Debt</th>
                            <th class="px-6 py-4 text-right">Recovered</th>
                            <th class="px-6 py-4 text-right">Outstanding</th>
                            <th class="px-6 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr v-for="invoice in report.invoices.data" :key="invoice.id">
                            <td class="px-6 py-4">
                                <p class="font-semibold text-slate-900 dark:text-slate-100">{{ invoice.invoice_number }}</p>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ invoice.order?.order_number || 'No order link' }}</p>
                            </td>
                            <td class="px-6 py-4 text-slate-600 dark:text-slate-300">{{ invoice.customer?.name || 'Customer' }}</td>
                            <td class="px-6 py-4">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" :class="invoice.status === 'paid' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-200' : invoice.status === 'overdue' ? 'bg-rose-100 text-rose-700 dark:bg-rose-950/40 dark:text-rose-200' : 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-200'">
                                    {{ invoice.status_label }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-slate-600 dark:text-slate-300">{{ invoice.due_date || '-' }}</td>
                            <td class="px-6 py-4 text-right font-semibold text-slate-900 dark:text-slate-100">{{ formatCurrency(invoice.total_amount) }}</td>
                            <td class="px-6 py-4 text-right text-slate-600 dark:text-slate-300">{{ formatCurrency(invoice.amount_paid) }}</td>
                            <td class="px-6 py-4 text-right font-semibold text-slate-900 dark:text-slate-100">{{ formatCurrency(invoice.outstanding_balance) }}</td>
                            <td class="px-6 py-4 text-right">
                                <Button
                                    v-if="invoice.outstanding_balance > 0"
                                    type="button"
                                    variant="outline"
                                    class="rounded-xl"
                                    @click="openRepaymentModal(invoice)"
                                >
                                    Record payment
                                </Button>
                            </td>
                        </tr>
                        <tr v-if="report.invoices.data.length === 0">
                            <td colspan="8" class="px-6 py-10 text-center text-sm text-slate-500 dark:text-slate-400">No receivables matched these filters.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="px-6 py-4">
                <Pagination :links="report.invoices.links" />
            </div>
        </section>

        <Dialog v-model:open="repaymentDialogOpen">
            <DialogContent class="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Record receivable payment</DialogTitle>
                    <DialogDescription>
                        Post cash, transfer, card, or other settlement lines against the selected invoice without recognizing revenue again.
                    </DialogDescription>
                </DialogHeader>

                <div v-if="activeInvoice" class="space-y-4">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-950">
                        <p class="font-semibold text-slate-900 dark:text-slate-100">{{ activeInvoice.invoice_number }}</p>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ activeInvoice.customer?.name || 'Customer' }}</p>
                        <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Outstanding: <span class="font-semibold text-slate-900 dark:text-slate-100">{{ formatCurrency(activeInvoice.outstanding_balance) }}</span></p>
                    </div>

                    <div class="space-y-3">
                        <div
                            v-for="(line, index) in repaymentForm.payment_lines"
                            :key="index"
                            class="grid gap-3 md:grid-cols-[minmax(0,1fr)_8rem_auto]"
                        >
                            <select v-model="line.method" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                                <option v-for="option in payment_method_options" :key="option.value" :value="option.value">{{ option.label }}</option>
                            </select>
                            <input v-model="line.amount" type="number" min="0" step="0.01" placeholder="0.00" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100" />
                            <Button type="button" variant="outline" class="rounded-2xl" :disabled="repaymentForm.payment_lines.length === 1" @click="removeRepaymentLine(index)">
                                Remove
                            </Button>
                            <input v-model="line.transaction_reference" type="text" placeholder="Reference (optional)" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm md:col-span-3 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100" />
                        </div>
                    </div>

                    <Button type="button" variant="outline" class="rounded-2xl" @click="addRepaymentLine">
                        Add payment line
                    </Button>
                </div>

                <DialogFooter>
                    <Button type="button" variant="ghost" @click="repaymentDialogOpen = false">Cancel</Button>
                    <Button type="button" :disabled="repaymentForm.processing" @click="submitRepayment">
                        Save payment
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
