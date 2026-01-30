<script setup>
import { router } from '@inertiajs/vue3'
import { ref, watch } from 'vue'
import debounce from 'lodash/debounce'
import {
    Search,
    Filter,
    Layers,
    CreditCard,
    Globe,
    Calendar,
    DollarSign,
    ArrowUpDown
} from 'lucide-vue-next'

const props = defineProps({
    transactions: Object,
    filters: Object,
})

const form = ref({
    search: props.filters.search || '',
    status: props.filters.status || '',
    source: props.filters.source || '',
    method: props.filters.method || '',
    currency: props.filters.currency || '',
    from: props.filters.from || '',
    to: props.filters.to || '',
    min_amount: props.filters.min_amount || '',
    max_amount: props.filters.max_amount || '',
    sort: props.filters.sort || 'created_at',
    dir: props.filters.dir || 'desc',
})

const applyFilters = debounce(() => {
    router.get(route('transactions.index'), form.value, {
        preserveState: true,
        replace: true,
    })
}, 400)

watch(form, applyFilters, { deep: true })

const resolveSource = (t) => {
    return t.channel === 'pos' ? 'POS' : 'Online'
}

const resolveCustomer = (t) => {
    if (t.channel === 'pos') {
        return t.sale?.customer?.name || 'Walk-in'
    }
    return t.user?.name || 'Guest'
}

const resolveMethod = (t) => {
    return t.payments?.[0]?.method
        ? t.payments[0].method.toUpperCase()
        : '—'
}
</script>

