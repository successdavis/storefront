<template>
    <div class="product-image-gallery">
        <!-- Desktop Layout -->
        <div class="hidden lg:flex gap-4">
            <!-- Thumbnail List - Left Side -->
            <ImageThumbnails
                :images="images"
                :product-name="productName"
                :selected-index="selectedIndex"
                @select="selectImage"
                orientation="vertical"
                class="max-h-[600px]"
            />

            <!-- Main Image Display -->
            <MainImageDisplay
                :image="images[selectedIndex]"
                :product-name="productName"
                :current-index="selectedIndex"
                :total-images="images.length"
                :show-navigation="true"
                @previous="previousImage"
                @next="nextImage"
                class="flex-1"
            />
        </div>

        <!-- Mobile Layout -->
        <div class="lg:hidden">
            <!-- Main Image Display -->
            <MainImageDisplay
                :image="images[selectedIndex]"
                :product-name="productName"
                :current-index="selectedIndex"
                :total-images="images.length"
                :show-navigation="true"
                @previous="previousImage"
                @next="nextImage"
                :mobile="true"
                class="mb-4"
            />

            <!-- Thumbnail List - Horizontal Scroll -->
            <ImageThumbnails
                ref="thumbnailContainer"
                :images="images"
                :product-name="productName"
                :selected-index="selectedIndex"
                @select="selectImage"
                orientation="horizontal"
            />
        </div>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import ImageThumbnails from './ProductImageComps/ImageThumbnails.vue'
import MainImageDisplay from './ProductImageComps/MainImageDisplay.vue'

const props = defineProps({
    images: {
        type: Array,
        default: () => [
            'https://shopinverse.com/cdn/shop/files/8gb-samsung-galaxy-tab-4-white-5560931.jpg?v=1769613563&width=1200',
            'https://shopinverse.com/cdn/shop/files/8gb-samsung-galaxy-tab-6-8412439.jpg?v=1769613564&width=1200',
            'https://shopinverse.com/cdn/shop/files/8gb-samsung-galaxy-tab-4-white-5560931.jpg?v=1769613563&width=1200',
            'https://shopinverse.com/cdn/shop/files/8gb-samsung-galaxy-tab-6-8412439.jpg?v=1769613564&width=1200',
            'https://shopinverse.com/cdn/shop/files/8gb-samsung-galaxy-tab-4-white-5560931.jpg?v=1769613563&width=1200', 'https://shopinverse.com/cdn/shop/files/8gb-samsung-galaxy-tab-4-white-5560931.jpg?v=1769613563&width=1200',
            'https://shopinverse.com/cdn/shop/files/8gb-samsung-galaxy-tab-6-8412439.jpg?v=1769613564&width=1200',
            'https://shopinverse.com/cdn/shop/files/8gb-samsung-galaxy-tab-4-white-5560931.jpg?v=1769613563&width=1200',
            'https://shopinverse.com/cdn/shop/files/8gb-samsung-galaxy-tab-6-8412439.jpg?v=1769613564&width=1200',
            'https://shopinverse.com/cdn/shop/files/8gb-samsung-galaxy-tab-4-white-5560931.jpg?v=1769613563&width=1200', 'https://shopinverse.com/cdn/shop/files/8gb-samsung-galaxy-tab-4-white-5560931.jpg?v=1769613563&width=1200',
            'https://shopinverse.com/cdn/shop/files/8gb-samsung-galaxy-tab-6-8412439.jpg?v=1769613564&width=1200',
            'https://shopinverse.com/cdn/shop/files/8gb-samsung-galaxy-tab-4-white-5560931.jpg?v=1769613563&width=1200',
            'https://shopinverse.com/cdn/shop/files/8gb-samsung-galaxy-tab-6-8412439.jpg?v=1769613564&width=1200',
            'https://shopinverse.com/cdn/shop/files/8gb-samsung-galaxy-tab-4-white-5560931.jpg?v=1769613563&width=1200',
        ]
    },
    productName: {
        type: String,
        default: 'Samsung Galaxy Tab 4'
    },
})

const selectedIndex = ref(0)
const thumbnailContainer = ref(null)

const selectImage = (index) => {
    selectedIndex.value = index
    scrollToThumbnail(index)
}

const previousImage = () => {
    if (selectedIndex.value > 0) {
        selectImage(selectedIndex.value - 1)
    }
}

const nextImage = () => {
    if (selectedIndex.value < props.images.length - 1) {
        selectImage(selectedIndex.value + 1)
    }
}

const scrollToThumbnail = (index) => {
    if (thumbnailContainer.value) {
        thumbnailContainer.value.scrollToThumbnail(index)
    }
}

// Watch for image changes to scroll thumbnail into view
watch(selectedIndex, (newIndex) => {
    scrollToThumbnail(newIndex)
})
</script>

<style scoped>
.product-image-gallery {
    max-width: 100%;
    overflow: hidden;
}
</style>
