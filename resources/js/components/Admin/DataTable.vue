<template>
    <div>
        <div class="flex items-center gap-2 mb-3">
            <input v-model="local.search" @input="emitSearch" placeholder="Search..." class="border rounded px-3 py-2 w-full" />
            <slot name="actions" />
        </div>


        <table class="min-w-full border text-sm">
            <thead>
            <tr class="bg-gray-50">
                <slot name="head" />
            </tr>
            </thead>
            <tbody>
            <slot name="body" />
            </tbody>
        </table>


        <div class="mt-3 flex justify-between items-center text-sm">
<!--            <div>Showing {{ meta.from }}–{{ meta.to }} of {{ meta.total }}</div>-->
            <div class="flex gap-2">
                <button :disabled="!links.prev" @click="$emit('go', links.prev)" class="px-3 py-1 border rounded">Prev</button>
                <button :disabled="!links.next" @click="$emit('go', links.next)" class="px-3 py-1 border rounded">Next</button>
            </div>
        </div>
    </div>
</template>
<script setup>
import { reactive, watch } from 'vue'
const props = defineProps({
    filters: Object,
    meta: Object,
    links: Object
})
const emit = defineEmits(['update:filters','go'])
const local = reactive({ search: props.filters?.search ?? '' })
function emitSearch(){ emit('update:filters', { ...props.filters, search: local.search }) }
watch(() => props.filters?.search, v => local.search = v)
</script>
