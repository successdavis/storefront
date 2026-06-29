<script setup>
import { Link, router, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import StorefrontLayout from '@/layouts/StorefrontLayout.vue'
import ProductCarousel from '@/components/Storefront/ProductCarousel.vue'
import ProductGrid from '@/components/Storefront/ProductGrid.vue'
import SeoHead from '@/components/Storefront/SeoHead.vue'

defineOptions({ layout: StorefrontLayout })

const props = defineProps({
    filters: {
        type: Object,
        default: () => ({}),
    },
    pageTitle: {
        type: String,
        default: 'All Products',
    },
    products: {
        type: Object,
        required: true,
    },
    featuredProducts: {
        type: Array,
        default: () => [],
    },
    latestProducts: {
        type: Array,
        default: () => [],
    },
    categoryPreviews: {
        type: Array,
        default: () => [],
    },
    seo: {
        type: Object,
        default: () => ({}),
    },
    structuredData: {
        type: Array,
        default: () => [],
    },
})

const page = usePage()
const categoryOptions = computed(() => Array.isArray(page.props.categories) ? page.props.categories : [])

const search = ref(props.filters?.q || '')
const selectedCategory = ref(props.filters?.category || '')

function applyFilters() {
    router.get(
        route('store.search'),
        {
            q: search.value || undefined,
            category: selectedCategory.value || undefined,
        },
        { preserveState: true },
    )
}

function categoryHref(category) {
    return category?.slug
        ? route('store.category', category.slug)
        : route('store.category.legacy', category.id)
}

const hasProducts = computed(() => Array.isArray(props.products?.data) && props.products.data.length > 0)
</script>

<template>
    <SeoHead :seo="seo" :structured-data="structuredData" />

<!--    <section class="relative overflow-hidden rounded-3xl bg-slate-900 px-6 py-10 text-white shadow-2xl sm:px-10">-->
<!--        <div class="absolute -right-14 -top-14 h-40 w-40 rounded-full bg-amber-400/40 blur-2xl" />-->
<!--        <div class="absolute -bottom-16 -left-8 h-48 w-48 rounded-full bg-teal-300/20 blur-2xl" />-->

<!--        <div class="relative grid gap-8 lg:grid-cols-2 lg:items-center">-->
<!--            <div class="space-y-4">-->
<!--                <h1 class="text-3xl font-extrabold tracking-tight sm:text-4xl">-->
<!--                    Shop with Confidence!-->
<!--                </h1>-->
<!--                <p class="max-w-xl text-sm text-slate-200 sm:text-base">-->
<!--                    S-Tech-Max store isn’t just another option — it’s the better way to shop.-->
<!--                </p>-->
<!--            </div>-->

<!--            <form class="rounded-2xl  bg-white p-4 text-slate-900 shadow-xl" @submit.prevent="applyFilters">-->
<!--                <div class="grid gap-3 sm:grid-cols-3">-->
<!--                    <input-->
<!--                        v-model="search"-->
<!--                        type="search"-->
<!--                        placeholder="Search products"-->
<!--                        class="rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none ring-amber-300 transition focus:ring-2"-->
<!--                    >-->
<!--                    <select-->
<!--                        v-model="selectedCategory"-->
<!--                        class="rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none ring-amber-300 transition focus:ring-2"-->
<!--                    >-->
<!--                        <option value="">All Categories</option>-->
<!--                        <option v-for="category in categoryOptions" :key="category.id" :value="category.id">-->
<!--                            {{ category.name }}-->
<!--                        </option>-->
<!--                    </select>-->
<!--                    <button-->
<!--                        type="submit"-->
<!--                        class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700"-->
<!--                    >-->
<!--                        Apply Filters-->
<!--                    </button>-->
<!--                </div>-->
<!--            </form>-->
<!--        </div>-->
<!--    </section>-->

    <section class="mt-10 space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-bold text-slate-900 dark:text-slate-100">Featured Products</h2>
            <Link :href="route('store.featured')" class="text-sm font-medium text-slate-600 transition hover:text-slate-900 dark:text-slate-300 dark:hover:text-amber-300">See all</Link>
        </div>
        <ProductGrid :products="featuredProducts" empty-title="No featured products yet" />
    </section>

    <section class="mt-10 space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-bold text-slate-900 dark:text-slate-100">Latest Products</h2>
            <Link :href="route('store.latest')" class="text-sm font-medium text-slate-600 transition hover:text-slate-900 dark:text-slate-300 dark:hover:text-amber-300">See all</Link>
        </div>
        <ProductCarousel :products="latestProducts" empty-title="No recent products yet" />
        <p class="text-sm text-slate-500 dark:text-slate-300">Swipe or use the arrows to browse newly added items.</p>
    </section>

    <section class="mt-10 space-y-4">
        <h2 class="text-xl font-bold text-slate-900 dark:text-slate-100">Shop Catalog</h2>
        <ProductGrid :products="products.data" empty-title="No products matched your filters" />

        <div v-if="hasProducts" class="flex justify-center">
            <Link
                :href="route('store.catalog')"
                class="rounded-xl border border-slate-300 bg-white px-5 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-500 hover:text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-amber-400 dark:hover:text-white"
            >
                See all
            </Link>
        </div>
    </section>

    <section class="mt-10 space-y-6">
        <h2 class="text-xl font-bold text-slate-900 dark:text-slate-100">Category Highlights</h2>

        <div class="space-y-6">
            <article
                v-for="category in categoryPreviews"
                :key="category.id"
                class="rounded-2xl border border-amber-100 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-950"
            >
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ category.name }}</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-300">{{ category.active_products_count }} active products</p>
                    </div>
                    <Link
                        :href="categoryHref(category)"
                        class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:border-slate-500 dark:border-slate-700 dark:text-slate-200 dark:hover:border-amber-400"
                    >
                        View Category
                    </Link>
                </div>

                <ProductGrid
                    :products="category.products"
                    empty-title="No products available in this category"
                />
            </article>
        </div>
    </section>
</template>
