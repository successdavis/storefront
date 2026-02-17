<script setup>
import {Link} from '@inertiajs/vue3'
import SearchPanel from "@/components/Welcome/SearchPanel.vue";
</script>

<template>
    <div class="flex items-center gap-4">

        <SearchPanel />


        <Link href="/login" title="login" class="text-primary">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                <path d="M10 2a5 5 0 100 10 5 5 0 000-10zM2 18a8 8 0 1116 0H2z"/>
            </svg>
        </Link>
        <Link href="/favourite" title="favourite" class="text-primary">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                <path d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657 3.172 10.828a4 4 0 010-5.656z"/>
            </svg>
        </Link>

        <Link href="/cart" title="cart" class="text-primary">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                <path d="M16 6H4l-1 12h14L16 6zM6 6V4a4 4 0 118 0v2h-2V4a2 2 0 10-4 0v2H6z"/>
            </svg>
        </Link>

    </div>
</template>
