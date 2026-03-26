<script setup>
import { Link, router } from '@inertiajs/vue3'
import { ref } from 'vue'

const props = defineProps({
    sessions: {
        type: Array,
        default: () => [],
    },
    routes: {
        type: Object,
        required: true,
    },
})

const deletingId = ref(null)

function formatDate(value) {
    if (!value) {
        return 'N/A'
    }

    return new Date(value).toLocaleString()
}

function discardSession(sessionId) {
    if (deletingId.value || !confirm('Discard this in-progress audit session?')) {
        return
    }

    deletingId.value = sessionId
    router.delete(props.routes.session_discard_base.replace('__SESSION__', String(sessionId)), {
        preserveScroll: true,
        onFinish: () => {
            deletingId.value = null
        },
    })
}
</script>

<template>
    <div class="space-y-6 px-5 py-4 text-gray-900 dark:text-gray-100">
        <div>
            <h1 class="text-2xl font-bold">Resume Audit Session</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Continue any in-progress audit from where it stopped.
            </p>
        </div>

        <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-4 py-3 text-left">Session</th>
                        <th class="px-4 py-3 text-left">Scope</th>
                        <th class="px-4 py-3 text-left">Warehouse</th>
                        <th class="px-4 py-3 text-left">Started By</th>
                        <th class="px-4 py-3 text-left">Started At</th>
                        <th class="px-4 py-3 text-left">Last Activity</th>
                        <th class="px-4 py-3 text-left">Progress</th>
                        <th class="px-4 py-3 text-left">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    <tr
                        v-for="session in sessions"
                        :key="session.id"
                        class="hover:bg-gray-50 dark:hover:bg-gray-800/70"
                    >
                        <td class="px-4 py-3">
                            <p class="font-medium">{{ session.reference }}</p>
                            <p class="text-xs text-gray-500">#{{ session.id }}</p>
                        </td>
                        <td class="px-4 py-3">
                            <p>{{ session.scope_type === 'category' ? 'Category' : 'Full Inventory' }}</p>
                            <p v-if="session.scope_type === 'category'" class="text-xs text-gray-500">
                                {{ session.category_name || 'N/A' }}
                            </p>
                        </td>
                        <td class="px-4 py-3">{{ session.warehouse_name || 'N/A' }}</td>
                        <td class="px-4 py-3">{{ session.started_by_name || 'N/A' }}</td>
                        <td class="px-4 py-3">{{ formatDate(session.started_at) }}</td>
                        <td class="px-4 py-3">{{ formatDate(session.last_activity_at) }}</td>
                        <td class="px-4 py-3">
                            <p>{{ session.total_scanned_items }} / {{ session.total_expected_items }}</p>
                            <p class="text-xs text-gray-500">{{ Number(session.coverage_percentage || 0).toFixed(2) }}%</p>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-2">
                                <Link
                                    :href="`${routes.index}?session_id=${session.id}`"
                                    class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-500"
                                >
                                    Resume Manual
                                </Link>
                                <Link
                                    :href="`${routes.mobile}?session_id=${session.id}&ready=1`"
                                    class="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-medium hover:bg-gray-100 dark:border-gray-700 dark:hover:bg-gray-800"
                                >
                                    Resume Mobile
                                </Link>
                                <button
                                    type="button"
                                    class="rounded-md bg-red-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-red-500 disabled:opacity-60"
                                    :disabled="deletingId === session.id"
                                    @click="discardSession(session.id)"
                                >
                                    Discard
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="sessions.length === 0">
                        <td class="px-4 py-6 text-center text-sm text-gray-500" colspan="8">
                            No in-progress sessions available.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
