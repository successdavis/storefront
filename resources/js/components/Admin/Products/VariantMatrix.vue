<script setup>
import { reactive, onBeforeUnmount } from 'vue'

// composables
import { useImages } from '@/Components/composables/useImages'
import { useValidation } from '@/Components/composables/useValidation'
import { useSkuCheck } from '@/Components/composables/useSkuCheck'
import { useVariantSelection } from '@/Components/composables/useVariantSelection'
import { useVariantDetailsModal } from '@/Components/composables/useVariantDetailsModal'

const props = defineProps({
    modelValue: { type: Array, default: () => [] },          // rows
    variantTypes: { type: Array, required: true },            // [{id,name,values:[{id,value}]}]
    storageBase: { type: String, default: '/storage' },
    skuCheckUrl: { type: String, default: '/admin/skus/check' },
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

// remove a row
function removeRow(idx) {
    const victim = rows[idx]
    if (victim) revokePreview(victim)
    const next = rows.slice()
    next.splice(idx, 1)
    rows.splice(0, rows.length, ...next)
    clearSkuState(idx)
    emit('update:modelValue', next)
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
        <div v-if="tableErrorCount" class="rounded border border-red-200 bg-red-50 text-red-700 px-3 py-2 text-sm">
            {{ tableErrorCount }} issue(s) found in the table. Fields with problems are highlighted below.
        </div>

        <!-- Variant table -->
        <div class="border rounded overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                <tr>
                    <th class="p-2 text-left">Values</th>
                    <th class="p-2">SKU</th>
                    <th class="p-2">Regular Price</th>
                    <th class="p-2">Sale Price</th>
                    <th class="p-2">Barcode</th>
                    <th class="p-2">Actions</th>
                </tr>
                </thead>

                <tbody>
                <tr v-for="(r, i) in rows" :key="i" class="border-t align-top">
                    <td class="p-2 align-middle">
                        <span class="text-gray-700">{{ resolveValueNames(r.value_ids).join(' / ') }}</span>
                    </td>

                    <td class="p-2">
                        <input
                            v-model="r.sku"
                            class="border rounded px-2 w-full py-1"
                            @input="onSkuInput(i)"
                            @blur="validateTableRow(i)"
                            autocomplete="off"
                            autocapitalize="characters"
                            spellcheck="false"
                            aria-invalid="true"
                            :aria-errormessage="errors.table[i]?.sku ? `err-sku-${i}` : undefined"
                        />
                        <p v-if="skuStatus[i]?.loading" class="text-xs text-gray-500 mt-1">Checking…</p>
                        <p v-else-if="skuStatus[i]?.available === false" :id="`err-sku-${i}`" class="text-xs text-red-600 mt-1">
                            SKU already in use.
                            <button v-if="skuStatus[i]?.suggestion" type="button" class="ml-2 underline text-blue-600" @click="applySuggestedSku(i)">
                                Use {{ skuStatus[i].suggestion }}
                            </button>
                        </p>
                        <p v-else-if="skuStatus[i]?.available === true && r.sku" class="text-xs text-green-600 mt-1">Available</p>
                    </td>

                    <td class="p-2">
                        <input
                            v-model.number="r.regular_price"
                            type="number" step="0.01" min="0"
                            class="border rounded w-full py-1 "
                            :class="errors.table[i]?.regular_price ? 'border-red-400 bg-red-50' : ''"
                            @blur="validateTableRow(i)"
                            :aria-errormessage="errors.table[i]?.regular_price ? `err-regular-${i}` : undefined"
                        />
                        <p v-if="errors.table[i]?.regular_price" :id="`err-regular-${i}`" class="text-xs text-red-600 mt-1">{{ errors.table[i].regular_price }}</p>
                    </td>

                    <td class="p-2">
                        <input
                            v-model.number="r.sale_price"
                            type="number" min="0"
                            class="border rounded px-2 w-full py-1"
                            :class="errors.table[i]?.sale_price ? 'border-red-400 bg-red-50' : ''"
                            @blur="validateTableRow(i)"
                            :aria-errormessage="errors.table[i]?.sale_price ? `err-qty-${i}` : undefined"
                        />
                        <p v-if="errors.table[i]?.sale_price" :id="`err-qty-${i}`" class="text-xs text-red-600 mt-1">{{ errors.table[i].sale_price }}</p>
                    </td>


                    <td class="p-2">
                        <input
                            v-model="r.barcode"
                            class="border rounded px-2 w-full py-1"
                            :class="errors.table[i]?.barcode ? 'border-red-400 bg-red-50' : ''"
                            @blur="validateTableRow(i)"
                            placeholder="Optional"
                            :aria-errormessage="errors.table[i]?.barcode ? `err-barcode-${i}` : undefined"
                        />
                        <p v-if="errors.table[i]?.barcode" :id="`err-barcode-${i}`" class="text-xs text-red-600 mt-1">{{ errors.table[i].barcode }}</p>
                    </td>

                    <td class="p-2">
                        <div class="flex items-center gap-2">
                            <button type="button" class="px-3 py-1.5 rounded bg-blue-600 text-white hover:bg-blue-700" @click="openDetails(r, i)" title="Open details">Details</button>
                            <button type="button" class="px-3 py-1.5 rounded bg-red-50 text-red-600 hover:bg-red-100" @click="removeRow(i)" aria-label="Remove variant" title="Remove">Remove</button>
                        </div>
                    </td>
                </tr>

                <tr v-if="rows.length === 0">
                    <td class="p-3 text-center text-gray-500" colspan="6">No variants. Select values above to generate them.</td>
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
            <div class="relative z-50 w-full max-w-2xl bg-white rounded-lg shadow-lg p-4" @keydown.esc="closeDetails()">
                <div class="flex items-center justify-between border-b pb-2 mb-4">
                    <h3 class="text-base font-semibold">Variant details</h3>
                    <button class="p-1 rounded hover:bg-gray-100" @click="closeDetails()" aria-label="Close">✕</button>
                </div>

                <div v-if="errors.modal[editingIndex ?? -1] && Object.keys(errors.modal[editingIndex ?? -1]).length"
                     class="rounded border border-red-200 bg-red-50 text-red-700 px-3 py-2 text-sm mb-3">
                    Please correct the highlighted fields below.
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <!--          <label class="flex flex-col gap-1">-->
                    <!--            <span class="text-xs text-gray-500">Sale price</span>-->
                    <!--            <input-->
                    <!--              v-model.number="draft.sale_price"-->
                    <!--              type="number" step="0.01" min="0"-->
                    <!--              class="border rounded px-2 py-1"-->
                    <!--              :class="errors.modal[editingIndex ?? -1]?.sale_price ? 'border-red-400 bg-red-50' : ''"-->
                    <!--              @blur="validateModalDraft(editingIndex ?? -1)"-->
                    <!--            />-->
                    <!--            <span v-if="errors.modal[editingIndex ?? -1]?.sale_price" class="text-xs text-red-600">-->
                    <!--              {{ errors.modal[editingIndex ?? -1].sale_price }}-->
                    <!--            </span>-->
                    <!--          </label>-->

                    <label class="flex flex-col gap-1">
                        <span class="text-xs text-gray-500">Sale starts</span>
                        <input
                            v-model="draft.sale_starts_at"
                            type="datetime-local"
                            class="border rounded px-2 py-1"
                            :class="errors.modal[editingIndex ?? -1]?.sale_starts_at ? 'border-red-400 bg-red-50' : ''"
                            @blur="validateModalDraft(editingIndex ?? -1)"
                        />
                        <span v-if="errors.modal[editingIndex ?? -1]?.sale_starts_at" class="text-xs text-red-600">
              {{ errors.modal[editingIndex ?? -1].sale_starts_at }}
            </span>
                    </label>

                    <label class="flex flex-col gap-1">
                        <span class="text-xs text-gray-500">Sale ends</span>
                        <input
                            v-model="draft.sale_ends_at"
                            type="datetime-local"
                            class="border rounded px-2 py-1"
                            :class="errors.modal[editingIndex ?? -1]?.sale_ends_at ? 'border-red-400 bg-red-50' : ''"
                            @blur="validateModalDraft(editingIndex ?? -1)"
                        />
                        <span v-if="errors.modal[editingIndex ?? -1]?.sale_ends_at" class="text-xs text-red-600">
              {{ errors.modal[editingIndex ?? -1].sale_ends_at }}
            </span>
                    </label>

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
                        <span v-if="errors.modal[editingIndex ?? -1]?.length" class="text-xs text-red-600">
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
                        <span v-if="errors.modal[editingIndex ?? -1]?.width" class="text-xs text-red-600">
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
                        <span v-if="errors.modal[editingIndex ?? -1]?.height" class="text-xs text-red-600">
              {{ errors.modal[editingIndex ?? -1].height }}
            </span>
                    </label>

                    <div class="md:col-span-2">
                        <span class="block text-xs text-gray-500 mb-1">Image</span>
                        <div class="flex items-center gap-3">
                            <input class="block" type="file" accept="image/*" @change="onModalFileChange" />
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
                    <button class="px-3 py-1.5 rounded bg-gray-100 hover:bg-gray-200" @click="closeDetails()">Cancel</button>
                    <button class="px-3 py-1.5 rounded bg-blue-600 text-white hover:bg-blue-700" @click="applyDetails()">Save</button>
                </div>
            </div>
        </div>
    </div>
</template>
