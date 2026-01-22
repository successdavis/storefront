<script setup>
defineProps({
    terminals: {
        type: Array,
        required: true,
    },
})
</script>

<template>
    <div class="bg-white dark:bg-gray-900 rounded-xl p-6 shadow-sm">
        <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-200 mb-4">
            POS Terminal Status
        </h3>

        <ul class="space-y-3 text-sm">
            <li
                v-for="terminal in terminals"
                :key="terminal.name"
                class="flex items-center justify-between"
            >
        <span class="text-gray-700 dark:text-gray-300">
          {{ terminal.name }}
        </span>

                <span
                    class="font-medium"
                    :class="{
            'text-green-600 dark:text-green-400':
              terminal.status === 'Active',
            'text-red-600 dark:text-red-400':
              terminal.status !== 'Active',
          }"
                >
          {{ terminal.status }}
        </span>
            </li>
        </ul>

        <p
            v-if="terminals.length === 0"
            class="text-sm text-gray-500 dark:text-gray-400"
        >
            No terminals configured.
        </p>
    </div>
</template>
