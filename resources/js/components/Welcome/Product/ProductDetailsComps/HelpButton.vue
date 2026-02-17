<template>
    <div class="help-button">
        <button
            @click="openHelp"
            class="flex items-center gap-2 text-sm text-gray-700 hover:text-gray-900 font-medium transition-colors"
        >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span>Need help? Contact our support team</span>
        </button>
    </div>
</template>

<script setup>
const openHelp = () => {
    console.log('Opening help')
    // Implement help modal or chat
}
</script>
