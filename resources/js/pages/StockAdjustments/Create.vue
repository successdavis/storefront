<template>
    <div class="p-6 max-w-3xl mx-auto">
        <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-100 mb-6">
            New Stock Adjustment
        </h1>

        <form
            @submit.prevent="submit"
            class="bg-white dark:bg-gray-900 shadow rounded-lg p-6 space-y-5"
        >
            <!-- Product Variant -->
            <div>
                <label
                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                >
                    Product Variant
                </label>
                <select
                    v-model="form.variant_id"
                    class="w-full px-2 border-gray-300 py-2 dark:border-gray-700 bg-white dark:bg-gray-800
                 text-gray-900 dark:text-white rounded-md focus:ring-blue-500 focus:border-blue-500"
                >
                    <option value="">Select Variant</option>
                    <option  v-for="variant in variants" :key="variant.id" :value="variant.id">
                        {{ variant.label }}
                    </option>
                </select>
                <p v-if="form.errors.variant_id" class="text-red-500 text-sm mt-1">
                    {{ form.errors.variant_id }}
                </p>
            </div>

            <!-- Quantities -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label
                        class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                    >
                        Previous Quantity
                    </label>
                    <input disabled
                        type="number"
                        v-model="form.previous_quantity"
                        class="w-full py-2 px-2 border-gray-300 disabled dark:border-gray-700 bg-white dark:bg-gray-800
                   text-gray-900 dark:text-gray-100 rounded-md focus:ring-blue-500 focus:border-blue-500"
                    />
                </div>

                <div>
                    <label
                        class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                        title="Enter +5 if you found extra items or -5 if items are missing"
                    >
                        Stock Adjustment (+/–)
                    </label>
                    <input
                        type="number"
                        placeholder="change in qty, e.g., +5 or -10"
                        v-model="form.adjusted_quantity"
                        class="w-full py-2 px-2 border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800
                   text-gray-900 dark:text-gray-100 rounded-md focus:ring-blue-500 focus:border-blue-500"
                    />
                </div>
            </div>

            <!-- Reason -->
            <div>
                <label
                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                >
                    Reason
                </label>
                <select
                    v-model="form.reason"
                    class="w-full py-2 px-2 border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800
                 text-gray-900 dark:text-gray-100 rounded-md focus:ring-blue-500 focus:border-blue-500"
                >
                    <option value="manual_correction">Manual Correction</option>
                    <option value="damage">Damage</option>
                    <option value="loss">Loss</option>
                    <option value="count_discrepancy">Count Discrepancy</option>
                    <option value="other">Other</option>
                </select>
            </div>

            <!-- Note -->
            <div>
                <label
                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                >
                    Note (Optional)
                </label>
                <textarea
                    v-model="form.note"
                    rows="3"
                    class="w-full px-2 border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800
                 text-gray-900 dark:text-gray-100 rounded-md focus:ring-blue-500 focus:border-blue-500"
                ></textarea>
            </div>

            <!-- Buttons -->
            <div class="flex justify-end space-x-3">
                <Link
                    href="/admin/stock-adjustments"
                    class="px-4 py-2 rounded-lg border text-gray-700 dark:text-gray-200
                 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-800 transition"
                >
                    Cancel
                </Link>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700
                 disabled:opacity-60 transition"
                >
                    Submit for Approval
                </button>
            </div>
        </form>
    </div>
</template>
<script setup>
import { useForm, Link } from '@inertiajs/vue3'
import { watch } from 'vue'

const props = defineProps({
    variants: Array,
})

const form = useForm({
    warehouse_id: null,
    variant_id: '',
    previous_quantity: '',
    adjusted_quantity: '',
    reason: 'manual_correction',
    employee_id: null,
    note: '',
    reference: '',
})

// Watch for variant change and auto-fill previous_quantity
watch(() => form.variant_id, (newId) => {
    const selected = props.variants.find(v => v.id === newId)
    console.log(selected)
    form.previous_quantity = selected ? selected.current_quantity : ''
})

const submit = () => {
    form.post('/admin/stock-adjustments', { preserveScroll: true })
}
</script>
