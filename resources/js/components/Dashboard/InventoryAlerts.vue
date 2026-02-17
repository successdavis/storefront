<script setup>
import { AlertTriangle, X } from 'lucide-vue-next'
import { router } from '@inertiajs/vue3'

const props = defineProps({
    alerts: {
        type: Array,
        required: true,
    },
})

const closeAlert = (alertId) => {
    router.post(
        route('inventory-alerts.close', alertId),
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                // Optimistically remove from UI
                const index = props.alerts.findIndex(a => a.id === alertId)
                if (index !== -1) {
                    props.alerts.splice(index, 1)
                }
            },
        }
    )
}
</script>

<template>
    <div class="bg-white dark:bg-gray-900 rounded-xl p-6 shadow-sm max-h-[390px] overflow-y-auto">
        <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-200 mb-4">
            Inventory Alerts
        </h3>

        <ul class="space-y-3">
            <li
                v-for="alert in alerts"
                :key="alert.id"
                class="flex items-start justify-between gap-3 text-sm"
            >
                <div class="flex gap-3">
                    <AlertTriangle
                        class="w-4 h-4 mt-0.5 text-amber-500 flex-shrink-0"
                    />
                    <span class="text-gray-700 dark:text-gray-300">
                        {{ alert.message }}
                    </span>
                </div>

                <button
                    @click="closeAlert(alert.id)"
                    class="text-gray-400 hover:text-red-500 transition"
                >
                    <X class="w-4 h-4" />
                </button>
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