<template>
    <div class="p-6 space-y-6 bg-gray-50 dark:bg-gray-950 min-h-screen">

        <!-- Header -->
        <div class="flex justify-between items-center">
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-100">
                All Transactions
            </h1>
        </div>

        <!-- Filters -->
        <div class="rounded-xl p-5 shadow
            bg-white dark:bg-gray-900
            border border-gray-200 dark:border-gray-800
            grid grid-cols-1 md:grid-cols-6 gap-5">

            <!-- Search -->
            <div class="col-span-2 space-y-1">
                <label class="text-xs text-gray-500 dark:text-gray-400">Search</label>
                <div class="relative">
                    <Search class="w-4 h-4 absolute left-3 top-3 text-gray-400" />
                    <input
                        v-model="form.search"
                        placeholder="Order number, customer..."
                        class="w-full pl-9 pr-3 py-2 rounded-lg text-sm
               bg-white dark:bg-gray-800
               border border-gray-300 dark:border-gray-700
               text-gray-800 dark:text-gray-100
               placeholder-gray-400
               focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                </div>
            </div>

            <!-- Status -->
            <div class="space-y-1">
                <label class="text-xs text-gray-500 dark:text-gray-400">Status</label>
                <div class="relative">
                    <Filter class="w-4 h-4 absolute left-3 top-3 text-gray-400" />
                    <select
                        v-model="form.status"
                        class="w-full pl-9 pr-8 py-2 rounded-lg text-sm
               bg-white dark:bg-gray-800
               border border-gray-300 dark:border-gray-700
               text-gray-800 dark:text-gray-100
               focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                        <option value="">All</option>
                        <option value="completed">Completed</option>
                        <option value="paid">Paid</option>
                        <option value="pending">Pending</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
            </div>

            <!-- Source -->
            <div class="space-y-1">
                <label class="text-xs text-gray-500 dark:text-gray-400">Source</label>
                <div class="relative">
                    <Layers class="w-4 h-4 absolute left-3 top-3 text-gray-400" />
                    <select
                        v-model="form.source"
                        class="w-full pl-9 pr-8 py-2 rounded-lg text-sm
               bg-white dark:bg-gray-800
               border border-gray-300 dark:border-gray-700
               text-gray-800 dark:text-gray-100
               focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                        <option value="">All</option>
                        <option value="POS">POS</option>
                        <option value="Online">Online</option>
                    </select>
                </div>
            </div>

            <!-- Method -->
            <div class="space-y-1">
                <label class="text-xs text-gray-500 dark:text-gray-400">Method</label>
                <div class="relative">
                    <CreditCard class="w-4 h-4 absolute left-3 top-3 text-gray-400" />
                    <select
                        v-model="form.method"
                        class="w-full pl-9 pr-8 py-2 rounded-lg text-sm
               bg-white dark:bg-gray-800
               border border-gray-300 dark:border-gray-700
               text-gray-800 dark:text-gray-100
               focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                        <option value="">All</option>
                        <option value="cash">Cash</option>
                        <option value="card">Card</option>
                        <option value="transfer">Transfer</option>
                        <option value="wallet">Wallet</option>
                    </select>
                </div>
            </div>

            <!-- Currency -->
            <div class="space-y-1">
                <label class="text-xs text-gray-500 dark:text-gray-400">Currency</label>
                <div class="relative">
                    <Globe class="w-4 h-4 absolute left-3 top-3 text-gray-400" />
                    <select
                        v-model="form.currency"
                        class="w-full pl-9 pr-8 py-2 rounded-lg text-sm
               bg-white dark:bg-gray-800
               border border-gray-300 dark:border-gray-700
               text-gray-800 dark:text-gray-100
               focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                        <option value="">All</option>
                        <option value="NGN">NGN</option>
                        <option value="USD">USD</option>
                    </select>
                </div>
            </div>

            <!-- From -->
            <div class="space-y-1">
                <label class="text-xs text-gray-500 dark:text-gray-400">From</label>
                <div class="relative">
                    <Calendar class="w-4 h-4 absolute left-3 top-3 text-gray-400" />
                    <input
                        type="date"
                        v-model="form.from"
                        class="w-full pl-9 pr-3 py-2 rounded-lg text-sm
               bg-white dark:bg-gray-800
               border border-gray-300 dark:border-gray-700
               text-gray-800 dark:text-gray-100
               focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                </div>
            </div>

            <!-- To -->
            <div class="space-y-1">
                <label class="text-xs text-gray-500 dark:text-gray-400">To</label>
                <div class="relative">
                    <Calendar class="w-4 h-4 absolute left-3 top-3 text-gray-400" />
                    <input
                        type="date"
                        v-model="form.to"
                        class="w-full pl-9 pr-3 py-2 rounded-lg text-sm
               bg-white dark:bg-gray-800
               border border-gray-300 dark:border-gray-700
               text-gray-800 dark:text-gray-100
               focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                </div>
            </div>

            <!-- Min -->
            <div class="space-y-1">
                <label class="text-xs text-gray-500 dark:text-gray-400">Min</label>
                <div class="relative">
                    <DollarSign class="w-4 h-4 absolute left-3 top-3 text-gray-400" />
                    <input
                        type="number"
                        v-model="form.min_amount"
                        class="w-full pl-9 pr-3 py-2 rounded-lg text-sm
               bg-white dark:bg-gray-800
               border border-gray-300 dark:border-gray-700
               text-gray-800 dark:text-gray-100
               focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                </div>
            </div>

            <!-- Max -->
            <div class="space-y-1">
                <label class="text-xs text-gray-500 dark:text-gray-400">Max</label>
                <div class="relative">
                    <DollarSign class="w-4 h-4 absolute left-3 top-3 text-gray-400" />
                    <input
                        type="number"
                        v-model="form.max_amount"
                        class="w-full pl-9 pr-3 py-2 rounded-lg text-sm
               bg-white dark:bg-gray-800
               border border-gray-300 dark:border-gray-700
               text-gray-800 dark:text-gray-100
               focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                </div>
            </div>

            <!-- Sort -->
            <div class="space-y-1">
                <label class="text-xs text-gray-500 dark:text-gray-400">Sort</label>
                <div class="relative">
                    <ArrowUpDown class="w-4 h-4 absolute left-3 top-3 text-gray-400" />
                    <select
                        v-model="form.sort"
                        class="w-full pl-9 pr-8 py-2 rounded-lg text-sm
               bg-white dark:bg-gray-800
               border border-gray-300 dark:border-gray-700
               text-gray-800 dark:text-gray-100
               focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                        <option value="created_at">Date</option>
                        <option value="total_amount">Amount</option>
                        <option value="status">Status</option>
                    </select>
                </div>
            </div>

            <!-- Direction -->
            <div class="space-y-1">
                <label class="text-xs text-gray-500 dark:text-gray-400">Direction</label>
                <div class="relative">
                    <ArrowUpDown class="w-4 h-4 absolute left-3 top-3 text-gray-400" />
                    <select
                        v-model="form.dir"
                        class="w-full pl-9 pr-8 py-2 rounded-lg text-sm
               bg-white dark:bg-gray-800
               border border-gray-300 dark:border-gray-700
               text-gray-800 dark:text-gray-100
               focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                        <option value="desc">Desc</option>
                        <option value="asc">Asc</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="rounded-xl shadow overflow-x-auto
                    bg-white dark:bg-gray-900
                    border border-gray-200 dark:border-gray-800">

            <table class="w-full text-sm">
                <thead class="border-b border-gray-200 dark:border-gray-800
                              text-gray-600 dark:text-gray-400">
                <tr>
                    <th class="p-3 text-left">Order #</th>
                    <th class="text-left">Customer</th>
                    <th>Source</th>
                    <th>Method</th>
                    <th>Date</th>
                    <th class="text-right">Amount</th>
                    <th>Status</th>
                </tr>
                </thead>

                <tbody>
                <tr v-for="t in transactions.data"
                    :key="t.id"
                    class="border-b border-gray-100 dark:border-gray-800
                           hover:bg-gray-50 dark:hover:bg-gray-800/60">

                    <td class="p-3 font-medium">
                        {{ t.order_number }}
                    </td>

                    <td>
                        {{ resolveCustomer(t) }}
                    </td>

                    <td>
                        {{ resolveSource(t) }}
                    </td>

                    <td>
                        {{ resolveMethod(t) }}
                    </td>

                    <td>
                        {{ new Date(t.created_at).toLocaleDateString() }}
                    </td>

                    <td class="text-right font-semibold">
                        {{ t.currency }} {{ Number(t.total_amount).toLocaleString() }}
                    </td>

                    <td>
                        <span
                            class="px-2 py-1 rounded text-xs font-medium"
                            :class="{
                              'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400': t.status === 'completed',
                              'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400': t.status === 'paid',
                              'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400': t.status === 'pending',
                              'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400': t.status === 'cancelled'
                            }">
                            {{ t.status.toUpperCase() }}
                        </span>
                    </td>
                </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="flex justify-end gap-2">
            <button
                v-for="link in transactions.links"
                :key="link.label"
                v-html="link.label"
                @click="router.visit(link.url)"
                :disabled="!link.url"
                class="px-3 py-1 rounded text-sm border
                       border-gray-300 dark:border-gray-700
                       text-gray-700 dark:text-gray-300
                       hover:bg-gray-100 dark:hover:bg-gray-800"
                :class="{ 'bg-gray-200 dark:bg-gray-700 font-semibold': link.active }"
            />
        </div>
    </div>
</template>
