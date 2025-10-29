<!-- drawer/DrawerBody.vue -->
<template>
    <!-- match shadcn / design intent: scrollable body with padding -->
    <div class="px-4 pt-2 pb-6 overflow-y-auto flex-1">
        <slot />
    </div>
</template>

<script setup>
// no script required — simple wrapper for layout and slot
</script>

<style scoped>
/* optional: small local tweaks if you want a consistent visual */
/* keep simple — styling is via Tailwind utilities above */
</style>
