<template>
  <div class="p-6 max-w-2xl mx-auto">
    <h1 class="text-2xl font-bold mb-4">Create Product</h1>
    <form @submit.prevent="submit">
      <div class="mb-4">
        <label class="block text-sm font-medium text-gray-700">Name</label>
        <input v-model="form.name" type="text" class="mt-1 block w-full border-gray-300 rounded shadow-sm" />
      </div>

      <div class="mb-4">
        <label class="block text-sm font-medium text-gray-700">Category</label>
        <select v-model="form.category_id" class="mt-1 block w-full border-gray-300 rounded shadow-sm">
          <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
        </select>
      </div>

      <div class="mb-4">
        <label class="block text-sm font-medium text-gray-700">Brand</label>
        <select v-model="form.brand_id" class="mt-1 block w-full border-gray-300 rounded shadow-sm">
          <option v-for="brand in brands" :key="brand.id" :value="brand.id">{{ brand.name }}</option>
        </select>
      </div>

      <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">Save</button>
    </form>
  </div>
</template>

<script setup>
import { reactive } from 'vue';
import { useForm } from '@inertiajs/vue3';

defineProps({ categories: Array, brands: Array })

const form = useForm({
  name: '',
  category_id: '',
  brand_id: ''
})

const submit = () => form.post('/products')
</script>

<style scoped></style>
