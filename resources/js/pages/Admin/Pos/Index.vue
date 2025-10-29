<template>
    <div
        class="flex h-screen bg-gray-50 text-gray-900 transition-colors duration-200 dark:bg-gray-900 dark:text-gray-100"
    >
        <!-- Left: Product Section -->
        <ProductSection />

        <!-- Right: Cart Section -->
        <CartSection @open-orders="showOrders = true" />

    </div>
</template>

<script setup>
import { ref } from 'vue'
import ProductSection from './components/ProductSection.vue'
import CartSection from './components/CartSection.vue'
import OffcanvasOrders from './components/OffcanvasOrders.vue'

const showOrders = ref(false)
</script>
