<script setup>
import AuditSessionSelect from '@/components/Admin/AuditSessionSelect.vue'
import Pagination from '@/components/Pagination.vue'
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog'
import { browserTimeZone, formatDate, formatDateTime, formatRelative } from '@/lib/datetime'
import { Head, Link, router } from '@inertiajs/vue3'
import {
    AlertTriangle,
    ArrowUpRight,
    Check,
    CheckCheck,
    CheckCircle2,
    ClipboardCheck,
    History,
    Layers,
    LoaderCircle,
    MinusCircle,
    Package,
    ScanLine,
    Scale,
    Search,
    SearchX,
    X,
} from 'lucide-vue-next'
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue'

const props = defineProps({
    alerts: {
        type: Object,
        required: true,
    },
    summary: {
        type: Object,
        default: () => ({}),
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    sessionOptions: {
        type: Array,
        default: () => [],
    },
    categoryOptions: {
        type: Array,
        default: () => [],
    },
})

const ISSUES = {
    mismatch: {
        label: 'Count mismatch',
        badge: 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-200',
    },
    missing: {
        label: 'Not scanned',
        badge: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
    },
    negative: {
        label: 'Negative stock',
        badge: 'bg-rose-100 text-rose-800 dark:bg-rose-500/15 dark:text-rose-200',
    },
    ledger: {
        label: 'Ledger mismatch',
        badge: 'bg-violet-100 text-violet-800 dark:bg-violet-500/15 dark:text-violet-200',
    },
}

const SEVERITIES = {
    critical: { label: 'Critical', dot: 'bg-rose-500' },
    high: { label: 'High', dot: 'bg-orange-500' },
    medium: { label: 'Medium', dot: 'bg-amber-400' },
    low: { label: 'Low', dot: 'bg-slate-400' },
}

const SOURCES = {
    audit: 'Audit sessions',
    system: 'System stock scans',
}

const SORTS = {
    newest: 'Newest first',
    oldest: 'Oldest first',
    variance: 'Largest variance',
    severity: 'Highest severity',
}

const ADJUSTMENT_STATUSES = {
    pending: { label: 'Pending', dot: 'bg-amber-400' },
    approved: { label: 'Approved', dot: 'bg-emerald-500' },
    rejected: { label: 'Rejected', dot: 'bg-rose-500' },
}

const inputClass = 'h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200 dark:border-slate-700 dark:bg-slate-950 dark:[color-scheme:dark] dark:focus:border-slate-500 dark:focus:ring-slate-700'
const labelClass = 'mb-1 block text-xs font-medium uppercase text-slate-500 dark:text-slate-400'

const form = reactive({
    search: props.filters.search ?? '',
    session_id: props.filters.session_id ?? null,
    category_id: props.filters.category_id ?? null,
    issue: props.filters.issue ?? 'all',
    severity: props.filters.severity ?? 'all',
    source: props.filters.source ?? 'all',
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
    sort: props.filters.sort ?? 'newest',
})

const loading = ref(false)
const processingId = ref(null)
const bulkResolving = ref(false)
const confirmOpen = ref(false)
const selectedIds = ref([])
const selectAllMatching = ref(false)

let lastRequestedQuery = JSON.stringify(queryParams())
let searchTimer = null

const selectedSession = computed(() => props.sessionOptions.find((session) => session.id === props.filters.session_id) ?? null)
const selectedCategory = computed(() => props.categoryOptions.find((category) => category.id === props.filters.category_id) ?? null)

const pageIds = computed(() => props.alerts.data.map((alert) => alert.id))
const allPageSelected = computed(() => pageIds.value.length > 0 && pageIds.value.every((id) => selectedIds.value.includes(id)))
const somePageSelected = computed(() => selectedIds.value.length > 0 && !allPageSelected.value)
const selectionCount = computed(() => (selectAllMatching.value ? props.alerts.total : selectedIds.value.length))
const canSelectAllMatching = computed(() => allPageSelected.value && props.alerts.total > props.alerts.data.length)
const busy = computed(() => bulkResolving.value || processingId.value !== null)

const activeChips = computed(() => {
    const chips = []
    const applied = props.filters

    if (applied.search) {
        chips.push({ key: 'search', label: `“${applied.search}”` })
    }
    if (applied.session_id) {
        chips.push({ key: 'session_id', label: selectedSession.value?.reference ?? `Session #${applied.session_id}` })
    }
    if (applied.category_id) {
        chips.push({ key: 'category_id', label: selectedCategory.value?.name ?? 'Category' })
    }
    if (applied.issue && applied.issue !== 'all') {
        chips.push({ key: 'issue', label: ISSUES[applied.issue]?.label ?? applied.issue })
    }
    if (applied.severity && applied.severity !== 'all') {
        chips.push({ key: 'severity', label: `${SEVERITIES[applied.severity]?.label ?? applied.severity} severity` })
    }
    if (applied.source && applied.source !== 'all') {
        chips.push({ key: 'source', label: SOURCES[applied.source] ?? applied.source })
    }
    if (applied.from || applied.to) {
        chips.push({ key: 'dates', label: detectedRangeLabel(applied.from, applied.to) })
    }

    return chips
})

const summaryCards = computed(() => [
    {
        issue: 'all',
        label: 'Open alerts',
        value: props.summary.total ?? 0,
        hint: props.summary.sessions
            ? `Across ${props.summary.sessions} audit ${props.summary.sessions === 1 ? 'session' : 'sessions'}`
            : 'No audit sessions involved',
        icon: AlertTriangle,
        tone: 'text-slate-900 dark:text-slate-100',
    },
    {
        issue: 'mismatch',
        label: 'Count mismatches',
        value: props.summary.mismatch ?? 0,
        hint: unitsHint(),
        icon: Scale,
        tone: 'text-amber-700 dark:text-amber-300',
    },
    {
        issue: 'missing',
        label: 'Not scanned',
        value: props.summary.missing ?? 0,
        hint: 'Skipped during an audit',
        icon: ScanLine,
        tone: 'text-slate-700 dark:text-slate-200',
    },
    {
        issue: 'negative',
        label: 'Negative stock',
        value: props.summary.negative ?? 0,
        hint: 'Needs immediate review',
        icon: MinusCircle,
        tone: 'text-rose-700 dark:text-rose-300',
    },
    {
        issue: 'ledger',
        label: 'Ledger mismatches',
        value: props.summary.ledger ?? 0,
        hint: 'Stock differs from movement history',
        icon: Layers,
        tone: 'text-violet-700 dark:text-violet-300',
    },
])

watch(
    [
        () => form.session_id,
        () => form.category_id,
        () => form.issue,
        () => form.severity,
        () => form.source,
        () => form.from,
        () => form.to,
        () => form.sort,
    ],
    applyFilters,
)

watch(
    () => form.search,
    () => {
        clearTimeout(searchTimer)
        searchTimer = setTimeout(applyFilters, 350)
    },
)

watch(
    () => props.alerts,
    () => {
        selectedIds.value = []
        selectAllMatching.value = false
    },
)

watch(selectedIds, (ids) => {
    if (selectAllMatching.value && ids.length < pageIds.value.length) {
        selectAllMatching.value = false
    }
})

onBeforeUnmount(() => clearTimeout(searchTimer))

function queryParams() {
    const params = {}
    const search = form.search.trim()

    if (search) params.search = search
    if (form.session_id) params.session_id = form.session_id
    if (form.category_id) params.category_id = form.category_id
    if (form.issue !== 'all') params.issue = form.issue
    if (form.severity !== 'all') params.severity = form.severity
    if (form.source !== 'all') params.source = form.source
    if (form.from) params.from = form.from
    if (form.to) params.to = form.to
    if ((form.from || form.to) && browserTimeZone()) params.tz = browserTimeZone()
    if (form.sort !== 'newest') params.sort = form.sort

    return params
}

function applyFilters() {
    clearTimeout(searchTimer)

    const params = queryParams()
    const signature = JSON.stringify(params)

    if (signature === lastRequestedQuery) {
        return
    }

    lastRequestedQuery = signature

    router.get(route('admin.inventory.discrepancies'), params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onStart: () => {
            loading.value = true
        },
        onFinish: () => {
            loading.value = false
        },
    })
}

