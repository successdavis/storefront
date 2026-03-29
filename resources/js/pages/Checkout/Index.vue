<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3'
import axios from 'axios'
import { computed, onBeforeUnmount, ref, watch } from 'vue'
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
    saved_addresses,
    selected_shipping,
    delivery_estimate,
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
    saved_addresses: {
        type: Array,
        default: () => [],
    },
    selected_shipping: {
        type: Object,
        default: () => ({
            address_id: null,
            shipping_method_id: null,
            state_id: null,
            lga_id: null,
            pickup_location_id: null,
            phone: null,
            line1: null,
            line2: null,
            save_address: false,
        }),
    },
    delivery_estimate: {
        type: Object,
        default: () => null,
    },
})

const page = usePage()
const lgas = ref(initialLgas || [])
const pickupLocations = ref(pickup_locations || [])
const loadingPickupLocations = ref(false)
const pendingAddressLgaId = ref(selected_shipping?.lga_id ? String(selected_shipping.lga_id) : '')
const totalsAreUpdating = ref(false)
const totalsRefreshTimer = ref(null)

const form = useForm({
    coupon: summary?.coupon || '',
    address_id: selected_shipping?.address_id || '',
    shipping_method_id: selected_shipping?.shipping_method_id || '',
    state_id: selected_shipping?.state_id || '',
    lga_id: selected_shipping?.lga_id || '',
    pickup_location_id: selected_shipping?.pickup_location_id || '',
    phone: selected_shipping?.phone || '',
    line1: selected_shipping?.line1 || '',
    line2: selected_shipping?.line2 || '',
    save_address: Boolean(selected_shipping?.save_address),
})

const savedAddressesById = computed(() => {
    return new Map(saved_addresses.map(address => [Number(address.id), address]))
})

const selectedSavedAddress = computed(() => {
    return savedAddressesById.value.get(Number(form.address_id || 0)) || null
})

const currentMethod = computed(() => {
    const selected = Number(form.shipping_method_id || 0)
    return shipping_methods.find(method => Number(method.id) === selected) || null
})

const isPickupMethod = computed(() => {
    const methodType = String(currentMethod.value?.method_type || '').toLowerCase()
    if (methodType !== '') {
        return methodType === 'pickup'
    }

    const methodName = String(currentMethod.value?.name || '').toLowerCase()
    return methodName.includes('pickup')
})

watch(() => form.address_id, (addressId) => {
    const address = savedAddressesById.value.get(Number(addressId || 0))
    if (!address) {
        pendingAddressLgaId.value = ''
        return
    }

    pendingAddressLgaId.value = address.lga_id ? String(address.lga_id) : ''
    form.save_address = false
    form.phone = address.phone || form.phone || ''
    form.line1 = address.line1 || ''
    form.line2 = address.line2 || ''
    form.state_id = address.state_id ? String(address.state_id) : ''

    if (!address.state_id) {
        form.lga_id = ''
        lgas.value = []
        return
    }

    if (!isPickupMethod.value && address.lga_id) {
        form.lga_id = String(address.lga_id)
    }
})

watch(() => form.state_id, async (state) => {
    const desiredLgaId = pendingAddressLgaId.value
    form.lga_id = ''

    if (!state) {
        lgas.value = []
        pendingAddressLgaId.value = ''
        return
    }

    if (isPickupMethod.value) {
        lgas.value = []
        pendingAddressLgaId.value = ''
        return
    }

    try {
        const { data } = await axios.get(route('locations.lgas', state))
        lgas.value = data
        if (desiredLgaId && data.some(lga => String(lga.id) === String(desiredLgaId))) {
            form.lga_id = desiredLgaId
        }
    } catch (error) {
        console.error('Failed loading LGAs', error)
        lgas.value = []
    } finally {
        pendingAddressLgaId.value = ''
    }
})

