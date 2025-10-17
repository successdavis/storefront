<template>
    <div
        class="flex h-screen bg-gray-50 text-gray-900 transition-colors duration-200 dark:bg-gray-900 dark:text-gray-100"
    >
        <!-- Left: Product section -->
        <div class="relative flex flex-1 flex-col">
            <!-- Fixed top controls (full width of product section) -->
            <div class=" bg-white p-4 shadow-md dark:bg-gray-800">
                <div class="flex items-center gap-3">
                    <div class="flex-1">
                        <input
                            v-model="filters.q"
                            placeholder="Search by Product Name/Barcode"
                            class="w-full rounded border border-gray-300 bg-white px-3 py-2 text-gray-800 placeholder-gray-400 focus:ring-2 focus:ring-blue-500 focus:outline-none dark:border-gray-700 dark:bg-gray-700 dark:text-gray-100 dark:placeholder-gray-500"
                        />
                    </div>

                    <select
                        v-model="filters.category_id"
                        @change="reload"
                        class="rounded border border-gray-300 bg-white px-3 py-2 text-gray-800 focus:outline-none dark:border-gray-700 dark:bg-gray-700 dark:text-gray-100"
                    >
                        <option :value="null">All Categories</option>
                        <option
                            v-for="c in categories"
                            :key="c.id"
                            :value="c.id"
                        >
                            {{ c.name }}
                        </option>
                    </select>

                    <select
                        v-model="filters.brand_id"
                        @change="reload"
                        class="rounded border border-gray-300 bg-white px-3 py-2 text-gray-800 focus:outline-none dark:border-gray-700 dark:bg-gray-700 dark:text-gray-100"
                    >
                        <option :value="null">All Brands</option>
                        <option v-for="b in brands" :key="b.id" :value="b.id">
                            {{ b.name }}
                        </option>
                    </select>
                </div>
            </div>

            <!-- Scrollable product grid -->
            <div class="flex-1 overflow-y-auto p-6 pt-4">
                <div class="3xl:grid-cols-5 grid-cols-4 gap-4 lg:grid">
                    <div
                        v-for="variant in variants.data"
                        :key="variant.id"
                        class="flex flex-col rounded bg-white shadow transition hover:shadow-md dark:bg-gray-800"
                    >
                        <div class="relative">
                            <div class="h-50 overflow-clip">
                                <img
                                    :src="
                                        '/storage/' +
                                        (variant.product?.images?.[0]?.path ||
                                            placeholder)
                                    "
                                    class="w-full rounded object-contain transition-transform duration-500 hover:scale-110"
                                    alt="product"
                                />
                            </div>
                            <div class="absolute top-1 left-1 p-1 opacity-85">
                                <span
                                    class="rounded bg-green-100 px-2 py-1 text-xs font-medium text-green-800 dark:bg-green-900 dark:text-green-200"
                                    :class="availableClass(variant)"
                                >
                                    {{ stockLabel(variant) }}
                                </span>
                            </div>
                        </div>

                        <div class="mt-2 flex-1 px-3">
                            <div class="truncate text-sm font-medium">
                                {{ variant.product?.name || variant.sku }}
                            </div>

                            <div
                                class="mt-2 text-sm font-semibold text-blue-600 dark:text-blue-400"
                            >
                                ${{ price(variant) }}
                            </div>

                            <div
                                class="truncate text-xs text-gray-500 dark:text-gray-400"
                            >
                                {{
                                    variant.values
                                        ?.map((v) => v.value)
                                        .join(', ')
                                }}
                            </div>
                        </div>

                        <div class="mt-1 flex items-center gap-2 px-3 py-1">
                            <button
                                @click="addToCart(variant.id)"
                                class="flex-1 rounded border border-gray-300 py-2 text-sm transition hover:bg-gray-100 dark:border-gray-700 dark:hover:bg-gray-700"
                            >
                                Add
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Pagination -->
                <div class="mt-6 text-center">
                    <button
                        v-if="variants.next_page_url"
                        @click="loadMore"
                        class="rounded bg-blue-100 px-4 py-2 text-blue-700 transition hover:opacity-90 dark:bg-blue-800 dark:text-blue-200"
                    >
                        Load More
                    </button>
                </div>
            </div>
        </div>

        <!-- Right: Cart section -->
        <!-- Right: Cart section -->
        <aside
            class="sticky top-0 flex h-screen w-[26rem] flex-col bg-white p-4 shadow-lg dark:bg-gray-800"
        >
            <div class="mb-3 gap-3 flex items-center justify-between">
                <select
                    v-model="selectedCustomer"
                    @change="handleCustomerChange"
                    class="w-full rounded border border-gray-300 bg-white px-2 py-3 text-sm dark:border-gray-700 dark:bg-gray-700 dark:text-gray-100"
                >
                    <option value="">Walk In Customer</option>
                    <option value="__add_new__">➕ Add New Customer</option>
                    <option v-for="c in customers" :key="c.id" :value="c.id">
                        {{ c.name }}
                    </option>
                </select>
