<template>
    <div ref="pickerRef" class="relative w-full">
        <button
            type="button"
            class="flex w-full items-center justify-between rounded border border-gray-300 bg-white px-3 py-3 text-left text-sm text-gray-900 transition hover:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
            @click="toggleOpen"
        >
            <div class="min-w-0">
                <p class="truncate font-medium">{{ selectedLabel }}</p>
                <p
                    v-if="selectedSecondary"
                    class="truncate text-xs text-gray-500 dark:text-gray-400"
                >
                    {{ selectedSecondary }}
                </p>
            </div>
            <svg
                class="ml-3 h-4 w-4 shrink-0 text-gray-500 dark:text-gray-400"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                viewBox="0 0 24 24"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="m19 9-7 7-7-7"
                />
            </svg>
        </button>

        <div
            v-if="isOpen"
            class="absolute left-0 right-0 top-full z-50 mt-2 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xl dark:border-gray-700 dark:bg-gray-900"
        >
            <div class="border-b border-gray-200 p-3 dark:border-gray-700">
                <input
                    ref="searchInputRef"
                    v-model="searchQuery"
                    type="text"
                    placeholder="Search customer by name, email, phone or ID"
                    class="w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-950 dark:text-gray-100"
                />
            </div>

            <div class="max-h-80 overflow-y-auto py-2">
                <button
                    type="button"
                    class="flex w-full items-start gap-3 px-3 py-2 text-left transition hover:bg-blue-50 dark:hover:bg-gray-800"
                    :class="!modelValue ? 'bg-blue-50 text-blue-700 dark:bg-gray-800 dark:text-blue-300' : 'text-gray-900 dark:text-gray-100'"
                    @click="selectWalkIn"
                >
                    <span class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full bg-blue-500" />
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium">Walk In Customer</p>
                        <p class="truncate text-xs text-gray-500 dark:text-gray-400">
                            Use this when the buyer does not need a saved customer record.
                        </p>
                    </div>
                </button>

                <div class="px-3 pb-1 pt-3 text-[11px] font-semibold uppercase tracking-[0.2em] text-gray-500 dark:text-gray-400">
                    {{ searchQuery.trim() ? 'Search results' : 'Recent customers' }}
                </div>

                <button
                    v-for="customer in options"
                    :key="customer.id"
                    type="button"
                    class="flex w-full items-start gap-3 px-3 py-2 text-left transition hover:bg-blue-50 dark:hover:bg-gray-800"
                    :class="String(modelValue ?? '') === String(customer.id) ? 'bg-blue-50 text-blue-700 dark:bg-gray-800 dark:text-blue-300' : 'text-gray-900 dark:text-gray-100'"
                    @click="selectCustomer(customer)"
                >
                    <span class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full bg-teal-500" />
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium">{{ customer.name }}</p>
                        <p
                            v-if="customer.email || customer.phone"
                            class="truncate text-xs text-gray-500 dark:text-gray-400"
                        >
                            {{ customer.email || customer.phone }}
                            <span v-if="customer.email && customer.phone"> &middot; {{ customer.phone }}</span>
                        </p>
                    </div>
                </button>

                <p
                    v-if="!loading && options.length === 0"
                    class="px-3 py-6 text-center text-sm text-gray-500 dark:text-gray-400"
                >
                    No customers found. Try another search or add a new customer.
                </p>

                <div
                    v-if="loading"
                    class="px-3 py-4 text-center text-sm text-gray-500 dark:text-gray-400"
                >
                    Loading customers...
                </div>
            </div>

            <div class="border-t border-gray-200 p-2 dark:border-gray-700">
                <button
                    type="button"
                    class="flex w-full items-center gap-3 rounded-xl px-3 py-2 text-left text-sm font-medium text-purple-700 transition hover:bg-purple-50 dark:text-purple-300 dark:hover:bg-gray-800"
                    @click="handleAddNew"
                >
                    <span class="text-lg leading-none">+</span>
                    <span>Add New Customer</span>
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'

const props = defineProps({
    modelValue: {
        type: [String, Number, null],
        default: '',
    },
    options: {
        type: Array,
        default: () => [],
    },
    selectedCustomer: {
        type: Object,
        default: null,
    },
    loading: {
        type: Boolean,
        default: false,
    },
})

const emit = defineEmits(['update:modelValue', 'search', 'add-new'])

const isOpen = ref(false)
const searchQuery = ref('')
const pickerRef = ref(null)
const searchInputRef = ref(null)

const selectedLabel = computed(() => props.selectedCustomer?.name || 'Walk In Customer')
const selectedSecondary = computed(() => {
    if (!props.selectedCustomer) {
        return null
    }

    return props.selectedCustomer.email || props.selectedCustomer.phone || null
})

function toggleOpen() {
    isOpen.value = !isOpen.value
}

function closePicker(resetSearch = true) {
    isOpen.value = false

    if (resetSearch) {
        searchQuery.value = ''
        emit('search', '')
    }
}

function selectWalkIn() {
    emit('update:modelValue', '')
    closePicker()
}

function selectCustomer(customer) {
    emit('update:modelValue', customer.id)
    closePicker()
}

function handleAddNew() {
    emit('add-new')
    closePicker()
}

function handleClickOutside(event) {
    if (pickerRef.value && !pickerRef.value.contains(event.target)) {
        closePicker()
    }
}

watch(searchQuery, (value) => {
    emit('search', value)
})

watch(isOpen, async (value) => {
    if (value) {
        await nextTick()
        searchInputRef.value?.focus()
        emit('search', searchQuery.value)
    }
})

onMounted(() => {
    document.addEventListener('click', handleClickOutside)
})

onBeforeUnmount(() => {
    document.removeEventListener('click', handleClickOutside)
})
</script>
