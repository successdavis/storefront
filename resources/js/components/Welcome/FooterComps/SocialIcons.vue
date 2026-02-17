<template>
    <!-- Social Icons -->
    <div class="flex gap-5 mt-6 ">

        <!-- Facebook -->
        <Link href="#" aria-label="Facebook" class="hover:opacity-80">
            <svg class="w-5 h-5 fill-current text-secondary" viewBox="0 0 24 24">
                <path d="M22 12a10 10 0 1 0-11.5 9.9v-7h-2.7V12h2.7V9.8c0-2.7 1.6-4.2 4-4.2 1.2 0 2.4.2 2.4.2v2.6h-1.4c-1.4 0-1.8.9-1.8 1.8V12h3.1l-.5 2.9h-2.6v7A10 10 0 0 0 22 12"/>
            </svg>
        </Link>

        <!-- X -->
        <Link href="#" aria-label="X" class="hover:opacity-80">
            <svg class="w-5 h-5 fill-current text-secondary" viewBox="0 0 24 24">
                <path d="M18.9 2H22l-6.5 7.4L23 22h-6.8l-5.3-6.9L4.7 22H1.6l7-8L1 2h6.9l4.8 6.3L18.9 2z"/>
            </svg>
        </Link>

        <!-- Instagram -->
        <Link href="#" aria-label="Instagram" class="hover:opacity-80">
            <svg class="w-5 h-5 fill-current text-secondary" viewBox="0 0 24 24">
                <path d="M7 2C4.2 2 2 4.2 2 7v10c0 2.8 2.2 5 5 5h10c2.8 0 5-2.2 5-5V7c0-2.8-2.2-5-5-5H7zm5 5.3A4.7 4.7 0 1 1 7.3 12 4.7 4.7 0 0 1 12 7.3zm6-1.6a1.1 1.1 0 1 1-1.1 1.1A1.1 1.1 0 0 1 18 5.7zM12 9a3 3 0 1 0 3 3 3 3 0 0 0-3-3z"/>
            </svg>
        </Link>

        <!-- YouTube -->
        <Link href="#" aria-label="YouTube" class="hover:opacity-80">
            <svg class="w-5 h-5 fill-current text-secondary" viewBox="0 0 24 24">
                <path d="M23.5 6.2a3 3 0 0 0-2.1-2.1C19.5 3.5 12 3.5 12 3.5s-7.5 0-9.4.6A3 3 0 0 0 .5 6.2 31 31 0 0 0 0 12a31 31 0 0 0 .5 5.8 3 3 0 0 0 2.1 2.1c1.9.6 9.4.6 9.4.6s7.5 0 9.4-.6a3 3 0 0 0 2.1-2.1A31 31 0 0 0 24 12a31 31 0 0 0-.5-5.8zM9.8 15.5v-7l6.2 3.5-6.2 3.5z"/>
            </svg>
        </Link>

        <!-- TikTok -->
        <Link href="#" aria-label="TikTok" class="hover:opacity-80">
            <svg class="w-5 h-5 fill-current text-secondary" viewBox="0 0 24 24">
                <path d="M16 3c.4 2.1 2 3.7 4 4v3a7 7 0 0 1-4-1.3V15a6 6 0 1 1-6-6c.3 0 .7 0 1 .1v3a3 3 0 1 0 2 2.8V3h3z"/>
            </svg>
        </Link>

    </div>

</template>

<script setup>
import {Link} from "@inertiajs/vue3"
</script>

<style scoped>

</style>
