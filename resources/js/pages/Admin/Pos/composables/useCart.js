// useCart.js
import { computed, ref } from 'vue'
import { route } from 'ziggy-js'
import axios from 'axios'
import { eventBus } from '@/eventBus.js';

// module-level shared cart state (singleton)
const cartItems = ref([])

export function useCart() {
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
                price: Number(variant.sale_price ?? variant.regular_price ?? 0),
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

    const subtotal = computed(() => cartItems.value.reduce((sum, i) => sum + i.price * i.quantity, 0))

    const placeOrder = async (payload = {}) => {
        if (cartItems.value.length === 0) {
            alert('Please add at least one product.')
            return
        }

        try {
            const response = await axios.post(route('admin.pos.placeOrder'), {
                customer_id: payload.customer_id ?? null,
                items: cartItems.value.map((i) => ({ variant_id: i.variant_id, quantity: i.quantity, price: i.price })),
                total: payload.total,
                subtotal: payload.subtotal,
                shipping: payload.shipping
            })

            if (response.data?.success) {
                alert(response.data.message || 'Order placed.')
                clearCart()

                eventBus.emit('order-placed');
            } else {
                alert('Something went wrong: ' + (response.data?.message ?? 'Unknown'))
                throw new Error(response.data?.message ?? 'Unknown error')

            }
        } catch (err) {
            console.error('Failed to place order:', err)
            alert(err.response?.data?.message || 'Failed to place order.')
            throw err // 🚀 rethrow so parent knows it failed
        }
    }

    return {
        cartItems,
        subtotal,
        addToCart,
        increment,
        decrement,
        removeItem,
        clearCart,
        updateItem,
        placeOrder,
    }
}
