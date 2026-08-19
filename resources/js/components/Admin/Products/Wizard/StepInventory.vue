<script setup>
import VariantMatrix from '@/components/Admin/Products/VariantMatrix.vue'
import MultiSelect from '@/components/MultiSelect.vue'
import axios from 'axios'
import { Boxes, Package, Truck, Warehouse } from 'lucide-vue-next'
import { computed, inject, onBeforeUnmount, reactive, ref } from 'vue'

const wizard = inject('productWizard')
const { form, mode, simpleRow, selectedTypeNames, suppliers, variantTypes, serverErr } = wizard

const variantTypeNames = computed(() => (variantTypes.value || []).map(type => type.name))
const filteredVariantTypes = computed(() =>
    (variantTypes.value || []).filter(type => selectedTypeNames.value.includes(type.name)))

const modeCards = [
    {
        value: 'simple',
        icon: Package,
        title: 'Simple product',
        text: 'One version, one price — most products. You can still add options later.',
    },
    {
        value: 'variants',
        icon: Boxes,
        title: 'Product with options',
        text: 'Comes in sizes, colours or other combinations, each with its own price and stock.',
    },
]

/* ---- Live SKU availability for the simple product ---- */
const skuStatus = reactive({ loading: false, available: null, suggestion: null })
let skuTimer = null

function checkSku() {
    skuStatus.available = null
    skuStatus.suggestion = null
    if (skuTimer) clearTimeout(skuTimer)

    const sku = String(simpleRow.sku || '').trim()
    if (!sku) return

    skuTimer = setTimeout(async () => {
        skuStatus.loading = true
        try {
            const { data } = await axios.get('/admin/skus/check', { params: { sku } })
            skuStatus.available = !!data.available
            skuStatus.suggestion = data.suggestion || null
        } catch {
            skuStatus.available = null
        } finally {
            skuStatus.loading = false
        }
    }, 350)
}

function applySuggestedSku() {
    if (!skuStatus.suggestion) return
    simpleRow.sku = skuStatus.suggestion
    checkSku()
}

onBeforeUnmount(() => {
    if (skuTimer) clearTimeout(skuTimer)
})

/* ---- Simple mode: variant image ---- */
const simpleImagePreview = ref('')

function onSimpleImageChange(event) {
    const file = event?.target?.files?.[0]
    if (!file) return
    if (simpleImagePreview.value) {
        try { URL.revokeObjectURL(simpleImagePreview.value) } catch { /* noop */ }
    }
    simpleRow.images = [file]
    simpleImagePreview.value = URL.createObjectURL(file)
}

onBeforeUnmount(() => {
    if (simpleImagePreview.value) {
        try { URL.revokeObjectURL(simpleImagePreview.value) } catch { /* noop */ }
    }
})

const margin = computed(() => {
    const price = Number(simpleRow.regular_price)
    const cost = Number(simpleRow.last_purchase_price)
    if (!Number.isFinite(price) || !Number.isFinite(cost) || price <= 0 || cost < 0) return null
    return Math.round(((price - cost) / price) * 100)
})

function fieldClass(extra = '') {
    return [
        'w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm transition',
        'text-gray-900 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100',
        'focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200 dark:focus:border-blue-400 dark:focus:ring-blue-900',
        extra,
    ].join(' ')
}
</script>

