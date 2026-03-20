<script setup>
import { Link, router } from '@inertiajs/vue3'
import { ref } from 'vue'

const props = defineProps({
    alerts: {
        type: Array,
        required: true,
    },
})

const processingId = ref(null)

function resolveAlert(alertId) {
    if (processingId.value) {
        return
    }

    processingId.value = alertId
    router.post(
        `/admin/inventory-alerts/${alertId}/close`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                processingId.value = null
            },
        },
    )
}
</script>

<template>
    <div class="space-y-6 px-5 py-4 text-gray-900 dark:text-gray-100">
        <div>
            <h1 class="text-2xl font-bold">Discrepancy Dashboard</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Open discrepancy and negative stock alerts awaiting investigation.
            </p>
        </div>

        <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-4 py-3 text-left">Product</th>
                        <th class="px-4 py-3 text-left">System Qty</th>
                        <th class="px-4 py-3 text-left">Physical Qty</th>
                        <th class="px-4 py-3 text-left">Variance</th>
                        <th class="px-4 py-3 text-left">Message</th>
                        <th class="px-4 py-3 text-left">Status</th>
                        <th class="px-4 py-3 text-left">Detected At</th>
                        <th class="px-4 py-3 text-left">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    <tr
                        v-for="alert in alerts"
                        :key="alert.id"
                        class="hover:bg-gray-50 dark:hover:bg-gray-800/70"
                    >
                        <td class="px-4 py-3">
                            <div class="font-medium">{{ alert.product }}</div>
                            <div class="text-xs text-gray-500">SKU: {{ alert.sku || 'N/A' }}</div>
                        </td>
                        <td class="px-4 py-3">{{ alert.system_quantity ?? 'N/A' }}</td>
                        <td class="px-4 py-3">{{ alert.physical_quantity ?? 'N/A' }}</td>
                        <td
                            class="px-4 py-3 font-semibold"
                            :class="{
                                'text-green-600': alert.variance > 0,
                                'text-red-600': alert.variance < 0,
                            }"
                        >
                            {{ alert.variance === null ? 'N/A' : `${alert.variance > 0 ? '+' : ''}${alert.variance}` }}
                        </td>
                        <td class="px-4 py-3">{{ alert.message }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full bg-amber-100 px-2 py-1 text-xs font-medium text-amber-800">
                                {{ alert.status }}
                            </span>
                        </td>
                        <td class="px-4 py-3">{{ alert.detected_at || 'N/A' }}</td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-500 disabled:opacity-60"
                                    :disabled="processingId === alert.id"
                                    @click="resolveAlert(alert.id)"
                                >
                                    Resolve
                                </button>
                                <Link
                                    v-if="alert.adjustment_id"
                                    :href="`/admin/stock-adjustments/${alert.adjustment_id}`"
                                    class="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-medium hover:bg-gray-100 dark:border-gray-700 dark:hover:bg-gray-800"
                                >
                                    View Adjustment
                                </Link>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="alerts.length === 0">
                        <td class="px-4 py-6 text-center text-sm text-gray-500" colspan="8">
                            No open discrepancy alerts.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
