<template>
    <div class="p-6 bg-gray-50 dark:bg-gray-900 min-h-screen transition-colors duration-300">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-100">
                Stock Adjustment Details
            </h1>
            <Link
                href="/admin/stock-adjustments"
                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 dark:hover:bg-blue-500 transition"
            >
                Back
            </Link>
        </div>

        <div class="bg-white dark:bg-gray-800 shadow-md rounded-xl p-6 border border-gray-200 dark:border-gray-700 space-y-6">
            <div class="flex items-center gap-3">
                <p class="text-sm text-gray-500 dark:text-gray-400">Status:</p>
                <span
                    class="inline-flex rounded-full px-3 py-1 text-xs font-semibold"
                    :class="{
                        'bg-amber-100 text-amber-800': adjustment.status === 'pending',
                        'bg-green-100 text-green-800': adjustment.status === 'approved',
                        'bg-red-100 text-red-800': adjustment.status === 'rejected',
                    }"
                >
                    {{ adjustment.status }}
                </span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Product Variant</p>
                    <p class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ adjustment.product_variant }}</p>
                </div>

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
                    <p class="text-sm text-gray-500 dark:text-gray-400">Adjustment Type</p>
                    <p class="text-lg text-gray-900 dark:text-gray-100">{{ adjustment.adjustment_type_label }}</p>
                </div>

                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Submitted By</p>
                    <p class="text-lg text-gray-900 dark:text-gray-100">{{ adjustment.employee }}</p>
                </div>

                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Submitted At</p>
                    <p class="text-lg text-gray-900 dark:text-gray-100">{{ adjustment.created_at }}</p>
                </div>

                <div v-if="adjustment.approved_by">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Approved By</p>
                    <p class="text-lg text-gray-900 dark:text-gray-100">{{ adjustment.approved_by }} ({{ adjustment.approved_at }})</p>
                </div>

                <div v-if="adjustment.rejected_by">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Rejected By</p>
                    <p class="text-lg text-gray-900 dark:text-gray-100">{{ adjustment.rejected_by }} ({{ adjustment.rejected_at }})</p>
                </div>

                <div class="sm:col-span-2">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Submission Note</p>
                    <p class="text-gray-900 dark:text-gray-100">{{ adjustment.note || 'N/A' }}</p>
                </div>

                <div class="sm:col-span-2">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Approval Note</p>
                    <p class="text-gray-900 dark:text-gray-100">{{ adjustment.approval_note || 'N/A' }}</p>
                </div>
            </div>

            <div v-if="adjustment.can_approve" class="border-t border-gray-200 dark:border-gray-700 pt-5 space-y-3">
                <div class="space-y-2">
                    <label class="block text-sm text-gray-700 dark:text-gray-300">
                        Adjustment Type
                    </label>
                    <select
                        v-model="reviewAdjustmentType"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 dark:border-gray-700 dark:bg-gray-900"
                    >
                        <option
                            v-for="option in adjustment_type_options"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        {{ selectedAdjustmentTypeDescription }}
                    </p>
                </div>

                <label class="block text-sm text-gray-700 dark:text-gray-300">
                    Approval Note (optional)
                </label>
                <textarea
                    v-model="approvalNote"
                    rows="3"
                    class="w-full rounded-md border border-gray-300 px-3 py-2 dark:border-gray-700 dark:bg-gray-900"
                ></textarea>

                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        :disabled="processing"
                        @click="approve"
                        class="px-4 py-2 rounded-lg bg-green-600 text-white hover:bg-green-700 disabled:opacity-60"
                    >
                        Approve and Apply
                    </button>
                    <button
                        type="button"
                        :disabled="processing"
                        @click="reject"
                        class="px-4 py-2 rounded-lg bg-red-600 text-white hover:bg-red-700 disabled:opacity-60"
                    >
                        Reject
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

const props = defineProps({
    adjustment: Object,
    adjustment_type_options: Array,
})

const approvalNote = ref(props.adjustment.approval_note || '')
const reviewAdjustmentType = ref(props.adjustment.adjustment_type || 'correction')
const processing = ref(false)
const selectedAdjustmentTypeDescription = computed(() => (
    props.adjustment_type_options.find(option => option.value === reviewAdjustmentType.value)?.description
    || ''
))

function approve() {
    if (processing.value) return
    processing.value = true
    router.post(
        `/admin/stock-adjustments/${props.adjustment.id}/approve`,
        {
            approval_note: approvalNote.value || null,
            adjustment_type: reviewAdjustmentType.value,
        },
        {
            preserveScroll: true,
            onFinish: () => (processing.value = false),
        },
    )
}

function reject() {
    if (processing.value) return
    processing.value = true
    router.post(
        `/admin/stock-adjustments/${props.adjustment.id}/reject`,
        { approval_note: approvalNote.value || null },
        {
            preserveScroll: true,
            onFinish: () => (processing.value = false),
        },
    )
}
</script>
