<template>
    <aside
        class="sticky top-0 flex w-[26rem] flex-col bg-white p-4 shadow-lg dark:bg-gray-800"
    >
        <div class="mb-3 flex items-center justify-between gap-3">
            <select
                v-model="selectedCustomer"
                @change="handleCustomerChange"
                class="w-full rounded border px-2 py-3 text-sm"
            >
                <option class="dark:text-black" value="">Walk In Customer</option>
                <option class="dark:text-black" value="__add_new__">➕ Add New Customer</option>
                <option class="dark:text-black" v-for="c in customers" :key="c.id" :value="c.id">
                    {{ c.name }}
                </option>
            </select>

            <ShippingButton @open="showShippingModal = true" />

            <ShippingModal
                v-show="showShippingModal"
                :countries="countries"
                :states="states"
                :lgas="lgas"
                :items="cartItems"
                :subtotal="previewTotals.subtotal"
                :initial-shipping="shippingInfo"
                :methods="shippingMethods"
                :zones="shippingZones"
                :pickup-locations="pickupLocations"
                @close="showShippingModal = false"
                @created="handleShipmentCreated"
                @load-states="loadStates"
                @load-lgas="loadLgas"
            />

            <div class="flex items-center gap-2">
                <!-- Orders panel open button -->
                <OffcanvasOrders />
            </div>
        </div>

        <!-- Customer modal -->
        <CustomerModal
            v-if="showCustomerModal"
            :countries="countries"
            :states="states"
            :lgas="lgas"
            :newCustomer="newCustomer"
            @close="showCustomerModal = false"
            @save="handleSaveNewCustomer"
            @load-states="loadStates"
            @load-lgas="loadLgas"
        />

        <!-- Cart items -->
        <div style="overflow-y: auto; flex-grow: 1;" class="pr-2" >
            <div
                v-if="cartItems.length === 0"
                class="py-8 text-center text-gray-500 dark:text-gray-400"
            >
                Cart is empty
            </div>

            <div
                v-for="item in cartItems"
                :key="item.variant_id"
                class="flex items-start gap-3 border-b border-gray-200 py-3 dark:border-gray-700"
            >
                <img :src="item.image" class="h-14 w-14 rounded object-cover" />
                <div class="flex-1">
                    <div class="font-medium">{{ item.name }}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        {{ item.variant_label }}
                    </div>

                    <div class="mt-2 flex items-center gap-2">
                        <button
                            @click="decrement(item)"
                            class="rounded border dark:bg-gray-500 px-2 py-1"
                        >
                            -
                        </button>
                        <input
                            type="number"
                            v-model.number="item.quantity"
                            @change="updateItem(item)"
                            class="w-16 rounded border dark:text-black bg-white px-2 py-1 text-center"
                        />
                        <button
                            @click="increment(item)"
                            class="rounded border px-2 py-1 dark:bg-gray-500"
                        >
                            +
                        </button>
                    </div>
                </div>

                <div class="text-right">
                    <div class="font-semibold text-blue-600 dark:text-blue-400">
                        {{ formatCurrency(item.price * item.quantity) }}
                    </div>
                    <button
                        @click="removeItem(item)"
                        class="mt-2 text-xs text-red-500"
                    >
                        Remove
                    </button>
                </div>
            </div>
        </div>

        <!-- Totals -->
        <div class="mt-4 border-t border-gray-200 pt-4 dark:border-gray-700">
            <!-- Shipping cost -->
            <div v-if="previewTotals.shipping_total > 0" class="mb-2 flex justify-between text-sm">
                <div>
                    Shipping ({{ shippingInfo?.shipping_method_name || 'Shipping' }})
                </div>
                <div>
                    {{ formatCurrency(previewTotals.shipping_total) }}
                </div>
            </div>

            <!-- Subtotal -->
            <div class="flex justify-between text-sm">
                <div>Sub Total</div>
                <div>{{ formatCurrency(previewTotals.subtotal) }}</div>
            </div>

            <!-- Discount -->
            <div v-if="previewTotals.discount > 0" class="flex justify-between text-sm text-green-600 mt-1">
                <div>
                    Discount
                    <span v-if="previewTotals.discount_label" class="italic">
            ({{ previewTotals.discount_label }})
          </span>
                </div>
                <div>-{{ formatCurrency(previewTotals.discount) }}</div>
            </div>

            <!-- Coupon input -->
            <div class="mt-3 flex gap-2">
                <input
                    v-model="couponCode"
                    type="text"
                    placeholder="Enter coupon code"
                    class="flex-1 rounded border px-2 py-2 text-sm"
                />
                <button
                    @click="refreshPreview"
                    class="rounded bg-blue-100 px-3 py-2 text-blue-700 text-sm"
                    :disabled="loadingPreview"
                >
                    Apply
                </button>
            </div>

            <!-- Total -->
            <div class="mt-3 flex justify-between text-lg font-bold">
                <div>Total</div>
                <div>{{ formatCurrency(previewTotals.total) }}</div>
            </div>

            <!-- Actions -->
            <div class="mt-4 flex gap-2">
                <button
                    @click="onPlaceOrder"
                    class="flex-1 rounded bg-blue-600 py-2 text-white"
                >
                    Place Order
                </button>
                <button
                    @click="clearCartAndShipping"
                    class="rounded border border-gray-300 px-4 py-2"
                >
                    Clear
                </button>
            </div>
        </div>
    </aside>
