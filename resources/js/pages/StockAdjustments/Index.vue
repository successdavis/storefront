<script setup>
import Pagination from '@/components/Pagination.vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'

const props = defineProps({
    adjustments: Object,
    filters: Object,
    status_options: Array,
    bulk_actions: Array,
})

const selectedIds = ref([])
const rowActionState = ref({})
const filters = ref({
    status: props.filters?.status || '',
})
const bulkForm = useForm({
    adjustment_ids: [],
    action: '',
    approval_note: '',
})

watch(
    () => props.adjustments.data.map((item) => item.id),
    (visibleIds) => {
        selectedIds.value = selectedIds.value.filter((id) => visibleIds.includes(id))
    },
)

watch(
    () => filters.value.status,
    (status) => {
        router.get(route('admin.stock-adjustments.index'), {
            status,
        }, {
            preserveState: true,
            replace: true,
        })
    },
)

const reviewableIds = computed(() => (
    props.adjustments.data
        .filter((item) => item.can_review)
        .map((item) => item.id)
))

const allVisibleSelected = computed(() => (
    reviewableIds.value.length > 0
    && reviewableIds.value.every((id) => selectedIds.value.includes(id))
))

function toggleAll() {
    selectedIds.value = allVisibleSelected.value
        ? []
        : [...reviewableIds.value]
}

function statusBadgeClass(status) {
    return {
        'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-200': status === 'pending',
        'bg-green-100 text-green-800 dark:bg-green-950/40 dark:text-green-200': status === 'approved',
        'bg-red-100 text-red-800 dark:bg-red-950/40 dark:text-red-200': status === 'rejected',
    }
}

function beginRowAction(id) {
    rowActionState.value = {
        ...rowActionState.value,
        [id]: true,
    }
}

function endRowAction(id) {
    rowActionState.value = {
        ...rowActionState.value,
        [id]: false,
    }
}

function reviewAdjustment(id, action) {
    if (rowActionState.value[id]) {
        return
    }

    beginRowAction(id)

    router.post(route(`admin.stock-adjustments.${action}`, id), {}, {
        preserveScroll: true,
        onFinish: () => endRowAction(id),
    })
}

function runBulkAction() {
    if (!selectedIds.value.length || !bulkForm.action) {
        return
    }

    bulkForm.adjustment_ids = [...selectedIds.value]
    bulkForm.post(route('admin.stock-adjustments.bulk-review'), {
        preserveScroll: true,
        onSuccess: () => {
            selectedIds.value = []
            bulkForm.reset('action', 'approval_note')
        },
    })
}

function iconButtonClass(tone = 'neutral') {
    const palette = {
        neutral: 'border-slate-300 text-slate-700 hover:border-slate-500 dark:border-slate-600 dark:text-slate-200 dark:hover:border-slate-400',
        approve: 'border-emerald-300 text-emerald-700 hover:border-emerald-500 dark:border-emerald-700 dark:text-emerald-300 dark:hover:border-emerald-500',
        reject: 'border-rose-300 text-rose-700 hover:border-rose-500 dark:border-rose-700 dark:text-rose-300 dark:hover:border-rose-500',
    }

    return [
        'inline-flex h-9 w-9 items-center justify-center rounded-xl border transition disabled:opacity-50',
        palette[tone] || palette.neutral,
    ]
}
</script>

