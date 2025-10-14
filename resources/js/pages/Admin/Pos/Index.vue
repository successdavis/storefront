<template>
    <div
        class="flex h-screen bg-gray-50 text-gray-900 transition-colors duration-200 dark:bg-gray-900 dark:text-gray-100"
    >
        <!-- Left: Product section -->
        <div class="flex flex-col flex-1 relative">
            <!-- Fixed top controls (full width of product section) -->
            <div
                class="z-10 bg-white p-4 shadow-md dark:bg-gray-800"
            >
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
                        <option v-for="c in categories" :key="c.id" :value="c.id">
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
                <div class="lg:grid grid-cols-4 3xl:grid-cols-5 gap-4">
                    <div
                        v-for="variant in variants.data"
                        :key="variant.id"
                        class="flex flex-col rounded bg-white shadow transition hover:shadow-md dark:bg-gray-800"
                    >
                        <div class="relative">
                            <div class="h-50 overflow-clip">
                                <img
                                    :src="'/storage/' + (variant.product?.images?.[0]?.path || placeholder)"
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
                            <div class="text-sm font-medium truncate">
                                {{ variant.product?.name || variant.sku }}
                            </div>

                            <div class="mt-2 text-sm font-semibold text-blue-600 dark:text-blue-400">
                                ${{ price(variant) }}
                            </div>

                            <div class="text-xs text-gray-500 dark:text-gray-400 truncate">
                                {{ variant.values?.map((v) => v.value).join(', ') }}
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
        <aside
            class="w-[26rem] flex flex-col bg-white p-4 shadow-lg dark:bg-gray-800 h-screen sticky top-0"
        >
            <div class="mb-3 flex items-center justify-between">
                <div class="text-lg font-semibold">Cart</div>
                <select
                    v-model="selectedCustomer"
                    class="rounded border border-gray-300 bg-white px-2 py-1 text-sm dark:border-gray-700 dark:bg-gray-700 dark:text-gray-100"
                >
                    <option value="">Walk In Customer</option>
                </select>
            </div>

            <!-- Scrollable cart items -->
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
                    <img
                        :src="item.image || placeholder"
                        class="h-14 w-14 rounded object-cover"
                    />
                    <div class="flex-1">
                        <div class="font-medium truncate">{{ item.name }}</div>
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
                        <div class="font-semibold text-blue-600 dark:text-blue-400">
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
            <div class="mt-4 border-t border-gray-200 pt-4 dark:border-gray-700">
                <div class="flex justify-between text-sm">
                    <div>Sub Total</div>
                    <div>${{ subtotal.toFixed(2) }}</div>
                </div>
                <div class="mt-1 flex justify-between text-sm text-gray-500 dark:text-gray-400">
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
import { computed, ref, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import axios from 'axios';

const { props } = usePage()

// Props
const variants = computed(() => props?.variants ?? { data: [], next_page_url: null, current_page: 1 })
const categories = computed(() => props?.categories ?? [])
const brands = computed(() => props?.brands ?? [])
const filters = ref({ ...(props?.filters ?? {}) })

// Local-only cart for POS staff
const cartItems = ref([])

const placeholder = '/images/placeholder.png'
const selectedCustomer = ref(null)


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
    )
}

function debounce(fn, delay = 500) {
    let timeout
    return (...args) => {
        clearTimeout(timeout)
        timeout = setTimeout(() => fn(...args), delay)
    }
}
const debouncedReload = debounce(reload, 500)

watch(() => filters.value.q, () => debouncedReload())

// === Product helpers ===
function price(variant) {
    const p = variant.sale_price ?? variant.regular_price ?? 0
    return Number(p).toFixed(2)
}

function stockLabel(variant) {
    const avail = variant.available ?? variant.quantity ?? 0
    return avail > 0 ? `In stock : ${avail}` : 'Out of Stock'
}

function availableClass(variant) {
    const avail = variant.available ?? variant.quantity ?? 0
    return avail > 0
        ? 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300'
        : 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300'
}


// === Cart management (local only) ===
function addToCart(variantId) {
    const variant = variants.value.data.find(v => v.id === variantId)
    if (!variant) return

    const existing = cartItems.value.find(i => i.variant_id === variant.id)
    if (existing) {
        existing.quantity += 1
    } else {
        cartItems.value.push({
            variant_id: variant.id,
            name: variant.product?.name || variant.sku,
            price: Number(variant.sale_price ?? variant.regular_price ?? 0),
            quantity: 1,
            image: '/storage/' + (variant.product?.images?.[0]?.path || placeholder),
            variant_label: variant.values?.map(v => v.value).join(', ') ?? '',
        })
    }
}

function increment(item) {
    item.quantity++
}

function decrement(item) {
    if (item.quantity > 1) item.quantity--
    else removeItem(item)
}

function removeItem(item) {
    cartItems.value = cartItems.value.filter(i => i.variant_id !== item.variant_id)
}

function clearCart() {
    cartItems.value = []
}


// === Totals ===
const subtotal = computed(() =>
    cartItems.value.reduce((s, i) => s + i.price * i.quantity, 0),
)


// === Place Order ===
async function placeOrder() {
    if (cartItems.value.length === 0) {
        alert('Please add at least one product.')
        return
    }

    try {
        const response = await axios.post(route('admin.pos.placeOrder'), {
            customer_id: selectedCustomer.value,
            items: cartItems.value.map(i => ({
                variant_id: i.variant_id,
                quantity: i.quantity,
                price: i.price,
            })),
            total: subtotal.value,
        })

        if (response.data.success) {
            alert(response.data.message)
            clearCart()
        } else {
            alert('Something went wrong: ' + response.data.message)
        }
    } catch (err) {
        console.error('Failed to place order:', err)
        alert(err.response?.data?.message || 'Failed to place order.')
    }
}


</script>


<style scoped>
/* keep small utility tweaks if needed */

body {
    position: fixed;
}
</style>