function setIssue(issue) {
    form.issue = form.issue === issue && issue !== 'all' ? 'all' : issue
}

function clearFilter(key) {
    if (key === 'search') form.search = ''
    if (key === 'session_id') form.session_id = null
    if (key === 'category_id') form.category_id = null
    if (key === 'issue') form.issue = 'all'
    if (key === 'severity') form.severity = 'all'
    if (key === 'source') form.source = 'all'
    if (key === 'dates') {
        form.from = ''
        form.to = ''
    }
}

function clearAllFilters() {
    Object.assign(form, {
        search: '',
        session_id: null,
        category_id: null,
        issue: 'all',
        severity: 'all',
        source: 'all',
        from: '',
        to: '',
    })
}

function togglePage(checked) {
    selectedIds.value = checked ? [...pageIds.value] : []
    selectAllMatching.value = false
}

function clearSelection() {
    selectedIds.value = []
    selectAllMatching.value = false
}

function resolveOne(alert) {
    if (busy.value) {
        return
    }

    processingId.value = alert.id

    router.post(
        route('admin.inventory.discrepancies.resolve'),
        { alert_ids: [alert.id] },
        {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => {
                processingId.value = null
            },
        },
    )
}

function confirmBulkResolve() {
    if (busy.value || selectionCount.value === 0) {
        return
    }

    bulkResolving.value = true

    const payload = selectAllMatching.value
        ? { all_matching: true, until_id: props.summary.matching_latest_id, filters: props.filters }
        : { alert_ids: [...selectedIds.value] }

    router.post(route('admin.inventory.discrepancies.resolve'), payload, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            confirmOpen.value = false
        },
        onFinish: () => {
            bulkResolving.value = false
        },
    })
}

