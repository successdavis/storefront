<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'

const props = defineProps<{ entry?: any }>()

const form = useForm({
  warehouse_id: props.entry?.warehouse_id || '',
  variant_id: props.entry?.variant_id || '',
  quantity: props.entry?.quantity || 0,
  unit_cost: props.entry?.unit_cost || 0,
  type: props.entry?.type || 'stock_in',
  effective_at: props.entry?.effective_at || new Date().toISOString().slice(0,16),
  reason: props.entry?.reason || '',
  note: props.entry?.note || ''
})

function submit() {
  if (props.entry) {
    form.put(route('stock-entries.update', props.entry.id))
  } else {
    form.post(route('stock-entries.store'))
  }
}
</script>

<template>
  <form @submit.prevent="submit" class="space-y-4 p-4">
    <!-- example field -->
    <div>
      <label class="block mb-1 font-medium">Quantity</label>
      <input v-model.number="form.quantity" type="number" class="border rounded-lg px-3 py-2 w-full"/>
      <div v-if="form.errors.quantity" class="text-red-600 text-sm">{{ form.errors.quantity }}</div>
    </div>
    <!-- add the rest similarly -->
    <button type="submit"
            class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
      Save
    </button>
  </form>
</template>
