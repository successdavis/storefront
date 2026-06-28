<script setup>
import { computed, ref, watch } from 'vue'
import { ChevronLeft, ChevronRight, Search, X, ZoomIn, ZoomOut } from 'lucide-vue-next'

const props = defineProps({
    images: {
        type: Array,
        default: () => [],
    },
    fallbackAlt: {
        type: String,
        default: 'Product image',
    },
})

const activeImageId = ref(null)
const isZoomOpen = ref(false)
const zoomPercent = ref(100)
const touchStartX = ref(null)
const touchStartY = ref(null)
const slideDirection = ref('next')

const sortedImages = computed(() => {
    return [...(props.images || [])].sort((a, b) => Number(a.sort_order || 0) - Number(b.sort_order || 0))
})

const activeImage = computed(() => {
    if (!sortedImages.value.length) {
        return null
    }

    const byId = sortedImages.value.find((image) => image.id === activeImageId.value)
    return byId || sortedImages.value[0]
})

const activeImageIndex = computed(() => {
    if (!activeImage.value) {
        return -1
    }

    return sortedImages.value.findIndex((image) => image.id === activeImage.value.id)
})

const hasMultipleImages = computed(() => sortedImages.value.length > 1)
const transitionClasses = computed(() => {
    if (slideDirection.value === 'previous') {
        return {
            enterFrom: '-translate-x-full opacity-60',
            leaveTo: 'translate-x-full opacity-60',
        }
    }

    return {
        enterFrom: 'translate-x-full opacity-60',
        leaveTo: '-translate-x-full opacity-60',
    }
})

function imageVariantUrl(image, variant, fallback = null) {
    return image?.variants?.[variant]?.url || fallback || image?.url || null
}

function galleryImageUrl(image) {
    return imageVariantUrl(image, 'gallery')
}

function thumbnailImageUrl(image) {
    return imageVariantUrl(image, 'thumbnail')
}

function zoomImageUrl(image) {
    return imageVariantUrl(image, 'original', imageVariantUrl(image, 'gallery'))
}

function selectImage(index, direction = null) {
    const image = sortedImages.value[index]
    if (!image) {
        return
    }

    if (image.id === activeImageId.value) {
        return
    }

    if (direction) {
        slideDirection.value = direction
    } else {
        const currentIndex = activeImageIndex.value >= 0 ? activeImageIndex.value : 0
        slideDirection.value = index < currentIndex ? 'previous' : 'next'
    }

    activeImageId.value = image.id
}

function showPreviousImage() {
    if (!hasMultipleImages.value) {
        return
    }

    const currentIndex = activeImageIndex.value >= 0 ? activeImageIndex.value : 0
    const nextIndex = currentIndex <= 0 ? sortedImages.value.length - 1 : currentIndex - 1
    selectImage(nextIndex, 'previous')
}

function showNextImage() {
    if (!hasMultipleImages.value) {
        return
    }

    const currentIndex = activeImageIndex.value >= 0 ? activeImageIndex.value : 0
    const nextIndex = currentIndex >= sortedImages.value.length - 1 ? 0 : currentIndex + 1
    selectImage(nextIndex, 'next')
}

function openZoom() {
    if (!zoomImageUrl(activeImage.value)) {
        return
    }

    zoomPercent.value = 100
    isZoomOpen.value = true
}

function closeZoom() {
    isZoomOpen.value = false
    zoomPercent.value = 100
}

function zoomIn() {
    zoomPercent.value = Math.min(zoomPercent.value + 25, 300)
}

function zoomOut() {
    zoomPercent.value = Math.max(zoomPercent.value - 25, 100)
}

function resetZoom() {
    zoomPercent.value = 100
}

function handleTouchStart(event) {
    const touch = event.changedTouches?.[0]
    if (!touch) {
        return
    }

    touchStartX.value = touch.clientX
    touchStartY.value = touch.clientY
}

function handleTouchEnd(event) {
    const touch = event.changedTouches?.[0]
    if (!touch || touchStartX.value === null || touchStartY.value === null) {
        touchStartX.value = null
        touchStartY.value = null
        return
    }

    const deltaX = touch.clientX - touchStartX.value
    const deltaY = touch.clientY - touchStartY.value

    touchStartX.value = null
    touchStartY.value = null

    if (Math.abs(deltaX) < 40 || Math.abs(deltaX) < Math.abs(deltaY)) {
        return
    }

    if (deltaX > 0) {
        showPreviousImage()
        return
    }

    showNextImage()
}

watch(
    () => sortedImages.value,
    (images) => {
        if (!images.length) {
            activeImageId.value = null
            isZoomOpen.value = false
            return
        }

        if (!images.some((image) => image.id === activeImageId.value)) {
            activeImageId.value = images[0].id
        }
    },
    { immediate: true },
)
</script>

