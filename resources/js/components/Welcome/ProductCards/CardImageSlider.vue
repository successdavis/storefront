<script setup>
defineProps({
    images: {
        type: Array,
        required: true
    },
    currentImageIndex: {
        type: Number,
        required: true
    },
    isHovered: {
        type: Boolean,
        required: true
    }
})
</script>

<template>
    <div class="w-full h-full relative">
        <TransitionGroup
            name="slide"
            tag="div"
            class="w-full h-full"
        >
            <img
                v-for="(image, index) in images"
                v-show="index === currentImageIndex"
                :key="index"
                :src="image"
                :alt="`Product image ${index + 1}`"
                class="absolute inset-0 w-full h-full object-cover"
            />
        </TransitionGroup>
    </div>
</template>

<style scoped>
.slide-enter-active,
.slide-leave-active {
    transition: transform 1s ease, opacity 1s ease;
}

.slide-enter-from {
    transform: translateX(10px);
    opacity: 0;
}

.slide-leave-to {
    transform: translateX(-10px);
    opacity: 0;
}
</style>
