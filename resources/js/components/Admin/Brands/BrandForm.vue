<script setup>
import { reactive, ref, watch, computed } from 'vue'
import { useForm } from '@inertiajs/vue3'

const props = defineProps({
  initial: { type: Object, required: true },
  isEdit: { type: Boolean, default: false }
})

const emit = defineEmits(['submit'])

const form = useForm({
  name: props.initial.name ?? '',
  slug: props.initial.slug ?? '',
  logo: null, // file
  meta_title: props.initial.meta_title ?? '',
  meta_description: props.initial.meta_description ?? '',
  description: props.initial.description ?? '',
  top_brand: !!props.initial.top_brand
})

const submitting = ref(false)
const previewUrl = ref(props.initial.logo_url || null)

// local slugger when user types name and slug is blank
watch(() => form.name, (val) => {
  if (!props.isEdit && (!form.slug || form.slug.trim() === '')) {
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
  submitting.value = true
  const payload = new FormData()
  Object.entries(form.data()).forEach(([k, v]) => {
    if (v === null || v === undefined) return
    // booleans must be cast for Laravel
    if (typeof v === 'boolean') {
      payload.append(k, v ? '1' : '0')
    } else {
      payload.append(k, v)
    }
  })

  emit('submit', payload)
}

</script>

<template>
  <form @submit.prevent="submit" class="grid grid-cols-1 gap-6 lg:grid-cols-12">
    <!-- Left column -->
    <div class="lg:col-span-8 space-y-6">
      <div>
        <label class="mb-1 block text-sm font-medium text-gray-700">Name</label>
        <input
          v-model="form.name"
          type="text"
          class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
          required
        />
        <div v-if="form.errors.name" class="mt-1 text-sm text-rose-600">{{ form.errors.name }}</div>
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
          <div v-if="form.errors.slug" class="mt-1 text-sm text-rose-600">{{ form.errors.slug }}</div>
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
        <div v-if="form.errors.meta_title" class="mt-1 text-sm text-rose-600">{{ form.errors.meta_title }}</div>
      </div>

      <div>
        <label class="mb-1 block text-sm font-medium text-gray-700">Meta Description</label>
        <textarea
          v-model="form.meta_description"
          rows="2"
          class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
        />
        <div v-if="form.errors.meta_description" class="mt-1 text-sm text-rose-600">{{ form.errors.meta_description }}</div>
      </div>

      <div>
        <label class="mb-1 block text-sm font-medium text-gray-700">Description</label>
        <textarea
          v-model="form.description"
          rows="5"
          class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
        />
        <div v-if="form.errors.description" class="mt-1 text-sm text-rose-600">{{ form.errors.description }}</div>
      </div>
    </div>

    <!-- Right column -->
    <div class="lg:col-span-4 space-y-6">
      <div>
        <label class="mb-1 block text-sm font-medium text-gray-700">Logo</label>
        <div class="flex items-center gap-4">
          <div class="h-20 w-20 overflow-hidden rounded bg-gray-100 ring-1 ring-gray-200">
            <img v-if="previewUrl" :src="previewUrl" alt="Preview" class="h-20 w-20 object-cover" />
          </div>
          <div>
            <input type="file" accept="image/*" @change="onFileChange" />
            <p class="mt-1 text-xs text-gray-500">JPG, PNG, or WEBP up to 2MB</p>
            <div v-if="form.errors.logo" class="mt-1 text-sm text-rose-600">{{ form.errors.logo }}</div>
          </div>
        </div>
      </div>

      <div class="flex items-center justify-end gap-3 pt-2">
        <router-link
          :href="route('admin.brands.index')"
          as="button"
          type="button"
          class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
        >
          Cancel
        </router-link>

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
</template>
