<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import axios from 'axios'
import StorefrontLayout from '@/layouts/StorefrontLayout.vue'

defineOptions({ layout: StorefrontLayout })

const {
    cart,
    summary,
    coupon_error,
    shipping_error,
    shipping_methods,
    states,
    lgas: initialLgas,
    pickup_locations,
    selected_shipping
} = defineProps({
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
    shipping_error: {
        type: String,
        default: null,
    },
    shipping_methods: {
        type: Array,
        default: () => [],
    },
    states: {
        type: Array,
        default: () => [],
    },
    lgas: {
        type: Array,
        default: () => [],
    },
    pickup_locations: {
        type: Array,
        default: () => [],
    },
    selected_shipping: {
        type: Object,
        default: () => ({
            shipping_method_id: null,
            state_id: null,
            lga_id: null,
            pickup_location_id: null,
            phone: null,
            line1: null,
        }),
    },
})

const page = usePage()

const lgas = ref(initialLgas || [])

const form = useForm({
    coupon: summary?.coupon || '',
    shipping_method_id: selected_shipping?.shipping_method_id || '',
    state_id: selected_shipping?.state_id || '',
    lga_id: selected_shipping?.lga_id || '',
    pickup_location_id: selected_shipping?.pickup_location_id || '',
    phone: selected_shipping?.phone || '',
    line1: selected_shipping?.line1 || '',
})

const currentMethod = computed(() => {
    const selected = Number(form.shipping_method_id || 0)
    return shipping_methods.find(method => Number(method.id) === selected) || null
})

const isPickupMethod = computed(() => {
    const methodName = String(currentMethod.value?.name || '').toLowerCase()
    return methodName.includes('pickup')
})

const pickupLocations = ref([])
const loadingPickupLocations = ref(false)

watch(() => form.state_id, async (state) => {

    form.lga_id = ''

    if (!state) {
        lgas.value = []
        return
    }

    if (!state || isPickupMethod.value) {
        lgas.value = []
        return
    }

    try {
        const { data } = await axios.get(route('locations.lgas', state))
        lgas.value = data
    } catch (error) {
        console.error('Failed loading LGAs', error)
        lgas.value = []
    }

})

watch([() => form.state_id, isPickupMethod], async ([state, isPickup]) => {

    form.pickup_location_id = ''
    pickupLocations.value = []

    if (!state || !isPickup) return

    loadingPickupLocations.value = true

    try {
        const { data } = await axios.get(route('shipping.locations.pickups', state))
        pickupLocations.value = data
    } catch (error) {
        console.error('Failed loading pickup locations', error)
        pickupLocations.value = []
    } finally {
        loadingPickupLocations.value = false
    }

})

const hasItems = computed(() => {
    return Array.isArray(cart?.items) && cart.items.length > 0
})





const formatter = new Intl.NumberFormat('en-NG', {
    style: 'currency',
    currency: 'NGN',
})

function money(value) {
    return formatter.format(Number(value || 0))
}

function updateTotals() {
    form.post(route('checkout.discount'), {
        preserveScroll: true,
        preserveState: true,
    })
}

function payNow() {
    form.post(route('checkout.pay'), {
        preserveScroll: true,
    })
}
</script>

