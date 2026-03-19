<script setup>
import { Head, router, useForm } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'

const props = defineProps({
    reference: {
        type: String,
        default: null,
    },
    checkout_session: {
        type: Object,
        default: null,
    },
    logs: {
        type: Array,
        default: () => [],
    },
})

const searchReference = ref(props.reference || '')

watch(
    () => props.reference,
    (value) => {
        searchReference.value = value || ''
    }
)

const reverifyForm = useForm({
    reference: props.reference || '',
})

const refundForm = useForm({
    reference: props.reference || '',
    reason: 'Manual refund reconciliation by admin',
    amount: '',
    force: false,
})

watch(
    () => props.reference,
    (value) => {
        reverifyForm.reference = value || ''
        refundForm.reference = value || ''
    }
)

const canAct = computed(() => !!props.reference && !!props.checkout_session)

function runSearch() {
    router.get(
        route('admin.payment-recovery.index'),
        { reference: searchReference.value || undefined },
        { preserveState: true, replace: true }
    )
}

function triggerReverify() {
    if (!canAct.value) return

    reverifyForm.reference = props.reference
    reverifyForm.post(route('admin.payment-recovery.reverify'), {
        preserveScroll: true,
    })
}

function triggerRefund() {
    if (!canAct.value) return

    refundForm.reference = props.reference
    refundForm.post(route('admin.payment-recovery.refund'), {
        preserveScroll: true,
    })
}

function formatMoney(value, currency = 'NGN') {
    const amount = Number(value || 0)
    return new Intl.NumberFormat('en-NG', {
        style: 'currency',
        currency: currency || 'NGN',
    }).format(amount)
}

function formatDate(value) {
    if (!value) return '-'
    return new Date(value).toLocaleString()
}
</script>

