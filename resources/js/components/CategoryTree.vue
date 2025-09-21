<script setup>
import { ref, watch } from 'vue'
import CategoryTreeNode from './CategoryTreeNode.vue'

const props = defineProps({
    categories: { type: Array, required: true },              // [{ id, name, children: [...] }]
    modelValue: { type: Array, default: () => [] },           // selected IDs (number|string)
    expandAll: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])

/* keep IDs as strings internally to avoid 1 vs "1" mismatch */
const selected = ref((props.modelValue || []).map(v => String(v)))

function norm(arr) {
    return (arr || []).map(String).sort()
}
function arraysEqual(a, b) {
    if (a.length !== b.length) return false
    for (let i = 0; i < a.length; i++) if (a[i] !== b[i]) return false
    return true
}

/* when parent changes, update local, but do not emit back */
watch(
    () => props.modelValue,
    v => {
        const next = (v || []).map(x => String(x))
        // Only replace when actually different to avoid retriggering the other watcher
        if (!arraysEqual(norm(selected.value), norm(next))) {
            selected.value = next
        }
    }
)

/* when local selection changes, emit only if structurally different from parent */
watch(
    selected,
    v => {
        const a = norm(v)
        const b = norm(props.modelValue)
        if (!arraysEqual(a, b)) {
            emit('update:modelValue', v.slice())   // clone to avoid ref reuse
        }
    },
    { deep: false }
)
</script>

<template>
    <div class="rounded-lg border p-4">
        <div class="text-sm font-medium mb-3">Product category</div>

        <ul class="max-h-80 overflow-auto pr-2 space-y-1">
            <CategoryTreeNode
                v-for="n in categories"
                :key="n.id"
                :node="n"
                :level="0"
                v-model:selected="selected"
                :expand-all="expandAll"
            />
        </ul>
    </div>
</template>
