<script setup>
import { ref, computed } from 'vue'
import CardImageSlider from './CardImageSlider.vue'
import SaleTag from './SaleTag.vue'
import ViewButton from './ViewButton.vue'
import AddToCartButton from './AddToCartButton.vue'
import CardInfo from './CardInfo.vue'
import CardSpecifications from "./CardSpecifications.vue";
import { Link } from "@inertiajs/vue3";
const props = defineProps({
    images: {
        type: Array,
        required: true,
        validator: (value) => value.length === 3
    },
    title: {
        type: String,
        required: true
    },
    price: {
        type: [String, Number],
        required: true
    },
    onSale: {
        type: Boolean,
        default: false
    },
    specifications: {
        type: Object,
        default: () => ({})
    }
})

const emit = defineEmits(['view', 'add-to-cart'])

const isHovered = ref(false)
const currentImageIndex = ref(1) // 0: left, 1: center (default), 2: right

const handleMouseEnter = () => {
    isHovered.value = true
}

const handleMouseLeave = () => {
    isHovered.value = false
    currentImageIndex.value = 1 // Reset to center image
}

const handleMouseMove = (event) => {
    if (!isHovered.value) return

    const rect = event.currentTarget.getBoundingClientRect()
    const x = event.clientX - rect.left
    const width = rect.width
    const percentage = x / width

    // Divide into three zones: left (0-0.33), center (0.33-0.66), right (0.66-1)
    if (percentage < 0.33) {
        currentImageIndex.value = 0 // Left image
    } else if (percentage > 0.66) {
        currentImageIndex.value = 2 // Right image
    } else {
        currentImageIndex.value = 1 // Center image
    }
}

const handleViewClick = () => {
    emit('view', {
        title: props.title,
        images: props.images,
        price: props.price
    })
}

const handleAddToCart = () => {
    emit('add-to-cart', {
        title: props.title,
        image: props.images[1],
        price: props.price
    })
}

const hasSpecifications = computed(() => {
    return Object.keys(props.specifications).length > 0
})
</script>

<template>
    <div
        class="w-full h-[450px] flex flex-col justify-self-start mb-6 rounded-md bg-white overflow-hidden shadow-md hover:shadow-2xl transition-all duration-300 hover:-translate-y-1 cursor-pointer"
        @mouseenter="handleMouseEnter"
        @mouseleave="handleMouseLeave"
        @mousemove="handleMouseMove"
    >

        <Link href="/products/view_product/details">

        <div class="relative w-full h-[300px] overflow-hidden bg-gray-100">
            <CardImageSlider
                :images="images"
                :currentImageIndex="currentImageIndex"
                :isHovered="isHovered"
            />

            <SaleTag v-if="onSale" />

            <ViewButton
                :isHovered="isHovered"
                @click="handleViewClick"
            />

            <AddToCartButton
                :isHovered="isHovered"
                @click="handleAddToCart"
            />
        </div>

        <CardInfo
            :title="title"
            :price="price"
        />
        <div
            v-if="hasSpecifications"
            class="pb-4">
            <CardSpecifications
                :specifications="specifications"
            />
        </div>
        </Link>
    </div>
</template>
