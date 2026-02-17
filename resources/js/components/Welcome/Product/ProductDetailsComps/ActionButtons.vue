<template>
    <div class="action-buttons space-y-3 mb-6">
        <button
            @click="$emit('add-to-cart')"
            class="w-full bg-black text-white py-4 px-6 rounded-xl font-semibold hover:bg-gray-800 transition-all transform hover:scale-[1.02] active:scale-[0.98] shadow-lg hover:shadow-xl"
        >
            <span class="flex items-center justify-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                </svg>
                Add to Cart - {{ currency }}{{ formatPrice(totalPrice) }}
            </span>
        </button>

        <button
            @click="$emit('add-to-wishlist')"
            class="w-full border-2 border-black text-black py-4 px-6 rounded-xl font-semibold hover:bg-gray-50 transition-all transform hover:scale-[1.02] active:scale-[0.98]"
        >
            <span class="flex items-center justify-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                </svg>
                Add to Wishlist
            </span>
        </button>

        <button
            @click="$emit('buy-now')"
            class="w-full border-2 border-gray-300 text-gray-900 py-4 px-6 rounded-xl font-semibold hover:bg-gray-50 hover:border-gray-400 transition-all transform hover:scale-[1.02] active:scale-[0.98]"
        >
            Buy it Now
        </button>
    </div>
</template>

<script setup>
defineProps({
    totalPrice: Number,
    currency: String
})

defineEmits(['add-to-cart', 'add-to-wishlist', 'buy-now'])

const formatPrice = (price) => {
    return price.toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}
</script>
