<script setup>
import { ref, watch, onMounted, onBeforeUnmount } from 'vue'
import { ChevronDown } from 'lucide-vue-next'

const props = defineProps({
    modelValue: {
        type: String,
        default: 'today',
    },
})

const emit = defineEmits(['update:modelValue'])

const open = ref(false)
const dropdownRef = ref(null)

const options = [
    { label: 'Today', value: 'today' },
    { label: 'Yesterday', value: 'yesterday' },
    { label: 'Last 7 Days', value: 'last_7_days' },
    { label: 'This Month', value: 'this_month' },
    { label: 'Last Month', value: 'last_month' },
    { label: 'Last Three Month', value: 'last_three_months' },
    { label: 'Last Six Month', value: 'last_six_months' },
    { label: 'This Year', value: 'this_year' },
    { label: 'All Time', value: 'all_time' },
]

const selected = ref(props.modelValue)

watch(selected, value => {
    emit('update:modelValue', value)
    open.value = false
})

const handleClickOutside = (event) => {
    if (dropdownRef.value && !dropdownRef.value.contains(event.target)) {
        open.value = false
    }
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside)
})

onBeforeUnmount(() => {
    document.removeEventListener('click', handleClickOutside)
})
</script>

<template>
    <div ref="dropdownRef" class="relative">
        <button
            @click="open = !open"
            class="flex items-center gap-2 px-3 py-2 rounded-lg bg-white dark:bg-gray-900 shadow-sm text-sm text-gray-700 dark:text-gray-200"
        >
            {{ options.find(o => o.value === selected)?.label }}
            <ChevronDown class="w-4 h-4 text-gray-500" />
        </button>

        <div
            v-if="open"
            class="absolute right-0 mt-2 w-44 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-lg shadow-lg z-50"
        >
            <button
                v-for="option in options"
                :key="option.value"
                @click="selected = option.value"
                class="w-full text-left px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-700 dark:text-gray-200"
            >
                {{ option.label }}
            </button>
        </div>
    </div>
</template>
