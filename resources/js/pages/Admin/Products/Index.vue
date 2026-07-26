<!-- resources/js/Pages/Products/Index.vue -->
<script setup>
import ImagePreviewModal from '@/components/ImagePreviewModal.vue'
import { router } from '@inertiajs/vue3'
import { ref, watch, computed, nextTick } from 'vue'
import {
    EyeIcon,
    PencilSquareIcon,
    DocumentDuplicateIcon,
    TrashIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    products: Object,
    filters: Object,
    brands: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
})

const imagePreview = ref(null)

function openImagePreview(product) {
    if (!product.thumb) {
        return
    }

    imagePreview.value = {
        url: product.thumb,
        label: product.name,
        subtitle: [product.category, product.brand].filter(Boolean).join(' - '),
    }
}

function closeImagePreview() {
    imagePreview.value = null
}

const search = ref(props.filters?.search ?? '')

const advancedFilters = ref({
    status: props.filters?.status ?? '',
    featured: props.filters?.featured ?? '',
    stock: props.filters?.stock ?? '',
    on_sale: props.filters?.on_sale ?? '',
    fulfillment: props.filters?.fulfillment ?? '',
    brand_id: props.filters?.brand_id ?? '',
    category_id: props.filters?.category_id ?? '',
})

const activeFilterCount = computed(() =>
    Object.values(advancedFilters.value).filter(v => v !== '' && v !== null).length,
)

function currentParams() {
    const params = { search: search.value || undefined }

    Object.entries(advancedFilters.value).forEach(([key, value]) => {
        if (value !== '' && value !== null) {
            params[key] = value
        }
    })

    return params
}

function applyFilters() {
    router.get(route('admin.products.index'), currentParams(), { preserveState: true, replace: true })
}

function clearFilters() {
    search.value = ''
    Object.keys(advancedFilters.value).forEach(key => (advancedFilters.value[key] = ''))
}

watch(advancedFilters, applyFilters, { deep: true })

let searchTimeout
watch(search, () => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(applyFilters, 350)
})

function togglePublished(id) {
    router.patch(route('admin.products.toggle-published', id), {}, { preserveScroll: true, preserveState: true })
}
function toggleFeatured(id) {
    router.patch(route('admin.products.toggle-featured', id), {}, { preserveScroll: true, preserveState: true })
}
function duplicateProduct(id) {
    router.post(route('admin.products.duplicate', id), {}, { preserveScroll: true })
}
function destroyProduct(id) {
    if (!confirm('Delete this product?')) return
    router.delete(route('admin.products.destroy', id), { preserveScroll: true })
}

/* === Selection + Bulk Action === */
const selectedIds = ref([])                 // array of product IDs selected
const bulkAction = ref('')                  // 'delete' | future: 'publish' | 'unpublish' | etc.
const masterRef = ref(null)                 // master checkbox element

// Keep master checkbox checked/indeterminate in sync
const totalRows = computed(() => props.products?.data?.length ?? 0)
const allChecked = computed(() => selectedIds.value.length === totalRows.value && totalRows.value > 0)
const someChecked = computed(() => selectedIds.value.length > 0 && !allChecked.value)

watch([allChecked, someChecked], async () => {
    await nextTick()
    if (masterRef.value) {
        masterRef.value.indeterminate = someChecked.value
    }
})

// Select or deselect all visible rows
function toggleAll(evt) {
    const checked = evt.target.checked
    selectedIds.value = checked ? props.products.data.map(p => p.id) : []
}

// Apply the chosen bulk action
function applyBulk() {
    if (!bulkAction.value || selectedIds.value.length === 0) return

    if (bulkAction.value === 'delete') {
        if (!confirm(`Delete ${selectedIds.value.length} product(s)?`)) return

        // Preferred: a backend route that accepts ids[]
        router.delete(route('admin.products.bulk-destroy'), {
            data: { ids: selectedIds.value },
            preserveScroll: true,
            onSuccess: () => {
                selectedIds.value = []
                bulkAction.value = ''
            },
        })
    }

    if (bulkAction.value === 'publish') {
      router.patch(route('admin.products.bulk-published'), { ids: selectedIds.value }, { preserveScroll: true })
    }

    if (bulkAction.value === 'unpublish') {
      router.patch(route('admin.products.bulk-un-published'), { ids: selectedIds.value }, { preserveScroll: true })
    }
}
</script>

