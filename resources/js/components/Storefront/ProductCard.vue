<script setup>
import { Link } from '@inertiajs/vue3'
import AddToCartButton from '@/components/Storefront/AddToCartButton.vue'

const props = defineProps({
    product: {
        type: Object,
        required: true,
    },
})

const formatter = new Intl.NumberFormat('en-NG', {
    style: 'currency',
    currency: 'NGN',
})

function money(value) {
    return formatter.format(Number(value || 0))
}
</script>

<template>
    <Link :href="route('store.product', product.slug)" class="group relative pb-8 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-xl">
        <div class="relative aspect-[4/3] overflow-hidden bg-slate-100">
            <img
                v-if="product.image"
                :src="product.image"
                :alt="product.name"
                class="h-full w-full object-cover transition duration-500 group-hover:scale-105"
                loading="lazy"
            >
            <div v-else class="flex h-full items-center justify-center text-sm text-slate-500">
                No image
            </div>

            <div class="absolute left-3 top-3 flex flex-wrap gap-1">
                <span
                    v-if="product.price?.has_discount"
                    class="rounded-full bg-rose-500 px-2.5 py-1 text-[11px] font-semibold text-white"
                >
                    -{{ product.price?.discount_percentage || 0 }}%
                </span>
                <span
                    v-for="badge in product.badges || []"
                    :key="badge"
                    class="rounded-full bg-slate-900/90 px-2.5 py-1 text-[11px] font-semibold text-white"
                >
                    {{ badge }}
                </span>
            </div>
        </div>

        <div class="space-y-3 p-4 ">
            <div class="space-y-1">
                <h3 class="line-clamp-2 text-sm font-semibold text-slate-900">
                    {{ product.name }}
                </h3>
                <p class="line-clamp-2 text-xs text-slate-500">
                    {{ product.description || 'Premium product for modern retail operations.' }}
                </p>
            </div>

            <div class="flex items-end justify-between gap-2">
                <div>
                    <p class="text-base font-bold text-slate-900">
                        <span v-if="product.price?.from" class="mr-1 text-xs text-slate-500">From</span>
                        {{ money(product.price?.current) }}
                    </p>
                    <p v-if="product.price?.has_discount" class="text-xs text-slate-400 line-through">
                        {{ money(product.price?.regular) }}
                    </p>
                </div>
                <span
                    :class="[
                        'rounded-full px-2 py-1 text-[11px] font-semibold',
                        product.stock?.is_in_stock ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700',
                    ]"
                >
                    {{ product.stock?.is_in_stock ? 'In Stock' : 'Out of Stock' }}
                </span>
            </div>

            <div class="grid grid-cols-2 gap-2 absolute bottom-3">
                <AddToCartButton
                    :variant-id="product.default_variant_id"
                    :disabled="!product.stock?.is_in_stock"
                    label="Add to Cart"
                    full-width
                />
            </div>
        </div>
    </Link>
</template>
