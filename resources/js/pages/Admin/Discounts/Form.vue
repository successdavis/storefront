<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PromotionForm from '@/components/Admin/Promotions/PromotionForm.vue'

defineProps<{
    mode: 'create' | 'edit'
    discount: Record<string, unknown> | null
    categories: Array<{ id: number; label: string; meta?: string | null }>
    products: Array<{ id: number; label: string; meta?: string | null }>
    customers: Array<{ id: number; label: string; meta?: string | null }>
}>()
</script>

<template>
    <Head :title="mode === 'edit' ? 'Edit Discount' : 'Create Discount'" />

    <PromotionForm
        :mode="mode"
        form-type="discount"
        :promotion="discount"
        :categories="categories"
        :products="products"
        :customers="customers"
    />
</template>
