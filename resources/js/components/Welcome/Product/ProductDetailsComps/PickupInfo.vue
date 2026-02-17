<template>
    <div class="pickup-info mb-6 pb-6 border-b border-gray-200">
        <div class="flex items-start gap-3 p-4 bg-red-50 rounded-lg border border-red-100">
            <svg class="w-5 h-5 text-red-600 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
            </svg>
            <div class="flex-1">
                <p class="font-semibold text-gray-900 text-sm">
                    Pickup currently unavailable at {{ shippingInfo.pickup?.location || 'this location' }}
                </p>
                <button class="text-blue-600 hover:text-blue-700 hover:underline mt-1 text-sm font-medium">
                    Check availability at other stores →
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
defineProps({
    shippingInfo: Object
})
</script>