<template>
    <section class="space-y-4">
        <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h2 class="text-lg font-semibold">How is this product sold?</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                This decides whether you set one price or a price per option.
            </p>

            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <button
                    v-for="card in modeCards"
                    :key="card.value"
                    type="button"
                    class="flex items-start gap-3 rounded-xl border-2 p-4 text-left transition"
                    :class="mode === card.value
                        ? 'border-blue-600 bg-blue-50/60 dark:border-blue-400 dark:bg-blue-950/30'
                        : 'border-gray-200 hover:border-gray-300 dark:border-gray-800 dark:hover:border-gray-700'"
                    :aria-pressed="mode === card.value"
                    @click="mode = card.value"
                >
                    <span
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg"
                        :class="mode === card.value
                            ? 'bg-blue-600 text-white dark:bg-blue-500'
                            : 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400'"
                    >
                        <component :is="card.icon" class="h-5 w-5" aria-hidden="true" />
                    </span>
                    <span>
                        <span class="block text-sm font-semibold">{{ card.title }}</span>
                        <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">{{ card.text }}</span>
                    </span>
                </button>
            </div>
        </div>

        <!-- Simple product -->
        <div v-if="mode === 'simple'" class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h3 class="text-base font-semibold">Price & stock</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Just four quick numbers — the SKU is generated for you if you leave it blank.
            </p>

            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium" for="simple-price">
                        Selling price <span class="text-rose-500">*</span>
                    </label>
                    <input
                        id="simple-price"
                        v-model.number="simpleRow.regular_price"
                        type="number" min="0" step="0.01" placeholder="0.00"
                        :class="fieldClass('mt-1.5')"
                    />
                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">What the customer pays.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium" for="simple-cost">
                        Cost price <span class="text-rose-500">*</span>
                    </label>
                    <input
                        id="simple-cost"
                        v-model.number="simpleRow.last_purchase_price"
                        type="number" min="0" step="0.01" placeholder="0.00"
                        :class="fieldClass('mt-1.5')"
                    />
                    <p class="mt-1 text-xs" :class="margin !== null ? (margin > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400') : 'text-gray-400 dark:text-gray-500'">
                        <template v-if="margin !== null">≈ {{ margin }}% margin at this price</template>
                        <template v-else>What you pay your supplier — used for margins & reports.</template>
                    </p>
                </div>
                <div>
                    <label class="block text-sm font-medium" for="simple-qty">Quantity in stock</label>
                    <input
                        id="simple-qty"
                        v-model.number="simpleRow.quantity"
                        type="number" min="0" step="1"
                        :disabled="simpleRow.fulfillment_type === 'dropshipping'"
                        :class="fieldClass('mt-1.5 disabled:opacity-50')"
                    />
                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                        Counted into inventory as an opening balance.
                    </p>
                </div>
                <div>
                    <label class="block text-sm font-medium" for="simple-sku">SKU</label>
                    <input
                        id="simple-sku"
                        v-model="simpleRow.sku"
                        type="text" placeholder="Leave blank to auto-generate"
                        autocomplete="off" spellcheck="false"
                        :class="fieldClass('mt-1.5 uppercase')"
                        @input="checkSku"
                    />
                    <p v-if="skuStatus.loading" class="mt-1 text-xs text-gray-400 dark:text-gray-500">Checking availability…</p>
                    <p v-else-if="skuStatus.available === false" class="mt-1 text-xs text-rose-600 dark:text-rose-400">
                        Already in use.
                        <button v-if="skuStatus.suggestion" type="button" class="ml-1 underline" @click="applySuggestedSku">
                            Use {{ skuStatus.suggestion }}
                        </button>
                    </p>
                    <p v-else-if="skuStatus.available === true && simpleRow.sku" class="mt-1 text-xs text-emerald-600 dark:text-emerald-400">Available ✓</p>
                    <p v-else class="mt-1 text-xs text-gray-400 dark:text-gray-500">Your internal stock code.</p>
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium" for="simple-barcode">Barcode <span class="font-normal text-gray-400">(optional)</span></label>
                    <input
                        id="simple-barcode"
                        v-model="simpleRow.barcode"
                        type="text" placeholder="Scan or type the EAN / UPC"
                        autocomplete="off"
                        :class="fieldClass('mt-1.5')"
                    />
                </div>
            </div>

            <!-- Fulfillment -->
            <div class="mt-6 border-t border-gray-100 pt-5 dark:border-gray-800">
                <h4 class="text-sm font-semibold">How will you fulfil orders?</h4>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <button
                        type="button"
                        class="flex items-center gap-3 rounded-lg border p-3 text-left transition"
                        :class="simpleRow.fulfillment_type === 'stocked'
                            ? 'border-blue-600 bg-blue-50/60 dark:border-blue-400 dark:bg-blue-950/30'
                            : 'border-gray-200 hover:border-gray-300 dark:border-gray-800 dark:hover:border-gray-700'"
                        @click="simpleRow.fulfillment_type = 'stocked'"
                    >
                        <Warehouse class="h-5 w-5 shrink-0 text-gray-500 dark:text-gray-400" aria-hidden="true" />
                        <span>
                            <span class="block text-sm font-medium">From my stock</span>
                            <span class="block text-xs text-gray-500 dark:text-gray-400">You hold and ship the inventory.</span>
                        </span>
                    </button>
                    <button
                        type="button"
                        class="flex items-center gap-3 rounded-lg border p-3 text-left transition"
                        :class="simpleRow.fulfillment_type === 'dropshipping'
                            ? 'border-blue-600 bg-blue-50/60 dark:border-blue-400 dark:bg-blue-950/30'
                            : 'border-gray-200 hover:border-gray-300 dark:border-gray-800 dark:hover:border-gray-700'"
                        @click="simpleRow.fulfillment_type = 'dropshipping'"
                    >
                        <Truck class="h-5 w-5 shrink-0 text-gray-500 dark:text-gray-400" aria-hidden="true" />
                        <span>
                            <span class="block text-sm font-medium">Dropshipping</span>
                            <span class="block text-xs text-gray-500 dark:text-gray-400">A supplier ships it for you — no local stock.</span>
                        </span>
                    </button>
                </div>

                <div v-if="simpleRow.fulfillment_type === 'dropshipping'" class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium" for="simple-supplier">Supplier</label>
                        <select id="simple-supplier" v-model="simpleRow.default_supplier_id" :class="fieldClass('mt-1.5')">
                            <option :value="null">No supplier selected</option>
                            <option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">
                                {{ supplier.name }}{{ supplier.active === false ? ' (inactive)' : '' }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium" for="simple-supplier-cost">Supplier cost</label>
                        <input
                            id="simple-supplier-cost"
                            v-model.number="simpleRow.supplier_cost"
                            type="number" min="0" step="0.01"
                            :class="fieldClass('mt-1.5')"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium" for="simple-lead-time">Lead time (days)</label>
                        <input
                            id="simple-lead-time"
                            v-model.number="simpleRow.supplier_lead_time_days"
                            type="number" min="0" step="1"
                            :class="fieldClass('mt-1.5')"
                        />
                    </div>
                    <label class="flex items-center gap-2 self-end pb-2 text-sm">
                        <input v-model="simpleRow.show_as_available_when_dropshipping" type="checkbox" class="h-4 w-4 rounded border-gray-300" />
                        Show as available in the store
                    </label>
                </div>
            </div>

            <!-- Quick photo for this item -->
            <div class="mt-6 border-t border-gray-100 pt-5 dark:border-gray-800">
                <h4 class="text-sm font-semibold">Quick photo <span class="font-normal text-gray-400">(optional)</span></h4>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Attach a photo of this exact item. The main gallery comes in the next step.
                </p>
                <div class="mt-3 flex items-center gap-3">
                    <input type="file" accept="image/*" class="text-sm" @change="onSimpleImageChange" />
                    <img v-if="simpleImagePreview" :src="simpleImagePreview" alt="Preview" class="h-14 w-14 rounded-lg border object-cover dark:border-gray-700" />
                </div>
            </div>
        </div>

        <!-- Variants -->
        <template v-else>
            <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h3 class="text-base font-semibold">Which options does it come in?</h3>
                <ol class="mt-2 list-inside list-decimal space-y-1 text-sm text-gray-500 dark:text-gray-400">
                    <li>Pick the option types (e.g. Colour, Size).</li>
                    <li>Tap the values that apply — every combination becomes a variant below.</li>
                    <li>Fill in a price, cost and quantity for each row. Use <span class="font-medium text-gray-700 dark:text-gray-200">Details</span> for photos, dropshipping and more.</li>
                </ol>

                <div class="mt-4">
                    <MultiSelect v-model="selectedTypeNames" :options="variantTypeNames" placeholder="Choose option types…" />
                </div>

                <div class="mt-4">
                    <VariantMatrix
                        v-model="form.variants"
                        :is-edit="false"
                        :variant-types="filteredVariantTypes"
                        :suppliers="suppliers"
                    />
                </div>

                <p v-if="serverErr('variants')" class="mt-2 text-xs text-rose-600 dark:text-rose-400">{{ serverErr('variants') }}</p>
            </div>
        </template>
    </section>
</template>
