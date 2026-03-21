import { computed, ref, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { eventBus } from '@/eventBus.js';

export function useProducts() {
    const { props } = usePage()
    const variants = computed(() => props.variants ?? { data: [] })
    const categories = computed(() => props.categories ?? [])
    const brands = computed(() => props.brands ?? [])
    const filters = ref({ ...(props.filters ?? {}) })

    const reload = () => {
        router.get(props.pos_routes.index, filters.value, {
            preserveState: false,
            replace: true,
        })
    }

    eventBus.on('order-placed', () => {
        // Re-fetch product list to get updated stock
        reload();
    });

    const debounce = (fn, delay = 500) => {
        let timeout
        return (...args) => {
            clearTimeout(timeout)
            timeout = setTimeout(() => fn(...args), delay)
        }
    }

    const debouncedReload = debounce(reload, 500)
    watch(() => filters.value.q, () => debouncedReload())

    const price = (variant) => Number(variant.sale_price ?? variant.regular_price ?? 0).toFixed(2)
    const stockLabel = (variant) => {
        const avail = variant.available ?? variant.quantity ?? 0
        return avail > 0 ? `In stock: ${avail}` : 'Out of Stock'
    }
    const availableClass = (variant) => {
        const avail = variant.available ?? variant.quantity ?? 0
        return avail > 0
            ? 'bg-green-100 text-green-700'
            : 'bg-red-100 text-red-700'
    }

    const imageUrl = (variant) => '/storage/' + (variant.product?.images?.[0]?.path || 'placeholder.png')

    return { variants, filters, categories, brands, reload, price, stockLabel, availableClass, imageUrl }
}
