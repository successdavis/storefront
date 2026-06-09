<script setup>
import { reactive, onBeforeUnmount } from 'vue'

// composables
import { useImages } from '@/components/composables/useImages'
import { useValidation } from '@/components/composables/useValidation'
import { useSkuCheck } from '@/components/composables/useSkuCheck'
import { useVariantSelection } from '@/components/composables/useVariantSelection'
import { useVariantDetailsModal } from '@/components/composables/useVariantDetailsModal'

const props = defineProps({
    modelValue: { type: Array, default: () => [] },          // rows
    variantTypes: { type: Array, required: true },            // [{id,name,values:[{id,value}]}]
    storageBase: { type: String, default: '/storage' },
    skuCheckUrl: { type: String, default: '/admin/skus/check' },
    isEdit: {type: Boolean, default: false},
    suppliers: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:modelValue'])

// rows mirror v-model (kept here; passed into composables)
const rows = reactive([...(props.modelValue || [])])

// images / file previews
const { normalizeToUrl, previewSrc, revokePreview, onFileChange } = useImages(props)

// validation
const { errors, setErr, validateNonNegNumber, validateTableRow, tableErrorCount } = useValidation(rows)

// live SKU check
const { skuStatus, onSkuInput, applySuggestedSku, clearSkuState, pruneSkuStatus } =
    useSkuCheck(props, rows, setErr, validateTableRow)

// selection + row generation + syncing with props
const {
    state, selectionDirty, toggleValue, isActive,
    resolveValueNames
} = useVariantSelection(props, rows, emit, revokePreview, pruneSkuStatus)

// details modal
const {
    showModal, editingIndex, draft,
    openDetails, closeDetails, validateModalDraft,
    applyDetails, onModalFileChange, previewSrcModal
} = useVariantDetailsModal(rows, emit, errors, setErr, validateNonNegNumber, revokePreview, normalizeToUrl)

function emitRows() {
    emit('update:modelValue', rows.map(row => ({
        ...row,
        value_ids: Array.isArray(row.value_ids) ? [...row.value_ids] : [],
        images: Array.isArray(row.images) ? [...row.images] : [],
    })))
}

// remove a row
function removeRow(idx) {
    const victim = rows[idx]
    if (!victim) {
        return
    }

    if (victim.id) {
        victim.archived = true
        emitRows()
        return
    }

    revokePreview(victim)
    const next = rows.slice()
    next.splice(idx, 1)
    rows.splice(0, rows.length, ...next)
    clearSkuState(idx)
    emitRows()
}

onBeforeUnmount(() => {
    rows.forEach(r => revokePreview(r))
    Object.keys(skuStatus).forEach(k => clearSkuState(Number(k)))
})
</script>

<template>
    <div class="space-y-4">
        <!-- Variant pickers -->
        <div v-if="variantTypes && variantTypes.length" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div v-for="t in variantTypes" :key="t.id" class="border rounded p-3">
                <div class="font-medium mb-2">{{ t.name }}</div>

                <div v-if="t.values && t.values.length" class="flex flex-wrap gap-2">
                    <button
                        v-for="v in t.values"
                        :key="v.id"
                        class="px-2 py-1 border rounded"
                        :class="isActive(t.id, v.id) ? 'bg-blue-600 text-white' : ''"
                        @click.prevent="toggleValue(t.id, v.id)"
                    >
                        {{ v.value }}
                    </button>
                </div>

                <div v-else class="text-xs text-gray-500">No values defined for this type</div>
            </div>
        </div>

        <!-- Error summary -->
        <div v-if="tableErrorCount" class="rounded border border-red-300 bg-red-50 text-red-700 px-3 py-2 text-sm
                    dark:bg-red-900/30 dark:border-red-700 dark:text-red-400">
            {{ tableErrorCount }} issue(s) found in the table. Fields with problems are highlighted below.
        </div>

        <div
            v-if="rows.some(row => row.archived)"
            class="rounded border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:border-amber-700 dark:bg-amber-900/20 dark:text-amber-300"
        >
            Existing variants removed from the selected combinations stay attached to the product record and will be archived on save instead of being silently replaced.
        </div>

        <!-- Variant table -->
        <div class="border rounded overflow-x-auto bg-white dark:bg-gray-900 dark:border-gray-700">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-800">
                <tr>
                    <th class="p-2 text-left">Values</th>
                    <th class="p-2">SKU</th>
                    <th class="p-2">Purchase Price</th>
                    <th class="p-2">Price</th>
                    <th class="p-2">Quantity</th>
                    <th class="p-2">Barcode</th>
                    <th class="p-2">Actions</th>
                </tr>
                </thead>

                <tbody>
                <tr
                    v-for="(r, i) in rows"
                    :key="i"
                    class="border-t dark:border-gray-700 align-top"
                    :class="r.archived ? 'bg-amber-50/70 dark:bg-amber-950/20 opacity-80' : ''"
                >
                    <td class="p-2 align-middle">
                        <div class="flex flex-col gap-1">
                            <span class="text-gray-700">{{ resolveValueNames(r.value_ids).join(' / ') }}</span>
                            <span
                                v-if="r.archived"
                                class="text-xs font-medium text-amber-700 dark:text-amber-300"
                            >
                                Archived on save
                            </span>
                        </div>
                    </td>

                    <td class="p-2">
                        <input
                            v-model="r.sku"
                            class="border rounded px-2 w-full py-1 bg-white dark:bg-gray-800 dark:border-gray-600 dark:text-gray-100"
                            @input="onSkuInput(i); emitRows()"
                            @blur="validateTableRow(i)"
                            autocomplete="off"
                            :disabled="r.id || r.archived"
                            autocapitalize="characters"
                            spellcheck="false"
                            aria-invalid="true"
                            :aria-errormessage="errors.table[i]?.sku ? `err-sku-${i}` : undefined"
                        />
                        <p v-if="skuStatus[i]?.loading" class="text-xs text-gray-500 dark:text-gray-400 mt-1">Checking…</p>
                        <p v-else-if="skuStatus[i]?.available === false" :id="`err-sku-${i}`" class="text-xs text-red-600 mt-1">
                            SKU already in use.
                            <button v-if="skuStatus[i]?.suggestion" type="button" class="ml-2 underline text-blue-600" @click="applySuggestedSku(i)">
                                Use {{ skuStatus[i].suggestion }}
                            </button>
                        </p>
                        <p v-else-if="skuStatus[i]?.available === true && r.sku" class="text-xs dark:text-red-400 text-green-600 mt-1">Available</p>
                    </td>
                    <td class="p-2">
                        <input
                            v-model.number="r.last_purchase_price"
                            type="number" min="0"
                            :disabled="r.id || r.archived"
                            class="border rounded px-2 w-full py-1"
                            :class="errors.table[i]?.last_purchase_price ? 'border-red-400 bg-red-50' : ''"
                            @input="emitRows()"
                            @blur="validateTableRow(i)"
                            :aria-errormessage="errors.table[i]?.last_purchase_price ? `err-qty-${i}` : undefined"
                        />
                        <p v-if="errors.table[i]?.last_purchase_price" :id="`err-qty-${i}`" class="text-xs text-red-600 dark:text-red-400 mt-1">{{ errors.table[i].last_purchase_price }}</p>
                    </td>

                    <td class="p-2">
                        <input
                            v-model.number="r.regular_price"
                            type="number" step="0.01" min="0"
                            :disabled="r.archived"
                            class="border rounded w-full py-1 px-2"
                            :class="errors.table[i]?.regular_price ? 'border-red-400 ' : ''"
                            @input="emitRows()"
                            @blur="validateTableRow(i)"
                            :aria-errormessage="errors.table[i]?.regular_price ? `err-regular-${i}` : undefined"
                        />
                        <p v-if="errors.table[i]?.regular_price" :id="`err-regular-${i}`" class="text-xs dark:text-red-400 text-red-600 mt-1">{{ errors.table[i].regular_price }}</p>
                    </td>

                    <td class="p-2">
                        <input
                            v-model.number="r.quantity"
                            type="number" min="0"
                            :disabled="r.id || r.archived || r.fulfillment_type === 'dropshipping'"
                            class="border rounded px-2 w-full py-1"
                            :class="errors.table[i]?.quantity ? 'border-red-400 bg-red-50' : ''"
                            @input="emitRows()"
                            @blur="validateTableRow(i)"
                            :aria-errormessage="errors.table[i]?.quantity ? `err-qty-${i}` : undefined"
                        />
                        <p v-if="errors.table[i]?.quantity" :id="`err-qty-${i}`" class="text-xs text-red-600 dark:text-red-400 mt-1">{{ errors.table[i].quantity }}</p>
                    </td>

                    <td class="p-2">
                        <input
                            v-model="r.barcode"
                            :disabled="r.archived"
                            class="border rounded px-2 w-full py-1"
                            :class="errors.table[i]?.barcode ? 'border-red-400 bg-red-50' : ''"
                            @input="emitRows()"
                            @blur="validateTableRow(i)"
                            placeholder="Optional"
                            :aria-errormessage="errors.table[i]?.barcode ? `err-barcode-${i}` : undefined"
                        />
                        <p v-if="errors.table[i]?.barcode" :id="`err-barcode-${i}`" class="text-xs text-red-600 dark:text-red-400 mt-1">{{ errors.table[i].barcode }}</p>
                    </td>

                    <td class="p-2">
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                class="px-3 py-1.5 rounded bg-blue-600 text-white hover:bg-blue-700 dark:hover:bg-blue-500 disabled:cursor-not-allowed disabled:opacity-60"
                                :disabled="r.archived"
                                @click="openDetails(r, i)"
                                title="Open details"
                            >
                                Details
                            </button>
                            <button
                                type="button"
                                class="px-3 py-1.5 rounded bg-red-50 text-red-600 hover:bg-red-100 dark:bg-red-900/30 dark:text-red-400 dark:hover:bg-red-800/50"
                                @click="removeRow(i)"
                                aria-label="Remove variant"
                                :title="r.id ? 'Archive variant' : 'Remove variant'"
                            >
                                {{ r.id ? 'Archive' : 'Remove' }}
                            </button>
                        </div>
                    </td>
                </tr>

                <tr v-if="rows.length === 0">
                    <td class="p-3 text-center text-gray-500" colspan="7">No variants. Select values above to generate them.</td>
                </tr>
                </tbody>
            </table>
        </div>

        <!-- Details Modal -->
        <div
            v-if="showModal"
            class="fixed inset-0 z-40 flex items-center justify-center"
            aria-modal="true"
            role="dialog"
        >
            <div class="absolute inset-0 bg-black/40" @click="closeDetails()" />
            <div class="relative z-50 w-full max-w-3xl bg-white dark:bg-gray-900 rounded-lg shadow-lg p-4 text-gray-800 dark:text-gray-200" @keydown.esc="closeDetails()">
                <div class="flex items-center justify-between border-b pb-2 mb-4 dark:border-gray-700">
                    <h3 class="text-base font-semibold">Variant details</h3>
                    <button class="p-1 rounded hover:bg-gray-100 dark:hover:bg-gray-700" @click="closeDetails()" aria-label="Close">✕</button>
                </div>

                <div v-if="errors.modal[editingIndex ?? -1] && Object.keys(errors.modal[editingIndex ?? -1]).length"
                     class="rounded border border-red-200 bg-red-50 text-red-700 px-3 py-2 text-sm mb-3
                            dark:bg-red-900/30 dark:border-red-700 dark:text-red-400">
                    Please correct the highlighted fields below.
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div class="md:col-span-2 rounded border border-gray-200 p-3 dark:border-gray-700">
                        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                            <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-100">Fulfillment Method</h4>
                            <div v-if="draft.fulfillment_type === 'dropshipping'" class="flex flex-wrap gap-1 text-[11px]">
                                <span class="rounded-full bg-sky-100 px-2 py-0.5 font-medium text-sky-700 dark:bg-sky-950/40 dark:text-sky-200">Supplier Fulfilled</span>
                                <span class="rounded-full bg-emerald-100 px-2 py-0.5 font-medium text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-200">No Local Stock Required</span>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            <label class="flex flex-col gap-1">
                                <span class="text-xs text-gray-500">Fulfillment Type</span>
                                <select
                                    v-model="draft.fulfillment_type"
                                    class="border rounded px-2 py-1 bg-white dark:bg-gray-800 dark:border-gray-600 dark:text-gray-100"
                                >
                                    <option value="stocked">Stocked Inventory</option>
                                    <option value="dropshipping">Dropshipping</option>
                                </select>
                            </label>

                            <label v-if="draft.fulfillment_type === 'dropshipping'" class="flex flex-col gap-1">
                                <span class="text-xs text-gray-500">Supplier</span>
                                <select
                                    v-model="draft.default_supplier_id"
                                    class="border rounded px-2 py-1 bg-white dark:bg-gray-800 dark:border-gray-600 dark:text-gray-100"
                                >
                                    <option :value="null">No supplier selected</option>
                                    <option v-for="supplier in suppliers" :key="supplier.id" :value="supplier.id">
                                        {{ supplier.name }}{{ supplier.active === false ? ' (inactive)' : '' }}
                                    </option>
                                </select>
                            </label>

                            <label v-if="draft.fulfillment_type === 'dropshipping'" class="flex flex-col gap-1">
                                <span class="text-xs text-gray-500">Supplier Cost</span>
                                <input
                                    v-model.number="draft.supplier_cost"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    class="border rounded px-2 py-1"
                                    :class="errors.modal[editingIndex ?? -1]?.supplier_cost ? 'border-red-400 bg-red-50' : ''"
                                    @blur="validateModalDraft(editingIndex ?? -1)"
                                />
                                <span v-if="errors.modal[editingIndex ?? -1]?.supplier_cost" class="text-xs text-red-600 dark:text-red-400">
                                    {{ errors.modal[editingIndex ?? -1].supplier_cost }}
                                </span>
                            </label>

                            <label v-if="draft.fulfillment_type === 'dropshipping'" class="flex flex-col gap-1">
                                <span class="text-xs text-gray-500">Supplier Lead Time (days)</span>
                                <input
                                    v-model.number="draft.supplier_lead_time_days"
                                    type="number"
                                    min="0"
                                    class="border rounded px-2 py-1"
                                    :class="errors.modal[editingIndex ?? -1]?.supplier_lead_time_days ? 'border-red-400 bg-red-50' : ''"
                                    @blur="validateModalDraft(editingIndex ?? -1)"
                                />
                                <span v-if="errors.modal[editingIndex ?? -1]?.supplier_lead_time_days" class="text-xs text-red-600 dark:text-red-400">
                                    {{ errors.modal[editingIndex ?? -1].supplier_lead_time_days }}
                                </span>
                            </label>

                            <label v-if="draft.fulfillment_type === 'dropshipping'" class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-300">
                                <input v-model="draft.show_as_available_when_dropshipping" type="checkbox" />
                                Show as available
                            </label>

                            <label v-if="draft.fulfillment_type === 'dropshipping'" class="flex flex-col gap-1 md:col-span-2">
                                <span class="text-xs text-gray-500">Dropshipping Note</span>
                                <textarea
                                    v-model="draft.dropshipping_note"
                                    rows="2"
                                    placeholder="Internal dropshipping note"
                                    class="border rounded px-2 py-1"
                                />
                            </label>
                        </div>
                    </div>

                    <label class="flex flex-col gap-1">
                        <span class="text-xs text-gray-500">Weight (kg)</span>
                        <input
                            v-model.number="draft.weight"
                            type="number" step="0.001" min="0"
                            class="border rounded px-2 py-1"
                            :class="errors.modal[editingIndex ?? -1]?.weight ? 'border-red-400 bg-red-50' : ''"
                            @blur="validateModalDraft(editingIndex ?? -1)"
                        />
                        <span v-if="errors.modal[editingIndex ?? -1]?.weight" class="text-xs text-red-600">
              {{ errors.modal[editingIndex ?? -1].weight }}
            </span>
                    </label>

                    <label class="flex flex-col gap-1">
                        <span class="text-xs text-gray-500">Length (cm)</span>
                        <input
                            v-model.number="draft.length"
                            type="number" step="0.1" min="0"
                            class="border rounded px-2 py-1"
                            :class="errors.modal[editingIndex ?? -1]?.length ? 'border-red-400 bg-red-50' : ''"
                            @blur="validateModalDraft(editingIndex ?? -1)"
                        />
                        <span v-if="errors.modal[editingIndex ?? -1]?.length" class="text-xs text-red-600 dark:text-red-400 mt-1">
              {{ errors.modal[editingIndex ?? -1].length }}
            </span>
                    </label>

                    <label class="flex flex-col gap-1">
                        <span class="text-xs text-gray-500">Width (cm)</span>
                        <input
                            v-model.number="draft.width"
                            type="number" step="0.1" min="0"
                            class="border rounded px-2 py-1"
                            :class="errors.modal[editingIndex ?? -1]?.width ? 'border-red-400 bg-red-50' : ''"
                            @blur="validateModalDraft(editingIndex ?? -1)"
                        />
                        <span v-if="errors.modal[editingIndex ?? -1]?.width" class="text-xs text-red-600 dark:text-red-400 mt-1">
              {{ errors.modal[editingIndex ?? -1].width }}
            </span>
                    </label>

                    <label class="flex flex-col gap-1">
                        <span class="text-xs text-gray-500">Height (cm)</span>
                        <input
                            v-model.number="draft.height"
                            type="number" step="0.1" min="0"
                            class="border rounded px-2 py-1"
                            :class="errors.modal[editingIndex ?? -1]?.height ? 'border-red-400 bg-red-50' : ''"
                            @blur="validateModalDraft(editingIndex ?? -1)"
                        />
                        <span v-if="errors.modal[editingIndex ?? -1]?.height" class="text-xs text-red-600 dark:text-red-400 mt-1">
              {{ errors.modal[editingIndex ?? -1].height }}
            </span>
                    </label>

                    <div class="md:col-span-2">
                        <span class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Image</span>
                        <div class="flex items-center gap-3">
                            <input class="block text-gray-800 dark:text-gray-200" type="file" accept="image/*" @change="onModalFileChange" />
                            <div v-if="previewSrcModal()" class="mt-1">
                                <img :src="previewSrcModal()" alt="preview" class="w-16 h-16 object-cover rounded border" />
                            </div>
                        </div>
                        <p v-if="errors.modal[editingIndex ?? -1]?.images" class="text-xs text-red-600 mt-1">
                            {{ errors.modal[editingIndex ?? -1].images }}
                        </p>
                    </div>
                </div>

                <div class="mt-5 flex items-center justify-end gap-2">
                    <button class="px-3 py-1.5 rounded bg-gray-100 hover:bg-gray-200
                                   dark:bg-gray-800 dark:hover:bg-gray-700" @click="closeDetails()">Cancel</button>
                    <button class="px-3 py-1.5 rounded bg-blue-600 text-white hover:bg-blue-700 dark:hover:bg-blue-500"
                            @click="applyDetails()">Save</button>
                </div>
            </div>
        </div>
    </div>
</template>
