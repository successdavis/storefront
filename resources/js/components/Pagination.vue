<!-- resources/js/Components/Pagination.vue -->
<script setup>
import { Link } from '@inertiajs/vue3'

const props = defineProps({
  links: { type: Array, default: () => [] }, // standard Laravel paginator "links"
})
</script>

<template>
  <nav class="flex items-center justify-center gap-1 select-none" aria-label="Pagination" v-if="links && links.length">
    <template v-for="(link, i) in links" :key="i">
      <Link
        v-if="link.url"
        :href="link.url"
        :aria-current="link.active ? 'page' : undefined"
        class="px-3 py-2 text-sm rounded-md border"
        :class="[
          link.active
            ? 'bg-indigo-600 text-white border-indigo-600'
            : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'
        ]"
        v-html="link.label"
      />
      <span
        v-else
        class="px-3 py-2 text-sm rounded-md border bg-gray-100 text-gray-400 border-gray-200"
        v-html="link.label"
      />
    </template>
  </nav>
</template>
