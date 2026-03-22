<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import PromotionForm from '@/components/Admin/Promotions/PromotionForm.vue'

defineProps<{
    mode: 'create' | 'edit'
    coupon: Record<string, unknown> | null
    categories: Array<{ id: number; label: string; meta?: string | null }>
    products: Array<{ id: number; label: string; meta?: string | null }>
    customers: Array<{ id: number; label: string; meta?: string | null }>
}>()
</script>

<template>
    <Head :title="mode === 'edit' ? 'Edit Coupon' : 'Create Coupon'" />

    <PromotionForm
        :mode="mode"
        form-type="coupon"
        :promotion="coupon"
        :categories="categories"
        :products="products"
        :customers="customers"
    />
</template>
