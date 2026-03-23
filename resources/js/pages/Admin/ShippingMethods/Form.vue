<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import ShippingMethodForm from '@/components/Admin/Shipping/ShippingMethodForm.vue'

defineProps<{
    mode: 'create' | 'edit'
    shippingMethod: Record<string, unknown> | null
    methodTypes: Array<{ value: string; label: string }>
}>()
</script>

<template>
    <Head :title="mode === 'edit' ? 'Edit Shipping Method' : 'Create Shipping Method'" />

    <ShippingMethodForm
        :mode="mode"
        :shipping-method="shippingMethod"
        :method-types="methodTypes"
    />
</template>
