<template>
    <div
        class="main-image-display bg-gradient-to-br from-gray-50 to-gray-100 rounded-2xl overflow-hidden flex items-center justify-center relative"
        :class="[
            mobile ? 'h-[400px] p-6' : 'p-8',
            containerClass
        ]"
        :style="!mobile ? 'max-height: 600px;' : ''"
    >
        <!-- Image Counter -->
        <div
            class="absolute bg-secondary text-primary rounded-full font-medium backdrop-blur-sm z-10"
            :class="[
                mobile ? 'top-3 right-3 px-2.5 py-1 text-xs' : 'top-4 right-4 px-3 py-1.5 text-xs'
            ]"
        >
            {{ currentIndex + 1 }} / {{ totalImages }}
        </div>

        <!-- Main Image -->
        <div class="w-full h-full flex items-center justify-center">
            <img
                :src="image"
                :alt="productName"
                class="w-full h-full object-contain transition-opacity duration-300"
                :key="currentIndex"
            />
        </div>

        <!-- Navigation Arrows -->
        <template v-if="showNavigation">
            <button
                v-if="currentIndex > 0"
                @click="$emit('previous')"
                class="absolute top-1/2 -translate-y-1/2 bg-white rounded-full shadow-lg flex items-center justify-center hover:bg-gray-50 transition-all"
                :class="[
                    mobile
                        ? 'left-2 w-8 h-8 active:scale-95'
                        : 'left-4 w-10 h-10 hover:scale-110'
                ]"
            >
                <svg
                    class="text-gray-700"
                    :class="mobile ? 'w-4 h-4' : 'w-5 h-5'"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </button>

            <button
                v-if="currentIndex < totalImages - 1"
                @click="$emit('next')"
                class="absolute top-1/2 -translate-y-1/2 bg-white rounded-full shadow-lg flex items-center justify-center hover:bg-gray-50 transition-all"
                :class="[
                    mobile
                        ? 'right-2 w-8 h-8 active:scale-95'
                        : 'right-4 w-10 h-10 hover:scale-110'
                ]"
            >
                <svg
                    class="text-gray-700"
                    :class="mobile ? 'w-4 h-4' : 'w-5 h-5'"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </button>
        </template>

        <!-- Loading State (Optional) -->
        <div
            v-if="isLoading"
            class="absolute inset-0 bg-gray-100 bg-opacity-80 flex items-center justify-center"
        >
            <div class="w-12 h-12 border-4 border-blue-500 border-t-transparent rounded-full animate-spin"></div>
        </div>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
    image: {
        type: String,
        required: true
    },
    productName: {
        type: String,
        required: true
    },
    currentIndex: {
        type: Number,
        required: true
    },
    totalImages: {
        type: Number,
        required: true
    },
    showNavigation: {
        type: Boolean,
        default: true
    },
    mobile: {
        type: Boolean,
        default: false
    },
    containerClass: {
        type: String,
        default: ''
    }
})

defineEmits(['previous', 'next'])

const isLoading = ref(false)

// Optional: Add loading state when image changes
watch(() => props.image, () => {
    isLoading.value = true
    const img = new Image()
    img.onload = () => {
        isLoading.value = false
    }
    img.src = props.image
})
</script>

<style scoped>
/* Smooth transitions for image changes */
img {
    animation: fadeIn 0.3s ease-in-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}
</style>
