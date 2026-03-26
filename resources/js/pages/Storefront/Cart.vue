<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import StorefrontLayout from '@/layouts/StorefrontLayout.vue'
import CartItem from '@/components/Storefront/CartItem.vue'
import { route } from 'ziggy-js';

defineOptions({ layout: StorefrontLayout })

const props = defineProps({
    cart: {
        type: Object,
        default: () => ({ id: null, status: 'active', items: [] }),
    },
    summary: {
        type: Object,
        default: () => ({
            item_count: 0,
            subtotal: 0,
            discount: 0,
            discount_label: null,
            coupon: null,
            shipping: 0,
            tax: 0,
            total: 0,
        }),
    },
    coupon_error: {
        type: String,
        default: null,
    },
    savedForLater: {
        type: Array,
        default: () => [],
    },
    savedItemCounts: {
        type: Object,
        default: () => ({
            wishlist: 0,
            saved_for_later: 0,
        }),
    },
})

const page = usePage()
const couponCode = ref(props.summary?.coupon || '')

const isLoggedIn = computed(() => !!page.props.auth?.user)
const hasItems = computed(() => Array.isArray(props.cart?.items) && props.cart.items.length > 0)
const unavailableItems = computed(() => (props.cart?.items || []).filter(item => item.availability?.is_available === false))
const hasUnavailableItems = computed(() => unavailableItems.value.length > 0)

const formatter = new Intl.NumberFormat('en-NG', {
    style: 'currency',
    currency: 'NGN',
})

const emit = defineEmits(['updated'])

function money(value) {
    return formatter.format(Number(value || 0))
}

function applyCoupon() {
    router.post(
        route('store.cart.apply-coupon'),
        { coupon: couponCode.value || null },
        { preserveScroll: true },
    )
}

function clearCoupon() {
    couponCode.value = ''
    applyCoupon()
}

function checkout() {
    router.get(route('checkout.index'), {
        coupon: couponCode.value || undefined,
    })
}

function moveSavedToCart(id) {
    router.post(`/account/saved-items/${id}/move-to-cart`, {}, { preserveScroll: true })
}

function removeSaved(id) {
    router.delete(`/account/saved-items/${id}`, { preserveScroll: true })
}
</script>

