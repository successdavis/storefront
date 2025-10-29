<template>
    <aside
        class="sticky top-0 flex h-[90] w-[26rem] flex-col bg-white p-4 shadow-lg dark:bg-gray-800"
    >
        <div class="mb-3 flex items-center justify-between gap-3">
            <select
                v-model="selectedCustomer"
                @change="handleCustomerChange"
                class="w-full rounded border px-2 py-3 text-sm"
            >
                <option value="">Walk In Customer</option>
                <option value="__add_new__">➕ Add New Customer</option>
                <option v-for="c in customers" :key="c.id" :value="c.id">
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
                :subtotal="subtotal"
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

        <!-- Modal: Parent controls visibility -->
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
        <div class="flex-1 overflow-y-auto pr-2">
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
                    <div class="truncate font-medium">{{ item.name }}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        {{ item.variant_label }}
                    </div>

                    <div class="mt-2 flex items-center gap-2">
                        <button
                            @click="decrement(item)"
                            class="rounded border px-2 py-1"
                        >
                            -
                        </button>
                        <input
                            type="number"
                            v-model.number="item.quantity"
                            @change="updateItem(item)"
                            class="w-16 rounded border bg-white px-2 py-1 text-center"
                        />
                        <button
                            @click="increment(item)"
                            class="rounded border px-2 py-1"
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

        <div class="mt-4 border-t border-gray-200 pt-4 dark:border-gray-700">
            <!-- Show shipping cost only if shippingInfo exists -->
            <div v-if="shippingInfo" class="mb-2 flex justify-between text-sm">
                <div>
                    Shipping ({{
                        shippingInfo.shipping_method_name || 'Shipping'
                    }})
                </div>
                <div>
                    {{ formatCurrency(shippingInfo.shipping_cost || 0) }}
                </div>
            </div>

            <div class="flex justify-between text-sm">
                <div>Sub Total</div>
                <div>{{ formatCurrency(subtotal) }}</div>
            </div>

            <div class="mt-3 flex justify-between text-lg font-bold">
                <div>Total</div>
                <div>{{ formatCurrency(total) }}</div>
            </div>

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


// Customers + location lists are parent-controlled now
const customers = ref([]);
const selectedCustomer = ref('');
const showCustomerModal = ref(false);

const showShippingModal = ref(false);
const shippingInfo = ref(null); // when modal emits 'created' we set this

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

// computed total includes shipping cost if present
const total = computed(() => {
    const ship = Number(shippingInfo.value?.shipping_cost || 0);
    return Number(subtotal.value || 0) + ship;
});

// load initial data
async function loadCustomers() {
    const res = await axios.get(route('admin.customers.list'));
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
        const res = await axios.post(route('admin.customers.store'), payload);
        const newCust = res.data;
        customers.value.push(newCust);
        selectedCustomer.value = newCust.id;
        showCustomerModal.value = false;
        Object.keys(newCustomer.value).forEach(
            (k) => (newCustomer.value[k] = ''),
        );
    } catch (err) {
        alert('Failed to create customer.');
        console.error(err);
    }
}

// When shipping modal emits created (it does NOT persist to DB) we store the payload
function handleShipmentCreated(payload) {
    // payload expected to contain shipping_method_id, shipping_method_name (optional), shipping_cost, shipping_zone_id, address, etc.
    shippingInfo.value = { ...payload };
    showShippingModal.value = false;

    // if cart changes, we will auto-recalculate shipping (see watcher)
}

// Recalculate shipping on cart changes if shippingInfo exists
const recalcShippingDebounced = debounce(async () => {
    if (!shippingInfo.value) return;

    // cancel previous
    if (recalcController) {
        try {
            recalcController.abort();
        } catch {}
    }
    recalcController = new AbortController();

    try {
        const res = await axios.post(
            route('shipping.calculate'),
            {
                shipping_method_id: shippingInfo.value.shipping_method_id,
                shipping_zone_id: shippingInfo.value.shipping_zone_id,
                // include full items array and subtotal — server expects items => array
                items: cartItems.value.map((it) => ({
                    variant_id: it.variant_id,
                    product_id: it.product_id,
                    quantity: it.quantity,
                    weight: it.weight ?? 0,
                    price: it.price,
                })),
                subtotal: subtotal.value,
            },
            { signal: recalcController.signal },
        );

        const data = res?.data?.data ?? res?.data;
        if (data && data.total !== undefined && data.total !== null) {
            shippingInfo.value.shipping_cost = Number(data.total);
        } else {
            // fallback: set zero and leave message in console
            shippingInfo.value.shipping_cost = 0;
            console.warn('Shipping calculate returned no total', res.data);
        }
    } catch (err) {
        // If abort, ignore
        if (axios.isCancel?.(err) || err.name === 'CanceledError') return;
        console.error('Error recalculating shipping:', err);
        // Keep existing shipping cost, but you might want to show a notification
    } finally {
        recalcController = null;
    }
}, 350); // debounce 350ms

// watch cart items for recalculation
watch(
    () => cartItems.value.map((i) => `${i.variant_id}:${i.quantity}`), // watch items + quantities
    () => {
        if (shippingInfo.value) {
            recalcShippingDebounced();
        }
    },
);

// place order — include shipping info & items; useCart.placeOrder should accept this payload on backend
async function onPlaceOrder() {
    const payload = {
        customer_id: selectedCustomer.value || null,
        items: cartItems.value.map((it) => ({
            variant_id: it.variant_id,
            product_id: it.product_id,
            quantity: it.quantity,
            price: it.price,
        })),
        total: total.value,
        subtotal: subtotal.value,
        shipping: shippingInfo.value || null,
    };


    // delegate to useCart.placeOrder which may handle clearing cart on success
    try {
        await placeOrder(payload);
        // clear local shipping info when order placed
        shippingInfo.value = null;
    } catch (err) {
        console.error('Order placement failed', err);
        // Optionally show user error
    }
}


function clearCartAndShipping() {
    clearCart();
    shippingInfo.value = null;
}

// Expose increment/decrement/update/remove already provided by useCart
</script>
