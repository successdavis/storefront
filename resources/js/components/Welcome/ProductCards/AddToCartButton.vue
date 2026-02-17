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
    <Transition name="slide-up">
        <button
            v-if="isHovered"
            class="absolute bottom-0 h-10 w-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 bg-primary text-white border-0 px-6 py-3 rounded-lg text-sm font-semibold cursor-pointer flex items-center gap-2 z-10 shadow-lg shadow-blue-500/30 hover:bg-primary/90 hover:scale-105 active:scale-98 transition-all duration-200 whitespace-nowrap"
            @click.stop="handleClick"
        >
            <svg
                xmlns="http://www.w3.org/2000/svg"
                width="18"
                height="18"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
            >
                <circle cx="9" cy="21" r="1"></circle>
                <circle cx="20" cy="21" r="1"></circle>
                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
            </svg>
            Add to Cart
        </button>
    </Transition>
</template>

<style scoped>
.slide-up-enter-active,
.slide-up-leave-active {
    transition: opacity 0.3s ease, transform 0.3s ease;
}

.slide-up-enter-from,
.slide-up-leave-to {
    opacity: 0;
    transform: translate(-50%, calc(-50% + 10px));
}
</style>
