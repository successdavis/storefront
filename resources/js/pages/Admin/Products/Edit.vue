<script setup>
import {computed, nextTick, ref, watch} from 'vue'
import {useForm} from '@inertiajs/vue3'
import VariantMatrix from '@/Components/Admin/Products/VariantMatrix.vue'
import ProductImageGallery from '@/Components/Admin/Products/ProductImageGallery.vue'
import ProductFaqEditor from '@/Components/Admin/Products/ProductFaqEditor.vue'
import MultiSelect from "@/Components/MultiSelect.vue";
import CategoryTree from "@/Components/CategoryTree.vue";

const props = defineProps({
    product: Object,
    categories: Array,
    brands: Array,
    variantTypes: Array, // [{id, name, values: [{id, variant_type_id, value}]}]
})

const isEdit = computed(() => !!props.product)
const p = computed(() => props.product?.data ?? props.product ?? null)
const productId = computed(() => p.value?.id ?? null)
const variantNames = computed(() => props.variantTypes.map(v => v.name))

/* map: valueId -> typeName (built once from provided variantTypes) */
const valueIdToTypeName = computed(() => {
    const map = new Map()
    for (const vt of props.variantTypes || []) {
        const typeName = vt.name
        for (const val of vt.values || []) {
            map.set(String(val.id), typeName)
        }
    }
    return map
})

/* user’s selected type names shown in the multiselect */
const selectedVariants = ref([])

/* initialize selectedVariants on edit, once product + variantTypes are present */
watch(
    [() => p.value?.variants, () => props.variantTypes],
    ([rows, vts]) => {
        if (!isEdit.value) return
        if (!rows || !rows.length) return
        if (!vts || !vts.length) return
        if (selectedVariants.value.length) return

        const names = new Set()
        for (const row of rows) {
            for (const vid of (row.value_ids || [])) {
                const tName = valueIdToTypeName.value.get(String(vid))
                if (tName) names.add(tName)
            }
        }
        selectedVariants.value = Array.from(names)
    },
    {immediate: true}
)

/* when nothing is selected (create mode), show all; otherwise filter by picked type names */
const filteredVariantTypes = computed(() => {
    return (props.variantTypes || []).filter(vt => selectedVariants.value.includes(vt.name))
})

/* ---------------- Form ---------------- */
const form = useForm({
    category_ids: p.value?.categories?.map(c => c.id) ?? [],
    brand_id: p.value?.brand_id ?? null,
    name: p.value?.name ?? '',
    slug: p.value?.slug ?? '',
    meta_title: p.value?.meta_title ?? '',
    meta_description: p.value?.meta_description ?? '',
    youtube_video_url: p.value?.youtube_video_url ?? '',
    cash_on_delivery: p.value?.cash_on_delivery ?? false,
    featured: p.value?.featured ?? false,
    weight: p.value?.weight ?? null,
    weight_unit: p.value?.weight_unit ?? null,
    description: p.value?.description ?? '',
    is_active: p.value?.is_active ?? true,
    length: p.value?.length ?? null,
    width: p.value?.width ?? null,
    height: p.value?.height ?? null,
    faqs: p.value?.faqs ?? [],
    variants: p.value?.variants ?? [],
})

/* ---------------- Error helpers ---------------- */
/* Get the first matching error by key or prefix (supports arrays like category_ids.* or variants.0.sku) */
function err(key) {
    if (!form.errors) return ''
    if (form.errors[key]) return form.errors[key]
    // prefix match for arrays/nested: e.g., key = 'category_ids', matches 'category_ids.0'
    const pref = key + '.'
    const k = Object.keys(form.errors).find(k => k.startsWith(pref))
    return k ? form.errors[k] : ''
}

/* Collect errors that belong to a logical section */
const tabMap = {
    'General': [
        'name', 'brand_id', 'weight', 'weight_unit', 'description', 'length', 'width', 'height', 'category_ids'
    ],
    'Price & Stock': [
        'variants' // plus nested like variants.0.sku etc. handled by prefix
    ],
    'Files & Media': [
        'images'
    ],
    'SEO': [
        'meta_title', 'meta_description', 'youtube_video_url'
    ],
    'Shipping': [], // if you later add shipping fields
    'Warranty': ['faqs'],
    'Frequently Bought': [] // placeholder
}

