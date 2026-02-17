<script setup>
defineProps({
    icon: {
        type: String,
        required: true
    },
    label: {
        type: String,
        required: true
    },
    value: {
        type: String,
        required: true
    }
})
</script>

<template>
    <div class="flex items-center gap-3 bg-secondary text-primary rounded-md px-3  shadow-sm">
        <div class="text-2xl flex-shrink-0">{{ icon }}</div>
        <div class="flex-1 min-w-0">
            <div class="text-xs text-primary truncate text-primary font-medium ">
                {{ value }}
            </div>
            <div class="text-xs italic tracking-wide">
                {{ label }}
            </div>
        </div>
    </div>
</template>
