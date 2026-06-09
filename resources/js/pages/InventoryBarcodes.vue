<script setup>
import Pagination from '@/components/Pagination.vue'
import axios from 'axios'
import { Head, router } from '@inertiajs/vue3'
import { Printer } from 'lucide-vue-next'
import { computed, ref, watch } from 'vue'

const props = defineProps({
    variants: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
})

const search = ref(props.filters.search || '')
const selectedIds = ref(new Set())
const printing = ref(false)
const printingIds = ref(new Set())
const errorMessage = ref('')

watch(
    () => props.variants.data,
    () => {
        selectedIds.value = new Set()
    },
)

const allOnPageSelected = computed(() => {
    if (!props.variants.data.length) {
        return false
    }

    return props.variants.data.every((variant) => selectedIds.value.has(variant.id))
})

const selectedCount = computed(() => selectedIds.value.size)

function toggleRow(id) {
    const next = new Set(selectedIds.value)

    if (next.has(id)) {
        next.delete(id)
    } else {
        next.add(id)
    }

    selectedIds.value = next
}

function toggleAllOnPage() {
    const next = new Set(selectedIds.value)

    if (allOnPageSelected.value) {
        props.variants.data.forEach((variant) => next.delete(variant.id))
    } else {
        props.variants.data.forEach((variant) => next.add(variant.id))
    }

    selectedIds.value = next
}

function applySearch() {
    router.get(
        '/admin/barcodes',
        {
            search: search.value || undefined,
        },
        {
            preserveState: true,
            replace: true,
        },
    )
}

async function printSelected() {
    if (!selectedIds.value.size || printing.value) {
        return
    }

    await printVariantIds(Array.from(selectedIds.value))
}

async function printVariant(variant) {
    if (printing.value) {
        return
    }

    await printVariantIds([variant.id])
}

async function printVariantIds(variantIds) {
    if (!variantIds.length || printing.value) {
        return
    }

    errorMessage.value = ''
    const printWindow = window.open('', '_blank')

    if (!printWindow) {
        errorMessage.value = 'Allow pop-ups for this site to open the barcode print dialog.'
        return
    }

    printing.value = true
    setPrintingIds(variantIds, true)

    try {
        const response = await axios.post(
            '/barcodes/print',
            {
                variant_ids: variantIds,
            },
            {
                responseType: 'blob',
            },
        )

        const blob = new Blob([response.data], { type: 'application/pdf' })
        const url = window.URL.createObjectURL(blob)
        openPdfPrintDialog(printWindow, url)
    } catch (error) {
        printWindow.close()
        errorMessage.value = 'Unable to open barcode print dialog. Please try again.'
    } finally {
        printing.value = false
        setPrintingIds(variantIds, false)
    }
}

function setPrintingIds(ids, active) {
    const next = new Set(printingIds.value)

    ids.forEach((id) => {
        if (active) {
            next.add(id)
        } else {
            next.delete(id)
        }
    })

    printingIds.value = next
}

function openPdfPrintDialog(printWindow, url) {
    printWindow.document.title = 'Barcode Labels'
    printWindow.document.body.innerHTML = ''
    printWindow.document.body.style.margin = '0'

    const iframe = printWindow.document.createElement('iframe')
    iframe.title = 'Barcode labels'
    iframe.src = url
    iframe.style.border = '0'
    iframe.style.height = '100vh'
    iframe.style.width = '100vw'

    iframe.onload = () => {
        const target = iframe.contentWindow || printWindow

        target.focus()
        target.print()
        window.setTimeout(() => window.URL.revokeObjectURL(url), 60000)
    }

    printWindow.addEventListener(
        'beforeunload',
        () => {
            window.URL.revokeObjectURL(url)
        },
        { once: true },
    )

    printWindow.document.body.appendChild(iframe)
}
</script>

<template>
    <Head title="Barcode Printing" />

    <div class="space-y-6 px-5 py-4 text-gray-900 dark:text-gray-100">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold">Barcode Labels</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Select variants and print labels in one batch.
                </p>
            </div>

            <div class="flex w-full max-w-xl gap-2">
                <input
                    v-model="search"
                    type="text"
                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-900"
                    placeholder="Search product, SKU, or barcode"
                    @keyup.enter="applySearch"
                />
                <button
                    type="button"
                    class="rounded-lg bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700"
                    @click="applySearch"
                >
                    Search
                </button>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <p class="text-sm">
                {{ selectedCount }} variant{{ selectedCount === 1 ? '' : 's' }} selected
            </p>

            <button
                type="button"
                class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-60"
                :disabled="selectedCount === 0 || printing"
                @click="printSelected"
            >
                {{ printing ? 'Opening print...' : 'Print Barcode' }}
            </button>
        </div>

        <p v-if="errorMessage" class="rounded-lg border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-700">
            {{ errorMessage }}
        </p>

        <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-4 py-3 text-left">
                            <input
                                type="checkbox"
                                :checked="allOnPageSelected"
                                @change="toggleAllOnPage"
                            />
                        </th>
                        <th class="px-4 py-3 text-left">Product Variant</th>
                        <th class="px-4 py-3 text-left">SKU</th>
                        <th class="px-4 py-3 text-left">Barcode</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    <tr
                        v-for="variant in variants.data"
                        :key="variant.id"
                        class="hover:bg-gray-50 dark:hover:bg-gray-800/70"
                    >
                        <td class="px-4 py-3">
                            <input
                                type="checkbox"
                                :checked="selectedIds.has(variant.id)"
                                @change="toggleRow(variant.id)"
                            />
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-between gap-3">
                                <span>{{ variant.display_name }}</span>
                                <button
                                    type="button"
                                    class="inline-flex size-9 shrink-0 items-center justify-center rounded-lg border border-gray-200 text-gray-600 transition hover:border-indigo-300 hover:text-indigo-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:text-gray-300 dark:hover:border-indigo-500 dark:hover:text-indigo-300"
                                    :disabled="printing"
                                    :title="`Print barcode for ${variant.display_name}`"
                                    @click="printVariant(variant)"
                                >
                                    <Printer class="size-4" />
                                </button>
                            </div>
                        </td>
                        <td class="px-4 py-3 font-mono text-xs">{{ variant.sku }}</td>
                        <td class="px-4 py-3 font-mono text-xs">
                            {{ variant.barcode || 'Will be auto-generated' }}
                        </td>
                    </tr>
                    <tr v-if="variants.data.length === 0">
                        <td class="px-4 py-6 text-center text-sm text-gray-500" colspan="4">
                            No variants found.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination :links="variants.links" />
    </div>
</template>