<!--                Shipping info Button -->
                <span class="cursor-pointer py-2 px-2 border-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-car-icon lucide-car"><path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 0 0 2 12v4c0 .6.4 1 1 1h2"/><circle cx="7" cy="17" r="2"/><path d="M9 17h6"/><circle cx="17" cy="17" r="2"/></svg>
                </span>
            </div>

            <!-- Modal: Add New Customer -->
            <div
                v-if="showCustomerModal"
                class=" fixed inset-0 z-40 flex items-center justify-center"
            >
                <div class="w-full z-30 opacity-50 h-screen fixed bg-black"></div>
                <div
                    class="max-h-[90vh] z-50 w-[600px] overflow-y-auto rounded-lg bg-white p-6 shadow-lg dark:bg-gray-800"
                >
                    <h2 class="mb-4 text-lg font-bold">Add New Customer</h2>

                    <form @submit.prevent="submitNewCustomer">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="mb-1 block text-sm"
                                >Full Name</label
                                >
                                <input
                                    v-model="newCustomer.name"
                                    required
                                    class="w-full rounded border border-gray-300 px-3 py-2"
                                />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm">Email</label>
                                <input
                                    v-model="newCustomer.email"
                                    type="email"
                                    class="w-full rounded border border-gray-300 px-3 py-2"
                                />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm">Phone</label>
                                <input
                                    v-model="newCustomer.phone"
                                    required
                                    class="w-full rounded border border-gray-300 px-3 py-2"
                                />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm"
                                >Country</label
                                >
                                <select
                                    v-model="newCustomer.country_id"
                                    @change="loadStates"
                                    required
                                    class="w-full rounded border border-gray-300 px-3 py-2"
                                >
                                    <option value="">Select Country</option>
                                    <option
                                        v-for="c in countries"
                                        :key="c.id"
                                        :value="c.id"
                                    >
                                        {{ c.name }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm">State</label>
                                <select
                                    v-model="newCustomer.state_id"
                                    @change="loadLgas"
                                    required
                                    class="w-full rounded border border-gray-300 px-3 py-2"
                                >
                                    <option value="">Select State</option>
                                    <option
                                        v-for="s in states"
                                        :key="s.id"
                                        :value="s.id"
                                    >
                                        {{ s.name }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm">LGA</label>
                                <select
                                    v-model="newCustomer.lga_id"
                                    @change="loadCities"
                                    required
                                    class="w-full rounded border border-gray-300 px-3 py-2"
                                >
                                    <option value="">Select LGA</option>
                                    <option
                                        v-for="l in lgas"
                                        :key="l.id"
                                        :value="l.id"
                                    >
                                        {{ l.name }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm">Gender</label>
                                <select
                                    v-model="newCustomer.gender"
                                    class="w-full rounded border border-gray-300 px-3 py-2"
                                >
                                    <option value="">Select Gender</option>
                                    <option value="Male">Male</option>
                                    <option value="Male">Female</option>
                                </select>
                            </div>
                            <div class="col-span-2">
                                <label class="mb-1 block text-sm"
                                >Shipping Address</label
                                >
                                <textarea
                                    v-model="newCustomer.address"
                                    class="w-full rounded border border-gray-300 px-3 py-2"
                                ></textarea>
                            </div>
                        </div>

                        <div class="mt-5 flex justify-end gap-2">
                            <button
                                type="button"
                                @click="showCustomerModal = false"
                                class="rounded border border-gray-300 px-4 py-2 hover:bg-gray-100"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                class="rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700"
                            >
                                Save Customer
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Scrollable cart items -->
            <div class="flex-1 z-50 overflow-y-auto pr-2">
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
                    <img
                        :src="item.image || placeholder"
                        class="h-14 w-14 rounded object-cover"
                    />
                    <div class="flex-1">
                        <div class="truncate font-medium">{{ item.name }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            {{ item.variant_label }}
                        </div>
                        <div class="mt-2 flex items-center gap-2">
                            <button
                                @click="decrement(item)"
                                class="rounded border border-gray-300 px-2 py-1 transition hover:bg-gray-100 dark:border-gray-600 dark:hover:bg-gray-700"
                            >
                                -
                            </button>
                            <input
                                type="number"
                                v-model.number="item.quantity"
                                @change="updateItem(item)"
                                class="w-16 rounded border border-gray-300 bg-white px-2 py-1 text-center text-gray-800 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
                            />
                            <button
                                @click="increment(item)"
                                class="rounded border border-gray-300 px-2 py-1 transition hover:bg-gray-100 dark:border-gray-600 dark:hover:bg-gray-700"
                            >
                                +
                            </button>
                        </div>
                    </div>
                    <div class="text-right">
                        <div
                            class="font-semibold text-blue-600 dark:text-blue-400"
                        >
                            ${{ (item.price * item.quantity).toFixed(2) }}
                        </div>
                        <button
                            @click="removeItem(item)"
                            class="mt-2 text-xs text-red-500 transition hover:text-red-400"
                        >
                            Remove
                        </button>
                    </div>
                </div>
            </div>

            <!-- Cart totals -->
            <div
                class="mt-4 border-t border-gray-200 pt-4 dark:border-gray-700"
            >
                <div class="flex justify-between text-sm">
                    <div>Sub Total</div>
                    <div>${{ subtotal.toFixed(2) }}</div>
                </div>
                <div
                    class="mt-1 flex justify-between text-sm text-gray-500 dark:text-gray-400"
                >
                    <div>Tax</div>
                    <div>$0.00</div>
                </div>
                <div class="mt-3 flex justify-between text-lg font-bold">
                    <div>Total</div>
                    <div>${{ subtotal.toFixed(2) }}</div>
                </div>

                <div class="mt-4 flex gap-2">
                    <button
                        @click="placeOrder"
                        class="flex-1 rounded bg-blue-600 py-2 text-white transition hover:bg-blue-700"
                    >
                        Place Order
                    </button>
                    <button
                        @click="clearCart"
                        class="rounded border border-gray-300 px-4 py-2 transition hover:bg-gray-100 dark:border-gray-600 dark:hover:bg-gray-700"
                    >
                        Clear
                    </button>
                </div>
            </div>
        </aside>
    </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import axios from 'axios';

const { props } = usePage();

// Props
const variants = computed(
    () => props?.variants ?? { data: [], next_page_url: null, current_page: 1 },
);
const categories = computed(() => props?.categories ?? []);
const brands = computed(() => props?.brands ?? []);
const filters = ref({ ...(props?.filters ?? {}) });

// === Customers ===
const customers = ref([]);
const selectedCustomer = ref('');
const showCustomerModal = ref(false);
const newCustomer = ref({
    name: '',
    email: '',
    phone: '',
    country_id: '',
    state_id: '',
    lga_id: '',
    address: '',
    gender: ''
});

// === Location lists ===
const countries = ref([]);
const states = ref([]);
const lgas = ref([]);
const cities = ref([]);

// Local-only cart for POS staff
const cartItems = ref([]);

const placeholder = '/images/placeholder.png';
// const selectedCustomer = ref(null)

// === Load initial data ===
onMounted(async () => {
    await loadCustomers();
    await loadCountries();
});

async function loadCustomers() {
    const res = await axios.get(route('admin.customers.list'));
    customers.value = res.data;
}

async function loadCountries() {
    const res = await axios.get(route('locations.countries'));
    countries.value = res.data;
}
async function loadStates() {
    if (!newCustomer.value.country_id) return;
    const res = await axios.get(
        route('locations.states', newCustomer.value.country_id),
    );
    states.value = res.data;
}
async function loadLgas() {
    if (!newCustomer.value.state_id) return;
    const res = await axios.get(
        route('locations.lgas', newCustomer.value.state_id),
    );
    lgas.value = res.data;
}
async function loadCities() {
    if (!newCustomer.value.lga_id) return;
    const res = await axios.get(
        route('admin.locations.cities', newCustomer.value.lga_id),
    );
    cities.value = res.data;
}

function handleCustomerChange() {
    if (selectedCustomer.value === '__add_new__') {
        showCustomerModal.value = true;
        selectedCustomer.value = '';
    }
}

// === Save new customer ===
async function submitNewCustomer() {
    try {
        const res = await axios.post(
            route('admin.customers.store'),
            newCustomer.value,
        );
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

// === Filtering & reload ===
function reload() {
    router.get(
        route('admin.pos.index'),
        {
            q: filters.value.q,
            category_id: filters.value.category_id,
            brand_id: filters.value.brand_id,
        },
        { preserveState: false, replace: true },
    );
}

function debounce(fn, delay = 500) {
    let timeout;
    return (...args) => {
        clearTimeout(timeout);
        timeout = setTimeout(() => fn(...args), delay);
    };
}
const debouncedReload = debounce(reload, 500);

watch(
    () => filters.value.q,
    () => debouncedReload(),
);

// === Product helpers ===
function price(variant) {
    const p = variant.sale_price ?? variant.regular_price ?? 0;
    return Number(p).toFixed(2);
}

function stockLabel(variant) {
    const avail = variant.available ?? variant.quantity ?? 0;
    return avail > 0 ? `In stock : ${avail}` : 'Out of Stock';
}

function availableClass(variant) {
    const avail = variant.available ?? variant.quantity ?? 0;
    return avail > 0
        ? 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300'
        : 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300';
}

// === Cart management (local only) ===
function addToCart(variantId) {
    const variant = variants.value.data.find((v) => v.id === variantId);
    if (!variant) return;

    const existing = cartItems.value.find((i) => i.variant_id === variant.id);
    if (existing) {
        existing.quantity += 1;
    } else {
        cartItems.value.push({
            variant_id: variant.id,
            name: variant.product?.name || variant.sku,
            price: Number(variant.sale_price ?? variant.regular_price ?? 0),
            quantity: 1,
            image:
                '/storage/' +
                (variant.product?.images?.[0]?.path || placeholder),
            variant_label: variant.values?.map((v) => v.value).join(', ') ?? '',
        });
    }
}

function increment(item) {
    item.quantity++;
}

function decrement(item) {
    if (item.quantity > 1) item.quantity--;
    else removeItem(item);
}

function removeItem(item) {
    cartItems.value = cartItems.value.filter(
        (i) => i.variant_id !== item.variant_id,
    );
}

function clearCart() {
    cartItems.value = [];
}

// === Totals ===
const subtotal = computed(() =>
    cartItems.value.reduce((s, i) => s + i.price * i.quantity, 0),
);

// === Place Order ===
async function placeOrder() {
    if (cartItems.value.length === 0) {
        alert('Please add at least one product.');
        return;
    }

    try {
        const response = await axios.post(route('admin.pos.placeOrder'), {
            customer_id: selectedCustomer.value,
            items: cartItems.value.map((i) => ({
                variant_id: i.variant_id,
                quantity: i.quantity,
                price: i.price,
            })),
            total: subtotal.value,
        });

        if (response.data.success) {
            alert(response.data.message);
            clearCart();
        } else {
            alert('Something went wrong: ' + response.data.message);
        }
    } catch (err) {
        console.error('Failed to place order:', err);
        alert(err.response?.data?.message || 'Failed to place order.');
    }
}
</script>

<style scoped>
/* keep small utility tweaks if needed */

body {
    position: fixed;
}
</style>
