<template>
    <div class="accessories-section mb-6 pb-6 border-b border-gray-200">
        <h3 class="text-sm font-semibold text-gray-900 uppercase tracking-wide mb-4">
            Add Accessories
        </h3>

        <div class="space-y-3">
            <label
                v-for="accessory in accessories"
                :key="accessory.id"
                class="flex items-center justify-between p-4 border border-gray-200 rounded-lg cursor-pointer hover:border-blue-500 hover:bg-blue-50 transition-all"
                :class="{ 'border-blue-500 bg-blue-50': isSelected(accessory.id) }"
            >
                <div class="flex items-center gap-3 flex-1">
                    <input
                        type="checkbox"
                        :checked="isSelected(accessory.id)"
                        @change="toggleAccessory(accessory.id)"
                        class="w-5 h-5 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                    />
                    <div class="flex-1">
                        <p class="font-medium text-gray-900">{{ accessory.name }}</p>
                        <p class="text-sm text-gray-600">₦{{ formatPrice(accessory.price) }}</p>
                    </div>
                </div>

                <!-- Optional: Add image if available -->
                <div v-if="accessory.image" class="w-12 h-12 rounded overflow-hidden bg-gray-100 flex-shrink-0">
                    <img :src="accessory.image" :alt="accessory.name" class="w-full h-full object-cover" />
                </div>
            </label>
        </div>
    </div>
</template>

<script setup>
const props = defineProps({
    selectedAccessories: Array,
    accessories: Array
})

const emit = defineEmits(['update:selectedAccessories'])

const isSelected = (id) => {
    return props.selectedAccessories.includes(id)
}

const toggleAccessory = (id) => {
    const current = [...props.selectedAccessories]
    const index = current.indexOf(id)

    if (index > -1) {
        current.splice(index, 1)
    } else {
        current.push(id)
    }

    emit('update:selectedAccessories', current)
}

const formatPrice = (price) => {
    return price.toLocaleString('en-NG')
}
</script>
