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
    <div class="group flex h-full flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-xl dark:border-slate-800 dark:bg-slate-950">
        <Link :href="route('store.product', product.slug)" class="flex flex-1 flex-col">
            <div class="relative aspect-[4/3] overflow-hidden bg-slate-100 dark:bg-slate-900">
                <img
                    v-if="product.image"
                    :src="product.image"
                    :alt="product.name"
                    class="h-full w-full object-cover transition duration-500 group-hover:scale-105"
                    loading="lazy"
                >
                <div v-else class="flex h-full items-center justify-center text-sm text-slate-500 dark:text-slate-400">
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

            <div class="flex flex-1 flex-col space-y-3 p-3 sm:p-4">
                <div class="space-y-1">
                    <p v-if="product.brand?.name" class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400 dark:text-slate-500">
                        {{ product.brand.name }}
                    </p>
                    <h3 class="line-clamp-2 text-sm font-semibold text-slate-900 dark:text-slate-100 sm:text-base">
                        {{ product.name }}
                    </h3>

                    <div v-if="product.specs_summary?.length" class="flex flex-wrap gap-1.5 pt-1">
                        <span
                            v-for="spec in product.specs_summary"
                            :key="`${product.id}-${spec.label}-${spec.value}`"
                            class="inline-flex items-center rounded-full bg-slate-100 px-2 py-1 text-[11px] font-medium text-slate-600 dark:bg-slate-900 dark:text-slate-300"
                        >
                            {{ spec.label }}: {{ spec.value }}
                        </span>
                    </div>
                </div>

                <div class="mt-auto space-y-2">
                    <div class="min-w-0">
                        <p class="text-sm font-bold text-slate-900 dark:text-slate-100 sm:text-base">
                            <span v-if="product.price?.from" class="mr-1 text-xs text-slate-500">From</span>
                            {{ money(product.price?.current) }}
                        </p>
                        <p v-if="product.price?.has_discount" class="text-xs text-slate-400 line-through dark:text-slate-500">
                            {{ money(product.price?.regular) }}
                        </p>
                    </div>
                    <span
                        :class="[
                        'inline-flex w-fit rounded-full px-2.5 py-1 text-[11px] font-semibold',
                        product.stock?.is_in_stock ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700',
                    ]"
                    >
                        {{ product.stock?.is_in_stock ? 'In Stock' : 'Out of Stock' }}
                    </span>

                    <p v-if="product.delivery_estimate?.storefront_message" class="text-[11px] font-medium text-slate-500">
                        {{ product.delivery_estimate.storefront_message }}
                    </p>
                </div>
            </div>
        </Link>
        <div class="p-3 pt-0 sm:p-4 sm:pt-0">
            <AddToCartButton
                :variant-id="product.default_variant_id"
                :disabled="!product.stock?.is_in_stock"
                label="Add to Cart"
                full-width
            />
        </div>
    </div>
</template>
