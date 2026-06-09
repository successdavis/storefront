<template>
    <div
        class="admin-pos-page flex h-screen bg-gray-50 text-gray-900 transition-colors duration-200 dark:bg-gray-900 dark:text-gray-100"
    >
        <!-- Left: Product Section -->
        <ProductSection />

        <!-- Right: Cart Section -->
        <CartSection @open-orders="showOrders = true" />

    </div>
</template>

<script setup>
import { useSidebar } from '@/components/ui/sidebar'
import { onMounted, ref } from 'vue'
import ProductSection from './components/ProductSection.vue'
import CartSection from './components/CartSection.vue'
import { useBarcodeScanner } from './composables/useBarcodeScanner'
// import OffcanvasOrders from './components/OffcanvasOrders.vue'

const showOrders = ref(false)
const { setOpen, setOpenMobile } = useSidebar()
useBarcodeScanner()

onMounted(() => {
    setOpen(false)
    setOpenMobile(false)
})
</script>

<style>
.admin-pos-page select {
    background-color: #ffffff;
    color: #111827;
    color-scheme: light;
}

.admin-pos-page select option {
    background-color: #ffffff !important;
    color: #111827 !important;
}

.dark .admin-pos-page select {
    background-color: #111827;
    border-color: #4b5563;
    color: #f9fafb;
    color-scheme: dark;
}

.dark .admin-pos-page select option {
    background-color: #111827 !important;
    color: #f9fafb !important;
}

.dark .admin-pos-page select option:checked {
    background-color: #2563eb !important;
    color: #ffffff !important;
}
</style>