watch([() => form.state_id, isPickupMethod], async ([state, isPickup]) => {
    form.pickup_location_id = ''
    pickupLocations.value = []

    if (isPickup) {
        form.save_address = false
    }

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
const unavailableItems = computed(() => (cart?.items || []).filter(item => item.availability?.is_available === false))
const hasUnavailableItems = computed(() => unavailableItems.value.length > 0)
const visibleDeliveryEstimate = computed(() => {
    if (!delivery_estimate?.available || !delivery_estimate?.checkout_message) {
        return null
    }

    return delivery_estimate
})

const formatter = new Intl.NumberFormat('en-NG', {
    style: 'currency',
    currency: 'NGN',
})

function money(value) {
    return formatter.format(Number(value || 0))
}

function buildCheckoutQuery() {
    return {
        coupon: form.coupon || undefined,
        address_id: form.address_id || undefined,
        shipping_method_id: form.shipping_method_id || undefined,
        state_id: form.state_id || undefined,
        lga_id: form.lga_id || undefined,
        pickup_location_id: form.pickup_location_id || undefined,
        phone: form.phone || undefined,
        line1: form.line1 || undefined,
        line2: form.line2 || undefined,
        save_address: form.save_address ? 1 : undefined,
    }
}

function clearTotalsRefreshTimer() {
    if (totalsRefreshTimer.value) {
        clearTimeout(totalsRefreshTimer.value)
        totalsRefreshTimer.value = null
    }
}

function refreshTotals() {
    clearTotalsRefreshTimer()
    totalsAreUpdating.value = true

    router.get(route('checkout.index'), buildCheckoutQuery(), {
        preserveScroll: true,
        preserveState: true,
        replace: true,
        onFinish: () => {
            totalsAreUpdating.value = false
        },
    })
}

function scheduleTotalsRefresh(delay = 250) {
    clearTotalsRefreshTimer()

    totalsRefreshTimer.value = setTimeout(() => {
        refreshTotals()
    }, delay)
}

function payNow() {
    clearTotalsRefreshTimer()

    form.post(route('checkout.pay'), {
        preserveScroll: true,
    })
}

watch(
    () => [form.address_id, form.shipping_method_id, form.state_id, form.lga_id, form.pickup_location_id],
    () => {
        scheduleTotalsRefresh(250)
    },
)

watch(() => form.coupon, (value, oldValue) => {
    if (value === oldValue) {
        return
    }

    const normalized = String(value || '').trim()
    if (normalized !== '' && normalized.length < 3) {
        return
    }

    scheduleTotalsRefresh(600)
})

onBeforeUnmount(() => {
    clearTotalsRefreshTimer()
})
</script>

<template>
    <Head title="Checkout" />

    <section class="mb-8 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-3xl font-bold tracking-tight text-slate-900 dark:text-slate-100">Checkout</h1>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                Confirm your order totals, reuse saved delivery details when you have them, and continue securely with Paystack.
            </p>
        </div>

        <Link
            :href="route('store.cart')"
            class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-500 dark:border-slate-700 dark:text-slate-200 dark:hover:border-slate-500"
        >
            Back to Cart
        </Link>
    </section>

    <section v-if="!hasItems" class="rounded-2xl border border-slate-200 bg-white p-8 text-center shadow-sm dark:border-slate-800 dark:bg-slate-950">
        <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Your cart is empty</h2>
        <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Add products from the storefront before checkout.</p>

        <Link
            :href="route('store.home')"
            class="mt-4 inline-flex rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700"
        >
            Continue Shopping
        </Link>
    </section>

    <section v-else class="grid gap-6 lg:grid-cols-[1.7fr_1fr]">
        <div class="space-y-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-950">
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
                {{ unavailableItems.length }} item{{ unavailableItems.length === 1 ? '' : 's' }} in this checkout need attention. Totals exclude unavailable lines until you update or remove them.
            </div>

            <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">Cart Summary</h2>

            <article
                v-for="item in cart.items"
                :key="item.id"
                :class="[
                    'flex items-center gap-3 rounded-xl border p-3',
                    item.availability?.is_available === false ? 'border-rose-200 bg-rose-50/40 dark:border-rose-900/60 dark:bg-rose-950/30' : 'border-slate-100 dark:border-slate-800',
                ]"
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
                    class="flex h-16 w-16 items-center justify-center rounded-lg bg-slate-100 text-xs text-slate-600 dark:bg-slate-900 dark:text-slate-300"
                >
                    No image
                </div>

                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold text-slate-900 dark:text-slate-100">
                        {{ item.product?.name }}
                    </p>

                    <p class="truncate text-xs text-slate-600 dark:text-slate-300">
                        {{ item.variant?.label }}
                    </p>

                    <p class="mt-1 text-xs text-slate-600 dark:text-slate-300">
                        Qty: {{ item.quantity }}
                    </p>

                    <div v-if="item.variant?.price?.has_discount" class="mt-2 flex flex-wrap items-center gap-2">
                        <span class="text-xs font-semibold text-rose-700">On Sale</span>
                        <span class="text-xs text-slate-400 line-through dark:text-slate-500">{{ money(item.variant.price.regular) }}</span>
                    </div>

                    <p
                        v-if="item.availability?.message"
                        :class="[
                            'mt-2 rounded-xl px-3 py-2 text-xs font-medium',
                            item.availability?.is_available === false
                                ? 'bg-rose-100 text-rose-700 dark:bg-rose-950/50 dark:text-rose-300'
                                : 'bg-amber-100 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300',
                        ]"
                    >
                        {{ item.availability.message }}
                    </p>

                    <p
                        v-if="item.availability?.included_in_totals === false"
                        class="mt-2 text-xs font-medium text-slate-600 dark:text-slate-300"
                    >
                        This line is not included in the payable total yet.
                    </p>
                </div>

                <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">
                    {{ money(item.subtotal) }}
                </p>
            </article>
        </div>

        <aside class="space-y-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-950">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">Checkout Details</h2>
                        <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Choose shipping, review charges, and proceed when everything looks right.</p>
                    </div>
                    <Link
                        :href="route('account.addresses.index')"
                        class="text-xs font-semibold text-slate-700 transition hover:text-slate-900 dark:text-slate-200 dark:hover:text-slate-100"
                    >
                        Manage addresses
                    </Link>
                </div>

                <div class="mt-4 space-y-4">
                    <div v-if="saved_addresses.length" class="rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-900">
                        <label class="text-xs font-medium uppercase tracking-wide text-slate-600 dark:text-slate-300">
                            Saved address
                        </label>

                        <select
                            v-model="form.address_id"
                            class="mt-2 h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"
                        >
                            <option value="">Use a new address</option>
                            <option
                                v-for="address in saved_addresses"
                                :key="address.id"
                                :value="address.id"
                            >
                                {{ address.label }} - {{ address.line1 }}
                            </option>
                        </select>

                        <div v-if="selectedSavedAddress" class="mt-3 rounded-xl border border-slate-200 bg-white p-3 text-sm text-slate-700 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-300">
                            <p class="font-semibold text-slate-900 dark:text-slate-100">{{ selectedSavedAddress.recipient_name }}</p>
                            <p class="mt-1">{{ selectedSavedAddress.line1 }}<span v-if="selectedSavedAddress.line2">, {{ selectedSavedAddress.line2 }}</span></p>
                            <p class="mt-1">{{ [selectedSavedAddress.lga?.name, selectedSavedAddress.state?.name, selectedSavedAddress.country?.name].filter(Boolean).join(', ') }}</p>
                            <p v-if="selectedSavedAddress.phone" class="mt-1">{{ selectedSavedAddress.phone }}</p>
                        </div>
                    </div>

                    <div v-else class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-4 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300">
                        No saved addresses yet. Save one from your account area to make future checkout faster.
                    </div>

                    <div>
                        <label class="text-xs font-medium uppercase tracking-wide text-slate-600 dark:text-slate-300">
                            Discount code
                        </label>

                        <input
                            v-model="form.coupon"
                            type="text"
                            placeholder="Enter coupon code"
                            class="mt-1 h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 placeholder:text-slate-400 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 dark:placeholder:text-slate-500"
                            @blur="scheduleTotalsRefresh(0)"
                        />

                        <p v-if="coupon_error" class="mt-1 text-xs font-medium text-rose-600">
                            {{ coupon_error }}
                        </p>
                    </div>

                    <div>
                        <label class="text-xs font-medium uppercase tracking-wide text-slate-600 dark:text-slate-300">
                            Shipping method
                        </label>

                        <select
                            v-model="form.shipping_method_id"
                            class="mt-1 h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"
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

                        <p v-if="currentMethod?.description" class="mt-2 text-xs text-slate-600 dark:text-slate-300">
                            {{ currentMethod.description }}
                        </p>
                    </div>

                    <div>
                        <label class="text-xs font-medium uppercase tracking-wide text-slate-600 dark:text-slate-300">
                            Phone number
                        </label>

                        <input
                            v-model="form.phone"
                            type="text"
                            placeholder="Enter phone number"
                            class="mt-1 h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 placeholder:text-slate-400 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 dark:placeholder:text-slate-500"
                        />
                    </div>

                    <div>
                        <label class="text-xs font-medium uppercase tracking-wide text-slate-600 dark:text-slate-300">
                            State
                        </label>

                        <select
                            v-model="form.state_id"
                            class="mt-1 h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"
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
                        <label class="text-xs font-medium uppercase tracking-wide text-slate-600 dark:text-slate-300">
                            LGA / Town
                        </label>

                        <select
                            v-model="form.lga_id"
                            class="mt-1 h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"
                        >
                            <option value="">Select LGA / town</option>

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
                        <label class="text-xs font-medium uppercase tracking-wide text-slate-600 dark:text-slate-300">
                            Pickup location
                        </label>

                        <select
                            v-model="form.pickup_location_id"
                            class="mt-1 h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"
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

                        <p v-if="!loadingPickupLocations && !pickupLocations.length && form.state_id" class="mt-1 text-xs text-slate-600 dark:text-slate-300">
                            No pickup locations available for this state.
                        </p>
                    </div>

                    <div v-if="!isPickupMethod">
                        <label class="text-xs font-medium uppercase tracking-wide text-slate-600 dark:text-slate-300">
                            Address line 1
                        </label>

                        <input
                            v-model="form.line1"
                            type="text"
                            placeholder="Street address"
                            class="mt-1 h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 placeholder:text-slate-400 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 dark:placeholder:text-slate-500"
                        />
                    </div>

                    <div v-if="!isPickupMethod">
                        <label class="text-xs font-medium uppercase tracking-wide text-slate-600 dark:text-slate-300">
                            Address line 2
                        </label>

                        <input
                            v-model="form.line2"
                            type="text"
                            placeholder="Apartment, suite, landmark (optional)"
                            class="mt-1 h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 placeholder:text-slate-400 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 dark:placeholder:text-slate-500"
                        />
                    </div>

                    <label
                        v-if="!isPickupMethod && !form.address_id"
                        class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300"
                    >
                        <input v-model="form.save_address" type="checkbox" class="h-4 w-4 rounded border-slate-300 dark:border-slate-700 dark:bg-slate-950" />
                        Save this delivery address for future purchases
                    </label>

                    <div
                        v-else-if="!isPickupMethod && form.address_id"
                        class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300"
                    >
                        This checkout is using an address already saved to your account.
                    </div>

                    <p v-if="shipping_error" class="text-xs font-medium text-rose-600">
                        {{ shipping_error }}
                    </p>

                    <p v-if="page.props.errors?.stock" class="text-xs font-medium text-rose-600">
                        {{ page.props.errors.stock }}
                    </p>

                    <p v-if="page.props.errors?.payment" class="text-xs font-medium text-rose-600">
                        {{ page.props.errors.payment }}
                    </p>

                    <p v-if="page.props.errors?.reference" class="text-xs font-medium text-rose-600">
                        {{ page.props.errors.reference }}
                    </p>
                </div>

                <p class="mt-4 text-xs font-medium text-slate-600 dark:text-slate-300">
                    {{ totalsAreUpdating ? 'Refreshing totals...' : 'Totals update automatically when you change shipping details or coupon code.' }}
                </p>

                <div v-if="visibleDeliveryEstimate" class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-800 dark:bg-slate-900">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        Expected delivery
                    </p>
                    <p class="mt-1 text-sm font-semibold text-slate-900 dark:text-slate-100">
                        {{ visibleDeliveryEstimate.checkout_message }}
                    </p>
                    <p
                        v-if="visibleDeliveryEstimate?.warehouse?.name"
                        class="mt-1 text-xs text-slate-600 dark:text-slate-300"
                    >
                        Fulfillment estimate based on {{ visibleDeliveryEstimate.warehouse.name }}.
                    </p>
                </div>

                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between text-slate-600 dark:text-slate-300">
                        <dt>Subtotal</dt>
                        <dd>{{ money(summary.subtotal) }}</dd>
                    </div>

                    <div class="flex justify-between text-slate-600 dark:text-slate-300">
                        <dt>Discount</dt>
                        <dd class="text-emerald-600">-{{ money(summary.discount) }}</dd>
                    </div>

                    <div v-if="summary.discount_label" class="text-xs text-slate-600 dark:text-slate-300">
                        Applied: {{ summary.discount_label }}
                    </div>

                    <div class="flex justify-between text-slate-600 dark:text-slate-300">
                        <dt>Shipping</dt>

                        <dd v-if="summary.shipping_free" class="font-semibold text-emerald-600">
                            Free Shipping
                        </dd>

                        <dd v-else>
                            {{ money(summary.shipping) }}
                        </dd>
                    </div>

                    <div class="border-t border-slate-200 pt-3 text-base font-bold text-slate-900 dark:border-slate-800 dark:text-slate-100">
                        <div class="flex justify-between">
                            <dt>Total</dt>
                            <dd>{{ money(summary.total) }}</dd>
                        </div>
                    </div>
                </dl>

                <button
                    type="button"
                    class="mt-5 w-full rounded-xl bg-emerald-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="form.processing || totalsAreUpdating || hasUnavailableItems"
                    @click="payNow"
                >
                    {{
                        form.processing
                            ? 'Redirecting to Paystack...'
                            : totalsAreUpdating
                                ? 'Refreshing totals...'
                                : hasUnavailableItems
                                    ? 'Resolve cart issues to continue'
                                    : 'Pay now'
                    }}
                </button>
            </div>
        </aside>
    </section>
</template>



