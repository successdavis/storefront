<script setup>
import {Link} from '@inertiajs/vue3'
defineProps({
    items: {
        type: Array,
        required: true,
    },
})
</script>

<template>
    <div class="w-full">

        <!-- Desktop buttons -->
        <div class="hidden md:flex items-center gap-4">
            <Link
                v-for="item in items"
                :href="item.url"
                :key="item.name"
                class="relative px-4 py-2 text-primary rounded"
            >
                <span>{{ item.name }}</span>

                <span
                    v-if="item.count > 0"
                    class="absolute -top-1 -right-1 text-[10px] text-primary rounded-full px-1.5"
                >
                    {{ item.count }}
                </span>
            </Link>
        </div>

        <!-- Mobile select -->
        <select
            class="md:hidden w-full px-4 py-3 outline outline-secondary border-2 border-secondary text-primary rounded"
        >
            <option
                v-for="item in items"
                :key="item.name"
            >
                {{ item.name }} ({{ item.count }})
            </option>
        </select>

    </div>
</template>
