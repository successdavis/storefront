<script setup>
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { formatDate, formatRelative } from '@/lib/datetime'
import { Check, Copy, Package, Printer } from 'lucide-vue-next'
import { computed, ref, watch } from 'vue'

const props = defineProps({
    variant: {
        type: Object,
        default: null,
    },
    productName: {
        type: String,
        default: '',
    },
})

const open = defineModel('open', { type: Boolean, default: false })

const copied = ref(false)
let copiedTimer = null

const money = (value) => new Intl.NumberFormat('en-NG', {
    style: 'currency',
    currency: 'NGN',
}).format(Number(value || 0))

const sales = computed(() => props.variant?.sales ?? { units_sold: 0, orders_count: 0, last_sold_at: null })

const margin = computed(() => {
    const price = Number(props.variant?.price?.current ?? 0)
    const cost = Number(props.variant?.average_cost ?? 0)

    if (price <= 0 || cost <= 0) {
        return null
    }

    return { amount: price - cost, percent: ((price - cost) / price) * 100 }
})

watch(open, (isOpen) => {
    if (!isOpen) {
        copied.value = false
        clearTimeout(copiedTimer)
    }
})

async function copyBarcode() {
    try {
        await navigator.clipboard.writeText(props.variant.barcode)
        copied.value = true
        clearTimeout(copiedTimer)
        copiedTimer = setTimeout(() => {
            copied.value = false
        }, 2000)
    } catch {
        copied.value = false
    }
}

function replenishmentClass(status) {
    return {
        paused: 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-200',
        discontinued: 'bg-rose-100 text-rose-700 dark:bg-rose-950/40 dark:text-rose-200',
    }[status] || 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-200'
}