<template>
    <Head title="Payment Recovery" />

    <div class="space-y-6 px-5 py-4">
        <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Payment Recovery</h1>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Search by payment reference and run manual reverify or refund reconciliation with full audit trail.
            </p>

            <div class="mt-4 flex flex-col gap-3 sm:flex-row">
                <input
                    v-model="searchReference"
                    type="text"
                    placeholder="Enter payment reference (e.g. PSTK-...)"
                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                    @keyup.enter="runSearch"
                />
                <button
                    type="button"
                    class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                    @click="runSearch"
                >
                    Search
                </button>
            </div>
        </section>

        <section
            v-if="checkout_session"
            class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900"
        >
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="space-y-2 text-sm text-gray-700 dark:text-gray-300">
                    <div><span class="font-semibold">Reference:</span> {{ checkout_session.reference }}</div>
                    <div><span class="font-semibold">Payment Status:</span> {{ checkout_session.payment_status || '-' }}</div>
                    <div><span class="font-semibold">Session Used:</span> {{ checkout_session.used ? 'Yes' : 'No' }}</div>
                    <div><span class="font-semibold">Retry Count:</span> {{ checkout_session.retry_count }}</div>
                    <div><span class="font-semibold">Total:</span> {{ formatMoney(checkout_session.total, checkout_session.payment_currency || 'NGN') }}</div>
                    <div><span class="font-semibold">Payment Amount:</span> {{ formatMoney(checkout_session.payment_amount || 0, checkout_session.payment_currency || 'NGN') }}</div>
                    <div><span class="font-semibold">Expires At:</span> {{ formatDate(checkout_session.expires_at) }}</div>
                    <div><span class="font-semibold">Processed At:</span> {{ formatDate(checkout_session.processed_at) }}</div>
                    <div><span class="font-semibold">Last Error:</span> {{ checkout_session.processing_error || '-' }}</div>
                </div>

                <div class="min-w-[280px] space-y-3">
                    <button
                        type="button"
                        class="w-full rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-60"
                        :disabled="!canAct || reverifyForm.processing"
                        @click="triggerReverify"
                    >
                        {{ reverifyForm.processing ? 'Reverifying...' : 'Run Reverify' }}
                    </button>

                    <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            Refund reason
                        </label>
                        <input
                            v-model="refundForm.reason"
                            type="text"
                            class="w-full rounded-md border border-gray-300 bg-white px-2 py-2 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                        />

                        <label class="mb-1 mt-3 block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            Partial amount (optional)
                        </label>
                        <input
                            v-model="refundForm.amount"
                            type="number"
                            min="0.01"
                            step="0.01"
                            placeholder="Leave empty for full refund"
                            class="w-full rounded-md border border-gray-300 bg-white px-2 py-2 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                        />

                        <label class="mt-3 flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <input v-model="refundForm.force" type="checkbox" />
                            Force refund even when order already exists
                        </label>

                        <button
                            type="button"
                            class="mt-3 w-full rounded-lg bg-rose-600 px-4 py-2 text-sm font-medium text-white hover:bg-rose-700 disabled:opacity-60"
                            :disabled="!canAct || refundForm.processing"
                            @click="triggerRefund"
                        >
                            {{ refundForm.processing ? 'Processing Refund...' : 'Run Refund Reconciliation' }}
                        </button>
                    </div>
                </div>
            </div>

            <div
                v-if="checkout_session.order"
                class="mt-5 rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm dark:border-gray-700 dark:bg-gray-800/40"
            >
                <h2 class="mb-2 text-base font-semibold text-gray-900 dark:text-gray-100">Linked Order</h2>
                <div class="grid gap-2 text-gray-700 dark:text-gray-300 md:grid-cols-2">
                    <div><span class="font-semibold">Order #:</span> {{ checkout_session.order.order_number }}</div>
                    <div><span class="font-semibold">Status:</span> {{ checkout_session.order.status }}</div>
                    <div><span class="font-semibold">Channel:</span> {{ checkout_session.order.channel }}</div>
                    <div><span class="font-semibold">Amount:</span> {{ formatMoney(checkout_session.order.total_amount, checkout_session.payment_currency || 'NGN') }}</div>
                    <div><span class="font-semibold">Customer:</span> {{ checkout_session.order.user?.name || 'N/A' }}</div>
                    <div><span class="font-semibold">Created:</span> {{ formatDate(checkout_session.order.created_at) }}</div>
                </div>
            </div>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Audit Logs</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Showing latest {{ logs.length }} {{ reference ? 'entries for this reference' : 'entries' }}.
            </p>

            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr class="text-left text-xs uppercase tracking-wider text-gray-600 dark:text-gray-300">
                            <th class="px-3 py-2">Time</th>
                            <th class="px-3 py-2">Reference</th>
                            <th class="px-3 py-2">Action</th>
                            <th class="px-3 py-2">Status</th>
                            <th class="px-3 py-2">Actor</th>
                            <th class="px-3 py-2">Message</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-for="log in logs" :key="log.id" class="align-top">
                            <td class="px-3 py-2 text-gray-700 dark:text-gray-300">{{ formatDate(log.created_at) }}</td>
                            <td class="px-3 py-2 font-mono text-xs text-gray-700 dark:text-gray-300">{{ log.reference }}</td>
                            <td class="px-3 py-2 text-gray-700 dark:text-gray-300">{{ log.action }}</td>
                            <td class="px-3 py-2">
                                <span
                                    class="rounded px-2 py-1 text-xs font-medium"
                                    :class="{
                                        'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300': log.status === 'success',
                                        'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300': log.status === 'skipped',
                                        'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300': log.status === 'failed'
                                    }"
                                >
                                    {{ log.status }}
                                </span>
                            </td>
                            <td class="px-3 py-2 text-gray-700 dark:text-gray-300">
                                {{ log.actor?.name || 'System' }}
                            </td>
                            <td class="px-3 py-2 text-gray-700 dark:text-gray-300">
                                {{ log.message || '-' }}
                            </td>
                        </tr>
                        <tr v-if="logs.length === 0">
                            <td colspan="6" class="px-3 py-6 text-center text-gray-500 dark:text-gray-400">
                                No audit logs found.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>

