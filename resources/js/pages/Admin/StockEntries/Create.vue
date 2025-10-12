<template>
    <Head title="Stock In" />

    <div class="px-10 py-8">
        <h1 class="text-2xl font-semibold mb-6">New Stock-In</h1>

        <form @submit.prevent="submit" class="space-y-6 bg-white shadow rounded-lg p-6">
            <!-- Warehouse -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Warehouse</label>
                <select v-model="form.warehouse_id"
                        class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Select warehouse</option>
                    <option v-for="wh in warehouses" :key="wh.id" :value="wh.id">
                        {{ wh.name }}
                    </option>
                </select>
                <InputError :message="form.errors.warehouse_id" />
            </div>

            <!-- Variant Search -->
            <div class="relative">
                <label class="block text-sm font-medium text-gray-700 mb-1">Product Variant</label>
                <input
                    type="text"
                    v-model="variantQuery"
                    @input="searchVariants"
                    placeholder="Search by SKU or Product Name"
                    class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
                <InputError :message="form.errors.variant_id" />

                <!-- Dropdown -->
                <div v-if="showDropdown && variantResults.length"
                     class="absolute z-10 mt-1 w-full bg-white border border-gray-300 rounded-md shadow-lg max-h-56 overflow-auto">
                    <ul>
                        <li v-for="v in variantResults" :key="v.id"
                            @click="selectVariant(v)"
                            class="px-4 py-2 hover:bg-gray-100 cursor-pointer">
                            {{ v.product?.name }} — {{ v.sku }}
                        </li>
                    </ul>
                </div>
                <p v-if="selectedVariant" class="mt-1 text-sm text-green-600">
                    Selected: {{ selectedVariant.product?.name }} — {{ selectedVariant.sku }}
                </p>
            </div>

            <!-- Quantity -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Quantity</label>
                <input type="number" min="1" v-model="form.quantity"
                       class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                <InputError :message="form.errors.quantity" />
            </div>

            <!-- Unit Cost -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Unit Cost</label>
                <input type="number" step="0.01" v-model="form.unit_cost"
                       class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                <InputError :message="form.errors.unit_cost" />
            </div>

            <!-- Effective Date -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Effective Date</label>
                <input type="datetime-local" v-model="form.effective_at"
                       class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                <InputError :message="form.errors.effective_at" />
            </div>

            <!-- Reason -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Reason</label>
                <input type="text" v-model="form.reason"
                       class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                <InputError :message="form.errors.reason" />
            </div>

            <!-- Note -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Note</label>
                <textarea v-model="form.note" rows="3"
                          class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                <InputError :message="form.errors.note" />
            </div>

            <div class="pt-4 flex justify-end space-x-3">
                <Link :href="route('admin.stock-entries.index')"
                      class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">
                    Cancel
                </Link>
                <button type="submit"
                        class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:opacity-50"
                        :disabled="form.processing">
                    Save
                </button>
            </div>
        </form>
    </div>
</template>

<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'
import InputError from '@/Components/InputError.vue'
import axios from 'axios'

const props = defineProps({
    warehouses: Array
})

const form = useForm({
    warehouse_id: '',
    variant_id: '',
    quantity: '',
    unit_cost: '',
    effective_at: '',
    reason: '',
    note: ''
})

const variantQuery = ref('')
const variantResults = ref([])
const selectedVariant = ref(null)
const showDropdown = ref(false)

async function searchVariants() {
    showDropdown.value = true
    if (variantQuery.value.length < 2) {
        variantResults.value = []
        return
    }
    try {
        const { data } = await axios.get(route('admin.variants.search', { q: variantQuery.value }))
        variantResults.value = data
    } catch (e) {
        variantResults.value = []
    }
}

function selectVariant(variant) {
    selectedVariant.value = variant
    form.variant_id = variant.id
    variantQuery.value = `${variant.product?.name} — ${variant.sku}`
    showDropdown.value = false
}

function submit() {
    form.post(route('stock-entries.store'))
}
</script>
