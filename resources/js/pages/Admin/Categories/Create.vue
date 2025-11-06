<script setup>
import { ref, watch, onMounted } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'

// This single page serves BOTH Create and Edit modes.
// Controller should pass: parents[], optional category, and optional mode="edit".
const props = defineProps({
  parents: { type: Array, default: () => [] },
  category: { type: Object, default: null },
  mode: { type: String, default: 'create' } // 'create' | 'edit'
})

const isEdit = ref(props.mode === 'edit' && !!props.category)

// Helpers
const toSlug = s => s
  .toString()
  .normalize('NFKD')
  .replace(/[^\w\s-]/g, '')
  .trim()
  .replace(/\s+/g, '-')
  .toLowerCase()

const guessAssetUrl = (val) => {
  if (!val) return null
  if (val.startsWith('http')) return val
  if (val.startsWith('/')) return val
  return `/storage/${val}` // adjust if your files live elsewhere
}

// Inertia form with defaults from category when editing
const form = useForm({
  name: props.category?.name ?? '',
  banner: null,
  icon: null,
  cover_image: null,
  featured: props.category?.featured ?? false,
  order: props.category?.order ?? 0,
  meta_title: props.category?.meta_title ?? '',
  meta_description: props.category?.meta_description ?? '',
  slug: props.category?.slug ?? '',
  description: props.category?.description ?? '',
  parent_id: props.category?.parent_id ?? null
})

// Auto-slug (user can override)
const manualSlug = ref(false)
watch(() => form.name, (v) => {
  if (!manualSlug.value && !isEdit.value) form.slug = toSlug(v || '')
})
watch(() => form.slug, (v) => {
  if (v && v !== toSlug(form.name || '')) manualSlug.value = true
})

// File previews (use existing images on mount if editing)
const bannerPreview = ref(null)
const iconPreview = ref(null)
const coverPreview = ref(null)

onMounted(() => {
  if (isEdit.value) {
    bannerPreview.value = guessAssetUrl(props.category?.banner)
    iconPreview.value = guessAssetUrl(props.category?.icon)
    coverPreview.value = guessAssetUrl(props.category?.cover_image)
  }
})

function onFileChange(e, key, previewRef) {
  const file = e.target.files?.[0]
  form[key] = file || null
  if (file) {
    const reader = new FileReader()
    reader.onload = () => { previewRef.value = reader.result }
    reader.readAsDataURL(file)
  } else {
    // if cleared, keep existing preview in edit mode unless user truly clears
    previewRef.value = isEdit.value ? guessAssetUrl(props.category?.[key]) : null
  }
}

function clearFile(key, previewRef, inputRef) {
  form[key] = null
  previewRef.value = null
  if (inputRef?.value) inputRef.value.value = ''
}

const bannerInput = ref(null)
const iconInput = ref(null)
const coverInput = ref(null)

function submit() {
  if (isEdit.value) {
    form.put(route('admin.categories.update', props.category.id), { method: 'put' })
  } else {
    form.post(route('admin.categories.store'))
  }
}
</script>

