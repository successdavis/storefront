<script setup>
import axios from 'axios'
import { Head, router, usePage } from '@inertiajs/vue3'
import { Heart } from 'lucide-vue-next'
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
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
const variantDeliveryEstimates = ref(buildVariantEstimateMap(props.product))
const lastEstimateSignature = ref(null)
const descriptionExpanded = ref(false)
const isDesktopViewport = ref(false)

let descriptionViewportQuery = null
let removeDescriptionViewportListener = null

const selectedVariant = computed(() => {
    return (props.product.variants || []).find((variant) => variant.id === selectedVariantId.value) || props.product.variants?.[0] || null
})

const browsingLocation = computed(() => page.props.browsingLocation || null)

const gallery = computed(() => {
    if (selectedVariant.value?.images?.length) {
        return selectedVariant.value.images
    }

    return props.product.images || []
})

const activePrice = computed(() => selectedVariant.value?.price || props.product.price)
const activeStock = computed(() => selectedVariant.value?.stock || props.product.stock)
const activeDeliveryEstimate = computed(() => {
    const selectedVariantEstimate = selectedVariant.value?.id
        ? variantDeliveryEstimates.value[selectedVariant.value.id]
        : null

    return selectedVariantEstimate
        ?? selectedVariant.value?.delivery_estimate
        ?? props.product.delivery_estimate
        ?? null
})
const visibleDeliveryEstimate = computed(() => {
    if (!activeDeliveryEstimate.value?.available || !activeDeliveryEstimate.value?.storefront_message) {
        return null
    }

    return activeDeliveryEstimate.value
})
const descriptionText = computed(() => (props.product.description || '').trim())
const descriptionCharacterLimit = computed(() => (isDesktopViewport.value ? 300 : 200))
const shouldTruncateDescription = computed(() => descriptionText.value.length > descriptionCharacterLimit.value)
const visibleDescription = computed(() => {
    if (!descriptionText.value) {
        return 'No description provided yet.'
    }

    if (descriptionExpanded.value || !shouldTruncateDescription.value) {
        return descriptionText.value
    }

    return `${descriptionText.value.slice(0, descriptionCharacterLimit.value).trimEnd()}...`
})

const formatter = new Intl.NumberFormat('en-NG', {
    style: 'currency',
    currency: 'NGN',
})

function money(value) {
    return formatter.format(Number(value || 0))
}

function buildVariantEstimateMap(product) {
    return Object.fromEntries(
        (product?.variants || []).map((variant) => [variant.id, variant.delivery_estimate ?? null]),
    )
}

async function refreshDeliveryEstimate() {
    const variantId = selectedVariant.value?.id
    const location = browsingLocation.value

    if (!variantId || (!location?.state_id && !location?.lga_id)) {
        lastEstimateSignature.value = null
        return
    }

    const signature = JSON.stringify({
        variant_id: variantId,
        state_id: location.state_id ?? null,
        lga_id: location.lga_id ?? null,
        destination_label: location.destination_label ?? null,
    })

    if (lastEstimateSignature.value === signature) {
        return
    }

    lastEstimateSignature.value = signature

    try {
        const { data } = await axios.post(route('store.product.delivery-estimate', props.product.slug), {
            variant_id: variantId,
            destination: {
                country_id: location.country_id ?? null,
                state_id: location.state_id ?? null,
                lga_id: location.lga_id ?? null,
                state_name: location.state_name ?? null,
                city_name: location.city_name ?? null,
                destination_label: location.destination_label ?? null,
            },
        })

        variantDeliveryEstimates.value = {
            ...variantDeliveryEstimates.value,
            [variantId]: data?.delivery_estimate ?? null,
        }
    } catch (error) {
        console.error('Failed to refresh product delivery estimate', error)
    }
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

function syncDescriptionViewport() {
    isDesktopViewport.value = descriptionViewportQuery?.matches ?? false
}

function toggleDescription() {
    if (!shouldTruncateDescription.value) {
        return
    }

    descriptionExpanded.value = !descriptionExpanded.value
}

watch(selectedVariantId, () => {
    quantity.value = 1
})

watch(
    () => props.product,
    (product) => {
        variantDeliveryEstimates.value = buildVariantEstimateMap(product)
        descriptionExpanded.value = false
    },
    { deep: true },
)

watch(
    [
        () => selectedVariant.value?.id ?? null,
        () => browsingLocation.value?.state_id ?? null,
        () => browsingLocation.value?.lga_id ?? null,
        () => browsingLocation.value?.destination_label ?? null,
    ],
    () => {
        void refreshDeliveryEstimate()
    },
    { immediate: true },
)

onMounted(() => {
    if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') {
        return
    }

    descriptionViewportQuery = window.matchMedia('(min-width: 1024px)')
    syncDescriptionViewport()

    const handler = () => syncDescriptionViewport()

    if (typeof descriptionViewportQuery.addEventListener === 'function') {
        descriptionViewportQuery.addEventListener('change', handler)
        removeDescriptionViewportListener = () => descriptionViewportQuery?.removeEventListener('change', handler)
        return
    }

    descriptionViewportQuery.addListener(handler)
    removeDescriptionViewportListener = () => descriptionViewportQuery?.removeListener(handler)
})

onBeforeUnmount(() => {
    removeDescriptionViewportListener?.()
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

                <div class="mt-5 grid grid-cols-[84px_minmax(0,1fr)_auto] gap-2 sm:grid-cols-[120px_minmax(0,1fr)_auto] sm:gap-3">
                    <input
                        v-model.number="quantity"
                        type="number"
                        min="1"
                        class="product-quantity-input h-11 w-full appearance-auto rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 placeholder:text-slate-400 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:placeholder:text-slate-500"
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
                        class="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-slate-300 text-slate-700 transition hover:border-slate-500 dark:border-slate-700 dark:text-slate-200 dark:hover:border-slate-500"
                        @click="addToWishlist"
                        aria-label="Add to wishlist"
                        title="Add to wishlist"
                    >
                        <Heart class="size-4.5" />
                    </button>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-950">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Product Description</h2>
                <p class="mt-3 whitespace-pre-line text-sm leading-6 text-slate-700 dark:text-slate-300">
                    {{ visibleDescription }}
                </p>
                <button
                    v-if="shouldTruncateDescription"
                    type="button"
                    class="mt-3 text-sm font-semibold text-slate-900 transition hover:text-slate-700 dark:text-slate-100 dark:hover:text-slate-300"
                    @click="toggleDescription"
                >
                    {{ descriptionExpanded ? 'See less' : 'See more' }}
                </button>
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

<style scoped>
.product-quantity-input {
    appearance: auto;
    -moz-appearance: auto;
}

.product-quantity-input::-webkit-inner-spin-button,
.product-quantity-input::-webkit-outer-spin-button {
    -webkit-appearance: auto;
    margin: 0;
}
</style>
