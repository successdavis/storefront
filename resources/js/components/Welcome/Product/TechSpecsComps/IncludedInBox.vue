<template>
    <div class="included-in-box border border-gray-200 rounded-xl p-6 bg-gradient-to-br from-gray-50 to-white h-fit sticky top-4">
        <div class="flex items-center gap-3 mb-6">
            <div class="w-10 h-10 rounded-full bg-green-500 flex items-center justify-center">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                </svg>
            </div>
            <h3 class="text-xl font-bold text-gray-900">Included in the Box</h3>
        </div>

        <div class="space-y-4">
            <div
                v-for="(item, index) in items"
                :key="index"
                class="flex items-center gap-4 p-4 bg-white rounded-lg border border-gray-200 hover:border-green-500 hover:shadow-md transition-all"
            >
                <div class="w-12 h-12 rounded-full bg-green-50 flex items-center justify-center flex-shrink-0">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            :d="getItemIcon(item.icon)"
                        />
                    </svg>
                </div>
                <div class="flex-1">
                    <p class="font-semibold text-gray-900">{{ item.name }}</p>
                    <p class="text-xs text-gray-500 mt-0.5">Included</p>
                </div>
                <div class="flex-shrink-0">
                    <svg class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Additional Info -->
        <div class="mt-6 p-4 bg-blue-50 rounded-lg border border-blue-200">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-blue-600 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                </svg>
                <div>
                    <p class="text-sm font-medium text-blue-900">Package Contents</p>
                    <p class="text-xs text-blue-700 mt-1">All items are brand new and sealed unless specified otherwise</p>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
defineProps({
    items: {
        type: Array,
        required: true
    }
})

const getItemIcon = (iconType) => {
    const icons = {
        laptop: 'M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
        charger: 'M13 10V3L4 14h7v7l9-11h-7z',
        bag: 'M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z'
    }
    return icons[iconType] || 'M5 13l4 4L19 7'
}
</script>

<style scoped>
/* Sticky positioning for desktop */
@media (min-width: 1024px) {
    .included-in-box {
        position: sticky;
        top: 1rem;
    }
}
</style>
