<script setup>
defineProps({
    isHovered: {
        type: Boolean,
        required: true
    }
})

const emit = defineEmits(['click'])

const handleClick = () => {
    emit('click')
}
</script>

<template>
    <Transition name="fade">
        <button
            v-if="isHovered"
            class="absolute top-3 right-3 w-10 h-10 rounded-full bg-white border-0 flex items-center justify-center cursor-pointer z-10 shadow-lg hover:bg-gray-50 hover:scale-110 active:scale-95 transition-all duration-200"
            @click.stop="handleClick"
            aria-label="View product"
        >
            <svg
                xmlns="http://www.w3.org/2000/svg"
                width="20"
                height="20"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
                class="text-gray-800"
            >
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                <circle cx="12" cy="12" r="3"></circle>
            </svg>
        </button>
    </Transition>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