</template>


<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import ShippingButton from './ShippingButton.vue';
import ShippingModal from './ShippingModal.vue';
import CustomerModal from './CustomerModal.vue';
import { useCart } from '../composables/useCart';
import axios from 'axios';
import { route } from 'ziggy-js';
import debounce from 'lodash/debounce';
import { useCurrencyFormatter } from '@/pages/Admin/Pos/composables/useCurrencyFormatter.js';
import OffcanvasOrders from '@/pages/Admin/Pos/components/OffcanvasOrders.vue'; // optional: you can implement your own debounce
import { usePage } from '@inertiajs/vue3';

// useCart provides reactive cartItems and subtotal, and item operations
const {
    cartItems,
    subtotal,
    increment,
    decrement,
    removeItem,
    clearCart,
    placeOrder,
    updateItem,
} = useCart();

const { formatCurrency } = useCurrencyFormatter();
const page = usePage()
const posRoutes = computed(() => page.props.pos_routes || {})


// Customers + location lists are parent-controlled now
const customers = ref([]);
const selectedCustomer = ref('');
const showCustomerModal = ref(false);

const showShippingModal = ref(false);
const shippingInfo = ref(null); // when modal emits 'created' we set this

const couponCode = ref('');
const previewTotals = ref({
    subtotal: 0,
    shipping_total: 0,
    discount: 0,
    discount_label: null,
    total: 0,
});

const loadingPreview = ref(false);
let controller = null;

const newCustomer = ref({
    name: '',
    email: '',
    phone: '',
    country_id: '',
    state_id: '',
    lga_id: '',
    address: '',
    gender: '',
});

const countries = ref([]);
const states = ref([]);
const lgas = ref([]);

// request cancellation for recalculating shipping
let recalcController = null;

const shippingMethods = ref([]);
const shippingZones = ref([]);
const pickupLocations = ref([]);

let checkout_token = ref('');

// computed total includes shipping cost if present
const total = computed(() => {
    const ship = Number(shippingInfo.value?.shipping_cost || 0);
    return Number(subtotal.value || 0) + ship;
});

// load initial data
async function loadCustomers() {
    const res = await axios.get(posRoutes.value.customers_list);
    customers.value = res.data;
}

async function loadCountries() {
    const res = await axios.get(route('locations.countries'));
    countries.value = res.data;
}

async function loadStates(countryId) {
    if (!countryId) return;
    const res = await axios.get(route('locations.states', countryId));
    states.value = res.data;
}

async function loadLgas(stateId) {
    if (!stateId) return;
    const res = await axios.get(route('locations.lgas', stateId));
    lgas.value = res.data;
}

// initial mounts
loadCustomers();
loadCountries();

