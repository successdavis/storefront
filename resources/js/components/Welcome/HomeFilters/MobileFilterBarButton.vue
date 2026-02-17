<script setup>
</script>

<template>
    <div class="fixed bottom-4 left-1/2 -translate-x-1/2 md:hidden z-40">
        <button
            class="px-6 py-3 bg-primary text-secondary rounded-full"
            @click="$emit('open')"
        >
            Filters and Sort
        </button>
    </div>
</template>
