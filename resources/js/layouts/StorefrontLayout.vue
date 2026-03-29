<script setup>
import StorefrontSearchBar from '@/components/Storefront/StorefrontSearchBar.vue'
import { useStorefrontLocation } from '@/composables/useStorefrontLocation'
import { Link, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

const page = usePage()
const storefrontLocation = useStorefrontLocation()

const authUser = computed(() => page.props.auth?.user ?? null)
const cartCount = computed(() => Number(page.props.cartCount ?? 0))
const categories = computed(() => Array.isArray(page.props.categories) ? page.props.categories : [])
const initialQuery = computed(() => {
    if (typeof window === 'undefined') {
        return ''
    }

    return new URLSearchParams(window.location.search).get('q') || ''
})
</script>

<template>
    <div class="min-h-screen bg-[radial-gradient(circle_at_top,_#fff4d6_0%,_#fff8ea_28%,_#f8fafc_62%,_#eef4ff_100%)] text-slate-900 dark:bg-[radial-gradient(circle_at_top,_#1f2937_0%,_#020617_55%,_#000000_100%)] dark:text-slate-100">
        <header class="relative z-50 border-b border-amber-100/60 bg-white/85 backdrop-blur dark:border-slate-800 dark:bg-slate-950/85">
            <div class="mx-auto flex w-full max-w-8xl flex-col gap-3 px-4 py-4 sm:px-6 md:hidden lg:px-12">
                <div class="flex items-center justify-between gap-3">
                    <Link
                        :href="route('store.home')"
                        class="min-w-0 truncate text-lg font-bold tracking-tight text-slate-900 dark:text-slate-100 sm:text-xl"
                    >
                        S-Tech-Max LTD
                    </Link>

                    <div class="flex shrink-0 items-center gap-2">
                    <Link
                        :href="route('store.cart')"
                        class="relative rounded-2xl border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-800 transition hover:border-slate-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 sm:px-4 sm:text-sm"
                    >
                        Cart
                        <span
                            class="ml-2 inline-flex min-w-5 items-center justify-center rounded-full bg-amber-500 px-1.5 text-xs font-semibold text-white dark:text-slate-950"
                        >
                            {{ cartCount }}
                        </span>
                    </Link>

                    <Link
                        v-if="authUser"
                        :href="route('dashboard')"
                        class="rounded-2xl bg-amber-500 px-3 py-2 text-xs font-semibold text-white transition hover:bg-amber-600 dark:text-slate-950 sm:px-4 sm:text-sm"
                    >
                        Account
                    </Link>
                    <Link
                        v-else
                        :href="route('login')"
                        class="rounded-2xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white transition hover:bg-slate-700 dark:bg-amber-500 dark:text-slate-950 dark:hover:bg-amber-400 sm:px-4 sm:text-sm"
                    >
                        Sign In
                    </Link>
                    </div>
                </div>

                <StorefrontSearchBar
                    :initial-query="initialQuery"
                    class="w-full"
                />
            </div>

            <div class="mx-auto hidden w-full max-w-8xl items-center gap-4 px-4 py-4 sm:px-6 md:flex lg:px-12">
                <Link :href="route('store.home')" class="text-xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                    S-Tech-Max LTD
                </Link>

                <StorefrontSearchBar :initial-query="initialQuery" />

                <div class="flex items-center gap-3">
                    <Link
                        :href="route('store.cart')"
                        class="relative rounded-2xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-800 transition hover:border-slate-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
                    >
                        Cart
                        <span
                            class="ml-2 inline-flex min-w-5 items-center justify-center rounded-full bg-amber-500 px-1.5 text-xs font-semibold text-white dark:text-slate-950"
                        >
                            {{ cartCount }}
                        </span>
                    </Link>

                    <Link
                        v-if="authUser"
                        :href="route('dashboard')"
                        class="rounded-2xl bg-amber-500 px-4 py-2 text-sm font-semibold text-white transition hover:bg-amber-600 dark:text-slate-950"
                    >
                        Account
                    </Link>
                    <Link
                        v-else
                        :href="route('login')"
                        class="rounded-2xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700 dark:bg-amber-500 dark:text-slate-950 dark:hover:bg-amber-400"
                    >
                        Sign In
                    </Link>
                </div>
            </div>

            <div class="mx-auto w-full max-w-8xl px-4 pb-3 sm:px-6 lg:px-12">
                <div class="storefront-category-scroll flex gap-2 overflow-x-auto pb-2">
                    <Link
                        :href="route('store.home')"
                        class="whitespace-nowrap rounded-full border border-amber-200 bg-white px-3 py-1 text-xs font-medium text-slate-700 transition hover:border-amber-400 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-amber-400"
                    >
                        All Products
                    </Link>
                    <Link
                        v-for="category in categories"
                        :key="category.id"
                        :href="route('store.category', category.id)"
                        class="whitespace-nowrap rounded-full border border-amber-200 bg-white px-3 py-1 text-xs font-medium text-slate-700 transition hover:border-amber-400 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-amber-400"
                    >
                        {{ category.name }}
                    </Link>
                </div>
            </div>
        </header>

        <main class="mx-auto w-full max-w-8xl px-4 py-8 sm:px-6 lg:px-12">
            <section
                v-if="storefrontLocation.showPromptBanner"
                class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4 text-slate-800 shadow-sm dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-slate-100"
            >
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm font-semibold">Use your current location</p>
                        <p class="mt-1 text-sm text-slate-700 dark:text-slate-300">
                            {{ storefrontLocation.promptMessage }}
                        </p>
                    </div>

                    <button
                        v-if="storefrontLocation.canRetryBrowserLocation"
                        type="button"
                        class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-amber-500 dark:text-slate-950 dark:hover:bg-amber-400"
                        :disabled="storefrontLocation.isResolving"
                        @click="storefrontLocation.requestBrowserLocation"
                    >
                        {{ storefrontLocation.isResolving ? 'Checking location...' : 'Allow location access' }}
                    </button>
                    <p
                        v-else-if="storefrontLocation.status === 'denied'"
                        class="text-sm font-medium text-slate-700 dark:text-slate-300"
                    >
                        Enable location in your browser settings, then refresh this page.
                    </p>
                </div>
            </section>

            <slot />
        </main>
    </div>
</template>
