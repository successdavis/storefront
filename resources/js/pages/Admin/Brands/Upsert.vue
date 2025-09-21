<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'

const props = defineProps({
    // When creating, pass null. When editing, pass the Brand resource.
    brand: {
        type: Object,
        default: null
    }
})

const isEdit = computed(() => !!props.brand)
const pageTitle = computed(() => (isEdit.value ? `Edit Brand — ${props.brand?.name}` : 'Create Brand'))

const form = useForm({
    name: props.brand?.name ?? '',
    slug: props.brand?.slug ?? '',
    logo: null, // file
    meta_title: props.brand?.meta_title ?? '',
    meta_description: props.brand?.meta_description ?? '',
    description: props.brand?.description ?? '',
    top_brand: !!props.brand?.top_brand
})

const previewUrl = ref(props.brand?.logo_url ?? null)

// Auto-slug while creating (or if slug is empty)
watch(() => form.name, (val) => {
    if (!isEdit.value || !form.slug) {
        form.slug = slugify(val)
    }
})

function slugify(s) {
    return String(s || '')
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/(^-|-$)+/g, '')
}

function onFileChange(e) {
    const file = e.target.files?.[0]
    if (!file) return
    form.logo = file
    const r = new FileReader()
    r.onload = () => { previewUrl.value = r.result }
    r.readAsDataURL(file)
}

function submit() {
    if (isEdit.value) {
        form.transform(data => ({ ...data, _method: 'PUT' }))
            .post(route('admin.brands.update', props.brand.id), {
                forceFormData: true,
                preserveScroll: true,
                replace: true,
                onSuccess: () => {
                    // optional: reset only certain fields
                    form.reset()
                }
            })
    } else {
        form.post(route('admin.brands.store'), {
            forceFormData: true,
            preserveScroll: true,
            replace: true
        })
    }
}

function destroyBrand() {
    if (!isEdit.value) return
    if (!confirm('Delete this brand? This cannot be undone.')) return
    router.delete(route('admin.brands.destroy', props.brand.id), {
        preserveScroll: true
    })
}
</script>

<template>
    <div class="space-y-6">
        <Head :title="pageTitle" />

        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold">{{ isEdit ? 'Edit Brand' : 'Create Brand' }}</h1>
            <div class="flex items-center gap-2">
                <Link
                    :href="route('admin.brands.index')"
                    class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
                >
                    Back to list
                </Link>
                <button
                    v-if="isEdit"
                    type="button"
                    class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-medium text-white hover:bg-rose-700"
                    @click="destroyBrand"
                >
                    Delete
                </button>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-6">
            <form @submit.prevent="submit" class=" grid-cols-1 gap-6 lg:grid-cols-12">
                <!-- Left -->
                <div class="space-y-6 lg:col-span-8">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Name</label>
                        <input
                            v-model="form.name"
                            type="text"
                            class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            required
                        />
                        <p v-if="form.errors.name" class="mt-1 text-sm text-rose-600">{{ form.errors.name }}</p>
                    </div>

                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Slug</label>
                            <input
                                v-model="form.slug"
                                type="text"
                                class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                placeholder="auto from name if left blank"
                            />
                            <p v-if="form.errors.slug" class="mt-1 text-sm text-rose-600">{{ form.errors.slug }}</p>
                        </div>

                        <div class="flex items-center gap-3">
                            <input
                                id="top_brand"
                                v-model="form.top_brand"
                                type="checkbox"
                                class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                            />
                            <label for="top_brand" class="text-sm text-gray-800">Top brand</label>
                        </div>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Meta Title</label>
                        <input
                            v-model="form.meta_title"
                            type="text"
                            class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        />
                        <p v-if="form.errors.meta_title" class="mt-1 text-sm text-rose-600">{{ form.errors.meta_title }}</p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Meta Description</label>
                        <textarea
                            v-model="form.meta_description"
                            rows="2"
                            class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        />
                        <p v-if="form.errors.meta_description" class="mt-1 text-sm text-rose-600">{{ form.errors.meta_description }}</p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Description</label>
                        <textarea
                            v-model="form.description"
                            rows="5"
                            class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        />
                        <p v-if="form.errors.description" class="mt-1 text-sm text-rose-600">{{ form.errors.description }}</p>
                    </div>
                </div>

                <!-- Right -->
                <div class="space-y-6 lg:col-span-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Logo</label>
                        <div class="flex items-center gap-4">
                            <div class="h-20 w-20 overflow-hidden rounded bg-gray-100 ring-1 ring-gray-200">
                                <img v-if="previewUrl" :src="previewUrl" alt="Preview" class="h-20 w-20 object-cover" />
                            </div>
                            <div>
                                <input type="file" accept="image/*" @change="onFileChange" />
                                <p class="mt-1 text-xs text-gray-500">JPG, PNG, or WEBP up to 2MB</p>
                                <p v-if="form.errors.logo" class="mt-1 text-sm text-rose-600">{{ form.errors.logo }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-2">
                        <Link
                            :href="route('admin.brands.index')"
                            class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
                        >
                            Cancel
                        </Link>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                        >
                            <svg v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" />
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4A4 4 0 004 12z"/>
                            </svg>
                            Save
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</template>
