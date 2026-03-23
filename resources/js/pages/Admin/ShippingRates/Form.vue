<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import ShippingRateForm from '@/components/Admin/Shipping/ShippingRateForm.vue'

defineProps<{
    mode: 'create' | 'edit'
    shippingRate: Record<string, unknown> | null
    methods: Array<{ id: number; name: string; method_type: string; is_active: boolean }>
    zones: Array<{ id: number; name: string; state_count: number }>
    states: Array<{ id: number; name: string }>
    lgas: Array<{ id: number; name: string }>
    scopeTypes: Array<{ value: string; label: string }>
    rateTypes: Array<{ value: string; label: string }>
}>()
</script>

<template>
    <Head :title="mode === 'edit' ? 'Edit Shipping Rate' : 'Create Shipping Rate'" />

    <ShippingRateForm
        :mode="mode"
        :shipping-rate="shippingRate"
        :methods="methods"
        :zones="zones"
        :states="states"
        :lgas="lgas"
        :scope-types="scopeTypes"
        :rate-types="rateTypes"
    />
</template>
