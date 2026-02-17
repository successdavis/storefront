<template>
    <div class="upgrade-options space-y-4 mb-6 pb-6 border-b border-gray-200">
        <h3 class="text-sm font-semibold text-gray-900 uppercase tracking-wide">Customize Your Device</h3>

        <!-- Storage Upgrade -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
                Storage Upgrade
            </label>
            <select
                :value="storage"
                @change="$emit('update:storage', $event.target.value)"
                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all bg-white text-gray-900"
            >
                <option
                    v-for="option in storageOptions"
                    :key="option.value"
                    :value="option.value"
                >
                    {{ option.label }}{{ option.price > 0 ? ` (+₦${formatPrice(option.price)})` : '' }}
                </option>
            </select>
        </div>

        <!-- RAM Upgrade -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
                RAM Upgrade
            </label>
            <select
                :value="ram"
                @change="$emit('update:ram', $event.target.value)"
                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all bg-white text-gray-900"
            >
                <option
                    v-for="option in ramOptions"
                    :key="option.value"
                    :value="option.value"
                >
                    {{ option.label }}{{ option.price > 0 ? ` (+₦${formatPrice(option.price)})` : '' }}
                </option>
            </select>
        </div>
    </div>
</template>

<script setup>
defineProps({
    storage: String,
    ram: String,
    storageOptions: Array,
    ramOptions: Array
})

defineEmits(['update:storage', 'update:ram'])

const formatPrice = (price) => {
    return price.toLocaleString('en-NG')
}
</script>
