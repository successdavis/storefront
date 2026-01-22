<script setup>
defineProps({
    title: String,
    value: String,
    subtitle: String,
    progress: [String, Number],
    icon: Object,
})
</script>

<template>
    <div class="bg-white dark:bg-gray-900 rounded-xl p-5 shadow-sm">
        <div class="flex items-center gap-4">
            <div class="p-3 rounded-lg bg-gray-100 dark:bg-gray-800">
                <component :is="icon" class="w-6 h-6 text-gray-700 dark:text-gray-200" />
            </div>

            <div class="flex-1">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ title }}
                </p>
                <p class="text-xl font-semibold text-gray-800 dark:text-gray-100">
                    {{ value }}
                </p>
            </div>
        </div>

        <p
            v-if="subtitle"
            class="mt-2 text-xs text-green-600 dark:text-green-400"
        >
            {{ subtitle }}
        </p>

        <div v-if="progress" class="mt-3 h-1.5 bg-gray-200 dark:bg-gray-800 rounded">
            <div
                class="h-full bg-blue-600 rounded"
                :style="{ width: `${progress}%` }"
            />
        </div>
    </div>
</template>
