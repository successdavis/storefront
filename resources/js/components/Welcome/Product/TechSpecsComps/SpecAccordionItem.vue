<template>
    <div
        class="spec-accordion-item border border-gray-200 rounded-xl overflow-hidden transition-all duration-300"
        :class="{ 'ring-2 ring-blue-500 border-blue-500': isOpen }"
    >
        <!-- Header/Toggle Button -->
        <button
            @click="$emit('toggle')"
            class="w-full flex items-center justify-between p-5 lg:p-6 bg-white hover:bg-gray-50 transition-colors"
            :class="{ 'bg-blue-50 hover:bg-blue-50': isOpen }"
        >
            <div class="flex items-center gap-3">
                <div
                    class="w-10 h-10 rounded-full flex items-center justify-center transition-all"
                    :class="isOpen ? 'bg-blue-500' : 'bg-gray-200'"
                >
                    <svg
                        class="w-5 h-5 transition-colors"
                        :class="isOpen ? 'text-white' : 'text-gray-600'"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            v-if="getTitleIcon(title)"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            :d="getTitleIcon(title)"
                        />
                    </svg>
                </div>
                <h3
                    class="text-lg lg:text-xl font-bold text-left transition-colors"
                    :class="isOpen ? 'text-blue-900' : 'text-gray-900'"
                >
                    {{ title }}
                </h3>
            </div>

            <!-- Toggle Icon -->
            <div
                class="flex items-center justify-center w-8 h-8 rounded-full transition-all"
                :class="isOpen ? 'bg-blue-100' : 'bg-gray-100'"
            >
                <svg
                    class="w-5 h-5 transition-transform duration-300"
                    :class="[
                        isOpen ? 'rotate-180 text-blue-600' : 'text-gray-600'
                    ]"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </div>
        </button>

        <!-- Content (Expandable) -->
        <transition
            enter-active-class="transition-all duration-300 ease-out"
            leave-active-class="transition-all duration-300 ease-in"
            enter-from-class="max-h-0 opacity-0"
            enter-to-class="max-h-[2000px] opacity-100"
            leave-from-class="max-h-[2000px] opacity-100"
            leave-to-class="max-h-0 opacity-0"
        >
            <div v-show="isOpen" class="overflow-hidden">
                <div class="p-5 lg:p-6 pt-0 bg-white">
                    <div class="space-y-3">
                        <div
                            v-for="(item, index) in items"
                            :key="index"
                            class="flex items-start justify-between border-b border-gray-100 last:border-0"
                        >
                            <span class="text-sm font-medium text-gray-600 flex-1">
                                {{ item.label }}
                            </span>
                            <span class="text-sm font-semibold text-gray-900 text-right ml-4">
                                {{ item.value }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </transition>
    </div>
</template>

<script setup>
defineProps({
    title: {
        type: String,
        required: true
    },
    items: {
        type: Array,
        required: true
    },
    isOpen: {
        type: Boolean,
        default: false
    }
})

defineEmits(['toggle'])

const getTitleIcon = (title) => {
    const icons = {
        'Basic Details': 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
        'Display Properties': 'M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
        'Special Features': 'M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z'
    }
    return icons[title] || 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'
}
</script>

<style scoped>
/* Smooth transitions for accordion */
.spec-accordion-item {
    transition: all 0.3s ease;
}
</style>
