<script setup>
import { Link, router } from '@inertiajs/vue3'
import { ref } from 'vue'

const props = defineProps({
    alerts: {
        type: Array,
        required: true,
    },
    sessions: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({
            session_id: null,
            source: 'all',
        }),
    },
})

const processingId = ref(null)
const selectedSessionId = ref(props.filters.session_id || null)
const selectedSource = ref(props.filters.source || 'all')

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

function applyFilters() {
    router.get(
        '/admin/inventory/discrepancies',
        {
            session_id: selectedSessionId.value || null,
            source: selectedSource.value || 'all',
        },
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
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

        <div class="flex flex-wrap items-end gap-3 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <label class="text-sm">
                <span class="mb-1 block text-xs text-gray-500">Audit Session</span>
                <select
                    v-model.number="selectedSessionId"
                    class="w-56 rounded-md border border-gray-300 px-3 py-2 dark:border-gray-700 dark:bg-gray-900"
                >
                    <option :value="null">All sessions</option>
                    <option v-for="session in sessions" :key="session.id" :value="session.id">
                        {{ session.label }}
                    </option>
                </select>
            </label>

            <label class="text-sm">
                <span class="mb-1 block text-xs text-gray-500">Source</span>
                <select
                    v-model="selectedSource"
                    class="w-40 rounded-md border border-gray-300 px-3 py-2 dark:border-gray-700 dark:bg-gray-900"
                >
                    <option value="all">All</option>
                    <option value="audit">Audit</option>
                    <option value="system">System</option>
                </select>
            </label>

            <button
                type="button"
                class="rounded-lg border border-indigo-300 px-4 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-50 dark:border-indigo-700 dark:text-indigo-300 dark:hover:bg-indigo-900/30"
                @click="applyFilters"
            >
                Apply Filters
            </button>
        </div>

        <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-4 py-3 text-left">Product</th>
                        <th class="px-4 py-3 text-left">Session</th>
                        <th class="px-4 py-3 text-left">Source</th>
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
                        <td class="px-4 py-3 text-xs">
                            {{ alert.session_id ? `#${alert.session_id}` : 'N/A' }}
                        </td>
                        <td class="px-4 py-3 text-xs">
                            <span
                                class="rounded-full px-2 py-1 font-medium"
                                :class="alert.source === 'audit' ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200'"
                            >
                                {{ alert.source }}
                            </span>
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
                        <td class="px-4 py-6 text-center text-sm text-gray-500" colspan="10">
                            No open discrepancy alerts.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
