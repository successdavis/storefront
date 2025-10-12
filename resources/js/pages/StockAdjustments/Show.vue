<template>
    <div class="p-6 bg-gray-50 dark:bg-gray-900 min-h-screen transition-colors duration-300">
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-100">
                Stock Adjustment Details
            </h1>
            <Link
                href="/admin/stock-adjustments"
                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 dark:hover:bg-blue-500 transition"
            >
                ← Back
            </Link>
        </div>

        <!-- Details Card -->
        <div class="bg-white dark:bg-gray-800 shadow-md rounded-xl p-6 border border-gray-200 dark:border-gray-700">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Product SKU</p>
                    <p class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ adjustment.product_sku }}</p>
                </div>

                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Warehouse</p>
                    <p class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ adjustment.warehouse }}</p>
                </div>

                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Previous Quantity</p>
                    <p class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ adjustment.previous_quantity }}</p>
                </div>

                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Adjusted Quantity</p>
                    <p
                        class="text-lg font-medium"
                        :class="adjustment.adjusted_quantity > 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'"
                    >
                        {{ adjustment.adjusted_quantity }}
                    </p>
                </div>

                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">New Quantity</p>
                    <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ adjustment.new_quantity }}</p>
                </div>

                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Reason</p>
                    <p class="text-lg text-gray-900 dark:text-gray-100">{{ adjustment.reason }}</p>
                </div>

                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Adjusted By</p>
                    <p class="text-lg text-gray-900 dark:text-gray-100">{{ adjustment.employee }}</p>
                </div>

                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Date</p>
                    <p class="text-lg text-gray-900 dark:text-gray-100">{{ adjustment.created_at }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Note</p>
                    <p>{{adjustment.note}}</p>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
    adjustment: Object,
})
</script>
