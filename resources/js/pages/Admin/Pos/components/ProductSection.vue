<template>
  <div class="relative flex flex-1 flex-col">
    <!-- Filters (unchanged) -->
    <div class="bg-white p-4 shadow-md dark:bg-gray-800">
      <div class="flex items-center gap-3">
        <input v-model="filters.q" placeholder="Search by Product Name/Barcode" class="flex-1 rounded border px-3 py-2" />
        <select v-model="filters.category_id" @change="reload" class="rounded border px-3 py-2">
          <option :value="null">All Categories</option>
          <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>
        <select v-model="filters.brand_id" @change="reload" class="rounded border px-3 py-2">
          <option :value="null">All Brands</option>
          <option v-for="b in brands" :key="b.id" :value="b.id">{{ b.name }}</option>
        </select>
      </div>
    </div>

    <!-- Product Grid -->
    <div class="flex-1 overflow-y-auto p-6 pt-4">
      <div class="3xl:grid-cols-5 grid-cols-4 gap-4 lg:grid">
        <div v-for="variant in variants.data" :key="variant.id" class="flex flex-col rounded bg-white shadow dark:bg-gray-800">
          <div class="relative">
            <div class="h-50 overflow-clip">
              <img :src="imageUrl(variant)" class="w-full rounded object-contain transition-transform duration-500 hover:scale-110" />
            </div>
            <div class="absolute top-1 left-1 p-1 opacity-85">
              <span :class="availableClass(variant)" class="rounded px-2 py-1 text-xs font-medium">{{ stockLabel(variant) }}</span>
            </div>
          </div>

          <div class="mt-2 flex-1 px-3">
            <div class="truncate text-sm font-medium">{{ variant.product?.name || variant.sku }}</div>
            <div class="mt-2 text-sm font-semibold text-blue-600 dark:text-blue-400">{{ formatCurrency(price(variant)) }}</div>
            <div class="truncate text-xs text-gray-500 dark:text-gray-400">{{ variant.values?.map(v => v.value).join(', ') }}</div>
          </div>

          <div class="mt-1 flex items-center gap-2 px-3 py-1">
            <!-- pass the whole variant object -->
            <button @click="addToCart(variant)" class="flex-1 rounded border border-gray-300 py-2 text-sm">
              Add
            </button>
          </div>
        </div>
      </div>

      <div class="mt-6 text-center">
        <button v-if="variants.next_page_url" @click="loadMore" class="rounded bg-blue-100 px-4 py-2 text-blue-700">Load More</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useProducts } from '../composables/useProducts'
import { useCart } from '../composables/useCart'
import { useCurrencyFormatter } from '@/pages/Admin/Pos/composables/useCurrencyFormatter.js';


const { variants, filters, categories, brands, reload, price, stockLabel, availableClass, imageUrl, loadMore } = useProducts()
const { addToCart } = useCart()
const { formatCurrency } = useCurrencyFormatter();


</script>
