<script setup>
import { ArrowLeft, ArrowRight, ImagePlus, Star, Trash2, UploadCloud } from 'lucide-vue-next'
import { computed, inject, onBeforeUnmount, ref } from 'vue'

const wizard = inject('productWizard')
const { form, serverErr } = wizard

const dragActive = ref(false)
const fileInput = ref(null)

const images = computed(() => form.images)

function addFiles(fileList) {
    const files = Array.from(fileList || []).filter(file => file.type?.startsWith('image/'))
    for (const file of files) {
        form.images.push({
            file,
            alt: '',
            is_primary: form.images.length === 0,
            sort_order: form.images.length,
            _preview: URL.createObjectURL(file),
        })
    }
}

function onPick(event) {
    addFiles(event.target.files)
    event.target.value = ''
}

function onDrop(event) {
    dragActive.value = false
    addFiles(event.dataTransfer?.files)
}

function setPrimary(index) {
    form.images.forEach((row, i) => { row.is_primary = i === index })
}

function removeAt(index) {
    const row = form.images[index]
    if (row?._preview) {
        try { URL.revokeObjectURL(row._preview) } catch { /* noop */ }
    }
    form.images.splice(index, 1)
    if (!form.images.some(item => item.is_primary) && form.images[0]) {
        form.images[0].is_primary = true
    }
    form.images.forEach((item, i) => { item.sort_order = i })
}

function move(index, direction) {
    const target = index + direction
    if (target < 0 || target >= form.images.length) return
    const copy = [...form.images]
    ;[copy[index], copy[target]] = [copy[target], copy[index]]
    copy.forEach((item, i) => { item.sort_order = i })
    form.images = copy
}

onBeforeUnmount(() => {
    form.images.forEach(row => {
        if (row?._preview) {
            try { URL.revokeObjectURL(row._preview) } catch { /* noop */ }
        }
    })
})
</script>

<template>
    <section class="space-y-4">
        <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h2 class="text-lg font-semibold">Show it off</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Products with photos sell dramatically better. Drop a few in — the starred one becomes the main image everywhere in the store.
            </p>

            <!-- Dropzone -->
            <button
                type="button"
                class="mt-5 flex w-full flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed px-6 py-10 text-center transition"
                :class="dragActive
                    ? 'border-blue-500 bg-blue-50/70 dark:border-blue-400 dark:bg-blue-950/30'
                    : 'border-gray-300 hover:border-blue-400 hover:bg-gray-50 dark:border-gray-700 dark:hover:border-blue-500 dark:hover:bg-gray-800/50'"
                @click="fileInput?.click()"
                @dragover.prevent="dragActive = true"
                @dragleave.prevent="dragActive = false"
                @drop.prevent="onDrop"
            >
                <UploadCloud class="h-8 w-8 text-gray-400 dark:text-gray-500" aria-hidden="true" />
                <span class="text-sm font-medium text-gray-700 dark:text-gray-200">
                    Drag & drop photos here, or <span class="text-blue-600 underline dark:text-blue-400">browse</span>
                </span>
                <span class="text-xs text-gray-400 dark:text-gray-500">JPG, PNG, WebP, AVIF or GIF — up to 5 MB each</span>
            </button>
            <input ref="fileInput" type="file" accept="image/*" multiple class="hidden" @change="onPick" />

            <p v-if="serverErr('images')" class="mt-2 text-xs text-rose-600 dark:text-rose-400">{{ serverErr('images') }}</p>

            <!-- Gallery grid -->
            <div v-if="images.length" class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <figure
                    v-for="(img, i) in images"
                    :key="i"
                    class="group relative overflow-hidden rounded-xl border shadow-sm transition dark:border-gray-700"
                    :class="img.is_primary ? 'border-blue-500 ring-2 ring-blue-200 dark:ring-blue-900' : 'border-gray-200'"
                >
                    <img :src="img._preview" :alt="img.alt || `Photo ${i + 1}`" class="aspect-square w-full object-cover" />

                    <span
                        v-if="img.is_primary"
                        class="absolute left-2 top-2 inline-flex items-center gap-1 rounded-full bg-blue-600 px-2 py-0.5 text-[11px] font-semibold text-white shadow"
                    >
                        <Star class="h-3 w-3 fill-current" aria-hidden="true" /> Main photo
                    </span>

                    <div class="absolute right-2 top-2 flex gap-1.5">
                        <button
                            v-if="!img.is_primary"
                            type="button"
                            class="rounded-full bg-white/90 p-1.5 text-gray-600 shadow transition hover:bg-white hover:text-blue-600 dark:bg-gray-900/90 dark:text-gray-300"
                            title="Make main photo"
                            aria-label="Make main photo"
                            @click="setPrimary(i)"
                        >
                            <Star class="h-4 w-4" aria-hidden="true" />
                        </button>
                        <button
                            type="button"
                            class="rounded-full bg-white/90 p-1.5 text-gray-600 shadow transition hover:bg-white hover:text-rose-600 dark:bg-gray-900/90 dark:text-gray-300"
                            title="Remove photo"
                            aria-label="Remove photo"
                            @click="removeAt(i)"
                        >
                            <Trash2 class="h-4 w-4" aria-hidden="true" />
                        </button>
                    </div>

                    <figcaption class="space-y-2 p-3">
                        <input
                            v-model="img.alt"
                            type="text"
                            placeholder="Describe this photo (alt text)"
                            class="w-full rounded-md border border-gray-200 bg-white px-2 py-1.5 text-xs dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100"
                        />
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] text-gray-400 dark:text-gray-500">Position {{ i + 1 }}</span>
                            <span class="flex gap-1">
                                <button
                                    type="button"
                                    class="rounded border border-gray-200 p-1 text-gray-500 transition hover:bg-gray-50 disabled:opacity-30 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-800"
                                    :disabled="i === 0"
                                    title="Move earlier"
                                    aria-label="Move earlier"
                                    @click="move(i, -1)"
                                >
                                    <ArrowLeft class="h-3.5 w-3.5" aria-hidden="true" />
                                </button>
                                <button
                                    type="button"
                                    class="rounded border border-gray-200 p-1 text-gray-500 transition hover:bg-gray-50 disabled:opacity-30 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-800"
                                    :disabled="i === images.length - 1"
                                    title="Move later"
                                    aria-label="Move later"
                                    @click="move(i, 1)"
                                >
                                    <ArrowRight class="h-3.5 w-3.5" aria-hidden="true" />
                                </button>
                            </span>
                        </div>
                    </figcaption>
                </figure>
            </div>

            <div v-else class="mt-6 flex items-center gap-3 rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm text-gray-500 dark:border-gray-800 dark:bg-gray-950 dark:text-gray-400">
                <ImagePlus class="h-5 w-5 shrink-0" aria-hidden="true" />
                No photos yet — that's okay, you can add them here now or from the product page later.
            </div>
        </div>
    </section>
</template>
