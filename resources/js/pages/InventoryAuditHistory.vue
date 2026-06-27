<script setup>
import Pagination from '@/components/Pagination.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import {
    AlertTriangle,
    ClipboardList,
    History,
    RotateCcw,
    Search,
    Smartphone,
} from 'lucide-vue-next'
import { computed, reactive } from 'vue'

const props = defineProps({
    sessions: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    summary: {
        type: Object,
        default: () => ({}),
    },
    routes: {
        type: Object,
        required: true,
    },
})

const filterForm = reactive({
    status: props.filters.status || 'all',
    source: props.filters.source || 'all',
    scope: props.filters.scope || 'all',
    search: props.filters.search || '',
})

const scannedShare = computed(() => {
    const expected = Number(props.summary.expected_items || 0)
    const scanned = Number(props.summary.scanned_items || 0)

    return expected > 0 ? Math.min(100, Math.round((scanned / expected) * 100)) : 0
})

function applyFilters() {
    router.get(
        props.routes.history,
        {
            status: filterForm.status !== 'all' ? filterForm.status : undefined,
            source: filterForm.source !== 'all' ? filterForm.source : undefined,
            scope: filterForm.scope !== 'all' ? filterForm.scope : undefined,
            search: filterForm.search.trim() || undefined,
        },
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        },
    )
}

function resetFilters() {
    filterForm.status = 'all'
    filterForm.source = 'all'
    filterForm.scope = 'all'
    filterForm.search = ''
    applyFilters()
}

function formatDate(value) {
    if (!value) {
        return 'N/A'
    }

    return new Date(value).toLocaleString()
}

function statusClass(status) {
    return {
        in_progress: 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-200',
        submitted: 'bg-blue-100 text-blue-800 dark:bg-blue-500/15 dark:text-blue-200',
        reviewed: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-200',
    }[status] || 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200'
}

function sourceClass(source) {
    return {
        mobile: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-500/15 dark:text-indigo-200',
        manual: 'bg-teal-100 text-teal-800 dark:bg-teal-500/15 dark:text-teal-200',
        audit: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
        system: 'bg-rose-100 text-rose-800 dark:bg-rose-500/15 dark:text-rose-200',
        unknown: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200',
    }[source] || 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200'
}

function statusLabel(status) {
    return String(status || 'unknown').replace('_', ' ')
}
</script>

