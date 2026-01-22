<script setup>
defineProps({
    title: String,
    value: [String, Number],
    icon: Object,
})
</script>

<template>
    <div class="bg-white rounded-xl p-5 shadow-sm flex items-center gap-4">
        <div class="p-3 rounded-lg bg-blue-50 text-blue-600">
            <component :is="icon" class="w-6 h-6" />
        </div>
        <div>
            <p class="text-sm text-gray-500">{{ title }}</p>
            <p class="text-xl font-semibold text-gray-800">
                {{ value }}
            </p>
        </div>
    </div>
</template>
