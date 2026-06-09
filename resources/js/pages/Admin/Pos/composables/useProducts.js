import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { eventBus } from '@/eventBus.js';

export function useProducts() {
    const page = usePage()
    const variants = ref(page.props.variants ?? { data: [] })
    const categories = computed(() => page.props.categories ?? [])
    const brands = computed(() => page.props.brands ?? [])
    const filters = ref({ ...(page.props.filters ?? {}) })

    watch(
        () => page.props.variants,
        (nextVariants) => {
            variants.value = nextVariants ?? { data: [] }
        },
        { deep: true },
    )

    watch(
        () => page.props.filters,
        (nextFilters) => {
            filters.value = { ...(nextFilters ?? {}) }
        },
        { deep: true },
    )

    const reload = () => {
        router.get(page.props.pos_routes.index, filters.value, {
            preserveState: true,
            replace: true,
        })
    }

    const applyStockUpdates = (updates = []) => {
        const updatesByVariant = new Map(
            (Array.isArray(updates) ? updates : [])
                .map((update) => [Number(update.variant_id || 0), update])
                .filter(([variantId]) => variantId > 0),
        )

        if (updatesByVariant.size === 0 || !Array.isArray(variants.value?.data)) {
            return
        }

        variants.value = {
            ...variants.value,
            data: variants.value.data.map((variant) => {
                const update = updatesByVariant.get(Number(variant.id))

                if (!update) {
                    return variant
                }

                return {
                    ...variant,
                    quantity: Number(update.quantity ?? variant.quantity ?? 0),
                    reserved: Number(update.reserved ?? variant.reserved ?? 0),
                    available: update.available === null || update.available === undefined
                        ? update.available
                        : Number(update.available),
                    is_sellable: Boolean(update.is_sellable),
                }
            }),
        }
    }

    const applySoldItemsFallback = (items = []) => {
        const soldByVariant = new Map()

        ;(Array.isArray(items) ? items : []).forEach((item) => {
            const variantId = Number(item.variant_id || 0)
            const quantity = Number(item.quantity || 0)

            if (variantId <= 0 || quantity <= 0) {
                return
            }

            soldByVariant.set(variantId, Number(soldByVariant.get(variantId) || 0) + quantity)
        })

        if (soldByVariant.size === 0 || !Array.isArray(variants.value?.data)) {
            return
        }

        variants.value = {
            ...variants.value,
            data: variants.value.data.map((variant) => {
                const sold = soldByVariant.get(Number(variant.id))

                if (!sold || variant.is_dropshipping) {
                    return variant
                }

                const quantity = Math.max(0, Number(variant.quantity ?? 0) - sold)
                const available = Math.max(0, Number(variant.available ?? variant.quantity ?? 0) - sold)

                return {
                    ...variant,
                    quantity,
                    available,
                    is_sellable: available > 0,
                }
            }),
        }
    }

    const handleOrderPlaced = (payload = {}) => {
        if (Array.isArray(payload.stock_updates) && payload.stock_updates.length > 0) {
            applyStockUpdates(payload.stock_updates)
        } else {
            applySoldItemsFallback(payload.items)
        }

        // Re-fetch product list to keep reserved/dropshipping state in sync.
        reload();
    }

    eventBus.on('order-placed', handleOrderPlaced);
    onBeforeUnmount(() => eventBus.off('order-placed', handleOrderPlaced))

    const debounce = (fn, delay = 500) => {
        let timeout
        return (...args) => {
            clearTimeout(timeout)
            timeout = setTimeout(() => fn(...args), delay)
        }
    }

    const debouncedReload = debounce(reload, 500)
    watch(() => filters.value.q, () => debouncedReload())

    const price = (variant) => Number(variant.regular_price ?? 0).toFixed(2)
    const stockLabel = (variant) => {
        if (variant.is_dropshipping) {
            return variant.is_sellable ? 'Dropshipping' : 'Supplier unavailable'
        }

        const avail = variant.available ?? variant.quantity ?? 0
        return avail > 0 ? `In stock: ${avail}` : 'Out of Stock'
    }
    const availableClass = (variant) => {
        if (variant.is_dropshipping) {
            return variant.is_sellable
                ? 'bg-sky-100 text-sky-700'
                : 'bg-red-100 text-red-700'
        }

        const avail = variant.available ?? variant.quantity ?? 0
        return avail > 0
            ? 'bg-green-100 text-green-700'
            : 'bg-red-100 text-red-700'
    }

    const imageUrl = (variant) => '/storage/' + (variant.product?.images?.[0]?.path || 'placeholder.png')

    return { variants, filters, categories, brands, reload, price, stockLabel, availableClass, imageUrl }
}
