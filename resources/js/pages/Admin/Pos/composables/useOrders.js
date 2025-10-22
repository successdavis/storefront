// composables/useOrders.js
import { ref } from 'vue'
import axios from 'axios'
import { route } from 'ziggy-js'

export function useOrders() {
    const orders = ref([])
    const meta = ref(null) // expects Laravel pagination meta: current_page, last_page, per_page etc.
    const loading = ref(false)
    const loadingMore = ref(false)
    const error = ref(null)
    let controller = null

    async function fetchOrders({ page = 1, per_page = 20, force = false } = {}) {
        // if we already have first page and not forced, reuse
        if (!force && page === 1 && orders.value.length && meta.value?.current_page === 1) return

        if (controller) {
            try { controller.abort() } catch (e) {}
            controller = null
        }
        controller = new AbortController()
        loading.value = true
        error.value = null

        try {
            const res = await axios.get(route('admin.pos.orders'), {
                params: { page, per_page },
                signal: controller.signal,
            })

            const payload = res.data
            // Standardize expected shape - prefer data + meta
            const list = payload.data ?? payload.orders ?? []
            const pagination = payload.meta ?? payload.pagination ?? payload.meta?.pagination ?? null

            if (page === 1) {
                orders.value = list
            } else {
                orders.value = orders.value.concat(list)
            }

            meta.value = pagination ?? {
                current_page: payload.current_page ?? page,
                last_page: payload.last_page ?? page,
            }
        } catch (err) {
            if (err.name === 'CanceledError' || axios.isCancel?.(err)) {
                // aborted; ignore
                return
            }
            console.error('useOrders fetch failed', err)
            error.value = err
        } finally {
            loading.value = false
            controller = null
        }
    }

    async function loadMore() {
        if (!meta.value || meta.value.current_page >= meta.value.last_page) return
        loadingMore.value = true
        try {
            const next = meta.value.current_page + 1
            const res = await axios.get(route('admin.pos.orders'), { params: { page: next } })
            const payload = res.data
            const list = payload.data ?? payload.orders ?? []
            const pagination = payload.meta ?? payload.pagination ?? payload.meta?.pagination ?? null
            orders.value = orders.value.concat(list)
            meta.value = pagination ?? { ...meta.value, current_page: next }
        } catch (err) {
            console.error('useOrders loadMore failed', err)
        } finally {
            loadingMore.value = false
        }
    }

    return { orders, meta, loading, loadingMore, error, fetchOrders, loadMore }
}
