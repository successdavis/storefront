<template>
    <div class="quantity-selector mb-6">
        <label class="block text-sm font-medium text-gray-700 mb-2">
            Quantity
        </label>
        <div class="flex items-center gap-3">
            <button
                @click="decrement"
                :disabled="modelValue <= 1"
                class="w-12 h-12 flex items-center justify-center border-2 border-gray-300 rounded-lg hover:bg-gray-50 hover:border-gray-400 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:bg-white disabled:hover:border-gray-300 transition-all"
            >
                <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/>
                </svg>
            </button>

            <div class="flex-1 text-center">
                <span class="text-2xl font-bold text-gray-900">{{ modelValue }}</span>
            </div>

            <button
                @click="increment"
                class="w-12 h-12 flex items-center justify-center border-2 border-gray-300 rounded-lg hover:bg-gray-50 hover:border-gray-400 transition-all"
            >
                <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
            </button>
        </div>
    </div>
</template>

<script setup>
const props = defineProps({
    modelValue: {
        type: Number,
        required: true
    }
})

const emit = defineEmits(['update:modelValue'])

const increment = () => {
    emit('update:modelValue', props.modelValue + 1)
}

const decrement = () => {
    if (props.modelValue > 1) {
        emit('update:modelValue', props.modelValue - 1)
    }
}
</script>
