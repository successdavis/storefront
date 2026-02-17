<template>
    <div class="product-header mb-6">
        <!-- Product Title -->
        <h1 class="text-2xl lg:text-3xl font-bold text-gray-900 mb-3">
            {{ product.name }}
        </h1>

        <!-- Price -->
        <div class="flex items-baseline gap-3 mb-4">
            <span class="text-4xl font-bold text-gray-900">
                {{ product.currency }}{{ formatPrice(product.price) }}
            </span>
        </div>

        <!-- Condition Badge -->
        <div class="flex items-center gap-3 flex-wrap">
            <span class="inline-flex items-center px-3 py-1.5 rounded-full text-sm bg-gray-100 text-gray-700 font-medium">
                <svg class="w-4 h-4 mr-1.5" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/>
                    <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"/>
                </svg>
                {{ product.condition }}
            </span>

            <!-- Stock Status -->
            <span v-if="stockStatus.message" class="inline-flex items-center px-3 py-1.5 rounded-full text-sm bg-orange-50 text-orange-700 font-medium">
                <svg class="w-4 h-4 mr-1.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                </svg>
                {{ stockStatus.message }}
            </span>
        </div>
    </div>
</template>

<script setup>
const props = defineProps({
    product: {
        type: Object,
        required: true
    },
    stockStatus: {
        type: Object,
        required: true
    }
})

const formatPrice = (price) => {
    return price.toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}
</script>
