<script setup>
import { ref, computed, watch } from 'vue'

const props = defineProps({
  node: { type: Object, required: true },     // { id, name, children? }
  level: { type: Number, default: 0 },
  selected: { type: Array, default: () => [] },
  expandAll: { type: Boolean, default: false }
})

const emit = defineEmits(['update:selected'])

const toKey = v => String(v)
const hasChildren = computed(() => Array.isArray(props.node.children) && props.node.children.length > 0)
const open = ref(props.expandAll && hasChildren.value)

function descendants(node, bag = []) {
  if (Array.isArray(node.children)) {
    for (const c of node.children) {
      bag.push(c)
      descendants(c, bag)
    }
  }
  return bag
}

const checked = computed(() => props.selected.includes(toKey(props.node.id)))

const indeterminate = computed(() => {
  if (!hasChildren.value) return false
  const kids = descendants(props.node).map(k => toKey(k.id))
  if (kids.length === 0) return false
  const checkedCount = kids.reduce((n, k) => n + (props.selected.includes(k) ? 1 : 0), 0)
  return checkedCount > 0 && checkedCount < kids.length
})

const cbRef = ref(null)
watch([indeterminate], () => {
  if (cbRef.value) cbRef.value.indeterminate = indeterminate.value
}, { immediate: true })

function toggleSelf() {
  const set = new Set(props.selected)
  const selfKey = toKey(props.node.id)
  if (set.has(selfKey)) set.delete(selfKey)
  else set.add(selfKey)
  emit('update:selected', Array.from(set))
}
</script>

<template>
  <li>
    <div class="flex items-center select-none" :style="{ paddingLeft: (level * 16) + 'px' }">
      <button
        v-if="hasChildren"
        class="mr-2 w-5 h-5 text-center border rounded"
        :aria-label="open ? 'Collapse' : 'Expand'"
        @click="open = !open"
      >
        {{ open ? '−' : '+' }}
      </button>
      <span v-else class="mr-2 w-5 h-5"></span>

      <input
        ref="cbRef"
        type="checkbox"
        class="mr-2 h-4 w-4"
        :checked="checked"
        @change="toggleSelf"
      />

      <span class="text-sm">{{ node.name }}</span>
    </div>

    <ul v-if="hasChildren && open" class="mt-1 space-y-1">
      <CategoryTreeNode
        v-for="child in node.children"
        :key="child.id"
        :node="child"
        :level="level + 1"
        :selected="selected"
        @update:selected="emit('update:selected', $event)"
        :expand-all="expandAll"
      />
    </ul>
  </li>
</template>