function unitsHint() {
    const short = Number(props.summary.shortage_units ?? 0)
    const over = Number(props.summary.overage_units ?? 0)

    if (!short && !over) {
        return 'No unit variance recorded'
    }

    return `${short.toLocaleString()} units short · ${over.toLocaleString()} over`
}

function detectedRangeLabel(from, to) {
    if (from && to) {
        return from === to ? `First detected ${formatDate(from)}` : `First detected ${formatDate(from)} – ${formatDate(to)}`
    }

    return from ? `First detected from ${formatDate(from)}` : `First detected until ${formatDate(to)}`
}

function issue(kind) {
    return ISSUES[kind] ?? ISSUES.mismatch
}

function severity(level) {
    return SEVERITIES[level] ?? { label: level || 'Unknown', dot: 'bg-slate-400' }
}

function adjustmentStatus(status) {
    return ADJUSTMENT_STATUSES[status] ?? { label: 'View', dot: 'bg-slate-400' }
}

function formatQuantity(value) {
    return value === null || value === undefined ? '—' : Number(value).toLocaleString()
}

function formatVariance(value) {
    if (value === null || value === undefined) {
        return '—'
    }

    if (value === 0) {
        return '0'
    }

    return value > 0 ? `+${value.toLocaleString()}` : `−${Math.abs(value).toLocaleString()}`
}

function varianceClass(value) {
    if (value > 0) return 'text-emerald-600 dark:text-emerald-400'
    if (value < 0) return 'text-rose-600 dark:text-rose-400'

    return 'text-slate-400'
}

function formatPercent(value) {
    const number = Number(value || 0)

    return `${Number.isInteger(number) ? number : number.toFixed(2)}%`
}
</script>

