<template>
    <div
        class="image-thumbnails"
        :class="containerClasses"
    >
        <!-- Thumbnail Container -->
        <div
            ref="thumbnailContainer"
            :class="scrollContainerClasses"
        >
            <button
                v-for="(image, index) in images"
                :key="index"
                @click="handleSelect(index)"
                :class="getThumbnailClasses(index)"
            >
                <img
                    :src="image"
                    :alt="`${productName} thumbnail ${index + 1}`"
                    :class="imageClasses"
                />
                <!-- Active Indicator for Desktop -->
                <div
                    v-if="selectedIndex === index && orientation === 'vertical'"
                    class="absolute inset-0 bg-blue-500/30 bg-opacity-10 pointer-events-none"
                ></div>
            </button>
        </div>

        <!-- Scroll Indicators (Mobile Horizontal Only) -->
        <div
            v-if="orientation === 'horizontal' && showIndicators"
            class="flex justify-center gap-1.5 mt-3"
        >
            <div
                v-for="(image, index) in images"
                :key="`indicator-${index}`"
                :class="[
                    'h-1.5 rounded-full transition-all duration-300 cursor-pointer',
                    selectedIndex === index
                        ? 'bg-blue-500 w-6'
                        : 'bg-gray-300 w-1.5 hover:bg-gray-400'
                ]"
                @click="handleSelect(index)"
            ></div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
    images: {
        type: Array,
        required: true
    },
    productName: {
        type: String,
        required: true
    },
    selectedIndex: {
        type: Number,
        required: true
    },
    orientation: {
        type: String,
        default: 'vertical', // 'vertical' or 'horizontal'
        validator: (value) => ['vertical', 'horizontal'].includes(value)
    },
    showIndicators: {
        type: Boolean,
        default: true
    }
})

const emit = defineEmits(['select'])

const thumbnailContainer = ref(null)

const containerClasses = computed(() => {
    return props.orientation === 'vertical'
        ? 'thumbnail-vertical'
        : 'thumbnail-horizontal relative'
})

const scrollContainerClasses = computed(() => {
    const baseClasses = 'flex gap-3 scrollbar-thin scrollbar-thumb-gray-300'

    if (props.orientation === 'vertical') {
        // Fixed height with scroll for vertical orientation
        return `${baseClasses} flex-col overflow-y-auto overflow-x-hidden pr-2 max-h-[600px]`
    } else {
        return `${baseClasses} overflow-x-auto overflow-y-hidden pb-2 snap-x snap-mandatory scroll-smooth`
    }
})

const getThumbnailClasses = (index) => {
    const baseClasses = 'border-2 rounded-xl overflow-hidden flex-shrink-0 transition-all duration-200 relative'
    const sizeClasses = props.orientation === 'vertical' ? 'w-24 h-24' : 'w-20 h-20 snap-start'
    const activeClasses = props.selectedIndex === index
        ? 'border-blue-500 shadow-md ring-2 ring-blue-200'
        : 'border-gray-200 hover:border-gray-400 hover:shadow-sm'
    const hoverClasses = props.orientation === 'vertical' ? 'group' : ''

    return `${baseClasses} ${sizeClasses} ${activeClasses} ${hoverClasses}`
}

const imageClasses = computed(() => {
    const baseClasses = 'w-full h-full object-cover'
    return props.orientation === 'vertical'
        ? `${baseClasses} transition-transform duration-200 group-hover:scale-105`
        : baseClasses
})

const handleSelect = (index) => {
    emit('select', index)
}

const scrollToThumbnail = (index) => {
    if (thumbnailContainer.value) {
        const thumbnail = thumbnailContainer.value.children[index]
        if (thumbnail) {
            thumbnail.scrollIntoView({
                behavior: 'smooth',
                block: 'nearest',
                inline: 'center'
            })
        }
    }
}

// Expose method to parent
defineExpose({
    scrollToThumbnail
})
</script>

<style scoped>
/* Custom Scrollbar Styling */
.scrollbar-thin::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}

.scrollbar-thin::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}

.scrollbar-thin::-webkit-scrollbar-thumb {
    background: #d1d5db;
    border-radius: 10px;
}

.scrollbar-thin::-webkit-scrollbar-thumb:hover {
    background: #9ca3af;
}

/* Firefox scrollbar */
* {
    scrollbar-width: thin;
    scrollbar-color: #d1d5db #f1f1f1;
}

/* Vertical Thumbnail Container - Fixed Height, Scrollable Content */
.thumbnail-vertical {
    height: 600px; /* Match main image display height */
    max-height: 600px;
    overflow: hidden;
}

.thumbnail-vertical > div {
    height: 100%;
}

/* Horizontal Thumbnail Container */
.thumbnail-horizontal {
    max-width: 100%;
    overflow: hidden;
}

/* Prevent page overflow */
.overflow-x-auto {
    overflow-x: auto;
    overflow-y: hidden;
    max-width: 100%;
    -webkit-overflow-scrolling: touch;
}

.overflow-y-auto {
    overflow-y: auto;
    overflow-x: hidden;
    -webkit-overflow-scrolling: touch;
}

/* Hide scrollbar on mobile for cleaner look */
@media (max-width: 640px) {
    .overflow-x-auto::-webkit-scrollbar {
        display: none;
    }

    .overflow-x-auto {
        -ms-overflow-style: none;
        scrollbar-width: none;
    }
}

/* Show scrollbar on desktop for vertical scrolling */
@media (min-width: 1024px) {
    .overflow-y-auto::-webkit-scrollbar {
        width: 6px;
    }

    .overflow-y-auto::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 10px;
    }

    .overflow-y-auto::-webkit-scrollbar-thumb {
        background: #d1d5db;
        border-radius: 10px;
    }

    .overflow-y-auto::-webkit-scrollbar-thumb:hover {
        background: #9ca3af;
    }
}
</style>
