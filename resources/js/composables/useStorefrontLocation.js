import axios from 'axios'
import { router, usePage } from '@inertiajs/vue3'
import { computed, onMounted, ref } from 'vue'

const STATUS_KEY = 'storefront-browser-location-status'
const RELOAD_ONLY = [
    'browsingLocation',
    'product',
    'relatedProducts',
    'products',
    'featuredProducts',
    'latestProducts',
    'categoryPreviews',
    'results',
]

function readStatus() {
    if (typeof window === 'undefined') {
        return null
    }

    return window.sessionStorage.getItem(STATUS_KEY)
}

function writeStatus(status) {
    if (typeof window === 'undefined') {
        return
    }

    window.sessionStorage.setItem(STATUS_KEY, status)
}

export function useStorefrontLocation() {
    const page = usePage()
    const isStorefrontPage = computed(() => String(page.component || '').startsWith('Storefront/'))
    const browsingLocationSource = computed(() => String(page.props.browsingLocation?.source || ''))
    const status = ref(readStatus() || 'idle')
    const isResolving = computed(() => status.value === 'resolving')
    const hasReliableLocation = computed(() => {
        return ['browser', 'saved_address', 'order_history'].includes(browsingLocationSource.value)
    })
    const canRequestBrowserLocation = computed(() => {
        return typeof window !== 'undefined' && typeof navigator !== 'undefined' && !!navigator.geolocation
    })
    const showPromptBanner = computed(() => {
        if (!isStorefrontPage.value || hasReliableLocation.value || isResolving.value) {
            return false
        }

        return ['idle', 'prompt', 'denied', 'timeout', 'unavailable', 'failed', 'unsupported'].includes(status.value)
    })
    const canRetryBrowserLocation = computed(() => {
        return canRequestBrowserLocation.value && !['denied', 'unsupported'].includes(status.value)
    })
    const promptMessage = computed(() => {
        if (status.value === 'denied') {
            return 'Location access is blocked in your browser right now. Enable it for this site to see delivery estimates for your area before checkout.'
        }

        if (status.value === 'timeout') {
            return 'We could not confirm your location in time. Allow location access to see delivery estimates for your area.'
        }

        if (status.value === 'unavailable' || status.value === 'failed') {
            return 'We could not resolve your location automatically. Allow location access to see delivery estimates for your area.'
        }

        if (status.value === 'unsupported') {
            return 'This browser or site context cannot provide your location. If you are on a non-secure http page, switch to https to use location access. You can still see delivery timing after choosing a destination at checkout.'
        }

        return 'Allow location access to see delivery estimates for your area before checkout.'
    })

    async function reloadStorefrontData() {
        router.reload({
            only: RELOAD_ONLY,
            preserveScroll: true,
            preserveState: true,
        })
    }

    async function clearBrowserLocation() {
        try {
            await axios.delete(route('store.location.browser.clear'))
        } catch (error) {
            console.error('Failed clearing browser location', error)
        }
    }

    async function persistBrowserLocation(position) {
        try {
            const { data } = await axios.post(route('store.location.browser.store'), {
                latitude: position.coords.latitude,
                longitude: position.coords.longitude,
                accuracy: position.coords.accuracy ?? null,
            })

            status.value = data?.resolved ? 'resolved' : 'failed'
            writeStatus(status.value)
        } catch (error) {
            console.error('Failed storing browser location', error)
            status.value = 'failed'
            writeStatus(status.value)
        }

        await reloadStorefrontData()
    }

    async function handleGeolocationError(error) {
        status.value = error?.code === error?.PERMISSION_DENIED
            ? 'denied'
            : error?.code === error?.TIMEOUT
                ? 'timeout'
                : 'unavailable'

        writeStatus(status.value)

        if (browsingLocationSource.value === 'browser') {
            await clearBrowserLocation()
            await reloadStorefrontData()
        }
    }

    async function requestBrowserLocation() {
        if (!canRequestBrowserLocation.value || isResolving.value) {
            status.value = canRequestBrowserLocation.value ? status.value : 'unsupported'
            writeStatus(status.value)
            return
        }

        status.value = 'resolving'
        writeStatus(status.value)

        navigator.geolocation.getCurrentPosition(
            (position) => {
                void persistBrowserLocation(position)
            },
            (error) => {
                void handleGeolocationError(error)
            },
            {
                enableHighAccuracy: false,
                timeout: 8000,
                maximumAge: 15 * 60 * 1000,
            },
        )
    }

    onMounted(async () => {
        if (!isStorefrontPage.value) {
            return
        }

        if (hasReliableLocation.value) {
            status.value = browsingLocationSource.value === 'browser' ? 'resolved' : 'fallback'
            writeStatus(status.value)
            return
        }

        if (!canRequestBrowserLocation.value) {
            status.value = 'unsupported'
            writeStatus(status.value)
            return
        }

        if (typeof navigator.permissions?.query === 'function') {
            try {
                const permission = await navigator.permissions.query({ name: 'geolocation' })
                if (permission.state === 'granted') {
                    await requestBrowserLocation()
                    return
                }

                status.value = permission.state === 'denied' ? 'denied' : 'prompt'
                writeStatus(status.value)
                return
            } catch (error) {
                console.error('Unable to inspect geolocation permission', error)
            }
        }

        if (!readStatus() || readStatus() === 'idle') {
            status.value = 'prompt'
            writeStatus(status.value)
        } else {
            status.value = readStatus() || 'prompt'
        }
    })

    return {
        canRetryBrowserLocation,
        canRequestBrowserLocation,
        hasReliableLocation,
        isResolving,
        promptMessage,
        requestBrowserLocation,
        showPromptBanner,
        status,
    }
}
