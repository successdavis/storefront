import { computed, ref } from 'vue'
import { usePage } from '@inertiajs/vue3';
import axios from 'axios'

// module-level shared cart state (singleton)
const cartItems = ref([])
const isPlacingOrder = ref(false)
const STOCK_ERROR_MESSAGE = 'An item in your cart is out of stock please remove'

const subtotal = computed(() => cartItems.value.reduce((sum, item) => {
    return sum + (Number(item.price || 0) * Number(item.quantity || 0))
}, 0))

const normalizeStockDetails = (details = []) => {
    let value = details

    if (typeof value === 'string') {
        try {
            value = JSON.parse(value)
        } catch {
            value = []
        }
    }

    if (value && !Array.isArray(value) && typeof value === 'object') {
        value = [value]
    }

    return (Array.isArray(value) ? value : [])
        .map((detail) => ({
            variant_id: Number(detail.variant_id || 0),
            sku: detail.sku || null,
            requested: Number(detail.requested || 0),
            available: Math.max(0, Number(detail.available || 0)),
        }))
        .filter((detail) => detail.variant_id > 0)
}

const markOutOfStockItems = (details = []) => {
    const detailsByVariant = new Map(
        normalizeStockDetails(details).map((detail) => [detail.variant_id, detail]),
    )

    cartItems.value.forEach((item) => {
        const detail = detailsByVariant.get(Number(item.variant_id))

        if (!detail) {
            return
        }

        item.stock_status = {
            ...detail,
            is_available: false,
            message: 'Out of stock',
        }
    })
}

const clearStockWarningIfResolved = (item) => {
    if (!item?.stock_status) {
        return
    }

    const available = Number(item.stock_status.available || 0)
    if (available > 0 && Number(item.quantity || 0) <= available) {
        item.stock_status = null
    }
}

const firstErrorMessage = (errors = {}) => {
    const value = Object.values(errors || {}).find(Boolean)

    return errorMessage(value)
}

const errorMessage = (value) => {
    if (Array.isArray(value)) {
        return value[0]
    }

    return value
}

const responseData = (error) => {
    const data = error?.response?.data

    return data && typeof data === 'object' ? data : {}
}

const isStockErrorMessage = (message = '') => {
    return String(message || '').toLowerCase().includes('out of stock')
        || String(message || '').toLowerCase().includes('insufficient stock')
}

export function useCart() {
    const page = usePage()

    // Add variant object directly — simpler and reliable
    const addToCart = (variant) => {
        if (!variant || !variant.id) return
        const existing = cartItems.value.find((i) => i.variant_id === variant.id)
        if (existing) {
            existing.quantity += 1
        } else {
            cartItems.value.push({
                variant_id: variant.id,
                name: variant.product?.name || variant.sku || 'Unnamed',
                price: Number(variant.regular_price ?? 0),
                quantity: 1,
                image: '/storage/' + (variant.product?.images?.[0]?.path || 'images/placeholder.png'),
                variant_label: variant.values?.map((v) => v.value).join(', ') ?? '',
                stock_status: null,
            })
        }
    }

    const increment = (item) => (item.quantity += 1)
    const decrement = (item) => {
        if (item.quantity > 1) {
            item.quantity -= 1
            clearStockWarningIfResolved(item)
        } else {
            removeItem(item)
        }
    }
    const removeItem = (item) => {
        cartItems.value = cartItems.value.filter((i) => i.variant_id !== item.variant_id)
    }
    const clearCart = () => (cartItems.value = [])

    const updateItem = (item) => {
        if (item.quantity <= 0) removeItem(item)
        else clearStockWarningIfResolved(item)
    }

    /**
     * Place order with server
     * @param {Object} payload
     * {
     *   customer_id,
     *   items,
     *   subtotal,   // from preview
     *   total,      // from preview
     *   shipping,   // from modal or preview
     *   coupon      // applied coupon (optional)
     * }
     */
    const placeOrder = async (payload = {}) => {
        if (cartItems.value.length === 0) {
            // optional: locally show a toast or inline message
            // but you said global toast is already set up elsewhere
            return Promise.resolve()
        }

        if (isPlacingOrder.value) {
            return Promise.reject(new Error('Sale confirmation is already in progress.'))
        }

        isPlacingOrder.value = true

        const data = {
            customer_id: payload.customer_id ?? null,
            items: cartItems.value.map(i => ({
                variant_id: i.variant_id,
                quantity: i.quantity,
                price: i.price,
            })),
            subtotal: payload.subtotal,
            total: payload.total,
            payment_mode: payload.payment_mode ?? 'full',
            payment_method: payload.payment_method ?? null,
            payment_lines: payload.payment_lines ?? [],
            due_date: payload.due_date ?? null,
            repayment_terms: payload.repayment_terms ?? null,
            shipping: payload.shipping,
            coupon: payload.coupon ?? null,
            checkout_token: payload.checkout_token,
        }

        try {
            const response = await axios.post(page.props.pos_routes.place_order, data, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            })
            const result = response?.data || {}

            if (!result || typeof result !== 'object' || result.type !== 'success') {
                throw { response: { data: result } }
            }

            clearCart()

            return {
                message: result.message || 'Sale placed successfully.',
                data: result,
            }
        } catch (error) {
            const body = responseData(error)
            const errors = body.errors || {}
            const outOfStockItems = normalizeStockDetails(
                body.out_of_stock_items
                || body.pos_out_of_stock_items
                || errors.pos_out_of_stock_items
            )
            const stockMessage = body.message || errorMessage(errors.stock)

            if (
                body.type === 'stock'
                || body.status === 'stock_error'
                || outOfStockItems.length > 0
                || isStockErrorMessage(stockMessage)
            ) {
                markOutOfStockItems(outOfStockItems)

                throw {
                    type: 'stock',
                    message: STOCK_ERROR_MESSAGE,
                    items: outOfStockItems,
                }
            }

            throw {
                type: body.type || 'error',
                message: firstErrorMessage(errors) || body.message || 'There was an error placing your order.',
                errors,
            }
        } finally {
            isPlacingOrder.value = false
        }
    }


    return {
        cartItems,
        isPlacingOrder,
        subtotal,
        addToCart,
        increment,
        decrement,
        removeItem,
        clearCart,
        updateItem,
        placeOrder,
        markOutOfStockItems,
    }
}
