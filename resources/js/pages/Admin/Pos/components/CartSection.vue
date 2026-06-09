<template>
    <aside
        class="sticky top-0 flex w-[26rem] flex-col bg-white p-4 shadow-lg dark:bg-gray-800"
    >
        <div class="mb-3 flex items-center justify-between gap-3">
            <CustomerPicker
                v-model="selectedCustomer"
                :options="customers"
                :selected-customer="selectedCustomerRecord"
                :loading="loadingCustomers"
                @search="handleCustomerSearch"
                @add-new="showCustomerModal = true"
            />

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
                :class="item.stock_status ? 'rounded-md border border-red-200 bg-red-50 px-2 dark:border-red-900/60 dark:bg-red-950/20' : ''"
            >
                <img :src="item.image" class="h-14 w-14 rounded object-cover" />
                <div class="flex-1">
                    <div class="font-medium">{{ item.name }}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        {{ item.variant_label }}
                    </div>
                    <p
                        v-if="item.stock_status"
                        class="mt-2 text-xs font-semibold text-red-700 dark:text-red-300"
                    >
                        Out of stock
                    </p>

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

            <Dialog v-model:open="showCheckoutModal">
                <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                    <DialogHeader class="space-y-3">
                        <DialogTitle>Checkout payments</DialogTitle>
                        <DialogDescription>
                            Capture full payment, split payment, or a credit sale before we finalize this POS order.
                        </DialogDescription>
                    </DialogHeader>

            <div class="mt-4 rounded-2xl border border-gray-200 p-3 dark:border-gray-700">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Payment</h3>
                    </div>
                    <div class="flex items-center gap-2 rounded-full bg-gray-100 p-1 dark:bg-gray-900">
                        <button
                            type="button"
                            class="rounded-full px-3 py-1 text-xs font-medium transition"
                            :class="paymentMode === 'full' ? 'bg-blue-600 text-white' : 'text-gray-600 dark:text-gray-300'"
                            @click="paymentMode = 'full'"
                        >
                            Full payment
                        </button>
                        <button
                            type="button"
                            class="rounded-full px-3 py-1 text-xs font-medium transition"
                            :class="paymentMode === 'partial' ? 'bg-amber-500 text-white' : 'text-gray-600 dark:text-gray-300'"
                            @click="paymentMode = 'partial'"
                        >
                            Partial / credit
                        </button>
                    </div>
                </div>

                <div class="mt-3 space-y-3">
                    <div
                        v-for="(line, index) in paymentLines"
                        :key="line.id"
                        class="grid gap-2 md:grid-cols-[minmax(0,1fr)_9rem_auto]"
                    >
                        <select
                            v-model="line.method"
                            class="rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                        >
                            <option
                                v-for="method in paymentMethodOptions"
                                :key="method.value"
                                :value="method.value"
                            >
                                {{ method.label }}
                            </option>
                        </select>

                        <input
                            v-model.number="line.amount"
                            type="number"
                            min="0"
                            step="0.01"
                            class="rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                            placeholder="0.00"
                        />

                        <button
                            type="button"
                            class="rounded-xl border border-gray-300 px-3 py-2 text-sm text-gray-600 transition hover:border-red-300 hover:text-red-500 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:text-gray-300"
                            :disabled="paymentLines.length === 1"
                            @click="removePaymentLine(index)"
                        >
                            Remove
                        </button>

                        <input
                            v-model="line.transaction_reference"
                            type="text"
                            class="rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm md:col-span-3 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                            placeholder="Reference (optional)"
                        />
                    </div>
                </div>

                <div class="mt-3 flex items-center justify-between gap-2">
                    <button
                        type="button"
                        class="rounded-xl border border-dashed border-blue-300 px-3 py-2 text-sm font-medium text-blue-600 transition hover:bg-blue-50 dark:border-blue-700 dark:text-blue-300 dark:hover:bg-gray-900"
                        @click="addPaymentLine"
                    >
                        + Add payment line
                    </button>

                    <div class="text-right text-xs text-gray-500 dark:text-gray-400">
                        Split payments are supported. Revenue is posted once; unpaid balance becomes receivable.
                    </div>
                </div>

                <div class="mt-3 grid gap-2 rounded-xl bg-gray-50 p-3 text-sm dark:bg-gray-900/60 md:grid-cols-2">
                    <div class="flex items-center justify-between">
                        <span class="text-gray-500 dark:text-gray-400">Total paid</span>
                        <span class="font-semibold text-gray-900 dark:text-gray-100">{{ formatCurrency(totalPaid) }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-gray-500 dark:text-gray-400">Outstanding balance</span>
                        <span class="font-semibold" :class="outstandingBalance > 0 ? 'text-amber-600 dark:text-amber-300' : 'text-emerald-600 dark:text-emerald-300'">
                            {{ formatCurrency(outstandingBalance) }}
                        </span>
                    </div>
                </div>

                <div
                    v-if="selectedCustomerDebt.outstanding > 0 || selectedCustomerDebt.overdueCount > 0"
                    class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-200"
                >
                    <p class="font-medium">Customer credit warning</p>
                    <p>
                        Current outstanding:
                        <span class="font-semibold">{{ formatCurrency(selectedCustomerDebt.outstanding) }}</span>
                        <span v-if="selectedCustomerDebt.overdueCount > 0">
                            · {{ selectedCustomerDebt.overdueCount }} overdue invoice(s)
                        </span>
                    </p>
                </div>

                <div v-if="paymentMode === 'partial'" class="mt-3 grid gap-3 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">
                            Due date
                        </label>
                        <input
                            v-model="dueDate"
                            type="date"
                            class="w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">
                            Repayment terms
                        </label>
                        <input
                            v-model="repaymentTerms"
                            type="text"
                            class="w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                            placeholder="e.g. Balance due within 14 days"
                        />
                    </div>
                </div>
            </div>

            <p v-if="checkoutError" class="mt-3 text-sm font-medium text-red-500">
                {{ checkoutError }}
            </p>

                    <DialogFooter class="mt-4 gap-2">
                        <DialogClose as-child>
                            <button
                                type="button"
                                class="rounded border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200"
                                @click="checkoutError = ''"
                            >
                                Cancel
                            </button>
                        </DialogClose>
                        <button
                            type="button"
                            class="rounded bg-blue-600 px-4 py-2 text-sm font-medium text-white disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="isPlacingOrder"
                            @click="onPlaceOrder"
                        >
                            {{ isPlacingOrder ? 'Confirming...' : 'Confirm sale' }}
                        </button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- Actions -->
            <div class="mt-4 flex gap-2">
                <button
                    @click="checkoutError = ''; showCheckoutModal = true"
                    :disabled="cartItems.length === 0"
                    class="flex-1 rounded bg-blue-600 py-2 text-white disabled:cursor-not-allowed disabled:opacity-60"
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
import CustomerPicker from './CustomerPicker.vue';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useCart } from '../composables/useCart';
import axios from 'axios';
import { route } from 'ziggy-js';
import debounce from 'lodash/debounce';
import { useCurrencyFormatter } from '@/pages/Admin/Pos/composables/useCurrencyFormatter.js';
import OffcanvasOrders from '@/pages/Admin/Pos/components/OffcanvasOrders.vue'; // optional: you can implement your own debounce
import { usePage } from '@inertiajs/vue3';
import { eventBus } from '@/eventBus.js';