<template>
    <Head title="Audit History" />

    <div class="space-y-5 px-4 py-4 text-slate-900 dark:text-slate-100 sm:px-5">
        <div class="flex flex-col gap-3 border-b border-slate-200 pb-4 dark:border-slate-800 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <div class="flex items-center gap-2 text-slate-700 dark:text-slate-300">
                    <History class="h-5 w-5" aria-hidden="true" />
                    <span class="text-sm font-semibold uppercase">Inventory Audit</span>
                </div>
                <h1 class="mt-1 text-2xl font-bold">Audit History</h1>
            </div>

            <div class="flex flex-wrap gap-2">
                <Link
                    :href="routes.index"
                    class="inline-flex items-center gap-2 rounded-md border border-slate-300 px-3 py-2 text-sm font-medium hover:bg-slate-100 dark:border-slate-700 dark:hover:bg-slate-800"
                >
                    <ClipboardList class="h-4 w-4" aria-hidden="true" />
                    <span>Manual Audit</span>
                </Link>
                <Link
                    :href="routes.mobile"
                    class="inline-flex items-center gap-2 rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-700 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-300"
                >
                    <Smartphone class="h-4 w-4" aria-hidden="true" />
                    <span>Mobile Audit</span>
                </Link>
            </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
            <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-medium uppercase text-slate-500">Sessions</p>
                <p class="mt-2 text-2xl font-semibold">{{ summary.total || 0 }}</p>
            </div>
            <div class="rounded-lg border border-amber-200 bg-white p-4 dark:border-amber-900/60 dark:bg-slate-900">
                <p class="text-xs font-medium uppercase text-slate-500">In Progress</p>
                <p class="mt-2 text-2xl font-semibold text-amber-700 dark:text-amber-300">{{ summary.in_progress || 0 }}</p>
            </div>
            <div class="rounded-lg border border-blue-200 bg-white p-4 dark:border-blue-900/60 dark:bg-slate-900">
                <p class="text-xs font-medium uppercase text-slate-500">Submitted</p>
                <p class="mt-2 text-2xl font-semibold text-blue-700 dark:text-blue-300">{{ summary.submitted || 0 }}</p>
            </div>
            <div class="rounded-lg border border-emerald-200 bg-white p-4 dark:border-emerald-900/60 dark:bg-slate-900">
                <p class="text-xs font-medium uppercase text-slate-500">Reviewed</p>
                <p class="mt-2 text-2xl font-semibold text-emerald-700 dark:text-emerald-300">{{ summary.reviewed || 0 }}</p>
            </div>
            <div class="rounded-lg border border-rose-200 bg-white p-4 dark:border-rose-900/60 dark:bg-slate-900">
                <p class="text-xs font-medium uppercase text-slate-500">Partial</p>
                <p class="mt-2 text-2xl font-semibold text-rose-700 dark:text-rose-300">{{ summary.partial || 0 }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-medium uppercase text-slate-500">Scanned</p>
                <p class="mt-2 text-2xl font-semibold">{{ scannedShare }}%</p>
            </div>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
            <div class="grid gap-3 lg:grid-cols-[1.3fr_0.7fr_0.7fr_0.7fr_auto]">
                <label class="block">
                    <span class="mb-1 block text-xs font-medium uppercase text-slate-500">Search</span>
                    <div class="relative">
                        <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                        <input
                            v-model="filterForm.search"
                            type="search"
                            class="h-10 w-full rounded-md border border-slate-300 bg-white pl-9 pr-3 text-sm dark:border-slate-700 dark:bg-slate-950"
                            placeholder="Reference, staff, category, warehouse"
                            @keyup.enter="applyFilters"
                        />
                    </div>
                </label>

                <label class="block">
                    <span class="mb-1 block text-xs font-medium uppercase text-slate-500">Status</span>
                    <select
                        v-model="filterForm.status"
                        class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm dark:border-slate-700 dark:bg-slate-950"
                        @change="applyFilters"
                    >
                        <option value="all">All</option>
                        <option value="in_progress">In progress</option>
                        <option value="submitted">Submitted</option>
                        <option value="reviewed">Reviewed</option>
                    </select>
                </label>

                <label class="block">
                    <span class="mb-1 block text-xs font-medium uppercase text-slate-500">Source</span>
                    <select
                        v-model="filterForm.source"
                        class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm dark:border-slate-700 dark:bg-slate-950"
                        @change="applyFilters"
                    >
                        <option value="all">All</option>
                        <option value="mobile">Mobile</option>
                        <option value="manual">Manual</option>
                        <option value="audit">Audit</option>
                        <option value="system">System</option>
                        <option value="unknown">Unknown</option>
                    </select>
                </label>

                <label class="block">
                    <span class="mb-1 block text-xs font-medium uppercase text-slate-500">Scope</span>
                    <select
                        v-model="filterForm.scope"
                        class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm dark:border-slate-700 dark:bg-slate-950"
                        @change="applyFilters"
                    >
                        <option value="all">All</option>
                        <option value="full">Full</option>
                        <option value="category">Category</option>
                    </select>
                </label>

                <div class="flex items-end">
                    <button
                        type="button"
                        class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-md border border-slate-300 px-3 text-sm font-medium hover:bg-slate-100 dark:border-slate-700 dark:hover:bg-slate-800 lg:w-auto"
                        @click="resetFilters"
                    >
                        <RotateCcw class="h-4 w-4" aria-hidden="true" />
                        <span>Reset</span>
                    </button>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
            <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500 dark:bg-slate-950/60 dark:text-slate-400">
                    <tr>
                        <th class="px-4 py-3 text-left">Session</th>
                        <th class="px-4 py-3 text-left">Status</th>
                        <th class="px-4 py-3 text-left">Source</th>
                        <th class="px-4 py-3 text-left">Scope</th>
                        <th class="px-4 py-3 text-left">Progress</th>
                        <th class="px-4 py-3 text-left">Variance</th>
                        <th class="px-4 py-3 text-left">Staff</th>
                        <th class="px-4 py-3 text-left">Dates</th>
                        <th class="px-4 py-3 text-left">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr
                        v-for="session in sessions.data"
                        :key="session.id"
                        class="align-top hover:bg-slate-50 dark:hover:bg-slate-800/60"
                    >
                        <td class="whitespace-nowrap px-4 py-3">
                            <p class="font-semibold">{{ session.reference }}</p>
                            <p class="text-xs text-slate-500">#{{ session.id }}</p>
                        </td>
                        <td class="px-4 py-3">
                            <span :class="['inline-flex rounded-full px-2 py-1 text-xs font-semibold capitalize', statusClass(session.status)]">
                                {{ statusLabel(session.status) }}
                            </span>
                            <p v-if="session.is_partial" class="mt-2 inline-flex items-center gap-1 text-xs font-medium text-rose-600 dark:text-rose-300">
                                <AlertTriangle class="h-3.5 w-3.5" aria-hidden="true" />
                                <span>Partial</span>
                            </p>
                        </td>
                        <td class="px-4 py-3">
                            <span :class="['inline-flex rounded-full px-2 py-1 text-xs font-semibold', sourceClass(session.source)]">
                                {{ session.source_label }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <p class="font-medium">{{ session.scope_label }}</p>
                            <p class="text-xs text-slate-500">{{ session.category_name || session.warehouse_name || 'All inventory' }}</p>
                        </td>
                        <td class="min-w-48 px-4 py-3">
                            <div class="flex items-center justify-between gap-3">
                                <p class="font-medium">{{ session.total_scanned_items }} / {{ session.total_expected_items }}</p>
                                <p class="text-xs text-slate-500">{{ Number(session.coverage_percentage || 0).toFixed(2) }}%</p>
                            </div>
                            <div class="mt-2 h-2 rounded-full bg-slate-100 dark:bg-slate-800">
                                <div
                                    class="h-2 rounded-full bg-emerald-500"
                                    :style="{ width: `${Math.min(100, Number(session.coverage_percentage || 0))}%` }"
                                ></div>
                            </div>
                            <p class="mt-1 text-xs text-slate-500">{{ session.missing_items }} missing</p>
                        </td>
                        <td class="px-4 py-3">
                            <p>{{ session.discrepancy_count }} discrepancies</p>
                            <p class="text-xs text-slate-500">{{ session.conflict_count }} conflicts</p>
                        </td>
                        <td class="px-4 py-3">
                            <p>{{ session.started_by_name || 'N/A' }}</p>
                            <p class="text-xs text-slate-500">Submitted by {{ session.submitted_by_name || 'N/A' }}</p>
                        </td>
                        <td class="min-w-52 px-4 py-3 text-xs text-slate-600 dark:text-slate-300">
                            <p>Started: {{ formatDate(session.started_at) }}</p>
                            <p>Submitted: {{ formatDate(session.submitted_at) }}</p>
                            <p>Activity: {{ formatDate(session.last_activity_at) }}</p>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-2">
                                <Link
                                    v-if="session.resume_manual_url"
                                    :href="session.resume_manual_url"
                                    class="inline-flex items-center gap-1 rounded-md bg-slate-900 px-3 py-1.5 text-xs font-medium text-white hover:bg-slate-700 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-300"
                                >
                                    <ClipboardList class="h-3.5 w-3.5" aria-hidden="true" />
                                    <span>Manual</span>
                                </Link>
                                <Link
                                    v-if="session.resume_mobile_url"
                                    :href="session.resume_mobile_url"
                                    class="inline-flex items-center gap-1 rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium hover:bg-slate-100 dark:border-slate-700 dark:hover:bg-slate-800"
                                >
                                    <Smartphone class="h-3.5 w-3.5" aria-hidden="true" />
                                    <span>Mobile</span>
                                </Link>
                                <Link
                                    v-if="session.discrepancies_url"
                                    :href="session.discrepancies_url"
                                    class="inline-flex items-center gap-1 rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium hover:bg-slate-100 dark:border-slate-700 dark:hover:bg-slate-800"
                                >
                                    <AlertTriangle class="h-3.5 w-3.5" aria-hidden="true" />
                                    <span>Alerts</span>
                                </Link>
                                <span v-if="!session.resume_manual_url && !session.discrepancies_url" class="text-xs text-slate-500">
                                    N/A
                                </span>
                            </div>
                        </td>
                    </tr>

                    <tr v-if="sessions.data.length === 0">
                        <td class="px-4 py-8 text-center text-sm text-slate-500" colspan="9">
                            No audit sessions found.
                        </td>
                    </tr>
                </tbody>
            </table>

            <div v-if="sessions.links?.length" class="border-t border-slate-200 px-4 py-4 dark:border-slate-800">
                <Pagination :links="sessions.links" />
            </div>
        </div>
    </div>
</template>
