import { ref } from 'vue'
import axios from 'axios'
import { eventBus } from '@/eventBus.js'
import { router, usePage } from '@inertiajs/vue3';

// module-level shared cart state (singleton)
const cartItems = ref([])

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
            })
        }
    }

    const increment = (item) => (item.quantity += 1)
    const decrement = (item) => {
        if (item.quantity > 1) item.quantity -= 1
        else removeItem(item)
    }
    const removeItem = (item) => {
        cartItems.value = cartItems.value.filter((i) => i.variant_id !== item.variant_id)
    }
    const clearCart = () => (cartItems.value = [])

    const updateItem = (item) => {
        if (item.quantity <= 0) removeItem(item)
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

        return new Promise((resolve, reject) => {
            router.post(page.props.pos_routes.place_order, data, {
                preserveScroll: true,
                preserveState: false,
                onSuccess: () => {
                    clearCart()
                    resolve(true)
                },
                onError: (errors) => {
                    console.debug('POS onError', errors)
                    reject(errors)
                },
                onCancel: () => {
                    reject(new Error('POS order request was cancelled.'))
                },
            })
        })
    }


    return {
        cartItems,
        addToCart,
        increment,
        decrement,
        removeItem,
        clearCart,
        updateItem,
        placeOrder,
    }
}
