<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from "vue";

// Props
const props = defineProps({
    options: {
        type: Array,
        required: true,
    },
    modelValue: {
        type: Array,
        default: () => [],
    },
    placeholder: {
        type: String,
        default: "Select options",
    },
});

// Emits
const emit = defineEmits(["update:modelValue"]);

// Local state
const isOpen = ref(false);
const selected = ref([...props.modelValue]);
const dropdownRef = ref(null);

// Watch for external modelValue changes
watch(
    () => props.modelValue,
    (newVal) => {
        selected.value = [...newVal];
    }
);

// Toggle dropdown visibility
const toggleDropdown = () => {
    isOpen.value = !isOpen.value;
};

// Toggle selection
const toggleOption = (option) => {
    if (selected.value.includes(option)) {
        selected.value = selected.value.filter((o) => o !== option);
    } else {
        selected.value.push(option);
    }
    emit("update:modelValue", selected.value);
};

// Display computed text
const displayText = computed(() => {
    if (selected.value.length === 0) return props.placeholder;
    if (selected.value.length === 1) return selected.value[0];
    return `${selected.value.length} items selected`;
});

// Close when clicking outside
const handleClickOutside = (event) => {
    if (dropdownRef.value && !dropdownRef.value.contains(event.target)) {
        isOpen.value = false;
    }
};

onMounted(() => {
    document.addEventListener("click", handleClickOutside);
});

onBeforeUnmount(() => {
    document.removeEventListener("click", handleClickOutside);
});
</script>

<template>
    <div ref="dropdownRef" class="relative w-full">
        <!-- Dropdown button -->
        <div
            @click="toggleDropdown"
            class="border border-blue-400 rounded-md px-3 py-2 flex justify-between items-center cursor-pointer
                   bg-white text-gray-700 hover:bg-gray-50
                   dark:bg-gray-800 dark:text-gray-200 dark:border-blue-500 dark:hover:bg-gray-700"
        >
            <span>{{ displayText }}</span>
            <svg
                class="w-4 h-4 text-gray-600 dark:text-gray-300"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                viewBox="0 0 24 24"
            >
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
            </svg>
        </div>

        <!-- Dropdown menu -->
        <div
            v-if="isOpen"
            class="absolute mt-1 w-full bg-white border border-gray-200 rounded-md shadow-lg z-10
                   dark:bg-gray-800 dark:border-gray-700 dark:shadow-gray-900/50"
        >
            <div
                v-for="option in options"
                :key="option"
                @click="toggleOption(option)"
                class="px-3 py-2 flex justify-between items-center cursor-pointer
                       hover:bg-gray-100 dark:hover:bg-gray-700"
            >
                <span class="text-gray-800 dark:text-gray-200">{{ option }}</span>
                <span v-if="selected.includes(option)" class="text-blue-500">✔</span>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Optional: Smooth transition for dark mode */
div, span, svg {
    transition: background-color 0.2s ease, color 0.2s ease, border-color 0.2s ease;
}
</style>
