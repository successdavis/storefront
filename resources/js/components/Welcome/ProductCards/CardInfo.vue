<script setup>
import { computed } from 'vue'

const props = defineProps({
    title: {
        type: String,
        required: true
    },
    price: {
        type: [String, Number],
        required: true
    }
})

const formattedPrice = computed(() => {
    if (typeof props.price === 'number') {
        return `$${props.price.toFixed(2)}`
    }
    return props.price
})
</script>

<template>
    <div class="p-4 bg-white flex flex-col md:flex-row justify-between items-start md:items-center ">
        <h3 class="text-base font-semibold text-gray-800 mb-2 leading-tight line-clamp-2">
            {{ title }}
        </h3>
        <p class="text-lg font-bold text-primary m-0">
            {{ formattedPrice }}
        </p>
    </div>
</template>