<template>
    <Head title="Shopping Cart" />

    <section class="mb-8 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-slate-900">Shopping Cart</h1>
            <p class="mt-1 text-sm text-slate-500">
                Review items, apply discounts, and place your order through your production order service.
            </p>
        </div>
        <Link :href="route('store.home')" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-500">
            Continue Shopping
        </Link>
    </section>

    <section v-if="!isLoggedIn" class="rounded-2xl border border-slate-200 bg-white p-8 text-center shadow-sm">
        <h2 class="text-lg font-semibold text-slate-900">Sign in to view your cart</h2>
        <p class="mt-2 text-sm text-slate-500">Your cart is linked to your account for secure checkout and discount eligibility.</p>
        <Link :href="route('login')" class="mt-4 inline-flex rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700">
            Sign In
        </Link>
    </section>

    <section v-else-if="!hasItems" class="rounded-2xl border border-slate-200 bg-white p-8 text-center shadow-sm">
        <h2 class="text-lg font-semibold text-slate-900">Your cart is empty</h2>
        <p class="mt-2 text-sm text-slate-500">Add products from the storefront and come back to checkout.</p>
    </section>

    <section v-else class="grid gap-6 lg:grid-cols-[1.7fr_1fr]">
        <div class="space-y-4">
            <div
                v-if="page.props.flash?.error"
                class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700"
            >
                {{ page.props.flash.error }}
            </div>

            <div
                v-if="hasUnavailableItems"
                class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"
            >
                {{ unavailableItems.length }} cart item{{ unavailableItems.length === 1 ? '' : 's' }} need attention before checkout. Fix the highlighted line{{ unavailableItems.length === 1 ? '' : 's' }} below.
            </div>

            <CartItem
                v-for="item in cart.items"
                :key="item.id"
                :item="item"
            />

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h2 class="text-base font-semibold text-slate-900">Saved for Later</h2>
                        <p class="mt-1 text-sm text-slate-500">
                            Items tied to your account and ready when you want them back.
                        </p>
                    </div>
                    <Link :href="route('account.saved.index')" class="text-sm font-semibold text-slate-700 hover:text-slate-900">
                        View all
                    </Link>
                </div>

                <div v-if="savedForLater.length" class="mt-4 space-y-4">
                    <article
                        v-for="item in savedForLater"
                        :key="item.id"
                        class="flex flex-col gap-4 rounded-2xl border border-slate-200 p-4 sm:flex-row sm:items-center"
                    >
                        <img
                            v-if="item.product?.image"
                            :src="item.product.image"
                            :alt="item.product?.name"
                            class="h-20 w-20 rounded-xl object-cover"
                        >
                        <div v-else class="flex h-20 w-20 items-center justify-center rounded-xl bg-slate-100 text-xs text-slate-500">
                            No image
                        </div>

                        <div class="flex-1">
                            <p class="font-semibold text-slate-900">{{ item.product?.name || item.snapshot?.product_name }}</p>
                            <p class="mt-1 text-sm text-slate-500">{{ item.variant?.label || item.snapshot?.variant_label }}</p>
                            <p class="mt-1 text-sm font-semibold text-slate-900">{{ money(item.variant?.price?.current || item.snapshot?.price) }}</p>
                            <p v-if="item.availability?.message" class="mt-2 text-xs font-medium text-amber-700">{{ item.availability.message }}</p>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <button
                                type="button"
                                class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:bg-slate-300"
                                :disabled="!item.availability?.is_available"
                                @click="moveSavedToCart(item.id)"
                            >
                                Move to cart
                            </button>
                            <button
                                type="button"
                                class="rounded-xl border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-600"
                                @click="removeSaved(item.id)"
                            >
                                Remove
                            </button>
                        </div>
                    </article>
                </div>

                <div v-else class="mt-4 rounded-2xl bg-slate-50 px-4 py-8 text-center">
                    <h3 class="text-sm font-semibold text-slate-900">Nothing saved yet</h3>
                    <p class="mt-2 text-sm text-slate-500">Move items out of your active cart without losing your chosen variant.</p>
                </div>
            </div>
        </div>

        <aside class="space-y-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-base font-semibold text-slate-900">Apply Coupon</h2>
                <div class="mt-3 flex gap-2">
                    <input
                        v-model="couponCode"
                        type="text"
                        placeholder="Coupon code"
                        class="h-10 flex-1 rounded-xl border border-slate-300 px-3 text-sm"
                    >
                    <button
                        type="button"
                        class="rounded-xl bg-slate-900 px-4 text-sm font-semibold text-white transition hover:bg-slate-700"
                        @click="applyCoupon"
                    >
                        Apply
                    </button>
                </div>
                <button
                    v-if="summary.coupon"
                    type="button"
                    class="mt-2 text-xs font-medium text-slate-500 transition hover:text-slate-800"
                    @click="clearCoupon"
                >
                    Remove coupon
                </button>
                <p v-if="coupon_error" class="mt-2 text-xs font-medium text-rose-600">
                    {{ coupon_error }}
                </p>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-base font-semibold text-slate-900">Order Summary</h2>

                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between text-slate-600">
                        <dt>Items ({{ summary.item_count }})</dt>
                        <dd>{{ money(summary.subtotal) }}</dd>
                    </div>
                    <div class="flex justify-between text-slate-600">
                        <dt>Discount</dt>
                        <dd class="text-emerald-600">-{{ money(summary.discount) }}</dd>
                    </div>
                    <div v-if="summary.discount_label" class="text-xs text-slate-500">
                        Applied: {{ summary.discount_label }}
                    </div>
                    <div class="flex justify-between text-slate-600">
                        <dt>Shipping</dt>
                        <dd>{{ money(summary.shipping) }}</dd>
                    </div>
                    <div class="flex justify-between text-slate-600">
                        <dt>Tax</dt>
                        <dd>{{ money(summary.tax) }}</dd>
                    </div>
                    <div class="border-t border-slate-200 pt-3 text-base font-bold text-slate-900">
                        <div class="flex justify-between">
                            <dt>Total</dt>
                            <dd>{{ money(summary.total) }}</dd>
                        </div>
                    </div>
                </dl>

                <button
                    type="button"
                    class="mt-5 w-full rounded-xl bg-amber-500 px-4 py-3 text-sm font-semibold text-white transition hover:bg-amber-600 disabled:cursor-not-allowed disabled:bg-amber-200"
                    :disabled="hasUnavailableItems"
                    @click="checkout"
                >
                    Checkout
                </button>

                <p v-if="hasUnavailableItems" class="mt-2 text-xs font-medium text-amber-700">
                    Remove or adjust unavailable items before proceeding to checkout.
                </p>
            </div>
        </aside>
    </section>
</template>