<template>
    <Head title="Discrepancies" />

    <div class="space-y-5 px-4 py-4 text-slate-900 dark:text-slate-100 sm:px-5">
        <div class="flex flex-col gap-3 border-b border-slate-200 pb-4 dark:border-slate-800 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <div class="flex items-center gap-2 text-slate-700 dark:text-slate-300">
                    <ScanLine class="h-5 w-5" aria-hidden="true" />
                    <span class="text-sm font-semibold uppercase">Inventory Audit</span>
                </div>
                <h1 class="mt-1 text-2xl font-bold">Discrepancies</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Open count mismatches, unscanned items and negative stock awaiting review.
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <Link
                    :href="route('admin.inventory.stock-audit.history')"
                    class="inline-flex items-center gap-2 rounded-md border border-slate-300 px-3 py-2 text-sm font-medium hover:bg-slate-100 dark:border-slate-700 dark:hover:bg-slate-800"
                >
                    <History class="h-4 w-4" aria-hidden="true" />
                    <span>Audit history</span>
                </Link>
                <Link
                    :href="route('admin.stock-adjustments.index')"
                    class="inline-flex items-center gap-2 rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-700 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-300"
                >
                    <Scale class="h-4 w-4" aria-hidden="true" />
                    <span>Stock adjustments</span>
                </Link>
            </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <button
                v-for="card in summaryCards"
                :key="card.issue"
                type="button"
                class="rounded-lg border bg-white p-4 text-left transition hover:border-slate-400 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 dark:bg-slate-900 dark:hover:border-slate-500"
                :class="form.issue === card.issue
                    ? 'border-slate-900 ring-1 ring-slate-900 dark:border-slate-200 dark:ring-slate-200'
                    : 'border-slate-200 dark:border-slate-800'"
                :aria-pressed="form.issue === card.issue"
                @click="setIssue(card.issue)"
            >
                <div class="flex items-center justify-between gap-2">
                    <p class="text-xs font-medium uppercase text-slate-500 dark:text-slate-400">{{ card.label }}</p>
                    <component :is="card.icon" class="h-4 w-4 text-slate-400" aria-hidden="true" />
                </div>
                <p class="mt-2 text-2xl font-semibold tabular-nums" :class="card.tone">
                    {{ Number(card.value).toLocaleString() }}
                </p>
                <p class="mt-1 truncate text-xs text-slate-500 dark:text-slate-400">{{ card.hint }}</p>
            </button>
        </div>

        <section class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900" aria-label="Filters">
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-6">
                <div class="md:col-span-2">
                    <label for="discrepancy-search" :class="labelClass">Search</label>
                    <div class="relative">
                        <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                        <input
                            id="discrepancy-search"
                            v-model="form.search"
                            type="search"
                            autocomplete="off"
                            placeholder="Product name, SKU or barcode"
                            :class="[inputClass, 'pl-9 pr-9']"
                            @keydown.enter.prevent="applyFilters"
                        />
                        <button
                            v-if="form.search"
                            type="button"
                            class="absolute right-2 top-1/2 inline-flex size-6 -translate-y-1/2 items-center justify-center rounded-full text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                            aria-label="Clear search"
                            @click="form.search = ''"
                        >
                            <X class="size-4" aria-hidden="true" />
                        </button>
                    </div>
                </div>

                <div class="md:col-span-2">
                    <label for="discrepancy-session" :class="labelClass">Audit session</label>
                    <AuditSessionSelect id="discrepancy-session" v-model="form.session_id" :sessions="sessionOptions" />
                </div>

                <div>
                    <label for="discrepancy-category" :class="labelClass">Category</label>
                    <select id="discrepancy-category" v-model="form.category_id" :class="inputClass">
                        <option :value="null">All categories</option>
                        <option v-for="category in categoryOptions" :key="category.id" :value="category.id">
                            {{ category.name }} ({{ category.open_count }})
                        </option>
                    </select>
                </div>

                <div>
                    <label for="discrepancy-issue" :class="labelClass">Issue</label>
                    <select id="discrepancy-issue" v-model="form.issue" :class="inputClass">
                        <option value="all">All issues</option>
                        <option v-for="(definition, key) in ISSUES" :key="key" :value="key">{{ definition.label }}</option>
                    </select>
                </div>

                <div>
                    <label for="discrepancy-severity" :class="labelClass">Severity</label>
                    <select id="discrepancy-severity" v-model="form.severity" :class="inputClass">
                        <option value="all">All severities</option>
                        <option v-for="(definition, key) in SEVERITIES" :key="key" :value="key">{{ definition.label }}</option>
                    </select>
                </div>

                <div>
                    <label for="discrepancy-source" :class="labelClass">Source</label>
                    <select id="discrepancy-source" v-model="form.source" :class="inputClass">
                        <option value="all">All sources</option>
                        <option v-for="(label, key) in SOURCES" :key="key" :value="key">{{ label }}</option>
                    </select>
                </div>

                <div>
                    <label for="discrepancy-from" :class="labelClass">First detected from</label>
                    <input id="discrepancy-from" v-model="form.from" type="date" :max="form.to || undefined" :class="inputClass" />
                </div>

                <div>
                    <label for="discrepancy-to" :class="labelClass">First detected to</label>
                    <input id="discrepancy-to" v-model="form.to" type="date" :min="form.from || undefined" :class="inputClass" />
                </div>
            </div>

            <div v-if="activeChips.length" class="mt-4 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-3 dark:border-slate-800">
                <span class="text-xs font-medium uppercase text-slate-500 dark:text-slate-400">Filtered by</span>
                <button
                    v-for="chip in activeChips"
                    :key="chip.key"
                    type="button"
                    class="inline-flex items-center gap-1 rounded-full bg-slate-100 py-1 pl-2.5 pr-1.5 text-xs font-medium text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                    :aria-label="`Remove filter ${chip.label}`"
                    @click="clearFilter(chip.key)"
                >
                    {{ chip.label }}
                    <X class="size-3.5" aria-hidden="true" />
                </button>
                <button
                    type="button"
                    class="ml-1 text-xs font-medium text-slate-600 underline-offset-2 hover:underline dark:text-slate-300"
                    @click="clearAllFilters"
                >
                    Clear all
                </button>
            </div>
        </section>

        <section
            v-if="selectedSession"
            class="flex flex-col gap-3 rounded-lg border border-amber-200 bg-amber-50/70 p-4 dark:border-amber-500/30 dark:bg-amber-500/5 lg:flex-row lg:items-center lg:justify-between"
        >
            <div class="flex gap-3">
                <ClipboardCheck class="mt-0.5 h-5 w-5 shrink-0 text-amber-700 dark:text-amber-300" aria-hidden="true" />
                <div>
                    <p class="font-semibold">
                        {{ selectedSession.reference }} ·
                        {{ selectedSession.scope_type === 'category' ? `${selectedSession.scope_label} category audit` : 'Full inventory audit' }}
                    </p>
                    <p class="mt-0.5 text-sm text-slate-600 dark:text-slate-300">
                        Submitted {{ formatDateTime(selectedSession.submitted_at) }}<template v-if="selectedSession.submitted_by"> by {{ selectedSession.submitted_by }}</template><template v-if="selectedSession.source_label"> · {{ selectedSession.source_label }}</template>
                        · {{ Number(selectedSession.total_scanned_items).toLocaleString() }} of {{ Number(selectedSession.total_expected_items).toLocaleString() }} items scanned ({{ formatPercent(selectedSession.coverage_percentage) }})<template v-if="selectedSession.is_partial"> · partial count</template>
                    </p>
                </div>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                <span class="rounded-full bg-white px-2.5 py-1 text-xs font-semibold text-amber-800 ring-1 ring-amber-200 dark:bg-slate-900 dark:text-amber-200 dark:ring-amber-500/30">
                    {{ selectedSession.open_count }} open
                </span>
                <Link
                    :href="selectedSession.history_url"
                    class="inline-flex items-center gap-1 rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-900 dark:hover:bg-slate-800"
                >
                    Audit history
                    <ArrowUpRight class="h-3.5 w-3.5" aria-hidden="true" />
                </Link>
            </div>
        </section>

        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col gap-3 border-b border-slate-200 px-4 py-3 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-between">
                <div v-if="selectionCount" class="flex flex-wrap items-center gap-3">
                    <p class="text-sm font-semibold">{{ selectionCount.toLocaleString() }} selected</p>
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-md bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-300"
                        :disabled="busy"
                        @click="confirmOpen = true"
                    >
                        <CheckCheck class="h-4 w-4" aria-hidden="true" />
                        Resolve selected
                    </button>
                    <button
                        type="button"
                        class="text-sm font-medium text-slate-600 underline-offset-2 hover:underline dark:text-slate-300"
                        @click="clearSelection"
                    >
                        Clear selection
                    </button>
                </div>
                <p v-else class="text-sm text-slate-600 dark:text-slate-300">
                    <template v-if="alerts.total">
                        Showing <span class="font-semibold">{{ alerts.from }}–{{ alerts.to }}</span>
                        of <span class="font-semibold">{{ alerts.total.toLocaleString() }}</span>
                        {{ alerts.total === 1 ? 'alert' : 'alerts' }}
                    </template>
                    <template v-else>No matching alerts</template>
                </p>

                <div class="flex items-center gap-2">
                    <label for="discrepancy-sort" class="text-sm text-slate-500 dark:text-slate-400">Sort</label>
                    <select
                        id="discrepancy-sort"
                        v-model="form.sort"
                        class="h-9 rounded-md border border-slate-300 bg-white px-2 text-sm outline-none focus:border-slate-500 focus:ring-2 focus:ring-slate-200 dark:border-slate-700 dark:bg-slate-950 dark:[color-scheme:dark] dark:focus:ring-slate-700"
                    >
                        <option v-for="(label, key) in SORTS" :key="key" :value="key">{{ label }}</option>
                    </select>
                </div>
            </div>

            <div
                v-if="canSelectAllMatching || selectAllMatching"
                class="border-b border-slate-200 bg-slate-50 px-4 py-2 text-center text-sm text-slate-700 dark:border-slate-800 dark:bg-slate-950/60 dark:text-slate-200"
            >
                <template v-if="selectAllMatching">
                    All {{ alerts.total.toLocaleString() }} matching alerts are selected.
                    <button type="button" class="font-semibold underline-offset-2 hover:underline" @click="clearSelection">Clear selection</button>
                </template>
                <template v-else>
                    All {{ alerts.data.length }} alerts on this page are selected.
                    <button type="button" class="font-semibold underline-offset-2 hover:underline" @click="selectAllMatching = true">
                        Select all {{ alerts.total.toLocaleString() }} matching alerts
                    </button>
                </template>
            </div>

            <div class="overflow-x-auto transition-opacity" :class="{ 'pointer-events-none opacity-60': loading }" :aria-busy="loading">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500 dark:bg-slate-950/60 dark:text-slate-400">
                        <tr>
                            <th scope="col" class="w-10 px-4 py-3 text-left">
                                <input
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-slate-300 text-slate-900 focus:ring-slate-500 dark:border-slate-600 dark:bg-slate-900"
                                    :checked="allPageSelected"
                                    :indeterminate="somePageSelected"
                                    :disabled="!alerts.data.length || busy"
                                    aria-label="Select all alerts on this page"
                                    @change="togglePage($event.target.checked)"
                                />
                            </th>
                            <th scope="col" class="px-4 py-3 text-left">Item</th>
                            <th scope="col" class="px-4 py-3 text-left">Issue</th>
                            <th scope="col" class="px-4 py-3 text-right">System</th>
                            <th scope="col" class="px-4 py-3 text-right">Counted</th>
                            <th scope="col" class="px-4 py-3 text-right">Variance</th>
                            <th scope="col" class="px-4 py-3 text-left">Session</th>
                            <th scope="col" class="px-4 py-3 text-left">First detected</th>
                            <th scope="col" class="px-4 py-3 text-right"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr
                            v-for="alert in alerts.data"
                            :key="alert.id"
                            class="align-middle transition-colors"
                            :class="selectAllMatching || selectedIds.includes(alert.id)
                                ? 'bg-amber-50/70 dark:bg-amber-500/5'
                                : 'hover:bg-slate-50 dark:hover:bg-slate-800/50'"
                        >
                            <td class="px-4 py-3">
                                <input
                                    v-model="selectedIds"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-slate-300 text-slate-900 focus:ring-slate-500 dark:border-slate-600 dark:bg-slate-900"
                                    :value="alert.id"
                                    :disabled="busy"
                                    :aria-label="`Select alert for ${alert.product}`"
                                />
                            </td>
                            <td class="min-w-[16rem] max-w-[24rem] px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <img
                                        v-if="alert.image"
                                        :src="alert.image"
                                        alt=""
                                        loading="lazy"
                                        class="h-10 w-10 shrink-0 rounded-md border border-slate-200 object-cover dark:border-slate-700"
                                    />
                                    <div v-else class="flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-slate-100 text-slate-400 dark:bg-slate-800">
                                        <Package class="h-4 w-4" aria-hidden="true" />
                                    </div>
                                    <div class="min-w-0">
                                        <Link
                                            v-if="alert.product_url"
                                            :href="alert.product_url"
                                            class="line-clamp-2 font-medium hover:text-amber-700 hover:underline dark:hover:text-amber-300"
                                        >
                                            {{ alert.product }}
                                        </Link>
                                        <p v-else class="line-clamp-2 font-medium">{{ alert.product }}</p>
                                        <p class="mt-0.5 truncate font-mono text-xs text-slate-500 dark:text-slate-400">{{ alert.sku || 'No SKU' }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3">
                                <span
                                    class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold"
                                    :class="issue(alert.kind).badge"
                                    :title="alert.message"
                                >
                                    {{ issue(alert.kind).label }}
                                </span>
                                <p class="mt-1 flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400">
                                    <span class="h-1.5 w-1.5 rounded-full" :class="severity(alert.severity).dot" aria-hidden="true"></span>
                                    {{ severity(alert.severity).label }} severity
                                </p>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums">{{ formatQuantity(alert.system_quantity) }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums">
                                <span v-if="alert.kind === 'missing'" class="text-xs italic text-slate-400">Not counted</span>
                                <template v-else-if="alert.kind === 'ledger'">
                                    {{ formatQuantity(alert.ledger_quantity) }}
                                    <span class="block text-[11px] text-slate-400">in ledger</span>
                                </template>
                                <template v-else>{{ formatQuantity(alert.physical_quantity) }}</template>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right font-semibold tabular-nums" :class="varianceClass(alert.variance)">
                                {{ formatVariance(alert.variance) }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-3">
                                <template v-if="alert.session">
                                    <button
                                        type="button"
                                        class="font-medium hover:text-amber-700 hover:underline dark:hover:text-amber-300"
                                        :title="`Show only alerts from ${alert.session.reference}`"
                                        @click="form.session_id = alert.session.id"
                                    >
                                        {{ alert.session.reference }}
                                    </button>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">
                                        {{ [alert.session.scope_label, alert.source_label].filter(Boolean).join(' · ') }}
                                    </p>
                                </template>
                                <template v-else>
                                    <p class="font-medium">System scan</p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">Automated stock check</p>
                                </template>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3">
                                <p :title="formatDateTime(alert.detected_at)">{{ formatDate(alert.detected_at) }}</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">{{ formatRelative(alert.detected_at) }}</p>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3">
                                <div class="flex items-center justify-end gap-2">
                                    <Link
                                        v-if="alert.adjustment"
                                        :href="alert.adjustment.url"
                                        class="inline-flex items-center gap-1.5 rounded-md border border-slate-300 px-2.5 py-1.5 text-xs font-medium hover:bg-slate-100 dark:border-slate-700 dark:hover:bg-slate-800"
                                        :title="`Stock adjustment #${alert.adjustment.id}: ${adjustmentStatus(alert.adjustment.status).label}`"
                                    >
                                        <span class="h-1.5 w-1.5 rounded-full" :class="adjustmentStatus(alert.adjustment.status).dot" aria-hidden="true"></span>
                                        Adjustment · {{ adjustmentStatus(alert.adjustment.status).label }}
                                    </Link>
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1.5 rounded-md bg-slate-900 px-2.5 py-1.5 text-xs font-medium text-white hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-300"
                                        :disabled="busy"
                                        @click="resolveOne(alert)"
                                    >
                                        <LoaderCircle v-if="processingId === alert.id" class="h-3.5 w-3.5 animate-spin" aria-hidden="true" />
                                        <Check v-else class="h-3.5 w-3.5" aria-hidden="true" />
                                        Resolve
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="!alerts.data.length">
                            <td colspan="9" class="px-4 py-12">
                                <div class="mx-auto flex max-w-md flex-col items-center text-center">
                                    <template v-if="alerts.total > 0">
                                        <SearchX class="h-8 w-8 text-slate-400" aria-hidden="true" />
                                        <p class="mt-3 font-semibold">No alerts on this page</p>
                                        <Link
                                            :href="alerts.first_page_url"
                                            class="mt-3 text-sm font-medium text-slate-700 underline-offset-2 hover:underline dark:text-slate-200"
                                        >
                                            Go to the first page
                                        </Link>
                                    </template>
                                    <template v-else-if="activeChips.length">
                                        <SearchX class="h-8 w-8 text-slate-400" aria-hidden="true" />
                                        <p class="mt-3 font-semibold">No alerts match these filters</p>
                                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Try widening the date range or removing a filter.</p>
                                        <button
                                            type="button"
                                            class="mt-4 inline-flex items-center gap-2 rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium hover:bg-slate-100 dark:border-slate-700 dark:hover:bg-slate-800"
                                            @click="clearAllFilters"
                                        >
                                            Clear all filters
                                        </button>
                                    </template>
                                    <template v-else>
                                        <CheckCircle2 class="h-8 w-8 text-emerald-500" aria-hidden="true" />
                                        <p class="mt-3 font-semibold">All clear</p>
                                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                            There are no open discrepancies. New ones appear here when an audit is submitted or a stock scan finds a mismatch.
                                        </p>
                                    </template>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-if="alerts.last_page > 1"
                class="flex flex-col items-center gap-3 border-t border-slate-200 px-4 py-4 dark:border-slate-800 sm:flex-row sm:justify-between"
            >
                <p class="text-xs text-slate-500 dark:text-slate-400">Page {{ alerts.current_page }} of {{ alerts.last_page }}</p>
                <Pagination :links="alerts.links" />
            </div>
        </section>

        <Dialog v-model:open="confirmOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>
                        Resolve {{ selectionCount.toLocaleString() }} {{ selectionCount === 1 ? 'alert' : 'alerts' }}?
                    </DialogTitle>
                    <DialogDescription>
                        They will be marked as resolved and removed from this dashboard. Stock adjustments raised by an audit are not changed; approve or reject those separately.
                    </DialogDescription>
                </DialogHeader>

                <div
                    v-if="selectAllMatching && activeChips.length"
                    class="rounded-md bg-slate-50 px-3 py-2 text-sm text-slate-700 dark:bg-slate-800/60 dark:text-slate-200"
                >
                    Matching: {{ activeChips.map((chip) => chip.label).join(', ') }}
                </div>

                <DialogFooter class="gap-2">
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-md border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-100 disabled:opacity-60 dark:border-slate-700 dark:hover:bg-slate-800"
                        :disabled="bulkResolving"
                        @click="confirmOpen = false"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center justify-center gap-2 rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700 disabled:opacity-60 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-300"
                        :disabled="bulkResolving"
                        @click="confirmBulkResolve"
                    >
                        <LoaderCircle v-if="bulkResolving" class="h-4 w-4 animate-spin" aria-hidden="true" />
                        Resolve {{ selectionCount.toLocaleString() }}
                    </button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
