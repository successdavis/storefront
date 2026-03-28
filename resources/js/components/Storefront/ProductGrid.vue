<script setup>
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
</script>

<template>
    <div>
        <div
            v-if="!products.length"
            class="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center"
        >
            <p class="text-base font-semibold text-slate-700">{{ emptyTitle }}</p>
            <p class="mt-2 text-sm text-slate-500">{{ emptyDescription }}</p>
        </div>

        <div
            v-else
            class="grid grid-cols-2 gap-4 xl:grid-cols-4"
        >
            <ProductCard
                v-for="product in products"
                :key="product.id"
                :product="product"
            />
        </div>
    </div>
</template>
