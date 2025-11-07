<script setup>
import { Head, router } from '@inertiajs/vue3'
import BrandForm from '@/components/Admin/Brands/BrandForm.vue';

function submit(values) {
  router.post(route('admin.brands.store'), values, {
    forceFormData: true
  })
}
</script>

<template>
  <div class="space-y-6">
    <Head title="Create Brand" />
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-semibold">Create Brand</h1>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-6">
      <BrandForm
        :initial="{
          name: '',
          slug: '',
          logo: null,
          meta_title: '',
          meta_description: '',
          description: '',
          top_brand: false
        }"
        @submit="submit"
      />
    </div>
  </div>
</template>