<template>
    <div class="space-y-3">
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-950">
            <div
                class="relative aspect-square bg-slate-100 dark:bg-slate-900"
                @touchstart.passive="handleTouchStart"
                @touchend.passive="handleTouchEnd"
            >
                <template v-if="galleryImageUrl(activeImage)">
                    <Transition
                        :enter-active-class="'absolute inset-0 transition duration-300 ease-out'"
                        :enter-from-class="transitionClasses.enterFrom"
                        enter-to-class="translate-x-0 opacity-100"
                        :leave-active-class="'absolute inset-0 transition duration-300 ease-out'"
                        leave-from-class="translate-x-0 opacity-100"
                        :leave-to-class="transitionClasses.leaveTo"
                    >
                        <img
                            :key="activeImage.id"
                            :src="galleryImageUrl(activeImage)"
                            :srcset="activeImage.srcset || undefined"
                            sizes="(min-width: 1024px) 50vw, 100vw"
                            :alt="activeImage.alt || fallbackAlt"
                            class="absolute inset-0 h-full w-full cursor-zoom-in object-cover select-none"
                            decoding="async"
                            @click="openZoom"
                        >
                    </Transition>
                </template>
                <div v-else class="flex h-full items-center justify-center text-sm text-slate-500 dark:text-slate-400">
                    No image available
                </div>

                <div v-if="galleryImageUrl(activeImage)" class="pointer-events-none absolute inset-x-0 bottom-0 flex justify-between bg-gradient-to-t from-slate-950/60 via-slate-950/10 to-transparent px-4 pb-4 pt-10">
                    <span class="rounded-full bg-white/90 px-2.5 py-1 text-[11px] font-semibold text-slate-700 shadow-sm dark:bg-slate-900/90 dark:text-slate-200">
                        Swipe images
                    </span>
                    <button
                        type="button"
                        class="pointer-events-auto inline-flex items-center gap-1 rounded-full bg-white/90 px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm transition hover:bg-white dark:bg-slate-900/90 dark:text-slate-200 dark:hover:bg-slate-800"
                        @click="openZoom"
                    >
                        <Search class="size-3.5" />
                        Zoom
                    </button>
                </div>

                <button
                    v-if="hasMultipleImages"
                    type="button"
                    class="absolute left-3 top-1/2 inline-flex size-9 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-slate-700 shadow-md transition hover:bg-white dark:bg-slate-900/90 dark:text-slate-100 dark:hover:bg-slate-800"
                    @click="showPreviousImage"
                >
                    <ChevronLeft class="size-4" />
                </button>

                <button
                    v-if="hasMultipleImages"
                    type="button"
                    class="absolute right-3 top-1/2 inline-flex size-9 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-slate-700 shadow-md transition hover:bg-white dark:bg-slate-900/90 dark:text-slate-100 dark:hover:bg-slate-800"
                    @click="showNextImage"
                >
                    <ChevronRight class="size-4" />
                </button>
            </div>
        </div>

        <div v-if="sortedImages.length" class="grid grid-cols-5 gap-2">
            <button
                v-for="image in sortedImages"
                :key="image.id"
                type="button"
                :class="[
                    'overflow-hidden rounded-xl border transition',
                    image.id === activeImage?.id ? 'border-slate-900 ring-2 ring-amber-300' : 'border-slate-200 hover:border-slate-400',
                ]"
                @click="selectImage(sortedImages.findIndex((candidate) => candidate.id === image.id))"
            >
                <img
                    :src="thumbnailImageUrl(image)"
                    :srcset="image.srcset || undefined"
                    sizes="96px"
                    :alt="image.alt || fallbackAlt"
                    class="aspect-square h-full w-full object-cover"
                    loading="lazy"
                    decoding="async"
                >
            </button>
        </div>

        <div
            v-if="isZoomOpen && zoomImageUrl(activeImage)"
            class="fixed inset-0 z-50 flex flex-col bg-slate-950/95 backdrop-blur-sm"
        >
            <div class="flex items-center justify-between gap-3 border-b border-white/10 px-4 py-3 text-white">
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold">{{ activeImage.alt || fallbackAlt }}</p>
                    <p class="text-xs text-slate-300">{{ zoomPercent }}% zoom</p>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="inline-flex size-10 items-center justify-center rounded-full bg-white/10 transition hover:bg-white/20"
                        @click="zoomOut"
                    >
                        <ZoomOut class="size-4" />
                    </button>
                    <button
                        type="button"
                        class="inline-flex size-10 items-center justify-center rounded-full bg-white/10 transition hover:bg-white/20"
                        @click="zoomIn"
                    >
                        <ZoomIn class="size-4" />
                    </button>
                    <button
                        type="button"
                        class="inline-flex size-10 items-center justify-center rounded-full bg-white/10 transition hover:bg-white/20"
                        @click="closeZoom"
                    >
                        <X class="size-4" />
                    </button>
                </div>
            </div>

            <div
                class="flex flex-1 items-center justify-center overflow-auto px-4 py-6"
                @touchstart.passive="handleTouchStart"
                @touchend.passive="handleTouchEnd"
            >
                <img
                    :src="zoomImageUrl(activeImage)"
                    :alt="activeImage.alt || fallbackAlt"
                    class="mx-auto h-auto max-h-none select-none object-contain"
                    decoding="async"
                    :style="{ width: `${zoomPercent}%`, maxWidth: `${zoomPercent}%` }"
                >
            </div>

            <div class="flex items-center justify-between gap-3 border-t border-white/10 px-4 py-3 text-white">
                <button
                    v-if="hasMultipleImages"
                    type="button"
                    class="inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-2 text-sm font-semibold transition hover:bg-white/20"
                    @click="showPreviousImage"
                >
                    <ChevronLeft class="size-4" />
                    Previous
                </button>
                <div v-else></div>

                <button
                    type="button"
                    class="rounded-full bg-white/10 px-4 py-2 text-sm font-semibold transition hover:bg-white/20"
                    @click="resetZoom"
                >
                    Reset zoom
                </button>

                <button
                    v-if="hasMultipleImages"
                    type="button"
                    class="inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-2 text-sm font-semibold transition hover:bg-white/20"
                    @click="showNextImage"
                >
                    Next
                    <ChevronRight class="size-4" />
                </button>
                <div v-else></div>
            </div>
        </div>
    </div>
</template>
