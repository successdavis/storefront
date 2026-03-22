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
    saved_addresses,
    selected_shipping,
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
})

const page = usePage()
const lgas = ref(initialLgas || [])
const pickupLocations = ref(pickup_locations || [])
const loadingPickupLocations = ref(false)
const pendingAddressLgaId = ref(selected_shipping?.lga_id ? String(selected_shipping.lga_id) : '')

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
                Confirm your order totals, reuse saved delivery details when you have them, and continue securely with Paystack.
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

                    <div v-if="item.variant?.price?.has_discount" class="mt-2 flex flex-wrap items-center gap-2">
                        <span class="text-xs font-semibold text-rose-700">On Sale</span>
                        <span class="text-xs text-slate-400 line-through">{{ money(item.variant.price.regular) }}</span>
                    </div>
                </div>

                <p class="text-sm font-semibold text-slate-900">
                    {{ money(item.subtotal) }}
                </p>
            </article>
        </div>

        <aside class="space-y-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold text-slate-900">Checkout Details</h2>
                        <p class="mt-1 text-sm text-slate-500">Choose shipping, review charges, and proceed when everything looks right.</p>
                    </div>
                    <Link
                        :href="route('account.addresses.index')"
                        class="text-xs font-semibold text-slate-600 transition hover:text-slate-900"
                    >
                        Manage addresses
                    </Link>
                </div>

                <div class="mt-4 space-y-4">
                    <div v-if="saved_addresses.length" class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <label class="text-xs font-medium uppercase tracking-wide text-slate-500">
                            Saved address
                        </label>

                        <select
                            v-model="form.address_id"
                            class="mt-2 h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm"
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

                        <div v-if="selectedSavedAddress" class="mt-3 rounded-xl border border-slate-200 bg-white p-3 text-sm text-slate-600">
                            <p class="font-semibold text-slate-900">{{ selectedSavedAddress.recipient_name }}</p>
                            <p class="mt-1">{{ selectedSavedAddress.line1 }}<span v-if="selectedSavedAddress.line2">, {{ selectedSavedAddress.line2 }}</span></p>
                            <p class="mt-1">{{ [selectedSavedAddress.lga?.name, selectedSavedAddress.state?.name, selectedSavedAddress.country?.name].filter(Boolean).join(', ') }}</p>
                            <p v-if="selectedSavedAddress.phone" class="mt-1">{{ selectedSavedAddress.phone }}</p>
                        </div>
                    </div>

                    <div v-else class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-4 text-sm text-slate-600">
                        No saved addresses yet. Save one from your account area to make future checkout faster.
                    </div>

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
                            Phone number
                        </label>

                        <input
                            v-model="form.phone"
                            type="text"
                            placeholder="Enter phone number"
                            class="mt-1 h-10 w-full rounded-xl border border-slate-300 px-3 text-sm"
                        />
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
                            Pickup location
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

                        <p v-if="!loadingPickupLocations && !pickupLocations.length && form.state_id" class="mt-1 text-xs text-slate-500">
                            No pickup locations available for this state.
                        </p>
                    </div>

                    <div v-if="!isPickupMethod">
                        <label class="text-xs font-medium uppercase tracking-wide text-slate-500">
                            Address line 1
                        </label>

                        <input
                            v-model="form.line1"
                            type="text"
                            placeholder="Street address"
                            class="mt-1 h-10 w-full rounded-xl border border-slate-300 px-3 text-sm"
                        />
                    </div>

                    <div v-if="!isPickupMethod">
                        <label class="text-xs font-medium uppercase tracking-wide text-slate-500">
                            Address line 2
                        </label>

                        <input
                            v-model="form.line2"
                            type="text"
                            placeholder="Apartment, suite, landmark (optional)"
                            class="mt-1 h-10 w-full rounded-xl border border-slate-300 px-3 text-sm"
                        />
                    </div>

                    <label
                        v-if="!isPickupMethod && !form.address_id"
                        class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700"
                    >
                        <input v-model="form.save_address" type="checkbox" class="h-4 w-4 rounded border-slate-300" />
                        Save this delivery address for future purchases
                    </label>

                    <div
                        v-else-if="!isPickupMethod && form.address_id"
                        class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600"
                    >
                        This checkout is using an address already saved to your account.
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
                    Update totals
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
                    {{ form.processing ? 'Redirecting to Paystack...' : 'Pay now' }}
                </button>
            </div>
        </aside>
    </section>
</template>