function countErrorsForTab(tab) {
    const keys = tabMap[tab] || []
    const all = Object.keys(form.errors || {})
    return all.filter(k => keys.some(f => k === f || k.startsWith(f + '.'))).length
}

const tabs = ['General', 'Price & Stock', 'Files & Media', 'SEO', 'Shipping', 'Warranty', 'Frequently Bought']
const activeTab = ref('General')

/* Error summary and focus handling */
const errorSummaryOpen = ref(false)
const firstErrorKey = computed(() => {
    const all = Object.keys(form.errors || {})
    return all.length ? all[0] : ''
})

/* Map some keys to element refs for focus after submit */
const fieldRefs = {
    name: ref(null),
    brand_id: ref(null),
    weight: ref(null),
    weight_unit: ref(null),
    description: ref(null),
    length: ref(null),
    width: ref(null),
    height: ref(null),
    meta_title: ref(null),
    meta_description: ref(null),
    youtube_video_url: ref(null),
    // category_ids and images are components; we will scroll their containers
}
const categoryRef = ref(null)
const imagesRef = ref(null)
const variantsRef = ref(null)

/* Set the right tab and focus the first invalid field */
async function handleErrorsAndFocus() {
    if (!Object.keys(form.errors).length) return
    // pick the tab that contains the first error
    const first = firstErrorKey.value
    const targetTab = tabs.find(t => {
        const keys = tabMap[t] || []
        return keys.some(f => first === f || first.startsWith(f + '.'))
    }) || 'General'
    activeTab.value = targetTab
    errorSummaryOpen.value = true

    await nextTick()
    // try focus by name
    const baseKey = first.split('.')[0] // e.g., variants.0.sku -> variants
    const direct = fieldRefs[first] || fieldRefs[baseKey]
    if (direct?.value) {
        direct.value.focus?.()
        return
    }
    // fallbacks: scroll containers
    if (baseKey === 'category_ids' && categoryRef.value) {
        categoryRef.value.scrollIntoView({behavior: 'smooth', block: 'center'})
    } else if (baseKey === 'images' && imagesRef.value) {
        imagesRef.value.scrollIntoView({behavior: 'smooth', block: 'center'})
    } else if (baseKey === 'variants' && variantsRef.value) {
        variantsRef.value.scrollIntoView({behavior: 'smooth', block: 'center'})
    }
}

/* Style helper */
function inputClass(key, extra = '') {
    return [
        'w-full border rounded px-3 py-2',
        extra,
        err(key) ? 'border-red-400 bg-red-50' : ''
    ].join(' ')
}

/* ---------------- Save ---------------- */
function save() {
    const opts = {
        preserveScroll: true,
        // keep component state so errors can render
        preserveState: true,
        onError: () => handleErrorsAndFocus(),
        onSuccess: () => {
            errorSummaryOpen.value = false
        }
    }

    if (productId.value) {
        form.put(route('admin.products.update', {product: productId.value}), opts)
    } else {
        form.post(route('admin.products.store'), opts)
    }
}

/* Re-run focus routine when errors change (e.g., server returns new set) */
watch(() => form.errors, () => {
    if (Object.keys(form.errors || {}).length) handleErrorsAndFocus()
})
</script>

