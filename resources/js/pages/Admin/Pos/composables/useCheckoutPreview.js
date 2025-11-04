import { ref, watch } from 'vue'
import axios from 'axios'
import { route } from 'ziggy-js'
import debounce from 'lodash/debounce'

export function useCheckoutPreview(cartItems, shippingInfo, couponCode, channel = 'pos') {
    const subtotal = ref(0)
    const shippingTotal = ref(0)
    const discountAmount = ref(0)
    const discountLabel = ref(null)
    const total = ref(0)
    const loading = ref(false)
    const error = ref(null)

    let controller = null

    const preview = debounce(async () => {
        if (controller) {
            try { controller.abort() } catch {}
        }
        controller = new AbortController()
        loading.value = true
        error.value = null

        try {
            const payload = {
                channel,
                coupon: couponCode.value || null,
                items: cartItems.value.map(i => ({
                    variant_id: i.variant_id,
                    quantity: i.quantity,
                })),
                shipping: shippingInfo.value || null,
            }

            const res = await axios.post(route('checkout.preview'), payload, { signal: controller.signal })
            const data = res?.data?.data ?? res?.data

            subtotal.value = Number(data.subtotal || 0)
            shippingTotal.value = Number(data.shipping_total || 0)
            discountAmount.value = Number(data.discount || 0)
            discountLabel.value = data.discount_label || null
            total.value = Number(data.total || subtotal.value + shippingTotal.value - discountAmount.value)
        } catch (e) {
            if (e.name !== 'CanceledError') {
                error.value = e.response?.data?.message || 'Failed to calculate totals'
            }
        } finally {
            loading.value = false
            controller = null
        }
    }, 300)

    // Auto-run whenever cart, shipping, or coupon changes
    watch(
        () => [
            couponCode.value,
            shippingInfo.value?.shipping_method_id || null,
            shippingInfo.value?.shipping_zone_id || null,
            shippingInfo.value?.pickup_location_id || null,
            ...cartItems.value.map(i => `${i.variant_id}:${i.quantity}`)
        ],
        () => preview(),
        { deep: false }
    )

    return { subtotal, shippingTotal, discountAmount, discountLabel, total, loading, error, preview }
}
