<script setup>
import { computed, ref, watch } from 'vue'

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

watch(
    () => sortedImages.value,
    (images) => {
        if (!images.length) {
            activeImageId.value = null
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
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="aspect-square bg-slate-100">
                <img
                    v-if="activeImage?.url"
                    :src="activeImage.url"
                    :alt="activeImage.alt || fallbackAlt"
                    class="h-full w-full object-cover"
                >
                <div v-else class="flex h-full items-center justify-center text-sm text-slate-500">
                    No image available
                </div>
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
                @click="activeImageId = image.id"
            >
                <img
                    :src="image.url"
                    :alt="image.alt || fallbackAlt"
                    class="aspect-square h-full w-full object-cover"
                    loading="lazy"
                >
            </button>
        </div>
    </div>
</template>