const labelClass = 'text-xs font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400'
const tileClass = 'rounded-2xl border border-slate-200 px-4 py-3 dark:border-slate-700'
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent v-if="variant" class="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
            <DialogHeader>
                <div class="flex items-start gap-4 pr-6">
                    <img
                        v-if="variant.image"
                        :src="variant.image"
                        alt=""
                        class="h-16 w-16 shrink-0 rounded-2xl border border-slate-200 object-cover dark:border-slate-700"
                    />
                    <div v-else class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-slate-100 text-slate-400 dark:bg-slate-800">
                        <Package class="h-6 w-6" aria-hidden="true" />
                    </div>
                    <div class="min-w-0 text-left">
                        <DialogTitle class="text-lg leading-snug text-slate-900 dark:text-slate-100">{{ variant.label }}</DialogTitle>
                        <DialogDescription class="mt-1 text-slate-500 dark:text-slate-400">
                            {{ productName }}<template v-if="variant.sku"> · <span class="font-mono">{{ variant.sku }}</span></template>
                        </DialogDescription>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <span
                                class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                :class="variant.stock.is_in_stock
                                    ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-200'
                                    : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'"
                            >
                                {{ variant.stock.is_in_stock ? 'In stock' : 'Out of stock' }}
                            </span>
                            <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="replenishmentClass(variant.replenishment_status)">
                                {{ variant.replenishment_status_label || 'Reorderable' }}
                            </span>
                            <span
                                v-if="variant.stock.is_dropshipping"
                                class="inline-flex rounded-full bg-sky-100 px-2.5 py-0.5 text-xs font-semibold text-sky-700 dark:bg-sky-950/40 dark:text-sky-200"
                            >
                                Dropshipping
                            </span>
                        </div>
                    </div>
                </div>
            </DialogHeader>

            <section class="space-y-2">
                <h3 :class="labelClass">Barcode</h3>
                <div v-if="variant.barcode" class="flex flex-col gap-3 rounded-2xl border border-slate-200 p-4 dark:border-slate-700 sm:flex-row sm:items-center">
                    <div class="flex justify-center rounded-xl bg-white px-4 py-3 ring-1 ring-slate-200 dark:ring-slate-600">
                        <img
                            v-if="variant.barcode_image"
                            :src="variant.barcode_image"
                            :alt="`Barcode ${variant.barcode}`"
                            class="h-14 max-w-full"
                        />
                        <span v-else class="font-mono text-sm text-slate-900">{{ variant.barcode }}</span>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="break-all font-mono text-base font-semibold tracking-wider text-slate-900 dark:text-slate-100">{{ variant.barcode }}</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:border-slate-500 dark:border-slate-600 dark:text-slate-200 dark:hover:border-slate-400"
                                @click="copyBarcode"
                            >
                                <Check v-if="copied" class="h-3.5 w-3.5 text-emerald-600" aria-hidden="true" />
                                <Copy v-else class="h-3.5 w-3.5" aria-hidden="true" />
                                {{ copied ? 'Copied' : 'Copy' }}
                            </button>
                            <a
                                :href="variant.labels_url"
                                class="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:border-slate-500 dark:border-slate-600 dark:text-slate-200 dark:hover:border-slate-400"
                            >
                                <Printer class="h-3.5 w-3.5" aria-hidden="true" />
                                Print labels
                            </a>
                        </div>
                    </div>
                </div>
                <p v-else class="rounded-2xl border border-dashed border-slate-300 px-4 py-3 text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">
                    No barcode assigned to this variant.
                </p>
            </section>

            <section class="space-y-2">
                <h3 :class="labelClass">Sales</h3>
                <div class="grid gap-3 sm:grid-cols-3">
                    <div :class="tileClass">
                        <p class="text-xs text-slate-500 dark:text-slate-400">Total sold</p>
                        <p class="mt-1 text-xl font-semibold tabular-nums text-slate-900 dark:text-slate-100">{{ Number(sales.units_sold).toLocaleString() }}</p>
                    </div>
                    <div :class="tileClass">
                        <p class="text-xs text-slate-500 dark:text-slate-400">Orders</p>
                        <p class="mt-1 text-xl font-semibold tabular-nums text-slate-900 dark:text-slate-100">{{ Number(sales.orders_count).toLocaleString() }}</p>
                    </div>
                    <div :class="tileClass">
                        <p class="text-xs text-slate-500 dark:text-slate-400">Last sold</p>
                        <template v-if="sales.last_sold_at">
                            <p class="mt-1 text-sm font-semibold text-slate-900 dark:text-slate-100">{{ formatDate(sales.last_sold_at) }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ formatRelative(sales.last_sold_at) }}</p>
                        </template>
                        <p v-else class="mt-1 text-sm text-slate-500 dark:text-slate-400">Never sold</p>
                    </div>
                </div>
            </section>

            <section class="space-y-2">
                <h3 :class="labelClass">Stock</h3>
                <div class="grid gap-3 grid-cols-2 sm:grid-cols-4">
                    <div :class="tileClass">
                        <p class="text-xs text-slate-500 dark:text-slate-400">On hand</p>
                        <p class="mt-1 text-lg font-semibold tabular-nums text-slate-900 dark:text-slate-100">{{ variant.stock.on_hand }}</p>
                    </div>
                    <div :class="tileClass">
                        <p class="text-xs text-slate-500 dark:text-slate-400">Reserved</p>
                        <p class="mt-1 text-lg font-semibold tabular-nums text-slate-900 dark:text-slate-100">{{ variant.stock.reserved }}</p>
                    </div>
                    <div :class="tileClass">
                        <p class="text-xs text-slate-500 dark:text-slate-400">Available</p>
                        <p class="mt-1 text-lg font-semibold tabular-nums text-slate-900 dark:text-slate-100">{{ variant.stock.available }}</p>
                    </div>
                    <div :class="tileClass">
                        <p class="text-xs text-slate-500 dark:text-slate-400">Reorder point</p>
                        <p class="mt-1 text-lg font-semibold tabular-nums text-slate-900 dark:text-slate-100">{{ variant.reorder_point ?? '-' }}</p>
                    </div>
                </div>
            </section>

            <section class="space-y-2">
                <h3 :class="labelClass">Pricing</h3>
                <dl class="grid gap-3 grid-cols-2 sm:grid-cols-4">
                    <div :class="tileClass">
                        <dt class="text-xs text-slate-500 dark:text-slate-400">Price</dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-900 dark:text-slate-100">{{ money(variant.price.current) }}</dd>
                        <dd v-if="variant.price.has_discount" class="text-xs text-slate-500 line-through dark:text-slate-400">{{ money(variant.price.regular) }}</dd>
                    </div>
                    <div :class="tileClass">
                        <dt class="text-xs text-slate-500 dark:text-slate-400">Cost</dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-900 dark:text-slate-100">
                            {{ variant.last_purchase_price !== null ? money(variant.last_purchase_price) : '-' }}
                        </dd>
                    </div>
                    <div :class="tileClass">
                        <dt class="text-xs text-slate-500 dark:text-slate-400">Average cost</dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-900 dark:text-slate-100">
                            {{ variant.average_cost !== null ? money(variant.average_cost) : '-' }}
                        </dd>
                    </div>
                    <div :class="tileClass">
                        <dt class="text-xs text-slate-500 dark:text-slate-400">Margin</dt>
                        <dd v-if="margin" class="mt-1 text-sm font-semibold" :class="margin.amount >= 0 ? 'text-emerald-700 dark:text-emerald-300' : 'text-rose-700 dark:text-rose-300'">
                            {{ money(margin.amount) }}
                        </dd>
                        <dd v-if="margin" class="text-xs text-slate-500 dark:text-slate-400">{{ margin.percent.toFixed(1) }}% of price</dd>
                        <dd v-else class="mt-1 text-sm text-slate-500 dark:text-slate-400">-</dd>
                    </div>
                </dl>
            </section>

            <section class="space-y-2">
                <h3 :class="labelClass">Details</h3>
                <dl class="divide-y divide-slate-100 rounded-2xl border border-slate-200 text-sm dark:divide-slate-800 dark:border-slate-700">
                    <div v-if="variant.attributes.length" class="flex flex-col gap-1 px-4 py-3 sm:flex-row sm:gap-4">
                        <dt class="w-40 shrink-0 text-slate-500 dark:text-slate-400">Attributes</dt>
                        <dd class="flex flex-wrap gap-1.5">
                            <span
                                v-for="attribute in variant.attributes"
                                :key="`${attribute.name}-${attribute.value}`"
                                class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs text-slate-700 dark:bg-slate-800 dark:text-slate-200"
                            >
                                <template v-if="attribute.name">{{ attribute.name }}: </template>{{ attribute.value }}
                            </span>
                        </dd>
                    </div>
                    <div class="flex flex-col gap-1 px-4 py-3 sm:flex-row sm:gap-4">
                        <dt class="w-40 shrink-0 text-slate-500 dark:text-slate-400">Fulfillment</dt>
                        <dd class="text-slate-900 dark:text-slate-100">{{ variant.stock.is_dropshipping ? 'Dropshipping' : 'Stocked locally' }}</dd>
                    </div>
                    <div v-if="variant.supplier" class="flex flex-col gap-1 px-4 py-3 sm:flex-row sm:gap-4">
                        <dt class="w-40 shrink-0 text-slate-500 dark:text-slate-400">Supplier</dt>
                        <dd class="text-slate-900 dark:text-slate-100">
                            {{ variant.supplier.name || 'Not set' }}
                            <span v-if="variant.supplier.cost !== null" class="text-slate-500 dark:text-slate-400"> · {{ money(variant.supplier.cost) }}</span>
                            <span v-if="variant.supplier.lead_time_days !== null" class="text-slate-500 dark:text-slate-400"> · {{ variant.supplier.lead_time_days }}-day lead time</span>
                        </dd>
                    </div>
                    <div class="flex flex-col gap-1 px-4 py-3 sm:flex-row sm:gap-4">
                        <dt class="w-40 shrink-0 text-slate-500 dark:text-slate-400">Replenishment</dt>
                        <dd class="text-slate-900 dark:text-slate-100">
                            {{ variant.replenishment_status_label || 'Reorderable' }}
                            <p v-if="variant.replenishment_note" class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ variant.replenishment_note }}</p>
                        </dd>
                    </div>
                    <div class="flex flex-col gap-1 px-4 py-3 sm:flex-row sm:gap-4">
                        <dt class="w-40 shrink-0 text-slate-500 dark:text-slate-400">Inventory tracking</dt>
                        <dd class="text-slate-900 dark:text-slate-100">{{ variant.track_inventory ? 'On' : 'Off' }}</dd>
                    </div>
                    <div class="flex flex-col gap-1 px-4 py-3 sm:flex-row sm:gap-4">
                        <dt class="w-40 shrink-0 text-slate-500 dark:text-slate-400">Created</dt>
                        <dd class="text-slate-900 dark:text-slate-100">{{ formatDate(variant.created_at) }}</dd>
                    </div>
                </dl>
            </section>
        </DialogContent>
    </Dialog>
</template>