<template>
    <Head title="Checkout" />

    <section class="mb-8 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-slate-900">Checkout</h1>
            <p class="mt-1 text-sm text-slate-500">
                Confirm your order totals and continue securely with Paystack.
            </p>
        </div>

        <Link
            :href="route('store.cart')"
            class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-500"
        >
            Back to Cart
        </Link>
    </section>

    <section v-if="!hasItems" class="rounded-2xl border border-slate-200 bg-white p-8 text-center shadow-sm">
        <h2 class="text-lg font-semibold text-slate-900">Your cart is empty</h2>
        <p class="mt-2 text-sm text-slate-500">Add products from the storefront before checkout.</p>

        <Link
            :href="route('store.home')"
            class="mt-4 inline-flex rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700"
        >
            Continue Shopping
        </Link>
    </section>

    <section v-else class="grid gap-6 lg:grid-cols-[1.7fr_1fr]">

        <div class="space-y-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">

            <h2 class="text-base font-semibold text-slate-900">Cart Summary</h2>

            <article
                v-for="item in cart.items"
                :key="item.id"
                class="flex items-center gap-3 rounded-xl border border-slate-100 p-3"
            >

                <img
                    v-if="item.product?.image"
                    :src="item.product.image"
                    :alt="item.product?.name"
                    class="h-16 w-16 rounded-lg object-cover"
                    loading="lazy"
                />

                <div
                    v-else
                    class="flex h-16 w-16 items-center justify-center rounded-lg bg-slate-100 text-xs text-slate-500"
                >
                    No image
                </div>

                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold text-slate-900">
                        {{ item.product?.name }}
                    </p>

                    <p class="truncate text-xs text-slate-500">
                        {{ item.variant?.label }}
                    </p>

                    <p class="mt-1 text-xs text-slate-500">
                        Qty: {{ item.quantity }}
                    </p>
                </div>

                <p class="text-sm font-semibold text-slate-900">
                    {{ money(item.subtotal) }}
                </p>

            </article>
        </div>

        <aside class="space-y-4">

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">

                <h2 class="text-base font-semibold text-slate-900">
                    Checkout Details
                </h2>

                <div class="mt-4 space-y-3">

                    <div>
                        <label class="text-xs font-medium uppercase tracking-wide text-slate-500">
                            Discount code
                        </label>

                        <input
                            v-model="form.coupon"
                            type="text"
                            placeholder="Enter coupon code"
                            class="mt-1 h-10 w-full rounded-xl border border-slate-300 px-3 text-sm"
                        />

                        <p v-if="coupon_error" class="mt-1 text-xs font-medium text-rose-600">
                            {{ coupon_error }}
                        </p>
                    </div>

                    <div>
                        <label class="text-xs font-medium uppercase tracking-wide text-slate-500">
                            Shipping method
                        </label>

                        <select
                            v-model="form.shipping_method_id"
                            class="mt-1 h-10 w-full rounded-xl border border-slate-300 px-3 text-sm"
                        >
                            <option value="">Select method</option>

                            <option
                                v-for="method in shipping_methods"
                                :key="method.id"
                                :value="method.id"
                            >
                                {{ method.name }}
                            </option>

                        </select>
                    </div>

                    <div>
                        <label class="text-xs font-medium uppercase tracking-wide text-slate-500">
                            Phone Number
                        </label>

                        <input
                            v-model="form.phone"
                            type="text"
                            placeholder="Enter Phone Number"
                            class="mt-1 h-10 w-full rounded-xl border border-slate-300 px-3 text-sm"
                        />

                        <!--                        <p v-if="phone_error" class="mt-1 text-xs font-medium text-rose-600">-->
                        <!--                            {{ phone_error }}-->
                        <!--                        </p>-->
                    </div>

                    <div>
                        <label class="text-xs font-medium uppercase tracking-wide text-slate-500">
                            State
                        </label>

                        <select
                            v-model="form.state_id"
                            class="mt-1 h-10 w-full rounded-xl border border-slate-300 px-3 text-sm"
                        >

                            <option value="">Select state</option>

                            <option
                                v-for="state in states"
                                :key="state.id"
                                :value="state.id"
                            >
                                {{ state.name }}
                            </option>

                        </select>
                    </div>

                    <div v-if="!isPickupMethod">
                        <label class="text-xs font-medium uppercase tracking-wide text-slate-500">
                            City / LGA
                        </label>

                        <select
                            v-model="form.lga_id"
                            class="mt-1 h-10 w-full rounded-xl border border-slate-300 px-3 text-sm"
                        >

                            <option value="">Select city/LGA</option>

                            <option
                                v-for="lga in lgas"
                                :key="lga.id"
                                :value="lga.id"
                            >
                                {{ lga.name }}
                            </option>

                        </select>
                    </div>

                    <div v-if="isPickupMethod">
                        <label class="text-xs font-medium uppercase tracking-wide text-slate-500">
                            Pickup Location
                        </label>

                        <select
                            v-model="form.pickup_location_id"
                            class="mt-1 h-10 w-full rounded-xl border border-slate-300 px-3 text-sm"
                        >
                            <option value="">
                                {{ loadingPickupLocations ? 'Loading...' : 'Select pickup location' }}
                            </option>

                            <option
                                v-for="location in pickupLocations"
                                :key="location.id"
                                :value="location.id"
                            >
                                {{ location.name }} - {{ location.address_line1 }}
                            </option>
                        </select>

                        <p v-if="!loadingPickupLocations && !pickupLocations.length && form.state_id"
                           class="mt-1 text-xs text-slate-500">
                            No pickup locations available for this state
                        </p>
                    </div>

                    <div v-if="!isPickupMethod">
                        <label class="text-xs font-medium uppercase tracking-wide text-slate-500">
                            Address
                        </label>

                        <input
                            v-model="form.line1"
                            type="text"
                            placeholder="Street address"
                            class="mt-1 h-10 w-full rounded-xl border border-slate-300 px-3 text-sm"
                        />
                    </div>

                    <p v-if="shipping_error" class="text-xs font-medium text-rose-600">
                        {{ shipping_error }}
                    </p>

                    <p v-if="page.props.errors?.payment" class="text-xs font-medium text-rose-600">
                        {{ page.props.errors.payment }}
                    </p>

                    <p v-if="page.props.errors?.reference" class="text-xs font-medium text-rose-600">
                        {{ page.props.errors.reference }}
                    </p>

                </div>

                <button
                    type="button"
                    class="mt-4 w-full rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-500 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="form.processing"
                    @click="updateTotals"
                >
                    Update Totals
                </button>

                <dl class="mt-4 space-y-3 text-sm">

                    <div class="flex justify-between text-slate-600">
                        <dt>Subtotal</dt>
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

                        <dd v-if="summary.shipping_free" class="font-semibold text-emerald-600">
                            Free Shipping
                        </dd>

                        <dd v-else>
                            {{ money(summary.shipping) }}
                        </dd>
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
                    class="mt-5 w-full rounded-xl bg-emerald-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="form.processing"
                    @click="payNow"
                >
                    {{ form.processing ? 'Redirecting to Paystack...' : 'Pay Now' }}
                </button>

            </div>
        </aside>

    </section>

</template>
