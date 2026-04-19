import { router } from '@inertiajs/vue3'

type AnalyticsConfig = {
    enabled?: boolean
    track_authenticated_pages?: boolean
    batch_size?: number
    flush_interval_ms?: number
    allowed_component_prefixes?: string[]
}

type AnalyticsPage = {
    component?: string
    url?: string
    props?: Record<string, any>
}

type AnalyticsEvent = {
    visitor_key: string
    page_path: string
    page_title: string
    component: string
    occurred_at: string
    referrer?: string | null
    location?: {
        country_code?: string | null
        country_name?: string | null
        state_name?: string | null
    } | null
}

const STORAGE_KEY = 'storefront.analytics.visitor_key'
const TRACK_ENDPOINT = '/analytics/storefront/page-views'

let booted = false
let queue: AnalyticsEvent[] = []
let flushTimer: ReturnType<typeof setTimeout> | null = null
let lastSignature = ''
let lastTrackedAt = 0
let previousUrl = ''
let config: AnalyticsConfig = {}

export function bootStorefrontAnalytics(initialPage?: AnalyticsPage) {
    if (booted || typeof window === 'undefined') {
        return
    }

    config = initialPage?.props?.analytics?.storefront ?? {}

    if (!config.enabled) {
        return
    }

    booted = true
    previousUrl = document.referrer || ''

    queuePageView(initialPage)

    router.on('navigate', (event) => {
        queuePageView(event.detail.page as AnalyticsPage)
    })

    window.addEventListener('pagehide', () => flush(true))
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'hidden') {
            flush(true)
        }
    })
}

function queuePageView(page?: AnalyticsPage) {
    if (!page?.component || !page?.url || !shouldTrackPage(page)) {
        return
    }

    const pagePath = normalizePath(page.url)
    const signature = `${page.component}|${pagePath}`
    const nowMs = Date.now()

    if (signature === lastSignature && nowMs - lastTrackedAt < 1200) {
        return
    }

    queue.push({
        visitor_key: getVisitorKey(),
        page_path: pagePath,
        page_title: document.title || page.component,
        component: page.component,
        occurred_at: new Date().toISOString(),
        referrer: previousUrl || document.referrer || null,
        location: extractLocation(page),
    })

    lastSignature = signature
    lastTrackedAt = nowMs
    previousUrl = window.location.href

    if (queue.length >= batchSize()) {
        void flush()
        return
    }

    if (!flushTimer) {
        flushTimer = window.setTimeout(() => {
            void flush()
        }, flushInterval())
    }
}

function shouldTrackPage(page: AnalyticsPage) {
    if (!config.track_authenticated_pages && page.props?.auth?.user) {
        return false
    }

    const allowedPrefixes = Array.isArray(config.allowed_component_prefixes)
        ? config.allowed_component_prefixes
        : []

    return allowedPrefixes.some((prefix) => page.component === prefix || page.component?.startsWith(prefix))
}

function normalizePath(url: string) {
    try {
        const parsed = new URL(url, window.location.origin)
        const path = parsed.pathname || '/'

        return path !== '/' ? path.replace(/\/+$/, '') : path
    } catch {
        return url || '/'
    }
}

function extractLocation(page: AnalyticsPage): AnalyticsEvent['location'] {
    const location = page.props?.browsingLocation

    if (!location) {
        return null
    }

    return {
        country_code: location.country_code ?? null,
        country_name: location.country_name ?? null,
        state_name: location.state_name ?? location.region_name ?? null,
    }
}

function getVisitorKey() {
    try {
        const stored = window.localStorage.getItem(STORAGE_KEY)

        if (stored && /^[a-zA-Z0-9\-_]{16,64}$/.test(stored)) {
            return stored
        }

        const generated = generateVisitorKey()
        window.localStorage.setItem(STORAGE_KEY, generated)

        return generated
    } catch {
        return generateVisitorKey()
    }
}

function generateVisitorKey() {
    if (typeof crypto !== 'undefined' && 'randomUUID' in crypto) {
        return crypto.randomUUID().replace(/-/g, '')
    }

    return `visitor${Math.random().toString(36).slice(2)}${Date.now().toString(36)}`
}

function batchSize() {
    return Math.max(1, Math.min(Number(config.batch_size || 5), 20))
}

function flushInterval() {
    return Math.max(500, Math.min(Number(config.flush_interval_ms || 2000), 10000))
}

async function flush(useBeacon = false) {
    if (flushTimer) {
        clearTimeout(flushTimer)
        flushTimer = null
    }

    if (!queue.length) {
        return
    }

    const events = queue.splice(0, queue.length)
    const payload = JSON.stringify({ events })

    if (useBeacon && navigator.sendBeacon) {
        const blob = new Blob([payload], { type: 'application/json' })
        navigator.sendBeacon(TRACK_ENDPOINT, blob)
        return
    }

    try {
        await fetch(TRACK_ENDPOINT, {
            method: 'POST',
            credentials: 'same-origin',
            keepalive: true,
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: payload,
        })
    } catch {
        queue = [...events, ...queue].slice(0, 20)
    }
}
