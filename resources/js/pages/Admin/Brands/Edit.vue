<script setup>
import { Head, router } from '@inertiajs/vue3'
import BrandForm from '@/components/Admin/Brands/BrandForm.vue';

const props = defineProps({
  brand: {
    type: Object,
    required: true
  }
})

function submit(values) {
  router.patch(route('admin.brands.update', props.brand.id), values, {
    method: 'put',
    forceFormData: true,
    preserveScroll: true
  })
}
</script>

<template>
  <div class="space-y-6">
    <Head :title="`Edit Brand — ${brand.name}`" />
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-semibold">Edit Brand</h1>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-6">
      <BrandForm
        :initial="{
          name: brand.name,
          slug: brand.slug,
          logo: null,
          logo_url: brand.logo_url,
          meta_title: brand.meta_title,
          meta_description: brand.meta_description,
          description: brand.description,
          top_brand: brand.top_brand
        }"
        :is-edit="true"
        @submit="submit"
      />
    </div>
  </div>
</template>
