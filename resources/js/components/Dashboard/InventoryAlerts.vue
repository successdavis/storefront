<script setup>
import { AlertTriangle } from 'lucide-vue-next'

defineProps({
    alerts: {
        type: Array,
        required: true,
    },
})
</script>

<template>
    <div class="bg-white dark:bg-gray-900 rounded-xl p-6 shadow-sm">
        <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-200 mb-4">
            Inventory Alerts
        </h3>

        <ul class="space-y-3">
            <li
                v-for="(alert, index) in alerts"
                :key="index"
                class="flex items-start gap-3 text-sm"
            >
                <AlertTriangle
                    class="w-4 h-4 mt-0.5 text-amber-500 flex-shrink-0"
                />
                <span class="text-gray-700 dark:text-gray-300">
          {{ alert }}
        </span>
            </li>
        </ul>

        <p
            v-if="alerts.length === 0"
            class="text-sm text-gray-500 dark:text-gray-400"
        >
            No inventory alerts at the moment.
        </p>
    </div>
</template>