<template>
    <div class="p-6 space-y-4">
        <div class="flex items-center justify-between mb-8">
            <h1 class="text-2xl font-semibold">Products</h1>
            <a :href="route('admin.products.create')" class="px-4 py-2 bg-blue-600 text-white rounded">New Product</a>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <input v-model="search" placeholder="Search name or slug" class="border rounded px-3 py-2 w-64" />

            <div class="flex items-center gap-2">
                <select
                    v-model="bulkAction"
                    class="bg-gray-50 border w-full border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500"
                >
                    <option value="">Bulk Action</option>
                    <option value="delete">Delete</option>
                    <option value="publish">Publish</option>
                    <option value="unpublish">Unpublish</option>
                </select>

                <button
                    @click="applyBulk"
                    :disabled="!bulkAction || selectedIds.length === 0"
                    class="px-3 py-2 rounded bg-blue-600 text-white disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    Apply
                </button>
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-800">
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Filters</span>

                <select v-model="advancedFilters.status" class="rounded border border-gray-300 bg-white px-2 py-1.5 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                    <option value="">Status: All</option>
                    <option value="published">Published</option>
                    <option value="draft">Unpublished</option>
                </select>

                <select v-model="advancedFilters.featured" class="rounded border border-gray-300 bg-white px-2 py-1.5 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                    <option value="">Featured: All</option>
                    <option value="1">Featured</option>
                    <option value="0">Not featured</option>
                </select>

                <select v-model="advancedFilters.stock" class="rounded border border-gray-300 bg-white px-2 py-1.5 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                    <option value="">Stock: All</option>
                    <option value="in">In stock</option>
                    <option value="out">Out of stock</option>
                </select>

                <select v-model="advancedFilters.on_sale" class="rounded border border-gray-300 bg-white px-2 py-1.5 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                    <option value="">Sale: All</option>
                    <option value="1">On sale</option>
                    <option value="0">Not on sale</option>
                </select>

                <select v-model="advancedFilters.fulfillment" class="rounded border border-gray-300 bg-white px-2 py-1.5 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                    <option value="">Fulfillment: All</option>
                    <option value="dropshipping">Dropshipping</option>
                    <option value="stocked">Stocked only</option>
                </select>

                <select v-model="advancedFilters.brand_id" class="rounded border border-gray-300 bg-white px-2 py-1.5 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                    <option value="">Brand: All</option>
                    <option v-for="b in props.brands" :key="b.id" :value="String(b.id)">{{ b.name }}</option>
                </select>

                <select v-model="advancedFilters.category_id" class="rounded border border-gray-300 bg-white px-2 py-1.5 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                    <option value="">Category: All</option>
                    <option v-for="c in props.categories" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
                </select>

                <button
                    v-if="activeFilterCount > 0 || search"
                    type="button"
                    class="ml-auto rounded border border-gray-300 px-3 py-1.5 text-sm text-gray-700 transition hover:bg-gray-100 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                    @click="clearFilters"
                >
                    Clear filters<span v-if="activeFilterCount"> ({{ activeFilterCount }})</span>
                </button>
            </div>
        </div>

        <div>
            <div class="relative overflow-x-auto shadow-md sm:rounded-lg">
                <table class="w-full text-sm text-left rtl:text-right text-gray-500 dark:text-gray-400">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                    <tr>
                        <th scope="col" class="p-4">
                            <div class="flex items-center">
                                <input
                                    id="checkbox-all"
                                    ref="masterRef"
                                    type="checkbox"
                                    :checked="allChecked"
                                    @change="toggleAll"
                                    class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded-sm focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 dark:focus:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600"
                                />
                                <label for="checkbox-all" class="sr-only">Select all</label>
                            </div>
                        </th>
                        <th scope="col" class="px-6 py-3">Product name</th>
                        <th scope="col" class="px-6 py-3">Total Stock</th>
                        <th scope="col" class="px-6 py-3">Published</th>
                        <th scope="col" class="px-6 py-3">Featured</th>
                        <th scope="col" class="px-6 py-3">Available</th>
                        <th scope="col" class="px-6 py-3">On Sale</th>
                        <th scope="col" class="px-6 py-3">Action</th>
                    </tr>
                    </thead>

                    <tbody>
                    <tr
                        v-for="p in props.products.data"
                        :key="p.id"
                        class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 border-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600"
                    >
                        <td class="w-4 p-4">
                            <div class="flex items-center">
                                <input
                                    :id="`row-check-${p.id}`"
                                    type="checkbox"
                                    v-model="selectedIds"
                                    :value="p.id"
                                    class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded-sm focus:ring-blue-500 dark:focus:ring-blue-600 dark:ring-offset-gray-800 dark:focus:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600"
                                />
                                <label :for="`row-check-${p.id}`" class="sr-only">Select row</label>
                            </div>
                        </td>

                        <th scope="row" class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                            <div class="flex items-center gap-3">
                                <button
                                    v-if="p.thumb"
                                    type="button"
                                    class="block h-10 w-10 shrink-0 overflow-hidden rounded transition hover:ring-2 hover:ring-gray-400 dark:hover:ring-gray-500"
                                    title="View image"
                                    :aria-label="`View image of ${p.name}`"
                                    @click="openImagePreview(p)"
                                >
                                    <img :src="p.thumb" alt="" class="h-full w-full object-cover" />
                                </button>
                                <div>
                                    <div class="font-medium">{{ p.name }}</div>
                                    <div class="text-xs text-gray-500">{{ p.category || '-' }} - {{ p.brand || '-' }}</div>
                                    <span
                                        v-if="p.has_dropshipping"
                                        class="mt-1 inline-flex rounded-full bg-sky-100 px-2 py-0.5 text-xs font-medium text-sky-700 dark:bg-sky-950/40 dark:text-sky-200"
                                    >
                                        Dropshipping
                                    </span>
                                </div>
                            </div>
                        </th>

                        <td class="px-6 py-4">{{ p.total_stock }}</td>

                        <td class="px-6 py-4">
                            <label class="inline-flex items-center cursor-pointer">
                                <input type="checkbox" :checked="p.published" @change="togglePublished(p.id)" class="sr-only peer" />
                                <div
                                    class="relative w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600 dark:peer-checked:bg-blue-600"
                                ></div>
                            </label>
                        </td>

                        <td class="px-6 py-4">
                            <label class="inline-flex items-center cursor-pointer">
                                <input type="checkbox" :checked="p.featured" @change="toggleFeatured(p.id)" class="sr-only peer" />
                                <div
                                    class="relative w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600 dark:peer-checked:bg-blue-600"
                                ></div>
                            </label>
                        </td>

                        <td class="px-6 py-4">
                            {{ p.total_stock > 0 ? 'Yes' : 'No' }}
                        </td>

                        <td class="px-6 py-4">
                            <span
                                :class="p.on_sale ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600'"
                                class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                            >
                                {{ p.on_sale ? 'Yes' : 'No' }}
                            </span>
                        </td>

                        <td class="flex items-center gap-2 px-6 py-4">
                            <a :href="route('admin.products.show', p.id)" class="p-2 rounded-full bg-emerald-50 hover:bg-emerald-100">
                                <EyeIcon class="h-5 w-5 text-emerald-700" />
                            </a>
                            <a :href="route('admin.products.edit', p.id)" class="p-2 rounded-full bg-blue-50 hover:bg-blue-100">
                                <PencilSquareIcon class="h-5 w-5 text-blue-700" />
                            </a>
                            <button @click="duplicateProduct(p.id)" class="p-2 rounded-full bg-amber-50 hover:bg-amber-100">
                                <DocumentDuplicateIcon class="h-5 w-5 text-amber-700" />
                            </button>
                            <button @click="destroyProduct(p.id)" class="p-2 rounded-full bg-rose-50 hover:bg-rose-100">
                                <TrashIcon class="h-5 w-5 text-rose-700" />
                            </button>
                        </td>
                    </tr>
                    <tr v-if="!props.products.data.length">
                        <td colspan="8" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                            No products match the current filters.
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="flex gap-2" v-if="props.products.links">
            <a
                v-for="l in props.products.links"
                :key="l.url + l.label"
                :href="l.url || '#'"
                v-html="l.label"
                :class="['px-3 py-1 border rounded', l.active ? 'bg-blue-600 text-white' : '']"
                @click.prevent="l.url && router.visit(l.url, { preserveState: true })"
            />
        </div>

        <ImagePreviewModal :preview="imagePreview" @close="closeImagePreview" />
    </div>
</template>
