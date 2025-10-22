<template>
    <Drawer v-model:open="open">
        <!-- DrawerTrigger is optional if we don't rely on it; we'll control open via v-model -->
        <template #content>
            <DrawerContent class="w-full max-w-[560px]">
                <DrawerHeader>
                    <div class="flex items-center justify-between w-full">
                        <div>
                            <DrawerTitle>Sales Orders</DrawerTitle>
                            <p class="text-sm text-muted-foreground">Recent sales for this employee</p>
                        </div>

                        <div class="flex items-center gap-2">
                            <button class="btn-sm" @click="refresh" :disabled="loading">
                                Refresh
                            </button>
                            <DrawerClose class="rounded-md p-1 hover:bg-gray-100 dark:hover:bg-gray-700">✕</DrawerClose>
                        </div>
                    </div>
                </DrawerHeader>

                <DrawerBody class="px-4 pt-2 pb-6">
                    <div v-if="loading" class="py-10 text-center text-sm text-muted-foreground">Loading orders…</div>

                    <div v-else>
                        <div v-if="orders.length === 0" class="py-8 text-center text-sm text-muted-foreground">No orders yet.</div>

                        <ul class="space-y-3">
                            <li v-for="order in orders" :key="order.id" class="rounded-2xl border bg-card p-3 shadow-sm">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2">
                                            <div class="truncate font-medium">#{{ order.reference ?? order.id }}</div>
                                            <div class="text-xs text-muted-foreground">• {{ formattedDate(order.created_at) }}</div>
                                        </div>

                                        <div class="mt-1 text-xs text-muted-foreground">
                                            Items: {{ order.items_count ?? order.items?.length ?? 0 }} • Qty: {{ order.total_quantity ?? order.items?.reduce((s,i)=>s+(i.quantity||0),0) ?? 0 }}
                                        </div>

                                        <div class="mt-2 text-sm font-semibold">{{ formatCurrency(order.total_amount ?? order.total) }}</div>

                                        <div v-if="order.customer_name" class="mt-2 text-xs text-muted-foreground">Customer: {{ order.customer_name }}</div>
                                    </div>

                                    <div class="flex flex-col items-end gap-2">
                                        <button
                                            @click="printOrder(order)"
                                            :disabled="printingId === order.id"
                                            class="rounded-md border px-3 py-1 text-sm"
                                        >
                                            <span v-if="printingId === order.id">Printing…</span>
                                            <span v-else>Print</span>
                                        </button>

                                        <button @click="openOrderDetail(order.id)" class="text-xs text-primary underline">View</button>
                                    </div>
                                </div>
                            </li>
                        </ul>

                        <!-- pagination / load more -->
                        <div v-if="meta?.last_page > 1" class="mt-4 flex justify-center">
                            <button v-if="meta.current_page < meta.last_page && !loadingMore" @click="loadMore" class="rounded bg-primary/10 px-4 py-2 text-primary">
                                Load More
                            </button>
                            <div v-if="loadingMore" class="text-sm text-muted-foreground py-2">Loading…</div>
                        </div>
                    </div>
                </DrawerBody>

                <DrawerFooter class="px-4 py-3">
                    <div class="text-xs text-muted-foreground">Tip: Use the Print button to print receipts.</div>
                </DrawerFooter>
            </DrawerContent>
        </template>
    </Drawer>
</template>

<script setup>
import { ref, watch } from 'vue'
import { Drawer, DrawerContent, DrawerHeader, DrawerTitle, DrawerBody, DrawerFooter, DrawerClose } from '@/components/ui/drawer' // exact import path depends on your shadcn-vue setup
import { useOrders } from '../composables/useOrders.js'
import { useCurrencyFormatter } from '@/pages/Admin/Pos/composables/useCurrencyFormatter.js'
import { eventBus } from '@/eventBus.js'

const props = defineProps({
    modelValue: { type: Boolean, default: false }
})
const emit = defineEmits(['update:modelValue'])

const open = ref(props.modelValue)
watch(() => props.modelValue, (v) => (open.value = v))
watch(open, (v) => emit('update:modelValue', v))

const { orders, meta, loading, loadingMore, fetchOrders, loadMore } = useOrders()
const { formatCurrency } = useCurrencyFormatter()
const printingId = ref(null)

function formattedDate(dt) {
    if (!dt) return ''
    return new Date(dt).toLocaleString()
}

watch(open, async (val) => {
    if (val) {
        await fetchOrders({ page: 1, force: true })
    }
})

async function refresh() {
    await fetchOrders({ page: 1, force: true })
}

function openOrderDetail(id) {
    // open detail route - prefer Inertia but fallback to window.open
    try {
        // If using Inertia:
        // router.get(route('admin.pos.orders.show', id))
        window.open(`/admin/pos/orders/${id}`, '_blank')
    } catch {
        window.open(`/admin/pos/orders/${id}`, '_blank')
    }
}

async function printOrder(order) {
    try {
        printingId.value = order.id
        // prefer server-side print route that returns full HTML
        const url = `/admin/pos/orders/${order.id}/print`
        const res = await fetch(url, { credentials: 'same-origin' })
        const html = await res.text()

        const w = window.open('', '_blank', 'width=800,height=700')
        if (!w) throw new Error('Failed to open print window')

        w.document.open()
        w.document.write(html)
        w.document.close()
        w.focus()
        setTimeout(() => {
            try { w.print() } catch (e) { console.error(e) }
            finally { printingId.value = null }
        }, 500)
    } catch (err) {
        console.error('printOrder failed', err)
        printingId.value = null
        alert('Failed to print order')
    }
}

// auto refresh when order is placed elsewhere
eventBus.on('order-placed', () => {
    if (open.value) fetchOrders({ page: 1, force: true })
})
</script>

<style scoped>
/* optional small tweaks to DrawerContent width/transition are handled by shadcn-vue classes */
</style>
