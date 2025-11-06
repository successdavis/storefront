<template>
    <Dialog :open="open" @update:open="val => !val && close()">
        <DialogContent class="sm:max-w-3xl rounded-xl p-6">
            <DialogHeader class="border-b pb-4">
                <DialogTitle class="text-xl font-semibold">Receive Items</DialogTitle>
            </DialogHeader>

            <form @submit.prevent="submit" class="space-y-6">
                <!-- Error message -->
                <div v-if="form.hasErrors" class="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-600">
                    <p v-for="(msg, key) in form.errors" :key="key">{{ msg }}</p>
                </div>

                <!-- Warehouse & Date -->
                <div class="grid grid-cols-2 gap-6">
                    <div class="items-center gap-3">
                        <label class=" text-sm font-medium">Warehouse</label>
                        <select v-model="form.warehouse_id"
                                class="flex-1 rounded border w-full block py-2">
                            <option class="dark:text-black" value="">Select warehouse</option>
                            <option class="dark:text-black" v-for="w in warehouses" :key="w.id" :value="w.id">
                                {{ w.name }}
                            </option>
                        </select>
                    </div>

                    <div class="gap-3">
                        <label class="text-sm font-medium">Received Date</label>
                        <input type="date" v-model="form.received_date"
                               class="flex-1 block w-full rounded border px-3 py-2" />
                    </div>
                </div>

                <!-- Items -->
                <div>
                    <div class="grid grid-cols-5 gap-3 border-b mb-3">
                        <h4 class="mb-3 text-base col-span-3 font-medium ">Items to Receive</h4>
                        <label class="w-28 text-sm font-medium">Qty Received</label>
                        <label class="w-28 text-sm font-medium">Unit Cost</label>
                    </div>

                    <div v-for="poItem in filteredItems" :key="poItem.id"
                         class="rounded-md   mb-4">
                        <div class="grid grid-cols-5 gap-3 mb-3">
                            <p class="font-medium col-span-3">
                                {{ poItem.product_variant?.sku ?? '—' }}
                            </p>

                            <!-- Qty Received -->
                            <div class="flex items-center gap-3">
                                <input type="number" min="0"
                                       :max="poItem.remaining_quantity"
                                       v-model.number="form.items[poItem.id].quantity_received"
                                       class="flex-1 rounded border px-3 py-1" />
                            </div>

                            <!-- Unit Cost -->
                            <div class="flex items-center">
                                <input type="number" min="0" step="0.01"
                                       v-model.number="form.items[poItem.id].unit_cost"
                                       class="rounded w-full border px-3 py-1" />
                            </div>

                            <input type="hidden" v-model="form.items[poItem.id].product_variant_id" />
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <DialogFooter class="border-t pt-4 flex justify-end gap-3">
                    <Button type="button" variant="outline" @click="close">Cancel</Button>
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

// Prefill items
props.order.items.forEach((it: any) => {
    form.items[it.id] = {
        purchase_order_item_id: it.id,
        product_variant_id: it.product_variant?.id ?? 0,
        quantity_received: it.remaining_quantity ?? 0,
        unit_cost: it.unit_cost ?? 0
    }
})

const filteredItems = computed(() =>
    props.order.items.filter((i: any) => i.remaining_quantity > 0)
)

function submit() {
    const backupItems = JSON.parse(JSON.stringify(form.items))

    Object.keys(form.items).forEach((key) => {
        const id = Number(key)
        const poItem = props.order.items.find((i: any) => i.id === id)
        if (!poItem || poItem.remaining_quantity <= 0) {
            delete (form.items as any)[key]
        }
    })

    form.post(route('admin.item-receipts.store', { purchaseOrder: props.order.id }), {
        onSuccess: () => {
            emit('success')
            emit('close')
        },
        onError: () => {
            // errors are auto-bound to form.errors
            // message block will display them
        },
        onFinish: () => {
            Object.keys(form.items).forEach(k => delete (form.items as any)[k])
            Object.entries(backupItems).forEach(([k, v]) => {
                (form.items as any)[k] = v
            })
        }
    })
}
</script>
