<script setup>
import { Head, router, usePage } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import StorefrontLayout from '@/layouts/StorefrontLayout.vue'
import AddToCartButton from '@/components/Storefront/AddToCartButton.vue'
import ProductGallery from '@/components/Storefront/ProductGallery.vue'
import ProductGrid from '@/components/Storefront/ProductGrid.vue'

defineOptions({ layout: StorefrontLayout })

const props = defineProps({
    product: {
        type: Object,
        required: true,
    },
    relatedProducts: {
        type: Array,
        default: () => [],
    },
})

const quantity = ref(1)
const selectedVariantId = ref(props.product.default_variant_id || props.product.variants?.[0]?.id || null)
const page = usePage()

const selectedVariant = computed(() => {
    return (props.product.variants || []).find((variant) => variant.id === selectedVariantId.value) || props.product.variants?.[0] || null
})

const gallery = computed(() => {
    if (selectedVariant.value?.images?.length) {
        return selectedVariant.value.images
    }

    return props.product.images || []
})

const activePrice = computed(() => selectedVariant.value?.price || props.product.price)
const activeStock = computed(() => selectedVariant.value?.stock || props.product.stock)
const activeDeliveryEstimate = computed(() => selectedVariant.value?.delivery_estimate || props.product.delivery_estimate || null)
const visibleDeliveryEstimate = computed(() => {
    if (!activeDeliveryEstimate.value?.available || !activeDeliveryEstimate.value?.storefront_message) {
        return null
    }

    return activeDeliveryEstimate.value
})

const formatter = new Intl.NumberFormat('en-NG', {
    style: 'currency',
    currency: 'NGN',
})

function money(value) {
    return formatter.format(Number(value || 0))
}

function addToWishlist() {
    if (!selectedVariant.value?.id) {
        return
    }

    if (!page.props.auth?.user) {
        router.visit(route('login'))
        return
    }

    router.post(route('store.wishlist.store'), {
        variant_id: selectedVariant.value.id,
    }, {
        preserveScroll: true,
    })
}

watch(selectedVariantId, () => {
    quantity.value = 1
})
</script>

<template>
    <Head :title="product.name" />

    <section class="grid gap-8 lg:grid-cols-2">
        <ProductGallery :images="gallery" :fallback-alt="product.name" />

        <div class="space-y-6">
            <div class="space-y-2">
                <div class="flex flex-wrap gap-2">
                    <span
                        v-for="badge in product.badges || []"
                        :key="badge"
                        class="rounded-full bg-slate-900 px-2.5 py-1 text-[11px] font-semibold text-white"
                    >
                        {{ badge }}
                    </span>
                </div>

                <h1 class="text-3xl font-bold text-slate-900 dark:text-slate-100">{{ product.name }}</h1>

                <p class="text-sm text-slate-600 dark:text-slate-300" v-if="product.brand?.name">
                    Brand: <span class="font-semibold text-slate-900 dark:text-slate-100">{{ product.brand.name }}</span>
                </p>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-950">
                <div class="flex flex-wrap items-end gap-3">
                    <p class="text-2xl font-bold text-slate-900 dark:text-slate-100">{{ money(activePrice?.current) }}</p>
                    <p v-if="activePrice?.has_discount" class="text-sm text-slate-400 line-through dark:text-slate-500">
                        {{ money(activePrice?.regular) }}
                    </p>
                    <span
                        v-if="activePrice?.has_discount"
                        class="rounded-full bg-rose-500 px-2.5 py-1 text-xs font-semibold text-white"
                    >
                        -{{ activePrice?.discount_percentage || 0 }}%
                    </span>
                </div>

                <p v-if="activePrice?.discount_label" class="mt-2 text-xs font-medium uppercase tracking-[0.18em] text-rose-600">
                    {{ activePrice.discount_label }}
                </p>

                <p
                    :class="[
                        'mt-3 inline-flex rounded-full px-2.5 py-1 text-xs font-semibold',
                        activeStock?.is_in_stock ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700',
                    ]"
                >
                    {{ activeStock?.is_in_stock ? `${activeStock.available} in stock` : 'Out of stock' }}
                </p>

                <p
                    v-if="visibleDeliveryEstimate"
                    class="mt-3 text-sm font-medium text-slate-800 dark:text-slate-200"
                >
                    {{ visibleDeliveryEstimate.storefront_message }}
                </p>

                <div v-if="product.variants?.length" class="mt-5 space-y-2">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Variants</p>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="variant in product.variants"
                            :key="variant.id"
                            type="button"
                            :class="[
                                'rounded-lg border px-3 py-2 text-xs font-medium transition',
                                selectedVariantId === variant.id
                                    ? 'border-slate-900 bg-slate-900 text-white'
                                    : 'border-slate-300 bg-white text-slate-700 hover:border-slate-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-slate-500',
                            ]"
                            @click="selectedVariantId = variant.id"
                        >
                            {{ variant.label }}
                        </button>
                    </div>
                </div>

                <div class="mt-5 grid gap-3 md:grid-cols-[120px_1fr_auto]">
                    <input
                        v-model.number="quantity"
                        type="number"
                        min="1"
                        class="h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 placeholder:text-slate-400 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:placeholder:text-slate-500"
                    >
                    <AddToCartButton
                        :variant-id="selectedVariant?.id"
                        :quantity="quantity"
                        :disabled="!activeStock?.is_in_stock"
                        label="Add to Cart"
                        full-width
                    />
                    <button
                        type="button"
                        class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-500 dark:border-slate-700 dark:text-slate-200 dark:hover:border-slate-500"
                        @click="addToWishlist"
                    >
                        Add to Wishlist
                    </button>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-950">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Product Description</h2>
                <p class="mt-3 whitespace-pre-line text-sm leading-6 text-slate-700 dark:text-slate-300">
                    {{ product.description || 'No description provided yet.' }}
                </p>
            </div>

            <div v-if="product.faqs?.length" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-950">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">FAQs</h2>
                <div class="mt-4 space-y-3">
                    <div v-for="faq in product.faqs" :key="faq.id" class="rounded-xl border border-slate-100 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-900">
                        <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ faq.question }}</p>
                        <p class="mt-1 text-sm text-slate-700 dark:text-slate-300">{{ faq.answer }}</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="mt-12 space-y-4">
        <h2 class="text-xl font-bold text-slate-900 dark:text-slate-100">Related Products</h2>
        <ProductGrid :products="relatedProducts" empty-title="No related products found" />
    </section>
</template>
