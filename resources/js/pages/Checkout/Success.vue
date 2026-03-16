<script setup>
import { Head, Link } from '@inertiajs/vue3'
import StorefrontLayout from '@/layouts/StorefrontLayout.vue'

defineOptions({ layout: StorefrontLayout })

const props = defineProps({
    order: {
        type: Object,
        default: null,
    },
})

const formatter = new Intl.NumberFormat('en-NG', {
    style: 'currency',
    currency: props.order?.currency || 'NGN',
})

function money(value) {
    return formatter.format(Number(value || 0))
}
</script>

<template>
    <Head title="Order Success" />

    <section class="mx-auto max-w-2xl rounded-2xl border border-emerald-200 bg-white p-8 text-center shadow-sm">
        <h1 class="text-3xl font-bold tracking-tight text-slate-900">Payment Successful</h1>
        <p class="mt-2 text-sm text-slate-600">
            Your payment has been verified and your order is now marked as paid.
        </p>

        <div v-if="order" class="mt-6 rounded-xl border border-slate-200 bg-slate-50 p-4 text-left text-sm">
            <p><span class="font-semibold text-slate-700">Order:</span> {{ order.order_number }}</p>
            <p class="mt-1"><span class="font-semibold text-slate-700">Status:</span> {{ order.status }}</p>
            <p class="mt-1"><span class="font-semibold text-slate-700">Total:</span> {{ money(order.total_amount) }}</p>
        </div>

        <div class="mt-6 flex flex-wrap justify-center gap-3">
            <Link
                :href="route('store.home')"
                class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700"
            >
                Continue Shopping
            </Link>
            <Link
                :href="route('orders.index')"
                class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-500"
            >
                View Orders
            </Link>
        </div>
    </section>
</template>
