<script setup>
import {watch, onBeforeUnmount, computed} from 'vue'
import { useForm } from '@inertiajs/vue3'

const props = defineProps({
    product: { type: Object, default: null }, // may be {data:{...}} or plain object
    storageBase: { type: String, default: '/storage' }
})

/* derive product + id safely */
const p = computed(() => props.product?.data ?? props.product ?? null)
const productId = computed(() => p.value?.id ?? null)

/* map any incoming product images to editable rows */
function mapImages(src = []) {
    return src.map((r, i) => ({
        id: r.id ?? null,
        path: r.path ?? '',
        file: null,
        alt: r.alt ?? '',
        is_primary: !!r.is_primary || i === 0,
        sort_order: Number.isFinite(+r.sort_order) ? +r.sort_order : i,
        _preview: '' // local object URL for newly picked files only
    }))
}

/* Inertia form payload */
const form = useForm({
    images: mapImages(p.value?.images ?? [])
})

/* keep form in sync if parent swaps product (edit mode) */
watch(
    () => p.value?.images,
    v => { form.images = mapImages(Array.isArray(v) ? v : []) },
    { immediate: true }
)

/* helpers */
function normalizePath(path) {
    if (!path) return ''
    if (/^(https?:)?\/\//i.test(path) || path.startsWith('data:') || path.startsWith('blob:')) return path
    const base = String(props.storageBase || '').replace(/\/+$/, '')
    const rel  = String(path).replace(/^\/+/, '')
    return base && rel ? `${base}/${rel}` : rel
}
function previewSrc(row) {
    if (row?._preview) return row._preview
    if (row?.path) return normalizePath(row.path)
    return ''
}

/* row ops */
function addBlank() {
    form.images.push({
        id: null,
        path: '',
        file: null,
        alt: '',
        is_primary: form.images.length === 0,
        sort_order: form.images.length,
        _preview: ''
    })
}
function removeAt(i) {
    const r = form.images[i]
    if (r?._preview) { try { URL.revokeObjectURL(r._preview) } catch {}
    }
    form.images.splice(i, 1)
    form.images.forEach((row, idx) => { row.sort_order = idx })
    if (!form.images.some(row => row.is_primary) && form.images[0]) form.images[0].is_primary = true
}
function move(i, dir) {
    const j = i + dir
    if (j < 0 || j >= form.images.length) return
    const tmp = form.images[i]; form.images[i] = form.images[j]; form.images[j] = tmp
    form.images.forEach((row, idx) => { row.sort_order = idx })
}
function setPrimary(i) {
    form.images.forEach((row, idx) => { row.is_primary = idx === i })
}
function onFileChange(e, row) {
    const file = e.target.files?.[0]
    if (!file) return
    row.file = file
    if (row._preview) { try { URL.revokeObjectURL(row._preview) } catch {} }
    row._preview = URL.createObjectURL(file)
}

/* build FormData with only fields your backend needs for sync */
function buildFormData() {
    const fd = new FormData()
    form.images.forEach((r, i) => {
        if (r.id != null)                 fd.append(`images[${i}][id]`, String(r.id))
        if (r.path)                       fd.append(`images[${i}][path]`, r.path)
        fd.append(`images[${i}][alt]`, r.alt || '')
        fd.append(`images[${i}][is_primary]`, r.is_primary ? '1' : '0')
        fd.append(`images[${i}][sort_order]`, String(+r.sort_order || 0))
        if (r.file instanceof File)       fd.append(`images[${i}][file]`, r.file)
    })
    return fd
}

/* submit: always POST, backend will handle sync logic */
function submitImages() {
    if (!productId.value) return
    form.clearErrors()
    form.transform(() => buildFormData()).post(
        route('admin.products.images.store', { product: productId.value }),
        { forceFormData: true, preserveScroll: true }
    )
}

/* clean previews on unmount */
onBeforeUnmount(() => {
    form.images.forEach(r => { if (r?._preview) { try { URL.revokeObjectURL(r._preview) } catch {} } })
})
</script>

<template>
    <div class="space-y-3">
        <div class="flex items-center justify-between">
            <h3 class="font-medium">Product Images</h3>
            <button class="px-3 py-2 border rounded dark:hover:bg-gray-800 cursor-pointer hover:bg-gray-50 dark:bg-gray-900" @click.prevent="addBlank">
                Add image
            </button>
        </div>

        <div class="space-y-3">
            <div v-for="(img, i) in form.images" :key="img.id ?? `row-${i}`" class="rounded border p-3">
                <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-start">
                    <!-- Preview -->
                    <div class="md:col-span-2">
                        <div class="w-full aspect-square rounded border bg-gray-50 dark:bg-gray-500 overflow-hidden flex items-center justify-center">
                            <img
                                v-if="previewSrc(img)"
                                :src="previewSrc(img)"
                                alt=""
                                class="w-full h-full object-cover"
                                @error="$event.target.style.display='none'"
                            />
                            <svg v-else xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                 class="w-8 h-8 text-gray-300">
                                <path fill="currentColor"
                                      d="M21 19V5a2 2 0 0 0-2-2H5C3.89 3 3 3.9 3 5v14a2 2 0 0 0 2 2h14c1.11 0 2-.9 2-2M8.5 12.5 11 15.5l3.5-4.5L19 18H5l3.5-5.5Z"/>
                            </svg>
                        </div>
                    </div>

                    <!-- Fields -->
                    <div class="md:col-span-8 grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <label class="block">
                            <span class="block text-xs text-gray-600 dark:text-gray-400 mb-1">Upload image</span>
                            <input type="file" accept="image/*" @change="e => onFileChange(e, img)" class="w-full text-sm" />
                        </label>

                        <label class="block">
                            <span class="block text-xs text-gray-600 mb-1">Alt text</span>
                            <input v-model="img.alt" placeholder="Short description for accessibility" class="w-full border rounded px-3 py-2" />
                            <span v-if="form.errors[`images.${i}.alt`]" class="text-xs text-red-600">{{ form.errors[`images.${i}.alt`] }}</span>
                        </label>

                        <label class="block">
                            <span class="block text-xs text-gray-600 mb-1">Order</span>
                            <input v-model.number="img.sort_order" type="number" min="0" step="1" class="w-full border rounded px-3 py-2" />
                            <span v-if="form.errors[`images.${i}.sort_order`]" class="text-xs text-red-600">{{ form.errors[`images.${i}.sort_order`] }}</span>
                        </label>

                        <label class="block">
                            <span class="block text-xs text-gray-600 mb-1">Primary</span>
                            <div class="flex items-center gap-2">
                                <input type="radio" :name="'primary-image'" :checked="img.is_primary" @change="setPrimary(i)" />
                                <span class="text-sm text-gray-700">Use as main image</span>
                            </div>
                        </label>
                    </div>

                    <!-- Actions -->
                    <div class="md:col-span-2 flex md:flex-col gap-2 justify-start md:justify-center md:items-end">
                        <button class="p-2 rounded border hover:bg-gray-50" :disabled="i===0" @click.prevent="move(i,-1)" title="Move up">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" class="w-5 h-5" fill="currentColor">
                                <path fill-rule="evenodd" d="M14.707 12.293a1 1 0 0 1-1.414 1.414L10 10.414l-3.293 3.293a1 1 0 0 1-1.414-1.414l4-4a1 1 0 0 1 1.414 0l4 4Z" clip-rule="evenodd"/>
                            </svg>
                        </button>
                        <button class="p-2 rounded border hover:bg-gray-50" :disabled="i===form.images.length-1" @click.prevent="move(i,1)" title="Move down">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" class="w-5 h-5" fill="currentColor">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 0 1 1.414 0L10 10.586l3.293-3.293a1 1 0 1 1 1.414 1.414l-4 4a1 1 0 0 1-1.414 0l-4-4a1 1 0 0 1 0-1.414Z" clip-rule="evenodd"/>
                            </svg>
                        </button>
                        <button class="p-2 rounded border border-red-200 text-red-600 hover:bg-red-50" @click.prevent="removeAt(i)" title="Remove image">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="w-5 h-5" fill="currentColor">
                                <path d="M9 3a1 1 0 0 0-1 1v1H5.5a1 1 0 1 0 0 2H6v12a3 3 0 0 0 3 3h6a3 3 0 0 0 3-3V7h.5a1 1 0 1 0 0-2H16V4a1 1 0 0 0-1-1H9Zm2 3h2V5h-2v1ZM9 9a1 1 0 0 1 1 1v8a1 1 0 1 1-2 0v-8a1 1 0 0 1-2-2Z"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <div v-if="form.images.length === 0" class="text-sm text-gray-500 border rounded p-3">
                No images yet. Click “Add image” to select from your computer.
            </div>

            <div v-if="form.hasErrors" class="text-sm text-red-700 bg-red-50 border border-red-200 rounded p-2">
                Please fix the highlighted fields and try again.
            </div>

            <div class="pt-2">
                <button
                    class="px-4 py-2 rounded bg-gray-900 text-white hover:bg-black disabled:opacity-50"
                    :disabled="form.processing || !productId"
                    @click.prevent="submitImages"
                >
                    <span v-if="form.processing">Saving…</span>
                    <span v-else>Save images</span>
                </button>
            </div>
        </div>
    </div>
</template>