<template>
    <Head title="Stock Adjustments" />

    <div class="space-y-6 px-5 py-4">
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold text-slate-900 dark:text-slate-100">Stock Adjustments</h1>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                        Review pending inventory corrections and approve or reject them without leaving the queue.
                    </p>
                </div>

                <Link
                    :href="route('admin.stock-adjustments.create')"
                    class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-300"
                >
                    New Adjustment
                </Link>
            </div>

        </section>

        <section class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="flex flex-wrap items-center gap-3">
                <select
                    v-model="filters.status"
                    class="h-10 rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100"
                >
                    <option
                        v-for="option in status_options"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </option>
                </select>
                <select
                    v-model="bulkForm.action"
                    class="h-10 rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100"
                >
                    <option value="">Bulk actions</option>
                    <option
                        v-for="action in bulk_actions"
                        :key="action.value"
                        :value="action.value"
                    >
                        {{ action.label }}
                    </option>
                </select>
                <input
                    v-model="bulkForm.approval_note"
                    type="text"
                    placeholder="Optional approval note"
                    class="h-10 min-w-[220px] flex-1 rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 placeholder:text-slate-400 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100 dark:placeholder:text-slate-500"
                >
                <button
                    type="button"
                    class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700 disabled:opacity-40 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-300"
                    :disabled="!selectedIds.length || !bulkForm.action || bulkForm.processing"
                    @click="runBulkAction"
                >
                    Apply to {{ selectedIds.length }} selected
                </button>
            </div>
        </section>

        <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead class="bg-slate-50 dark:bg-slate-950">
                        <tr class="text-left text-xs uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">
                            <th class="px-5 py-4">
                                <input type="checkbox" :checked="allVisibleSelected" @change="toggleAll">
                            </th>
                            <th class="px-5 py-4">Product Variant</th>
                            <th class="px-5 py-4 text-right">Previous</th>
                            <th class="px-5 py-4 text-right">Adjusted</th>
                            <th class="px-5 py-4 text-right">New Qty</th>
                            <th class="px-5 py-4">Reason</th>
                            <th class="px-5 py-4">Type</th>
                            <th class="px-5 py-4">Status</th>
                            <th class="px-5 py-4">Adjusted At</th>
                            <th class="px-5 py-4 text-right">Actions</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100 bg-white dark:divide-slate-800 dark:bg-slate-900">
                        <tr v-for="item in adjustments.data" :key="item.id" class="align-top">
                            <td class="px-5 py-4">
                                <input
                                    v-model="selectedIds"
                                    type="checkbox"
                                    :value="item.id"
                                    :disabled="!item.can_review"
                                >
                            </td>
                            <td class="px-5 py-4">
                                <p class="font-semibold text-slate-900 dark:text-slate-100">{{ item.variant_label }}</p>
                                <p v-if="item.variant_sku" class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ item.variant_sku }}</p>
                            </td>
                            <td class="px-5 py-4 text-right text-slate-700 dark:text-slate-300">
                                {{ item.previous_quantity }}
                            </td>
                            <td
                                class="px-5 py-4 text-right"
                                :class="item.adjusted_quantity > 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'"
                            >
                                {{ item.adjusted_quantity }}
                            </td>
                            <td class="px-5 py-4 text-right font-semibold text-slate-800 dark:text-slate-200">
                                {{ item.new_quantity }}
                            </td>
                            <td class="px-5 py-4 text-slate-700 dark:text-slate-300">{{ item.reason }}</td>
                            <td class="px-5 py-4 text-slate-700 dark:text-slate-300">{{ item.adjustment_type_label }}</td>
                            <td class="px-5 py-4">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                                    :class="statusBadgeClass(item.status)"
                                >
                                    {{ item.status }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-slate-700 dark:text-slate-300">{{ item.adjusted_at || '-' }}</td>
                            <td class="px-5 py-4">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <button
                                        v-if="item.can_review"
                                        type="button"
                                        :class="iconButtonClass('approve')"
                                        :disabled="rowActionState[item.id]"
                                        title="Approve adjustment"
                                        aria-label="Approve adjustment"
                                        @click="reviewAdjustment(item.id, 'approve')"
                                    >
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <path d="M20 6 9 17l-5-5" />
                                        </svg>
                                    </button>
                                    <button
                                        v-if="item.can_review"
                                        type="button"
                                        :class="iconButtonClass('reject')"
                                        :disabled="rowActionState[item.id]"
                                        title="Reject adjustment"
                                        aria-label="Reject adjustment"
                                        @click="reviewAdjustment(item.id, 'reject')"
                                    >
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <path d="m18 6-12 12" />
                                            <path d="m6 6 12 12" />
                                        </svg>
                                    </button>
                                    <Link
                                        :href="route('admin.stock-adjustments.show', item.id)"
                                        :class="iconButtonClass('neutral')"
                                        title="View adjustment details"
                                        aria-label="View adjustment details"
                                    >
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                            <path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0" />
                                            <circle cx="12" cy="12" r="3" />
                                        </svg>
                                    </Link>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="!adjustments.data.length">
                            <td colspan="10" class="px-5 py-14 text-center text-sm text-slate-500 dark:text-slate-400">
                                No stock adjustments recorded yet.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <Pagination :links="adjustments.links" />
    </div>
</template>
