const dateFormatter = new Intl.DateTimeFormat(undefined, { day: 'numeric', month: 'short', year: 'numeric' })
const dateTimeFormatter = new Intl.DateTimeFormat(undefined, {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
})
const relativeFormatter = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' })

const RELATIVE_UNITS = [
    ['year', 31536000],
    ['month', 2592000],
    ['week', 604800],
    ['day', 86400],
    ['hour', 3600],
    ['minute', 60],
]

function toDate(value) {
    if (!value) {
        return null
    }

    // Plain YYYY-MM-DD is a calendar day; new Date() would read it as UTC midnight.
    const dayOnly = typeof value === 'string' && value.match(/^(\d{4})-(\d{2})-(\d{2})$/)
    const date = dayOnly
        ? new Date(Number(dayOnly[1]), Number(dayOnly[2]) - 1, Number(dayOnly[3]))
        : new Date(value)

    return Number.isNaN(date.getTime()) ? null : date
}

export function formatDate(value, fallback = '—') {
    const date = toDate(value)

    return date ? dateFormatter.format(date) : fallback
}

export function formatDateTime(value, fallback = '—') {
    const date = toDate(value)

    return date ? dateTimeFormatter.format(date) : fallback
}

export function formatRelative(value) {
    const date = toDate(value)

    if (!date) {
        return ''
    }

    const seconds = Math.round((date.getTime() - Date.now()) / 1000)

    for (const [unit, size] of RELATIVE_UNITS) {
        if (Math.abs(seconds) >= size) {
            return relativeFormatter.format(Math.round(seconds / size), unit)
        }
    }

    return 'just now'
}

export function browserTimeZone() {
    try {
        return Intl.DateTimeFormat().resolvedOptions().timeZone || null
    } catch {
        return null
    }
}
