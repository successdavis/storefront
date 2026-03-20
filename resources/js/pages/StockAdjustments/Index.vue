<template>
    <div class="p-6 bg-gray-50 dark:bg-gray-900 min-h-screen transition-colors duration-300">
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-100">
                Stock Adjustments
            </h1>

            <Link
                href="/admin/stock-adjustments/create"
                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 dark:hover:bg-blue-500 transition"
            >
                + New Adjustment
            </Link>
        </div>

        <!-- Table -->
        <div
            class="overflow-x-auto bg-white dark:bg-gray-800 shadow rounded-lg border border-gray-200 dark:border-gray-700 transition-colors duration-300"
        >
            <table class="min-w-full border-collapse">
                <thead>
                <tr
                    class="bg-gray-50 dark:bg-gray-700 text-left text-sm font-semibold text-gray-700 dark:text-gray-200 border-b dark:border-gray-600"
                >
                    <th class="p-3">Variant</th>
                    <th class="p-3 text-right">Previous</th>
                    <th class="p-3 text-right">Adjusted</th>
                    <th class="p-3 text-right">New Qty</th>
                    <th class="p-3">Reason</th>
                    <th class="p-3">Adjusted At</th>
                    <th class="p-3 text-center">Action</th>
                </tr>
                </thead>

                <tbody>
                <tr
                    v-for="(item, index) in adjustments.data"
                    :key="item.id"
                    class="border-b border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition"
                >
                    <td class="p-3 font-medium text-gray-900 dark:text-gray-100">{{ item.product_name + ' - ' + item.variant_sku }}</td>
                    <td class="p-3 text-right text-gray-700 dark:text-gray-300">
                        {{ item.previous_quantity }}
                    </td>
                    <td
                        class="p-3 text-right"
                        :class="item.adjusted_quantity > 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'"
                    >
                        {{ item.adjusted_quantity }}
                    </td>
                    <td class="p-3 text-right font-semibold text-gray-800 dark:text-gray-200">
                        {{ item.new_quantity }}
                    </td>
                    <td class="p-3 text-gray-700 dark:text-gray-300">{{ item.reason }}</td>
                    <td class="p-3 text-gray-700 dark:text-gray-300">{{ item.adjusted_at }}</td>
                    <td class="p-3 text-center">
                        <Link
                            :href="`/admin/stock-adjustments/${item.id}`"
                            class="text-blue-600 dark:text-blue-400 hover:underline"
                        >
                            View
                        </Link>
                    </td>
                </tr>

                <tr v-if="!adjustments.data.length">
                    <td colspan="10" class="p-4 text-center text-gray-500 dark:text-gray-400">
                        No stock adjustments recorded yet.
                    </td>
                </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div v-if="adjustments.links.length > 3" class="mt-6 flex justify-end">
            <div class="flex space-x-2">
                <template v-for="(link, index) in adjustments.links" :key="index">

                    <!-- Valid link -->
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        v-html="link.label"
                        class="px-3 py-1 border rounded-md text-sm transition"
                        :class="{
          'bg-blue-600 text-white border-blue-600 dark:bg-blue-500 dark:border-blue-500': link.active,
          'text-gray-600 dark:text-gray-300 border-gray-300 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700': !link.active
        }"
                    />

                    <!-- Disabled link (null URL) -->
                    <span
                        v-else
                        v-html="link.label"
                        class="px-3 py-1 border rounded-md text-sm opacity-50 cursor-not-allowed text-gray-400 dark:text-gray-500 border-gray-300 dark:border-gray-600"
                    />

                </template>
            </div>
        </div>
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
    adjustments: Object,
})
</script>