<template>
  <Head :title="isEdit ? 'Edit Category' : 'Create Category'" />

  <div class="max-w-6xl mx-auto p-6">
    <div class="mb-6 flex items-center justify-between">
      <h1 class="text-2xl font-semibold">{{ isEdit ? 'Edit Category' : 'Create New Category' }}</h1>
      <Link :href="route('admin.categories.index')" class="text-sm underline">Back to list</Link>
    </div>

    <div class="bg-white shadow-sm rounded-2xl border border-gray-100">
      <div class="px-6 py-4 border-b">
        <h2 class="text-lg font-medium">Category Information</h2>
      </div>

      <form @submit.prevent="submit" class="p-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
          <!-- Name -->
          <label class="md:col-span-1 text-sm text-gray-600 self-center">Name</label>
          <div class="md:col-span-3">
            <input v-model="form.name" type="text" placeholder="Name" class="w-full rounded-lg border-gray-300" />
            <div v-if="form.errors.name" class="text-sm text-red-600 mt-1">{{ form.errors.name }}</div>
          </div>

          <!-- Parent -->
          <label class="md:col-span-1 text-sm text-gray-600 self-center">Parent Category</label>
          <div class="md:col-span-3">
            <select v-model="form.parent_id" class="w-full rounded-lg border-gray-300">
              <option :value="null">No Parent</option>
              <option v-for="p in props.parents" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
            <div v-if="form.errors.parent_id" class="text-sm text-red-600 mt-1">{{ form.errors.parent_id }}</div>
          </div>

          <!-- Order -->
          <label class="md:col-span-1 text-sm text-gray-600 self-center">Ordering Number</label>
          <div class="md:col-span-3">
            <input v-model.number="form.order" type="number" min="0" class="w-full rounded-lg border-gray-300" placeholder="Order Level" />
            <p class="text-xs text-gray-500 mt-1">Higher number has high priority</p>
            <div v-if="form.errors.order" class="text-sm text-red-600 mt-1">{{ form.errors.order }}</div>
          </div>

          <!-- Banner -->
          <label class="md:col-span-1 text-sm text-gray-600 self-center">Banner</label>
          <div class="md:col-span-3">
            <div class="flex items-center gap-4">
              <input ref="bannerInput" type="file" accept="image/*" @change="e => onFileChange(e, 'banner', bannerPreview)" class="block w-full text-sm text-gray-700" />
              <button type="button" class="px-3 py-2 text-sm rounded-lg bg-gray-100" @click="clearFile('banner', bannerPreview, { value: bannerInput })">Clear</button>
            </div>
            <p class="text-xs text-gray-500 mt-1">Minimum dimensions required: 150px width × 150px height.</p>
            <div v-if="bannerPreview" class="mt-3">
              <img :src="bannerPreview" alt="Banner preview" class="h-24 rounded-md border object-cover" />
            </div>
            <div v-if="form.errors.banner" class="text-sm text-red-600 mt-1">{{ form.errors.banner }}</div>
          </div>

          <!-- Icon -->
          <label class="md:col-span-1 text-sm text-gray-600 self-center">Icon</label>
          <div class="md:col-span-3">
            <div class="flex items-center gap-4">
              <input ref="iconInput" type="file" accept="image/*" @change="e => onFileChange(e, 'icon', iconPreview)" class="block w-full text-sm text-gray-700" />
              <button type="button" class="px-3 py-2 text-sm rounded-lg bg-gray-100" @click="clearFile('icon', iconPreview, { value: iconInput })">Clear</button>
            </div>
            <p class="text-xs text-gray-500 mt-1">Minimum dimensions required: 16px width × 16px height.</p>
            <div v-if="iconPreview" class="mt-3">
              <img :src="iconPreview" alt="Icon preview" class="h-16 rounded-md border object-contain" />
            </div>
            <div v-if="form.errors.icon" class="text-sm text-red-600 mt-1">{{ form.errors.icon }}</div>
          </div>

          <!-- Cover Image -->
          <label class="md:col-span-1 text-sm text-gray-600 self-center">Cover Image</label>
          <div class="md:col-span-3">
            <div class="flex items-center gap-4">
              <input ref="coverInput" type="file" accept="image/*" @change="e => onFileChange(e, 'cover_image', coverPreview)" class="block w-full text-sm text-gray-700" />
              <button type="button" class="px-3 py-2 text-sm rounded-lg bg-gray-100" @click="clearFile('cover_image', coverPreview, { value: coverInput })">Clear</button>
            </div>
            <p class="text-xs text-gray-500 mt-1">Minimum dimensions required: 260px width × 260px height.</p>
            <div v-if="coverPreview" class="mt-3">
              <img :src="coverPreview" alt="Cover preview" class="h-28 rounded-md border object-cover" />
            </div>
            <div v-if="form.errors.cover_image" class="text-sm text-red-600 mt-1">{{ form.errors.cover_image }}</div>
          </div>

          <!-- Meta Title -->
          <label class="md:col-span-1 text-sm text-gray-600 self-center">Meta Title</label>
          <div class="md:col-span-3">
            <input v-model="form.meta_title" type="text" placeholder="Meta Title" class="w-full rounded-lg border-gray-300" />
            <div v-if="form.errors.meta_title" class="text-sm text-red-600 mt-1">{{ form.errors.meta_title }}</div>
          </div>

          <!-- Meta Description -->
          <label class="md:col-span-1 text-sm text-gray-600 self-center">Meta description</label>
          <div class="md:col-span-3">
            <textarea v-model="form.meta_description" rows="4" class="w-full rounded-lg border-gray-300" placeholder="Meta description"></textarea>
            <div v-if="form.errors.meta_description" class="text-sm text-red-600 mt-1">{{ form.errors.meta_description }}</div>
          </div>

          <!-- Slug -->
          <label class="md:col-span-1 text-sm text-gray-600 self-center">Slug</label>
          <div class="md:col-span-3">
            <input v-model="form.slug" type="text" placeholder="slug" class="w-full rounded-lg border-gray-300" />
            <div v-if="form.errors.slug" class="text-sm text-red-600 mt-1">{{ form.errors.slug }}</div>
          </div>

          <!-- Description -->
          <label class="md:col-span-1 text-sm text-gray-600 self-center">Description</label>
          <div class="md:col-span-3">
            <textarea v-model="form.description" rows="4" class="w-full rounded-lg border-gray-300" placeholder="Optional description"></textarea>
            <div v-if="form.errors.description" class="text-sm text-red-600 mt-1">{{ form.errors.description }}</div>
          </div>

          <!-- Featured -->
          <label class="md:col-span-1 text-sm text-gray-600 self-center">Featured</label>
          <div class="md:col-span-3 flex items-center gap-3">
            <input id="featured" v-model="form.featured" type="checkbox" class="rounded border-gray-300" />
            <label for="featured" class="text-sm text-gray-700">Mark as featured</label>
            <div v-if="form.errors.featured" class="text-sm text-red-600">{{ form.errors.featured }}</div>
          </div>
        </div>

        <div class="mt-8 flex justify-end gap-3">
          <Link :href="route('admin.categories.index')" class="px-4 py-2 rounded-lg border">Cancel</Link>
          <button type="submit" :disabled="form.processing" class="px-5 py-2 rounded-lg text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-50">
            <span v-if="!form.processing">{{ isEdit ? 'Update' : 'Save' }}</span>
            <span v-else>{{ isEdit ? 'Updating…' : 'Saving…' }}</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<!--<style scoped>-->
<!--input[type="text"], input[type="number"], select, textarea {-->
<!--  @apply bg-white border px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500/30;-->
<!--}-->
<!--</style>-->
