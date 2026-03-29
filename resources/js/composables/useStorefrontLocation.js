import axios from 'axios'
import { router, usePage } from '@inertiajs/vue3'
import { computed, onMounted, ref, watch } from 'vue'

const STATUS_KEY = 'storefront-browser-location-status'
const ATTEMPTED_KEY = 'storefront-browser-location-attempted'
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

function hasAttemptedLocationLookup() {
    if (typeof window === 'undefined') {
        return false
    }

    return window.sessionStorage.getItem(ATTEMPTED_KEY) === '1'
}

function markLocationLookupAttempted() {
    if (typeof window === 'undefined') {
        return
    }

    window.sessionStorage.setItem(ATTEMPTED_KEY, '1')
}

export function useStorefrontLocation() {
    const page = usePage()
    const isStorefrontPage = computed(() => String(page.component || '').startsWith('Storefront/'))
    const pageBrowsingLocationSource = computed(() => String(page.props.browsingLocation?.source || ''))
    const resolvedLocationSource = ref(pageBrowsingLocationSource.value || '')
    const browsingLocationSource = computed(() => resolvedLocationSource.value || pageBrowsingLocationSource.value)
    const permissionState = ref('unknown')
    const status = ref(readStatus() || 'idle')
    const isResolving = computed(() => status.value === 'resolving')
    const hasReliableLocation = computed(() => {
        return ['browser', 'saved_address', 'order_history'].includes(browsingLocationSource.value)
    })
    const canRequestBrowserLocation = computed(() => {
        return typeof window !== 'undefined' && typeof navigator !== 'undefined' && !!navigator.geolocation
    })
    const hasSecureGeolocationContext = computed(() => {
        if (typeof window === 'undefined') {
            return true
        }

        const hostname = String(window.location?.hostname || '').toLowerCase()

        return window.isSecureContext || ['localhost', '127.0.0.1', '::1'].includes(hostname)
    })
    const showPromptBanner = computed(() => {
        if (!isStorefrontPage.value || hasReliableLocation.value) {
            return false
        }

        return [
            'denied',
            'timeout',
            'unavailable',
            'failed',
            'match_failed',
            'unsupported',
        ].includes(status.value)
    })
    const canRetryBrowserLocation = computed(() => {
        return canRequestBrowserLocation.value
            && hasSecureGeolocationContext.value
            && !isResolving.value
            && !['denied', 'unsupported'].includes(status.value)
    })
    const requestButtonLabel = computed(() => {
        if (isResolving.value) {
            return 'Checking location...'
        }

        if (['timeout', 'unavailable', 'failed', 'match_failed'].includes(status.value)) {
            return 'Try again'
        }

        return permissionState.value === 'granted' ? 'Use current location' : 'Allow location access'
    })
    const promptMessage = computed(() => {
        if (status.value === 'resolving') {
            return 'Checking your current location so we can show delivery estimates for your area.'
        }

        if (status.value === 'match_failed') {
            return 'We received your current coordinates, but could not match them to a delivery area yet. You can still continue to checkout and choose your destination there.'
        }

        if (status.value === 'denied') {
            return 'Location access is blocked in your browser right now. Enable it for this site to see delivery estimates for your area before checkout.'
        }

        if (status.value === 'timeout') {
            return 'We could not confirm your location in time. Try again to fetch your current location for delivery estimates.'
        }

        if (status.value === 'unavailable' || status.value === 'failed') {
            return 'We could not complete the location request automatically. Try again to fetch your current location for delivery estimates.'
        }

        if (status.value === 'unsupported' || !hasSecureGeolocationContext.value) {
            return 'This browser or site context cannot provide your location. If you are on a non-secure http page, switch to https to use location access. You can still see delivery timing after choosing a destination at checkout.'
        }

        if (permissionState.value === 'granted') {
            return 'Use your current location to get a delivery estimate for your area.'
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
            resolvedLocationSource.value = ''
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

            const location = data?.location ?? data ?? null
            const resolved = typeof data?.resolved === 'boolean'
                ? data.resolved
                : Boolean(location?.source === 'browser' && (location?.lga_id || location?.state_id))

            resolvedLocationSource.value = resolved ? 'browser' : ''
            status.value = resolved ? 'resolved' : 'match_failed'
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

        if (status.value === 'denied') {
            permissionState.value = 'denied'
        }

        resolvedLocationSource.value = ''
        writeStatus(status.value)

        if (browsingLocationSource.value === 'browser') {
            await clearBrowserLocation()
            await reloadStorefrontData()
        }
    }

    async function requestBrowserLocation({ automatic = false } = {}) {
        if (!canRequestBrowserLocation.value || isResolving.value) {
            status.value = canRequestBrowserLocation.value ? status.value : 'unsupported'
            writeStatus(status.value)
            return
        }

        if (!hasSecureGeolocationContext.value) {
            permissionState.value = 'unsupported'
            status.value = 'unsupported'
            writeStatus(status.value)
            return
        }

        if (automatic || !hasAttemptedLocationLookup()) {
            markLocationLookupAttempted()
        }

        status.value = 'resolving'
        writeStatus(status.value)

        navigator.geolocation.getCurrentPosition(
            (position) => {
                permissionState.value = 'granted'
                resolvedLocationSource.value = 'browser'
                void persistBrowserLocation(position)
            },
            (error) => {
                void handleGeolocationError(error)
            },
            {
                enableHighAccuracy: false,
                timeout: 10000,
                maximumAge: 5 * 60 * 1000,
            },
        )
    }

    watch(
        () => browsingLocationSource.value,
        (source) => {
            if (['browser', 'saved_address', 'order_history'].includes(source)) {
                status.value = source === 'browser' ? 'resolved' : 'fallback'
                writeStatus(status.value)
            }
        },
    )

    watch(
        () => pageBrowsingLocationSource.value,
        (source) => {
            resolvedLocationSource.value = source || ''
        },
        { immediate: true },
    )

    onMounted(async () => {
        if (!isStorefrontPage.value) {
            return
        }

        if (hasReliableLocation.value) {
            status.value = browsingLocationSource.value === 'browser' ? 'resolved' : 'fallback'
            writeStatus(status.value)
            return
        }

        if (!canRequestBrowserLocation.value || !hasSecureGeolocationContext.value) {
            permissionState.value = 'unsupported'
            status.value = 'unsupported'
            writeStatus(status.value)
            return
        }

        let permission = null

        if (typeof navigator.permissions?.query === 'function') {
            try {
                permission = await navigator.permissions.query({ name: 'geolocation' })
                permissionState.value = permission.state
                status.value = permission.state === 'denied' ? 'denied' : 'prompt'
                writeStatus(status.value)

                permission.onchange = () => {
                    permissionState.value = permission.state
                    status.value = permission.state === 'denied' ? 'denied' : 'prompt'
                    writeStatus(status.value)

                    if (permission.state === 'granted' && !hasReliableLocation.value && !isResolving.value) {
                        void requestBrowserLocation()
                    }
                }
            } catch (error) {
                console.error('Unable to inspect geolocation permission', error)
            }
        }

        if (permission?.state === 'denied') {
            return
        }

        if (!hasAttemptedLocationLookup() || permission?.state === 'granted') {
            void requestBrowserLocation({ automatic: true })
            return
        }

        permissionState.value = permissionState.value === 'unknown' ? 'prompt' : permissionState.value
        status.value = readStatus() && readStatus() !== 'idle'
            ? readStatus()
            : 'prompt'
        writeStatus(status.value)
    })

    return {
        canRetryBrowserLocation,
        hasReliableLocation,
        isResolving,
        permissionState,
        promptMessage,
        requestButtonLabel,
        requestBrowserLocation,
        showPromptBanner,
        status,
    }
}
