<script setup>
import { ref, computed, watch } from "vue"

const props = defineProps({
    isOpen: Boolean,
})

const isOpen = computed(() => props.isOpen ?? false)
// Sort, in-stock toggle
const sortBy = ref("new")
const inStockOnly = ref(false)

// Price range limits
const min = 0
const max = 195000

const minPrice = ref(min)
const maxPrice = ref(max)

// Computed positions for slider track fill
const minPercent = computed(() => ((minPrice.value - min) / (max - min)) * 100)
const maxPercent = computed(() => ((maxPrice.value - min) / (max - min)) * 100)

// Handlers for sliders
function onMinChange(e) {
    let value = Number(e.target.value)
    if (value < min) value = min
    if (value > maxPrice.value) value = maxPrice.value
    minPrice.value = value
}

function onMaxChange(e) {
    let value = Number(e.target.value)
    if (value > max) value = max
    if (value < minPrice.value) value = minPrice.value
    maxPrice.value = value
}

// Watch inputs to enforce limits if typed manually
watch(minPrice, (val) => {
    if (val < min) minPrice.value = min
    if (val > maxPrice.value) minPrice.value = maxPrice.value
})

watch(maxPrice, (val) => {
    if (val > max) maxPrice.value = max
    if (val < minPrice.value) maxPrice.value = minPrice.value
})
</script>

<template>
    <!-- Filters button -->
    <button
        @click="$emit('open')"
        class="px-4 py-2 text-primary rounded hidden md:block"
    >
        Filters
    </button>

    <!-- Overlay -->
    <div
        v-if="isOpen"
        class="fixed inset-0 bg-black/30 z-40"
        @click="$emit('close')"
    />

    <!-- Panel -->
    <div
        class="fixed z-50 bg-white transition-transform duration-300 ease-in-out
           md:top-0 md:right-0 md:h-full md:w-[360px]
           bottom-0 left-0 w-full h-[80%]"
        :class="isOpen
      ? 'translate-x-0 translate-y-0'
      : 'md:translate-x-full translate-y-full'"
    >
        <div class="p-6 flex flex-col gap-6 h-full">

            <!-- Header -->
            <div class="flex items-center justify-between">
                <h2 class="text-primary">Filters</h2>
                <button @click="isOpen = false">✕</button>
            </div>

            <!-- Sort -->
            <div class="flex flex-col gap-2">
                <label class="text-primary">Sort by</label>
                <select v-model="sortBy" class="px-3 py-2 rounded">
                    <option value="new">Date, new to old</option>
                    <option value="price-low">Price, low to high</option>
                    <option value="price-high">Price, high to low</option>
                </select>
            </div>

            <!-- Dual-thumb price range -->
            <div class="flex flex-col gap-3">
                <label class="text-primary">Price</label>

                <div class="relative h-2 bg-gray-200 rounded">
                    <!-- Track fill -->
                    <div
                        class="absolute h-2 bg-primary rounded"
                        :style="{ left: minPercent + '%', width: maxPercent - minPercent + '%' }"
                    ></div>

                    <!-- Left thumb -->
                    <input
                        type="range"
                        min="0"
                        max="195000"
                        :value="minPrice"
                        @input="onMinChange"
                        class="absolute w-full h-2 appearance-none bg-transparent pointer-events-auto"
                    />

                    <!-- Right thumb -->
                    <input
                        type="range"
                        min="0"
                        max="195000"
                        :value="maxPrice"
                        @input="onMaxChange"
                        class="absolute w-full h-2 appearance-none bg-transparent pointer-events-auto"
                    />
                </div>

                <!-- Inputs synced with slider -->
                <div class="flex gap-3">
                    <div class="flex flex-col gap-1 w-1/2">
                        <span>From price ₦</span>
                        <input
                            type="number"
                            v-model.number="minPrice"
                            class="px-3 py-2 rounded"
                            :min="min"
                            :max="maxPrice"
                        />
                    </div>

                    <div class="flex flex-col gap-1 w-1/2">
                        <span>To price ₦</span>
                        <input
                            type="number"
                            v-model.number="maxPrice"
                            class="px-3 py-2 rounded"
                            :min="minPrice"
                            :max="max"
                        />
                    </div>
                </div>
            </div>

            <!-- In stock -->
            <label class="flex items-center gap-3">
                <input type="checkbox" v-model="inStockOnly" />
                <span>In stock only</span>
            </label>

            <!-- View results -->
            <div class="mt-auto">
                <button class="w-full py-3 text-secondary bg-primary rounded cursor-pointer">
                    View results 26
                </button>
            </div>
        </div>

    </div>
</template>
