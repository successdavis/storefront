<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({ transactions: Array })
</script>

<template>
    <div class="bg-white dark:bg-gray-900 rounded-xl p-6 shadow-sm ">
        <div class="flex justify-between pr-3">
            <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-200 mb-4">
                Recent Transactions
            </h3>
            <Link href="/admin/transactions">View All</Link>
        </div>
        <div class="max-h-[520px] overflow-y-auto pr-3">
            <table class="w-full text-sm ">
                <thead class="text-gray-500 border-b dark:border-gray-800">
                <tr>
                    <th class="text-left py-2">Order ID</th>
                    <th>Source</th>
                    <th>Date</th>
                    <th>Customer</th>
                    <th class="text-right">Amount</th>
                    <th class="text-right">Status</th>
                </tr>
                </thead>
                <tbody>
                <tr
                    v-for="t in transactions"
                    :key="t.id"
                    class="border-b last:border-0 dark:border-gray-800"
                >
                    <td class="py-3">{{ t.id }}</td>
                    <td>{{ t.source }}</td>
                    <td>{{ t.date }}</td>
                    <td>{{ t.customer }}</td>
                    <td class="text-right">₦ {{ t.amount.toLocaleString() }}</td>
                    <td class="text-right">
            <span
                class="px-2 py-1 rounded-full text-xs font-medium"
                :class="{
                'bg-green-100 text-green-700': t.status === 'Completed',
                'bg-blue-100 text-blue-700': t.status === 'Processing',
              }"
            >
              {{ t.status }}
            </span>
                    </td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
