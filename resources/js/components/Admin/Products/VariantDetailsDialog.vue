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

const count = (value) => Number(value || 0).toLocaleString()

// No cost history is stored as 0, which would read as a real cost of ₦0.00.
const knownCost = (value) => (Number(value) > 0 ? Number(value) : null)

const margin = computed(() => {
    const price = Number(props.variant?.price?.current ?? 0)
    const averageCost = knownCost(props.variant?.average_cost)
    const lastCost = knownCost(props.variant?.last_purchase_price)
    const cost = averageCost ?? lastCost

    if (price <= 0 || cost === null) {
        return null
    }

    return {
        amount: price - cost,
        percent: ((price - cost) / price) * 100,
        basis: averageCost !== null ? 'vs avg. cost' : 'vs last cost',
    }
})

const metricGroups = computed(() => {
    const variant = props.variant

    if (!variant) {
        return []
    }

    const sales = variant.sales ?? { units_sold: 0, orders_count: 0, last_sold_at: null }

    return [
        {
            title: 'Sales',
            rows: [
                { label: 'Total sold', value: count(sales.units_sold) },
                { label: 'Orders', value: count(sales.orders_count) },
                {
                    label: 'Last sold',
                    value: sales.last_sold_at ? formatDate(sales.last_sold_at) : 'Never',
                    hint: sales.last_sold_at ? formatRelative(sales.last_sold_at) : null,
                },
            ],
        },
        {
            title: 'Stock',
            rows: [
                { label: 'On hand', value: count(variant.stock.on_hand) },
                { label: 'Reserved', value: count(variant.stock.reserved) },
                { label: 'Available', value: count(variant.stock.available) },
                { label: 'Reorder point', value: variant.reorder_point ?? '—' },
            ],
        },
        {
            title: 'Pricing',
            rows: [
                {
                    label: 'Price',
                    value: money(variant.price.current),
                    hint: variant.price.has_discount ? money(variant.price.regular) : null,
                    hintClass: 'line-through',
                },
                { label: 'Cost', value: knownCost(variant.last_purchase_price) !== null ? money(variant.last_purchase_price) : '—' },
                { label: 'Avg. cost', value: knownCost(variant.average_cost) !== null ? money(variant.average_cost) : '—' },
                {
                    label: 'Margin',
                    value: margin.value ? money(margin.value.amount) : '—',
                    hint: margin.value ? `${margin.value.percent.toFixed(1)}% ${margin.value.basis}` : null,
                    valueClass: margin.value
                        ? (margin.value.amount >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400')
                        : '',
                },
            ],
        },
    ]
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
        paused: 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-200',
        discontinued: 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-200',
    }[status] || 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-200'
}

const badgeClass = 'inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold'
const iconButtonClass = 'inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100'
const factLabelClass = 'text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-400 dark:text-slate-500'
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent v-if="variant" class="max-h-[90vh] grid-cols-[minmax(0,1fr)] gap-4 overflow-y-auto rounded-2xl p-5 sm:max-w-2xl">
            <DialogHeader class="gap-0 pr-8 text-left sm:text-left">
                <div class="flex items-center gap-3">
                    <img
                        v-if="variant.image"
                        :src="variant.image"
                        alt=""
                        class="h-11 w-11 shrink-0 rounded-xl border border-slate-200 object-cover dark:border-slate-700"
                    />
                    <div v-else class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-400 dark:bg-slate-800">
                        <Package class="h-5 w-5" aria-hidden="true" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <DialogTitle class="line-clamp-2 text-base font-semibold leading-tight text-slate-900 dark:text-slate-100" :title="variant.label">
                            {{ variant.label }}
                        </DialogTitle>
                        <DialogDescription class="mt-0.5 truncate text-xs text-slate-500 dark:text-slate-400">
                            {{ productName }}<template v-if="variant.sku"> · <span class="font-mono">{{ variant.sku }}</span></template>
                        </DialogDescription>
                    </div>
                </div>
                <div class="mt-3 flex flex-wrap gap-1.5">
                    <span
                        :class="[badgeClass, variant.stock.is_in_stock
                            ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-200'
                            : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300']"
                    >
                        {{ variant.stock.is_in_stock ? 'In stock' : 'Out of stock' }}
                    </span>
                    <span :class="[badgeClass, replenishmentClass(variant.replenishment_status)]">
                        {{ variant.replenishment_status_label || 'Reorderable' }}
                    </span>
                    <span v-if="variant.stock.is_dropshipping" :class="[badgeClass, 'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-200']">
                        Dropshipping
                    </span>
                </div>
            </DialogHeader>

            <div
                v-if="variant.barcode"
                class="flex flex-wrap items-center gap-x-3 gap-y-2 rounded-xl border border-slate-200 p-2 pl-2.5 dark:border-slate-700"
            >
                <div v-if="variant.barcode_image" class="rounded-md bg-white px-2 py-1 ring-1 ring-slate-200 dark:ring-slate-600">
                    <img :src="variant.barcode_image" :alt="`Barcode ${variant.barcode}`" class="h-8 w-auto" />
                </div>
                <span class="break-all font-mono text-sm font-semibold tracking-wider text-slate-900 dark:text-slate-100">{{ variant.barcode }}</span>
                <div class="ml-auto flex items-center gap-0.5">
                    <button
                        type="button"
                        :class="iconButtonClass"
                        :aria-label="copied ? 'Barcode copied' : 'Copy barcode'"
                        :title="copied ? 'Copied' : 'Copy barcode'"
                        @click="copyBarcode"
                    >
                        <Check v-if="copied" class="h-4 w-4 text-emerald-600 dark:text-emerald-400" aria-hidden="true" />
                        <Copy v-else class="h-4 w-4" aria-hidden="true" />
                    </button>
                    <a :href="variant.labels_url" :class="iconButtonClass" aria-label="Print barcode labels" title="Print labels">
                        <Printer class="h-4 w-4" aria-hidden="true" />
                    </a>
                </div>
            </div>
            <p v-else class="rounded-xl border border-dashed border-slate-300 px-3 py-2 text-xs text-slate-500 dark:border-slate-700 dark:text-slate-400">
                No barcode assigned to this variant.
            </p>

            <div class="grid divide-y divide-slate-200 overflow-hidden rounded-xl border border-slate-200 dark:divide-slate-700 dark:border-slate-700 sm:grid-cols-3 sm:divide-x sm:divide-y-0">
                <section v-for="group in metricGroups" :key="group.title" class="px-3.5 py-3">
                    <h3 class="mb-1.5 text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-400 dark:text-slate-500">{{ group.title }}</h3>
                    <dl class="space-y-1.5">
                        <div v-for="row in group.rows" :key="row.label" class="flex items-baseline justify-between gap-3 text-sm">
                            <dt class="shrink-0 text-slate-500 dark:text-slate-400">{{ row.label }}</dt>
                            <dd class="min-w-0 text-right">
                                <span class="font-semibold tabular-nums text-slate-900 dark:text-slate-100" :class="row.valueClass">{{ row.value }}</span>
                                <span v-if="row.hint" class="block text-[11px] text-slate-400 dark:text-slate-500" :class="row.hintClass">{{ row.hint }}</span>
                            </dd>
                        </div>
                    </dl>
                </section>
            </div>

            <dl class="grid grid-cols-2 gap-x-4 gap-y-3 sm:grid-cols-4">
                <div>
                    <dt :class="factLabelClass">Fulfillment</dt>
                    <dd class="mt-0.5 text-sm text-slate-900 dark:text-slate-100">{{ variant.stock.is_dropshipping ? 'Dropshipping' : 'Stocked locally' }}</dd>
                </div>
                <div>
                    <dt :class="factLabelClass">Replenishment</dt>
                    <dd class="mt-0.5 text-sm text-slate-900 dark:text-slate-100">{{ variant.replenishment_status_label || 'Reorderable' }}</dd>
                </div>
                <div>
                    <dt :class="factLabelClass">Tracking</dt>
                    <dd class="mt-0.5 text-sm text-slate-900 dark:text-slate-100">{{ variant.track_inventory ? 'On' : 'Off' }}</dd>
                </div>
                <div>
                    <dt :class="factLabelClass">Created</dt>
                    <dd class="mt-0.5 text-sm text-slate-900 dark:text-slate-100">{{ formatDate(variant.created_at) }}</dd>
                </div>
                <div v-if="variant.supplier" class="col-span-2 sm:col-span-4">
                    <dt :class="factLabelClass">Supplier</dt>
                    <dd class="mt-0.5 text-sm text-slate-900 dark:text-slate-100">
                        {{ variant.supplier.name || 'Not set' }}
                        <span v-if="variant.supplier.cost !== null" class="text-slate-500 dark:text-slate-400"> · {{ money(variant.supplier.cost) }}</span>
                        <span v-if="variant.supplier.lead_time_days !== null" class="text-slate-500 dark:text-slate-400"> · {{ variant.supplier.lead_time_days }}-day lead time</span>
                    </dd>
                </div>
                <div v-if="variant.replenishment_note" class="col-span-2 sm:col-span-4">
                    <dt :class="factLabelClass">Replenishment note</dt>
                    <dd class="mt-0.5 text-sm text-slate-600 dark:text-slate-300">{{ variant.replenishment_note }}</dd>
                </div>
            </dl>
        </DialogContent>
    </Dialog>
</template>