// useCart provides reactive cartItems and subtotal, and item operations
const {
    cartItems,
    isPlacingOrder,
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
const isAdminPosContext = computed(() => String(page.url || '').startsWith('/admin/'))
const customerListUrl = computed(() => posRoutes.value.customers_list || (isAdminPosContext.value ? '/admin/customers/list' : '/sales/pos/customers'))
const customerStoreUrl = computed(() => posRoutes.value.customers_store || (isAdminPosContext.value ? '/admin/customers/store' : '/sales/pos/customers'))


// Customers + location lists are parent-controlled now
const customers = ref(Array.isArray(page.props.recent_customers) ? page.props.recent_customers : []);
const selectedCustomer = ref('');
const showCustomerModal = ref(false);
const loadingCustomers = ref(false);
const selectedCustomerMeta = ref(null);
const paymentMode = ref('full');
const paymentLines = ref([createPaymentLine('cash', 0)]);
const dueDate = ref('');
const repaymentTerms = ref('');
const checkoutError = ref('');
const showCheckoutModal = ref(false);

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

const selectedCustomerRecord = computed(() => {
    if (!selectedCustomer.value) {
        return null;
    }

    return (
        customers.value.find((customer) => String(customer.id) === String(selectedCustomer.value))
        || selectedCustomerMeta.value
        || null
    );
});

const paymentMethodOptions = [
    { value: 'cash', label: 'Cash' },
    { value: 'transfer', label: 'Transfer' },
    { value: 'card', label: 'Card' },
    { value: 'wallet', label: 'Wallet' },
    { value: 'paypal', label: 'Paypal' },
    { value: 'stripe', label: 'Stripe' },
    { value: 'cheque', label: 'Cheque' },
];

const totalPaid = computed(() => paymentLines.value.reduce((sum, line) => sum + Number(line.amount || 0), 0));
const outstandingBalance = computed(() => Math.max(0, Number(previewTotals.value.total || 0) - totalPaid.value));
const selectedCustomerDebt = computed(() => ({
    outstanding: Number(selectedCustomerRecord.value?.outstanding_receivable || 0),
    overdueCount: Number(selectedCustomerRecord.value?.overdue_invoice_count || 0),
}));

function createPaymentLine(method = 'cash', amount = 0) {
    return {
        id: `payment-${Math.random().toString(36).slice(2, 10)}`,
        method,
        amount,
        transaction_reference: '',
    };
}

function addPaymentLine() {
    paymentLines.value.push(createPaymentLine('transfer', 0));
}

function removePaymentLine(index) {
    if (paymentLines.value.length === 1) {
        return;
    }

    paymentLines.value.splice(index, 1);
}

// load initial data
async function loadCustomers(query = '') {
    loadingCustomers.value = true;

    try {
        const res = await axios.get(customerListUrl.value, {
            params: {
                q: query || undefined,
                limit: 10,
            },
        });
        customers.value = Array.isArray(res.data) ? res.data : customers.value;
    } catch (error) {
        if (error?.response?.status === 404) {
            const fallbackUrl = isAdminPosContext.value ? '/admin/customers/list' : '/sales/pos/customers';

            try {
                const fallbackResponse = await axios.get(fallbackUrl, {
                    params: {
                        q: query || undefined,
                        limit: 10,
                    },
                });

                customers.value = Array.isArray(fallbackResponse.data) ? fallbackResponse.data : customers.value;
                return;
            } catch (fallbackError) {
                console.error('Failed to load POS customers:', fallbackError);
                return;
            }
        }

        console.error('Failed to load POS customers:', error);
    } finally {
        loadingCustomers.value = false;
    }
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

const searchCustomers = debounce((query) => {
    loadCustomers(query);
}, 250);

function handleCustomerSearch(query) {
    searchCustomers(query);
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
        const res = await axios.post(customerStoreUrl.value, payload);
        const newCust = res.data;
        const customerRecord = {
            id: newCust.id,
            name: newCust.name,
            email: newCust.email,
            phone: newCust.phone,
        };

        customers.value = [
            customerRecord,
            ...customers.value.filter((customer) => customer.id !== customerRecord.id),
        ].slice(0, 10);
        selectedCustomerMeta.value = customerRecord;
        selectedCustomer.value = newCust.id;
        showCustomerModal.value = false;
        Object.keys(newCustomer.value).forEach(
            (k) => (newCustomer.value[k] = ''),
        );
    } catch (err) {
        if (err?.response?.status === 404) {
            try {
                const fallbackUrl = isAdminPosContext.value ? '/admin/customers/store' : '/sales/pos/customers';
                const res = await axios.post(fallbackUrl, payload);
                const newCust = res.data;
                const customerRecord = {
                    id: newCust.id,
                    name: newCust.name,
                    email: newCust.email,
                    phone: newCust.phone,
                };

                customers.value = [
                    customerRecord,
                    ...customers.value.filter((customer) => customer.id !== customerRecord.id),
                ].slice(0, 10);
                selectedCustomerMeta.value = customerRecord;
                selectedCustomer.value = newCust.id;
                showCustomerModal.value = false;
                Object.keys(newCustomer.value).forEach(
                    (k) => (newCustomer.value[k] = ''),
                );
                return;
            } catch (fallbackError) {
                console.error(fallbackError);
            }
        }

        alert('Failed to create customer.');
        console.error(err);
    }
}

// auto call /checkout/preview
const refreshPreview = debounce(async function () {
    if (cartItems.value.length === 0) {
        checkout_token.value = '';
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

watch(selectedCustomer, () => {
    checkoutError.value = '';
    if (!selectedCustomer.value) {
        selectedCustomerMeta.value = null;
    } else if (selectedCustomerRecord.value) {
        selectedCustomerMeta.value = selectedCustomerRecord.value;
    }

    refreshPreview();
});

watch(
    () => previewTotals.value.total,
    (value) => {
        if (paymentMode.value === 'full' && paymentLines.value.length === 1) {
            paymentLines.value[0].amount = Number(value || 0);
        }
    },
    { immediate: true },
);

watch(paymentMode, (value) => {
    checkoutError.value = '';
    if (value === 'full' && paymentLines.value.length === 1) {
        paymentLines.value[0].amount = Number(previewTotals.value.total || 0);
    }
});



// place order — include shipping info & items; useCart.placeOrder should accept this payload on backend
async function onPlaceOrder() {
    if (isPlacingOrder.value) {
        return;
    }

    checkoutError.value = '';

    if (paymentLines.value.length === 0 || totalPaid.value <= 0) {
        checkoutError.value = 'Add at least one payment line before placing the sale.';
        return;
    }

    if (totalPaid.value > Number(previewTotals.value.total || 0) + 0.01) {
        checkoutError.value = 'Total paid cannot exceed the order total.';
        return;
    }

    if (paymentMode.value === 'full' && outstandingBalance.value > 0.01) {
        checkoutError.value = 'Switch to Partial / credit sale or make sure full payment covers the order total.';
        return;
    }

    if (outstandingBalance.value > 0.01) {
        const isWalkIn = !selectedCustomer.value;
        if (isWalkIn) {
            checkoutError.value = 'Select a saved customer before creating a credit sale.';
            return;
        }

        if (!dueDate.value) {
            checkoutError.value = 'Set a due date for the outstanding balance.';
            return;
        }

        if (!repaymentTerms.value.trim()) {
            checkoutError.value = 'Add repayment terms for the outstanding balance.';
            return;
        }
    }

    const payload = {
        customer_id: selectedCustomer.value || null,
        items: cartItems.value.map(i => ({ variant_id: i.variant_id, quantity: i.quantity, price: i.price })),
        subtotal: previewTotals.value.subtotal,
        total: previewTotals.value.total,
        payment_mode: outstandingBalance.value > 0.01 ? 'partial' : paymentMode.value,
        payment_method: paymentLines.value[0]?.method || 'cash',
        payment_lines: paymentLines.value.map((line) => ({
            method: line.method,
            amount: Number(line.amount || 0),
            transaction_reference: line.transaction_reference || null,
        })).filter((line) => line.amount > 0),
        due_date: dueDate.value || null,
        repayment_terms: repaymentTerms.value || null,
        shipping: shippingInfo.value || null,
        coupon: couponCode.value || null,
        checkout_token: checkout_token.value,
    };

    try {
        const result = await placeOrder(payload);
        showCheckoutModal.value = false;
        eventBus.emit('order-placed', {
            items: payload.items,
            stock_updates: result?.data?.stock_updates || [],
        });
        eventBus.emit('toast', {
            type: 'success',
            message: result?.message || 'Sale placed successfully.',
        });
        shippingInfo.value = null;
        couponCode.value = '';
        paymentMode.value = 'full';
        paymentLines.value = [createPaymentLine('cash', 0)];
        dueDate.value = '';
        repaymentTerms.value = '';
        checkout_token.value = '';
        checkoutError.value = '';
        previewTotals.value = { subtotal: 0, shipping_total: 0, discount: 0, discount_label: null, total: 0 };
    } catch (err) {
        console.error('Order placement failed', err);
        showCheckoutModal.value = false;
        checkoutError.value = '';
        eventBus.emit('toast', {
            type: 'error',
            message: err?.message || 'There was an error placing your order.',
        });
    }
}


function clearCartAndShipping() {
    clearCart();
    showCheckoutModal.value = false;
    shippingInfo.value = null;
    couponCode.value = '';
    paymentMode.value = 'full';
    paymentLines.value = [createPaymentLine('cash', 0)];
    dueDate.value = '';
    repaymentTerms.value = '';
    checkout_token.value = '';
    checkoutError.value = '';
    previewTotals.value = { subtotal: 0, shipping_total: 0, discount: 0, discount_label: null, total: 0 };
}

// Expose increment/decrement/update/remove already provided by useCart
</script>
