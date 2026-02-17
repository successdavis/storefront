<template>
    <div class="shipping-info space-y-3 text-sm mb-6">
        <div class="flex items-start gap-3">
            <svg class="w-5 h-5 text-green-600 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/>
            </svg>
            <div>
                <p class="font-medium text-gray-900">{{ shippingInfo.shipping }}</p>
                <p class="text-gray-600 text-xs mt-0.5">Free delivery on orders over ₦100,000</p>
            </div>
        </div>

        <div class="flex items-start gap-3">
            <svg class="w-5 h-5 text-blue-600 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
            </svg>
            <div>
                <p class="font-medium text-gray-900">{{ warranty }}</p>
                <p class="text-gray-600 text-xs mt-0.5">Extended warranty available at checkout</p>
            </div>
        </div>

        <div v-if="shippingInfo.inStock" class="flex items-start gap-3">
            <svg class="w-5 h-5 text-orange-600 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <div>
                <p class="font-medium text-gray-900">Ships within {{ shippingInfo.shipsWithin }}</p>
                <p class="text-gray-600 text-xs mt-0.5">Order now for fastest delivery</p>
            </div>
        </div>
    </div>
</template>

<script setup>
defineProps({
    shippingInfo: Object,
    warranty: String
})
</script>
