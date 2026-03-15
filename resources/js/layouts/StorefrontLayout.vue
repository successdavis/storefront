<script setup>
import { Link, router, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

const page = usePage()

const authUser = computed(() => page.props.auth?.user ?? null)
const cartCount = computed(() => Number(page.props.cartCount ?? 0))
const categories = computed(() => Array.isArray(page.props.categories) ? page.props.categories : [])

const search = ref('')

function submitSearch() {
    router.get(route('store.search'), { q: search.value || undefined }, { preserveState: true, replace: true })
}
</script>

<template>
    <div class="min-h-screen bg-[radial-gradient(circle_at_top,_#fef3c7_0%,_#fff7ed_30%,_#f8fafc_100%)] text-slate-900">
        <header class="border-b border-amber-100/60 bg-white/80 backdrop-blur">
            <div class="mx-auto flex w-full max-w-7xl flex-wrap items-center gap-4 px-4 py-4 sm:px-6 lg:px-8">
                <Link :href="route('store.home')" class="text-xl font-bold tracking-tight text-slate-900">
                    NovaMart
                </Link>

                <form class="flex min-w-[240px] flex-1 items-center gap-2" @submit.prevent="submitSearch">
                    <input
                        v-model="search"
                        type="search"
                        placeholder="Search products, brands, categories"
                        class="w-full rounded-xl border border-amber-200 bg-white px-4 py-2 text-sm text-slate-700 shadow-sm outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-200"
                    >
                    <button
                        type="submit"
                        class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-700"
                    >
                        Search
                    </button>
                </form>

                <div class="flex items-center gap-3">
                    <Link
                        :href="route('store.cart')"
                        class="relative rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-800 transition hover:border-slate-300"
                    >
                        Cart
                        <span
                            class="ml-2 inline-flex min-w-5 items-center justify-center rounded-full bg-amber-500 px-1.5 text-xs font-semibold text-white"
                        >
                            {{ cartCount }}
                        </span>
                    </Link>

                    <Link
                        v-if="authUser"
                        :href="route('dashboard')"
                        class="rounded-xl bg-amber-500 px-4 py-2 text-sm font-semibold text-white transition hover:bg-amber-600"
                    >
                        Account
                    </Link>
                    <Link
                        v-else
                        :href="route('login')"
                        class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700"
                    >
                        Sign In
                    </Link>
                </div>
            </div>

            <div class="mx-auto w-full max-w-7xl px-4 pb-3 sm:px-6 lg:px-8">
                <div class="scrollbar-thin flex gap-2 overflow-x-auto pb-1">
                    <Link
                        :href="route('store.home')"
                        class="whitespace-nowrap rounded-full border border-amber-200 bg-white px-3 py-1 text-xs font-medium text-slate-700 transition hover:border-amber-400"
                    >
                        All Products
                    </Link>
                    <Link
                        v-for="category in categories"
                        :key="category.id"
                        :href="route('store.category', category.id)"
                        class="whitespace-nowrap rounded-full border border-amber-200 bg-white px-3 py-1 text-xs font-medium text-slate-700 transition hover:border-amber-400"
                    >
                        {{ category.name }}
                    </Link>
                </div>
            </div>
        </header>

        <main class="mx-auto w-full max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <slot />
        </main>
    </div>
</template>
