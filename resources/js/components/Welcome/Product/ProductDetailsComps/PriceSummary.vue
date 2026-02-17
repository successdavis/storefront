<template>
    <div class="price-summary bg-gray-50 rounded-xl p-5 mb-6 space-y-3">
        <h3 class="text-sm font-semibold text-gray-900 uppercase tracking-wide mb-4">Price Breakdown</h3>

        <!-- Base Price -->
        <div class="flex justify-between items-center text-sm">
            <span class="text-gray-600">Base Price × {{ quantity }}</span>
            <span class="font-medium text-gray-900">{{ currency }}{{ formatPrice(basePrice * quantity) }}</span>
        </div>

        <!-- Storage Upgrade -->
        <div v-if="storageUpgradePrice > 0" class="flex justify-between items-center text-sm">
            <span class="text-gray-600">Storage Upgrade × {{ quantity }}</span>
            <span class="font-medium text-gray-900">{{ currency }}{{ formatPrice(storageUpgradePrice * quantity) }}</span>
        </div>

        <!-- RAM Upgrade -->
        <div v-if="ramUpgradePrice > 0" class="flex justify-between items-center text-sm">
            <span class="text-gray-600">RAM Upgrade × {{ quantity }}</span>
            <span class="font-medium text-gray-900">{{ currency }}{{ formatPrice(ramUpgradePrice * quantity) }}</span>
        </div>

        <!-- Accessories -->
        <div v-if="accessoriesPrice > 0" class="flex justify-between items-center text-sm">
            <span class="text-gray-600">Accessories × {{ quantity }}</span>
            <span class="font-medium text-gray-900">{{ currency }}{{ formatPrice(accessoriesPrice * quantity) }}</span>
        </div>

        <!-- Divider -->
        <div class="border-t border-gray-300 pt-3 mt-3">
            <div class="flex justify-between items-center">
                <span class="text-base font-bold text-gray-900">Total</span>
                <span class="text-2xl font-bold text-gray-900">{{ currency }}{{ formatPrice(totalPrice) }}</span>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
    basePrice: Number,
    quantity: Number,
    storageUpgradePrice: Number,
    ramUpgradePrice: Number,
    accessoriesPrice: Number,
    currency: String
})

const totalPrice = computed(() => {
    return (props.basePrice + props.storageUpgradePrice + props.ramUpgradePrice + props.accessoriesPrice) * props.quantity
})

const formatPrice = (price) => {
    return price.toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}
</script>