<template>
    <div class="flex min-h-screen bg-gray-50">
        <!-- Sidebar -->
        <aside class="w-64 bg-white border-r">
            <nav class="p-4 space-y-2">
                <button
                    v-for="tab in tabs"
                    :key="tab"
                    @click="activeTab = tab"
                    class="relative block w-full text-left px-3 py-2 rounded hover:bg-gray-100"
                    :class="{'bg-blue-50 text-blue-600 font-medium': activeTab === tab}"
                >
                    {{ tab }}
                    <span
                        v-if="countErrorsForTab(tab)"
                        class="absolute right-2 top-1/2 -translate-y-1/2 text-xs bg-red-100 text-red-700 rounded px-1.5 py-0.5"
                        aria-label="Errors in this section"
                    >
            {{ countErrorsForTab(tab) }}
          </span>
                </button>
            </nav>
        </aside>

        <!-- Main content -->
        <main class="flex-1 p-6">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-semibold">{{ isEdit ? 'Edit product' : 'Create product' }}</h1>
                <div class="flex gap-2">
                    <a :href="route('admin.products.index')" class="px-3 py-2 border rounded">Back</a>
                    <button @click="save" class="px-4 py-2 bg-blue-600 text-white rounded" :disabled="form.processing">
                        {{ form.processing ? 'Saving...' : 'Save' }}
                    </button>
                </div>
            </div>

            <!-- Error summary -->
            <div
                v-if="Object.keys(form.errors || {}).length"
                class="mb-4 rounded border border-red-200 bg-red-50 text-red-700"
            >
                <button
                    class="w-full text-left px-3 py-2 font-medium"
                    @click="errorSummaryOpen = !errorSummaryOpen"
                >
                    There {{ Object.keys(form.errors).length === 1 ? 'is' : 'are' }}
                    {{ Object.keys(form.errors).length }} validation
                    issue{{ Object.keys(form.errors).length === 1 ? '' : 's' }}.
                    Click to {{ errorSummaryOpen ? 'hide' : 'view' }} details.
                </button>
                <ul v-if="errorSummaryOpen" class="px-4 pb-3 list-disc text-sm">
                    <li v-for="(msg, key) in form.errors" :key="key">
                        <button
                            class="underline"
                            @click="
                () => {
                  // switch to the tab containing this key and focus it
                  const tgtTab = tabs.find(t => (tabMap[t]||[]).some(f => key===f || key.startsWith(f + '.'))) || 'General'
                  activeTab = tgtTab
                  nextTick(() => handleErrorsAndFocus())
                }
              "
                        >
                            {{ key }}:
                        </button>
                        {{ ' ' + msg }}
                    </li>
                </ul>
            </div>

            <!-- Dynamic sections -->
            <div v-if="activeTab === 'General'" class="space-y-6">
                <div class="bg-white p-4 border rounded space-y-4">
                    <h2 class="font-semibold">General Information</h2>

                    <div class="flex gap-4">
                        <div class="grow">
                            <div class="grid grid-cols-4 gap-2 mb-4">
                                <label class="block text-sm">Product Name</label>
                                <div class="col-span-3">
                                    <input
                                        ref="name" :ref="el => fieldRefs.name.value = el"
                                        v-model="form.name"
                                        :class="inputClass('name')"
                                    />
                                    <p v-if="err('name')" class="text-xs text-red-600 mt-1">{{ err('name') }}</p>
                                </div>
                            </div>

                            <div class="grid grid-cols-4 gap-2 mb-4">
                                <label class="block text-sm">Brand</label>
                                <div class="col-span-3">
                                    <select
                                        ref="brand_id" :ref="el => fieldRefs.brand_id.value = el"
                                        v-model="form.brand_id"
                                        :class="inputClass('brand_id')"
                                    >
                                        <option :value="null">None</option>
                                        <option v-for="b in brands" :key="b.id" :value="b.id">{{ b.name }}</option>
                                    </select>
                                    <p v-if="err('brand_id')" class="text-xs text-red-600 mt-1">{{
                                            err('brand_id')
                                        }}</p>
                                </div>
                            </div>

                            <div class="grid grid-cols-4 gap-2 mb-4">
                                <label class="block text-sm">Weight</label>
                                <div class="col-span-3">
                                    <input
                                        ref="weight" :ref="el => fieldRefs.weight.value = el"
                                        v-model.number="form.weight" type="number" step="0.001"
                                        :class="inputClass('weight')"
                                    />
                                    <p v-if="err('weight')" class="text-xs text-red-600 mt-1">{{ err('weight') }}</p>
                                </div>
                            </div>

                            <div class="grid grid-cols-4 gap-2 mb-4">
                                <label class="block text-sm">Weight unit</label>
                                <div class="col-span-3">
                                    <select
                                        ref="weight_unit" :ref="el => fieldRefs.weight_unit.value = el"
                                        v-model="form.weight_unit"
                                        :class="inputClass('weight_unit')"
                                    >
                                        <option :value="null">—</option>
                                        <option value="g">g</option>
                                        <option value="kg">kg</option>
                                        <option value="lb">lb</option>
                                        <option value="oz">oz</option>
                                    </select>
                                    <p v-if="err('weight_unit')" class="text-xs text-red-600 mt-1">{{
                                            err('weight_unit')
                                        }}</p>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="block text-sm">Description</label>
                                <textarea
                                    ref="description" :ref="el => fieldRefs.description.value = el"
                                    v-model="form.description" rows="4"
                                    class="w-full border rounded px-3 py-2"
                                    :class="err('description') ? 'border-red-400 bg-red-50' : ''"
                                ></textarea>
                                <p v-if="err('description')" class="text-xs text-red-600 mt-1">{{
                                        err('description')
                                    }}</p>
                            </div>

                            <div class="grid grid-cols-3 gap-2">
                                <div>
                                    <label class="block text-sm">L (cm)</label>
                                    <input
                                        ref="length" :ref="el => fieldRefs.length.value = el"
                                        v-model.number="form.length" type="number" step="0.01"
                                        class="w-full border rounded px-3 py-2"
                                        :class="err('length') ? 'border-red-400 bg-red-50' : ''"
                                    />
                                    <p v-if="err('length')" class="text-xs text-red-600 mt-1">{{ err('length') }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm">W (cm)</label>
                                    <input
                                        ref="width" :ref="el => fieldRefs.width.value = el"
                                        v-model.number="form.width" type="number" step="0.01"
                                        class="w-full border rounded px-3 py-2"
                                        :class="err('width') ? 'border-red-400 bg-red-50' : ''"
                                    />
                                    <p v-if="err('width')" class="text-xs text-red-600 mt-1">{{ err('width') }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm">H (cm)</label>
                                    <input
                                        ref="height" :ref="el => fieldRefs.height.value = el"
                                        v-model.number="form.height" type="number" step="0.01"
                                        class="w-full border rounded px-3 py-2"
                                        :class="err('height') ? 'border-red-400 bg-red-50' : ''"
                                    />
                                    <p v-if="err('height')" class="text-xs text-red-600 mt-1">{{ err('height') }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="w-2/5">
                            <div ref="categoryRef" :ref="el => categoryRef.value = el">
                                <CategoryTree
                                    v-model="form.category_ids"
                                    :categories="categories"
                                    :expand-all="false"
                                />
                            </div>
                            <p v-if="err('category_ids')" class="text-xs text-red-600 mt-2">{{
                                    err('category_ids')
                                }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="activeTab === 'Price & Stock'" class="space-y-6">
                <h2 class="font-semibold">Variants</h2>

                <multi-select v-model="selectedVariants" :options="variantNames"></multi-select>

                <div ref="variantsRef" :ref="el => variantsRef.value = el">
                    <VariantMatrix v-model="form.variants" :variant-types="filteredVariantTypes"/>
                </div>

                <!-- Variants root error or any nested error -->
                <div v-if="err('variants')" class="text-red-600 text-sm mt-1">{{ err('variants') }}</div>
                <div v-else-if="Object.keys(form.errors).some(k => k.startsWith('variants.'))"
                     class="text-red-600 text-sm mt-1">
                    One or more variants have errors. Please open the variant details to fix them.
                </div>
            </div>

            <div v-if="activeTab === 'Files & Media'" class="space-y-6">
                <div v-if="!isEdit">
                    <p>Please Create your product before adding images</p>
                </div>
                <div v-else class="bg-white p-4 border rounded">
                    <h2 class="font-semibold">Media</h2>
                    <div ref="imagesRef" :ref="el => imagesRef.value = el">
                        <ProductImageGallery :product="product"/>
                    </div>
                    <p v-if="err('images')" class="text-xs text-red-600 mt-2">{{ err('images') }}</p>
                    <!-- show first nested image error if present -->
                    <p v-else-if="Object.keys(form.errors).some(k => k.startsWith('images.'))"
                       class="text-xs text-red-600 mt-2">
                        {{ form.errors[Object.keys(form.errors).find(k => k.startsWith('images.'))] }}
                    </p>
                </div>
            </div>

            <div v-if="activeTab === 'SEO'" class="space-y-6">
                <div class="bg-white p-4 border rounded space-y-4">
                    <h2 class="font-semibold">SEO</h2>

                    <label class="block text-sm">Meta title</label>
                    <input
                        ref="meta_title" :ref="el => fieldRefs.meta_title.value = el"
                        v-model="form.meta_title"
                        class="w-full border rounded px-3 py-2"
                        :class="err('meta_title') ? 'border-red-400 bg-red-50' : ''"
                    />
                    <p v-if="err('meta_title')" class="text-xs text-red-600 mt-1">{{ err('meta_title') }}</p>

                    <label class="block text-sm">Meta description</label>
                    <textarea
                        ref="meta_description" :ref="el => fieldRefs.meta_description.value = el"
                        v-model="form.meta_description" rows="3"
                        class="w-full border rounded px-3 py-2"
                        :class="err('meta_description') ? 'border-red-400 bg-red-50' : ''"
                    ></textarea>
                    <p v-if="err('meta_description')" class="text-xs text-red-600 mt-1">{{
                            err('meta_description')
                        }}</p>

                    <label class="block text-sm">YouTube URL</label>
                    <input
                        ref="youtube_video_url" :ref="el => fieldRefs.youtube_video_url.value = el"
                        v-model="form.youtube_video_url"
                        class="w-full border rounded px-3 py-2"
                        :class="err('youtube_video_url') ? 'border-red-400 bg-red-50' : ''"
                    />
                    <p v-if="err('youtube_video_url')" class="text-xs text-red-600 mt-1">{{
                            err('youtube_video_url')
                        }}</p>
                </div>
            </div>

            <div v-if="activeTab === 'Shipping'">
                <h2>Shipping Configuration</h2>
                <div class="max-w-lg">
                    <div>
                        <label class="grid grid-cols-2 my-6 cursor-pointer">
                            <input type="checkbox" value="" class="sr-only peer">
                            <span class="ms-3 text-sm font-medium text-gray-900 dark:text-gray-300">Cash on Delivery</span>
                            <div class="relative w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600 dark:peer-checked:bg-blue-600"></div>
                        </label>
                    </div>
                    <div>
                        <label class="grid grid-cols-2 my-6 cursor-pointer">
                            <input type="checkbox" value="" class="sr-only peer">
                            <span class="ms-3 text-sm font-medium text-gray-900 dark:text-gray-300">Free Shipping</span>
                            <div class="relative w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600 dark:peer-checked:bg-blue-600"></div>
                        </label>
                    </div>
                    <div>
                        <label class="grid grid-cols-2 my-6 cursor-pointer">
                            <input type="checkbox" value="" class="sr-only peer">
                            <span class="ms-3 text-sm font-medium text-gray-900 dark:text-gray-300">Flat Rate</span>
                            <div class="relative w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600 dark:peer-checked:bg-blue-600"></div>
                        </label>
                    </div>
                </div>
            </div>

            <div v-if="activeTab === 'Warranty'" class="space-y-6">
                <div class="bg-white p-4 border rounded">
                    <h2 class="font-semibold">Warranty & FAQs</h2>
                    <ProductFaqEditor v-model="form.faqs" :variants="form.variants"/>
                    <p v-if="err('faqs')" class="text-xs text-red-600 mt-2">{{ err('faqs') }}</p>
                </div>
            </div>
        </main>
    </div>
</template>
