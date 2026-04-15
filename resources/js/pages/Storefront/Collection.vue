<script setup>
import Pagination from '@/components/Pagination.vue'
import ProductGrid from '@/components/Storefront/ProductGrid.vue'
import StorefrontLayout from '@/layouts/StorefrontLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'

defineOptions({ layout: StorefrontLayout })

const props = defineProps({
    pageTitle: {
        type: String,
        default: 'Products',
    },
    pageDescription: {
        type: String,
        default: '',
    },
    products: {
        type: Object,
        required: true,
    },
    activeCategory: {
        type: Object,
        default: null,
    },
    infiniteScroll: {
        type: Boolean,
        default: false,
    },
})

const displayedProducts = ref(Array.isArray(props.products?.data) ? [...props.products.data] : [])
const isLoadingMore = ref(false)
const pendingPage = ref(null)
const loadTrigger = ref(null)
let observer = null

const resultCount = computed(() => Number(props.products?.total ?? props.products?.data?.length ?? 0))
const nextPageUrl = computed(() => props.products?.next_page_url ?? null)
const hasMoreProducts = computed(() => Boolean(nextPageUrl.value))
const usesInfiniteScroll = computed(() => Boolean(props.infiniteScroll))
const hasReachedCollectionEnd = computed(() => usesInfiniteScroll.value && !hasMoreProducts.value && displayedProducts.value.length > 0)
const collectionKey = computed(() => props.activeCategory?.id ? `category:${props.activeCategory.id}` : `section:${props.pageTitle}`)

function replaceDisplayedProducts() {
    displayedProducts.value = Array.isArray(props.products?.data) ? [...props.products.data] : []
}

function appendDisplayedProducts(products) {
    const existingIds = new Set(displayedProducts.value.map((product) => product.id))

    displayedProducts.value = [
        ...displayedProducts.value,
        ...products.filter((product) => !existingIds.has(product.id)),
    ]
}

function loadNextPage() {
    if (!usesInfiniteScroll.value || !nextPageUrl.value || isLoadingMore.value) {
        return
    }

    pendingPage.value = Number(props.products?.current_page ?? 1) + 1
    isLoadingMore.value = true

    router.get(nextPageUrl.value, {}, {
        only: ['products'],
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onError: () => {
            pendingPage.value = null
            isLoadingMore.value = false
        },
    })
}

async function syncObserver() {
    if (observer) {
        observer.disconnect()
        observer = null
    }

    await nextTick()

    if (!usesInfiniteScroll.value || !hasMoreProducts.value || !loadTrigger.value) {
        return
    }

    observer = new IntersectionObserver((entries) => {
        if (entries.some((entry) => entry.isIntersecting)) {
            loadNextPage()
        }
    }, {
        rootMargin: '240px 0px',
    })

    observer.observe(loadTrigger.value)
}

watch(
    () => ({
        key: collectionKey.value,
        page: Number(props.products?.current_page ?? 1),
        ids: (props.products?.data ?? []).map((product) => product.id).join(','),
    }),
    (current, previous) => {
        const incomingProducts = Array.isArray(props.products?.data) ? props.products.data : []
        const isLoadMoreResponse = previous
            && previous.key === current.key
            && pendingPage.value !== null
            && current.page === pendingPage.value
            && current.page > previous.page

        if (isLoadMoreResponse) {
            appendDisplayedProducts(incomingProducts)
        } else {
            replaceDisplayedProducts()
        }

        pendingPage.value = null
        isLoadingMore.value = false
        syncObserver()
    },
    { immediate: true },
)

onMounted(() => {
    syncObserver()
})

onBeforeUnmount(() => {
    if (observer) {
        observer.disconnect()
        observer = null
    }
})
</script>

<template>
    <Head :title="pageTitle" />

    <section class="rounded-[2rem] border border-amber-100/80 bg-white/90 p-6 shadow-xl shadow-amber-100/40 backdrop-blur dark:border-slate-800 dark:bg-slate-950/90 dark:shadow-black/20">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div class="space-y-3">
                <p class="text-xs font-semibold uppercase tracking-[0.28em] text-amber-600 dark:text-amber-400">
                    Storefront Collection
                </p>
                <div>
                    <h1 class="text-3xl font-bold tracking-tight text-slate-900 dark:text-slate-100 sm:text-4xl">
                        {{ pageTitle }}
                    </h1>
                    <p v-if="pageDescription" class="mt-2 max-w-3xl text-sm leading-6 text-slate-500 dark:text-slate-400">
                        {{ pageDescription }}
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap gap-3">
                <Link
                    :href="route('store.home')"
                    class="inline-flex h-11 items-center justify-center rounded-2xl border border-slate-200 px-5 text-sm font-semibold text-slate-700 transition hover:border-slate-300 hover:text-slate-900 dark:border-slate-700 dark:text-slate-200 dark:hover:border-slate-500 dark:hover:text-slate-100"
                >
                    Back to home
                </Link>
            </div>
        </div>
    </section>

    <section class="mt-6 space-y-5">
        <div class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-950">
            <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">
                {{ resultCount }} product<span v-if="resultCount !== 1">s</span>
            </p>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                <template v-if="activeCategory?.name">
                    Showing products from {{ activeCategory.name }}.
                </template>
                <template v-else>
                    Browse everything available in this section.
                </template>
            </p>
        </div>

        <ProductGrid :products="displayedProducts" empty-title="No products available in this section" />

        <div
            v-if="usesInfiniteScroll && displayedProducts.length"
            ref="loadTrigger"
            class="flex min-h-16 items-center justify-center"
        >
            <p
                v-if="isLoadingMore"
                class="text-sm font-medium text-slate-500 dark:text-slate-400"
            >
                Loading more products...
            </p>
            <p
                v-else-if="hasReachedCollectionEnd"
                class="text-sm font-medium text-slate-500 dark:text-slate-400"
            >
                You&rsquo;ve reached the end of this section.
            </p>
        </div>

        <div v-else-if="products.links?.length" class="flex justify-center">
            <Pagination :links="products.links" />
        </div>
    </section>
</template>
