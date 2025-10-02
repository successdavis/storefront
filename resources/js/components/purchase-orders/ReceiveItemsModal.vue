<template>
  <Dialog :open="open" @update:open="val => !val && close()">
    <DialogContent class="sm:max-w-2xl">
      <DialogHeader>
        <DialogTitle>Receive Items</DialogTitle>
      </DialogHeader>

      <form @submit.prevent="submit" class="space-y-6">
        <div class="grid grid-cols-2 gap-6">
          <!-- Warehouse -->
          <div>
            <label class="block text-sm font-medium">Warehouse</label>
            <select v-model="form.warehouse_id" class="mt-1 w-full rounded border px-2 py-1">
              <option value="">Select warehouse</option>
              <option v-for="w in warehouses" :key="w.id" :value="w.id">
                {{ w.name }}
              </option>
            </select>
          </div>

          <!-- Received Date -->
          <div>
            <label class="block text-sm font-medium">Received Date</label>
            <input type="date" v-model="form.received_date" class="mt-1 w-full rounded border px-2 py-1"/>
          </div>
        </div>

        <!-- Items -->
        <div>
          <h4 class="mb-2 text-sm font-medium">Items</h4>

          <!-- Only show items that still have remaining_quantity > 0 -->
          <div v-for="poItem in filteredItems" :key="poItem.id" class="mb-4 rounded p-3">
            <p class="font-medium">
              {{ poItem.product_variant?.sku ?? '—' }}
              <span class="ml-2 text-xs text-muted-foreground">
                Remaining: {{ poItem.remaining_quantity }}
              </span>
            </p>

            <div class="grid grid-cols-3 gap-3 mt-2">
              <!-- Quantity -->
              <div>
                <label class="block text-xs">Qty Received</label>
                <input type="number" min="0"
                       :max="poItem.remaining_quantity"
                       v-model.number="form.items[poItem.id].quantity_received"
                       class="mt-1 w-full rounded border px-2 py-1"/>
              </div>

              <!-- Unit Cost -->
              <div>
                <label class="block text-xs">Unit Cost</label>
                <input type="number" min="0" step="0.01"
                       v-model.number="form.items[poItem.id].unit_cost"
                       class="mt-1 w-full rounded border px-2 py-1"/>
              </div>

              <input type="hidden" v-model="form.items[poItem.id].product_variant_id" />
            </div>
          </div>
        </div>

        <DialogFooter>
          <Button type="submit" :disabled="form.processing">
            {{ form.processing ? 'Receiving…' : 'Submit Receipt' }}
          </Button>
        </DialogFooter>
      </form>
    </DialogContent>
  </Dialog>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog'

interface Warehouse { id: number; name: string }
interface Props {
  open: boolean
  order: any
  warehouses: Warehouse[]
}
const props = defineProps<Props>()
const emit = defineEmits(['close','success'])
function close() { emit('close') }

const form = useForm({
  warehouse_id: '',
  received_date: new Date().toISOString().substring(0,10),
  items: {} as Record<number, {
    purchase_order_item_id: number|null
    product_variant_id: number
    quantity_received: number
    unit_cost: number
  }>
})

// Pre-fill item objects for reactivity (keeps form stable)
props.order.items.forEach((it: any) => {
  form.items[it.id] = {
    purchase_order_item_id: it.id,
    product_variant_id: it.product_variant?.id ?? 0,
    quantity_received: 0,
    unit_cost: it.unit_cost ?? 0
  }
})

// Computed list for rendering (only items with remaining > 0)
const filteredItems = computed(() =>
  props.order.items.filter((i: any) => i.remaining_quantity > 0)
)

/**
 * Submit handler
 * - make a deep backup of form.items
 * - delete keys for items with remaining_quantity <= 0
 * - post using form.post (useForm serializes the current `form`)
 * - restore the original items in onFinish so modal can be reused
 */
function submit() {
  // DEBUG: uncomment to inspect what will be sent
  // console.log('Before filtering, form.items=', JSON.parse(JSON.stringify(form.items)))

  // 1) deep clone backup so we can restore later
  const backupItems = JSON.parse(JSON.stringify(form.items))

  // 2) remove zero-remaining items from the reactive form object
  //    Use string keys because object keys are strings in JS
  Object.keys(form.items).forEach((key) => {
    const id = Number(key)
    const poItem = props.order.items.find((i: any) => i.id === id)
    if (!poItem || poItem.remaining_quantity <= 0) {
      // delete the property so useForm won't send it
      // cast to any to avoid TS index complaints
      delete (form.items as any)[key]
    }
  })

  // OPTIONAL: If you also want to avoid sending items the user didn't actually enter qty for,
  // uncomment the following block to remove items with quantity_received <= 0
  /*
  Object.keys(form.items).forEach((key) => {
    const entry = (form.items as any)[key]
    if (!entry || Number(entry.quantity_received) <= 0) {
      delete (form.items as any)[key]
    }
  })
  */

  // DEBUG: uncomment to inspect the actual payload that's about to be sent
  // console.log('Sending form.items=', JSON.parse(JSON.stringify(form.items)))

  form.post(route('admin.item-receipts.store', { purchaseOrder: props.order.id }), {
    onSuccess: () => {
      emit('success')
      emit('close')
    },
    // Always restore the original form.items after request finishes (success or error)
    onFinish: () => {
      // remove whatever keys are currently in form.items (the filtered set)
      Object.keys(form.items).forEach(k => delete (form.items as any)[k])

      // restore original backup
      Object.entries(backupItems).forEach(([k, v]) => {
        // keep numeric keys as numbers (object keys are strings internally but this matches prefill usage)
        (form.items as any)[k] = v
      })
    }
  })
}
</script>
