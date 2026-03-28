<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import ProductCard from '@/components/Storefront/ProductCard.vue'

const props = defineProps({
    products: {
        type: Array,
        default: () => [],
    },
    emptyTitle: {
        type: String,
        default: 'No products found',
    },
    emptyDescription: {
        type: String,
        default: 'Try changing your filters or search keywords.',
    },
})

const track = ref(null)
const canScrollPrev = ref(false)
const canScrollNext = ref(false)

const hasProducts = computed(() => props.products.length > 0)

function updateScrollState() {
    const element = track.value
    if (!element) {
        canScrollPrev.value = false
        canScrollNext.value = false
        return
    }

    const maxScrollLeft = Math.max(element.scrollWidth - element.clientWidth, 0)

    canScrollPrev.value = element.scrollLeft > 4
    canScrollNext.value = element.scrollLeft < maxScrollLeft - 4
}

function scrollCarousel(direction) {
    const element = track.value
    if (!element) {
        return
    }

    const distance = Math.max(element.clientWidth * 0.82, 220)

    element.scrollBy({
        left: direction * distance,
        behavior: 'smooth',
    })
}

function handleResize() {
    updateScrollState()
}

onMounted(() => {
    window.addEventListener('resize', handleResize)
    nextTick(() => updateScrollState())
})

onBeforeUnmount(() => {
    window.removeEventListener('resize', handleResize)
})

watch(
    () => props.products,
    async () => {
        await nextTick()
        updateScrollState()
    },
    { deep: true },
)
</script>

<template>
    <div>
        <div
            v-if="!hasProducts"
            class="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center"
        >
            <p class="text-base font-semibold text-slate-700">{{ emptyTitle }}</p>
            <p class="mt-2 text-sm text-slate-500">{{ emptyDescription }}</p>
        </div>

        <div v-else class="space-y-4">
            <div class="flex items-center justify-end gap-2">
                <button
                    type="button"
                    class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-slate-300 bg-white text-lg text-slate-700 transition hover:border-slate-500 disabled:cursor-not-allowed disabled:opacity-40"
                    :disabled="!canScrollPrev"
                    @click="scrollCarousel(-1)"
                >
                    <
                </button>
                <button
                    type="button"
                    class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-slate-300 bg-white text-lg text-slate-700 transition hover:border-slate-500 disabled:cursor-not-allowed disabled:opacity-40"
                    :disabled="!canScrollNext"
                    @click="scrollCarousel(1)"
                >
                    >
                </button>
            </div>

            <div
                ref="track"
                class="scrollbar-thin flex snap-x snap-mandatory gap-4 overflow-x-auto pb-2 scroll-smooth"
                @scroll="updateScrollState"
            >
                <div
                    v-for="product in products"
                    :key="product.id"
                    class="w-[42%] min-w-[150px] flex-none snap-start sm:w-[calc(33.333%-0.75rem)] xl:w-[calc(25%-0.75rem)]"
                >
                    <ProductCard :product="product" />
                </div>
            </div>
        </div>
    </div>
</template>