function handleCustomerChange() {
    if (selectedCustomer.value === '__add_new__') {
        showCustomerModal.value = true;
        selectedCustomer.value = '';
    }
    refreshPreview();
}

onMounted(async () => {
    const [methods, zones, pickups] = await Promise.all([
        axios.get(route('shipping.methods')),
        axios.get(route('shipping.zones')),
        axios.get(route('shipping.pickup_locations')),
    ]);
    shippingMethods.value = methods.data;
    shippingZones.value = zones.data;
    pickupLocations.value = pickups.data;
});

// called when modal emits 'save'
async function handleSaveNewCustomer(payload) {
    try {
        const res = await axios.post(posRoutes.value.customers_store, payload);
        const newCust = res.data;
        customers.value.push(newCust);
        selectedCustomer.value = newCust.id;
        showCustomerModal.value = false;
        Object.keys(newCustomer.value).forEach(
            (k) => (newCustomer.value[k] = ''),
        );

        refreshPreview();
    } catch (err) {
        alert('Failed to create customer.');
        console.error(err);
    }
}

// auto call /checkout/preview
const refreshPreview = debounce(async function () {
    if (cartItems.value.length === 0) {
        previewTotals.value = { subtotal: 0, shipping_total: 0, discount: 0, discount_label: null, total: 0 };
        return;
    }

    if (controller) {
        try { controller.abort() } catch {}
    }
    controller = new AbortController();
    loadingPreview.value = true;

    try {
        const res = await axios.post(route('checkout.preview'), {
            channel: 'pos',
            items: cartItems.value.map(i => ({ variant_id: i.variant_id, quantity: i.quantity })),
            shipping: shippingInfo.value || null,
            coupon: couponCode.value || null,
            customer_id: selectedCustomer.value || null,
            checkout_token: checkout_token.value
        }, { signal: controller.signal });

        const data = res?.data?.data ?? res?.data;

        checkout_token.value = String(data.checkout_token) || null;
        previewTotals.value = {
            subtotal: Number(data.subtotal || 0),
            shipping_total: Number(data.shipping_total || 0),
            discount: Number(data.discount || 0),
            discount_label: data.discount_label || null,
            total: Number(data.total || 0),
        };
    } catch (err) {
        if (err.name !== 'CanceledError') {
            console.error('Checkout preview failed:', err);
        }
    } finally {
        loadingPreview.value = false;
        controller = null;
    }
}, 500); // wait 500ms after the last trigger

// Called when shipping modal emits created
function handleShipmentCreated(payload) {
    shippingInfo.value = { ...payload };
    showShippingModal.value = false;
    refreshPreview();
}

// Watch cart, shipping, coupon
watch(
    () => [
        couponCode.value,
        shippingInfo.value?.shipping_method_id,
        shippingInfo.value?.shipping_zone_id,
        ...cartItems.value.map(i => `${i.variant_id}:${i.quantity}`)
    ],
    refreshPreview,
    { deep: false }
);



// place order — include shipping info & items; useCart.placeOrder should accept this payload on backend
async function onPlaceOrder() {
    const payload = {
        customer_id: selectedCustomer.value || null,
        items: cartItems.value.map(i => ({ variant_id: i.variant_id, quantity: i.quantity, price: i.price })),
        subtotal: previewTotals.value.subtotal,
        total: previewTotals.value.total,
        shipping: shippingInfo.value || null,
        coupon: couponCode.value || null,
        checkout_token: checkout_token.value,
    };

    try {
        await placeOrder(payload);
        shippingInfo.value = null;
        couponCode.value = '';
        previewTotals.value = { subtotal: 0, shipping_total: 0, discount: 0, discount_label: null, total: 0 };
    } catch (err) {
        console.error('Order placement failed', err);
    }
}


function clearCartAndShipping() {
    clearCart();
    shippingInfo.value = null;
    couponCode.value = '';
    previewTotals.value = { subtotal: 0, shipping_total: 0, discount: 0, discount_label: null, total: 0 };
}

// Expose increment/decrement/update/remove already provided by useCart
</script>
