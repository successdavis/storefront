<script setup>
import Pagination from '@/components/Pagination.vue'
import { Button } from '@/components/ui/button'
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog'
import { Head, router } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'

const props = defineProps({
    alerts: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    summary: {
        type: Object,
        required: true,
    },
    typeOptions: {
        type: Array,
        default: () => [],
    },
    severityOptions: {
        type: Array,
        default: () => [],
    },
})

const alerts = computed(() => props.alerts)
const summary = computed(() => props.summary)
const typeOptions = computed(() => props.typeOptions)
const severityOptions = computed(() => props.severityOptions)

const state = ref(props.filters.state || 'active')
const type = ref(props.filters.type || '')
const severity = ref(props.filters.severity || '')
const search = ref(props.filters.search || '')

const snoozeDialogOpen = ref(false)
const snoozeAlert = ref(null)
const snoozeDays = ref('7')
const snoozeReason = ref('')
const snoozeSubmitting = ref(false)

const reasonDialogOpen = ref(false)
const reasonAlert = ref(null)
const reasonAction = ref('')
const reasonText = ref('')
const reasonSubmitting = ref(false)
const selectedIds = ref([])
const batchAction = ref('')
const batchDialogOpen = ref(false)
const batchReason = ref('')
const batchSnoozeDays = ref('7')
const batchSubmitting = ref(false)

const stateTabs = computed(() => [
    { key: 'active', label: 'Active', count: props.summary.active },
    { key: 'snoozed', label: 'Snoozed', count: props.summary.snoozed },
    { key: 'suppressed', label: 'Suppressed', count: props.summary.suppressed },
    { key: 'resolved', label: 'Resolved', count: props.summary.resolved },
    { key: 'all', label: 'All', count: props.summary.all },
])

const selectableAlertIds = computed(() => (alerts.value.data || [])
    .filter(alert => alert.status !== 'resolved')
    .map(alert => alert.id))

const selectedCount = computed(() => selectedIds.value.length)
const allVisibleSelected = computed(() => selectableAlertIds.value.length > 0
    && selectableAlertIds.value.every(id => selectedIds.value.includes(id)))

const batchDialogTitle = computed(() => {
    if (batchAction.value === 'snooze') return 'Snooze selected alerts'
    if (batchAction.value === 'suppress') return 'Suppress selected alerts'
    if (batchAction.value === 'resolve') return 'Resolve selected alerts'
    return 'Batch update selected alerts'
})

const batchDialogDescription = computed(() => {
    if (batchAction.value === 'snooze') {
        return 'Selected alerts will move out of the active queue until the snooze period expires.'
    }

    if (batchAction.value === 'suppress') {
        return 'Selected alerts will be hidden from the active queue until someone reactivates them.'
    }

    if (batchAction.value === 'resolve') {
        return 'Selected alerts will be closed. If a condition appears again on a later scan, a new alert may be raised.'
    }

    return ''
})

const batchSubmitLabel = computed(() => {
    if (batchAction.value === 'snooze') return `Snooze ${selectedCount.value} alert(s)`
    if (batchAction.value === 'suppress') return `Suppress ${selectedCount.value} alert(s)`
    if (batchAction.value === 'resolve') return `Resolve ${selectedCount.value} alert(s)`
    return 'Apply'
})

watch(
    () => props.alerts.data,
    () => {
        selectedIds.value = []
    },
)

const reasonDialogTitle = computed(() => {
    if (reasonAction.value === 'suppress') return 'Suppress inventory alert'
    if (reasonAction.value === 'resolve') return 'Resolve inventory alert'
    return 'Update inventory alert'
})

const reasonDialogDescription = computed(() => {
    if (reasonAction.value === 'suppress') {
        return 'Move this alert out of the active queue indefinitely. It can be reactivated later.'
    }

    if (reasonAction.value === 'resolve') {
        return 'Close this alert. If the condition returns on a later scan, the system can raise a new alert.'
    }

    return ''
})

const reasonSubmitLabel = computed(() => {
    if (reasonAction.value === 'suppress') return 'Suppress alert'
    if (reasonAction.value === 'resolve') return 'Resolve alert'
    return 'Save'
})

function routeToIndex(overrides = {}) {
    router.get('/admin/inventory-alerts', {
        state: overrides.state ?? state.value,
        type: type.value || undefined,
        severity: severity.value || undefined,
        search: search.value || undefined,
    }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    })
}

function selectState(nextState) {
    state.value = nextState
    routeToIndex({ state: nextState })
}

function applyFilters() {
    routeToIndex()
}

function resetFilters() {
    state.value = 'active'
    type.value = ''
    severity.value = ''
    search.value = ''
    routeToIndex({ state: 'active' })
}

function toggleAllVisible(event) {
    selectedIds.value = event.target.checked ? [...selectableAlertIds.value] : []
}

function clearSelection() {
    selectedIds.value = []
    batchAction.value = ''
}

function formatDate(value) {
    if (!value) return 'N/A'
    return new Date(value).toLocaleString()
}

function auditEntries(alert) {
    return [
        ['Acknowledged', alert.audit?.acknowledged],
        ['Snoozed', alert.audit?.snoozed],
        ['Suppressed', alert.audit?.suppressed],
        ['Resolved', alert.audit?.resolved],
    ].filter(([, entry]) => entry)
}

function displayMessage(alert) {
    const product = String(alert.product || '').trim()
    const message = String(alert.message || '').trim()

    if (!product || !message) {
        return message
    }

    if (!message.toLowerCase().startsWith(product.toLowerCase())) {
        return message
    }

    return message
        .slice(product.length)
        .replace(/^[\s,.:;-]+/, '')
        .trim()
}

function severityClass(value) {
    return {
        critical: 'bg-rose-100 text-rose-700 dark:bg-rose-950/40 dark:text-rose-200',
        high: 'bg-orange-100 text-orange-700 dark:bg-orange-950/40 dark:text-orange-200',
        medium: 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-200',
        low: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
    }[value] || 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200'
}

function stateClass(value) {
    return {
        active: 'bg-blue-100 text-blue-700 dark:bg-blue-950/40 dark:text-blue-200',
        snoozed: 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-200',
        suppressed: 'bg-purple-100 text-purple-700 dark:bg-purple-950/40 dark:text-purple-200',
        resolved: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-200',
    }[value] || 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200'
}

function replenishmentClass(value) {
    return {
        paused: 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-200',
        discontinued: 'bg-rose-100 text-rose-700 dark:bg-rose-950/40 dark:text-rose-200',
        reorderable: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-200',
    }[value] || 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200'
}

function replenishmentLabel(value) {
    return {
        paused: 'Paused',
        discontinued: 'Discontinued',
        reorderable: 'Reorderable',
    }[value] || 'Unknown'
}

function postAlertAction(alert, action, payload = {}, options = {}) {
    router.post(`/admin/inventory-alerts/${alert.id}/${action}`, payload, {
        preserveScroll: true,
        ...options,
    })
}

function acknowledge(alert) {
    postAlertAction(alert, 'acknowledge')
}

function reactivate(alert) {
    postAlertAction(alert, 'unsuppress')
}

function openSnoozeDialog(alert) {
    snoozeAlert.value = alert
    snoozeDays.value = '7'
    snoozeReason.value = alert.snooze_reason || ''
    snoozeDialogOpen.value = true
}

function setSnoozeDialogOpen(open) {
    snoozeDialogOpen.value = open

    if (!open && !snoozeSubmitting.value) {
        snoozeAlert.value = null
        snoozeDays.value = '7'
        snoozeReason.value = ''
    }
}

function submitSnooze() {
    if (!snoozeAlert.value) return

    const until = new Date()
    until.setDate(until.getDate() + Number(snoozeDays.value || 7))
    snoozeSubmitting.value = true

    postAlertAction(snoozeAlert.value, 'snooze', {
        snoozed_until: until.toISOString(),
        snooze_reason: snoozeReason.value,
    }, {
        onSuccess: () => setSnoozeDialogOpen(false),
        onFinish: () => {
            snoozeSubmitting.value = false
        },
    })
}

function openReasonDialog(alert, action) {
    reasonAlert.value = alert
    reasonAction.value = action
    reasonText.value = action === 'suppress'
        ? (alert.suppress_reason || '')
        : (alert.resolved_reason || 'Condition reviewed and resolved.')
    reasonDialogOpen.value = true
}

function setReasonDialogOpen(open) {
    reasonDialogOpen.value = open

    if (!open && !reasonSubmitting.value) {
        reasonAlert.value = null
        reasonAction.value = ''
        reasonText.value = ''
    }
}

function submitReasonAction() {
    if (!reasonAlert.value || !reasonAction.value) return

    const action = reasonAction.value === 'resolve' ? 'close' : 'suppress'
    const payload = reasonAction.value === 'resolve'
        ? { resolved_reason: reasonText.value }
        : { suppress_reason: reasonText.value }

    reasonSubmitting.value = true
    postAlertAction(reasonAlert.value, action, payload, {
        onSuccess: () => setReasonDialogOpen(false),
        onFinish: () => {
            reasonSubmitting.value = false
        },
    })
}

function applyBatchAction() {
    if (!batchAction.value || selectedIds.value.length === 0) {
        return
    }

    if (batchAction.value === 'acknowledge') {
        batchSubmitting.value = true
        router.post('/admin/inventory-alerts/bulk', {
            ids: selectedIds.value,
            action: 'acknowledge',
        }, {
            preserveScroll: true,
            onSuccess: clearSelection,
            onFinish: () => {
                batchSubmitting.value = false
            },
        })
        return
    }

    batchReason.value = batchAction.value === 'resolve'
        ? 'Condition reviewed and resolved.'
        : ''
    batchSnoozeDays.value = '7'
    batchDialogOpen.value = true
}

function setBatchDialogOpen(open) {
    batchDialogOpen.value = open

    if (!open && !batchSubmitting.value) {
        batchReason.value = ''
        batchSnoozeDays.value = '7'
    }
}

function submitBatchAction() {
    if (!batchAction.value || selectedIds.value.length === 0) {
        return
    }

    const payload = {
        ids: selectedIds.value,
        action: batchAction.value,
        reason: batchReason.value,
    }

    if (batchAction.value === 'snooze') {
        const until = new Date()
        until.setDate(until.getDate() + Number(batchSnoozeDays.value || 7))
        payload.snoozed_until = until.toISOString()
    }

    batchSubmitting.value = true
    router.post('/admin/inventory-alerts/bulk', payload, {
        preserveScroll: true,
        onSuccess: () => {
            setBatchDialogOpen(false)
            clearSelection()
        },
        onFinish: () => {
            batchSubmitting.value = false
        },
    })
}
</script>

<template>
    <Head title="Inventory Alerts" />

    <div class="min-h-screen space-y-6 bg-gray-100 p-6 text-gray-900 dark:bg-gray-950 dark:text-gray-100">
        <section class="flex flex-col gap-4 rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-blue-600 dark:text-blue-300">Inventory control</p>
                <h1 class="mt-2 text-2xl font-semibold">Inventory Alerts</h1>
                <p class="mt-2 max-w-3xl text-sm text-gray-500 dark:text-gray-400">
                    Review every alert, including active, snoozed, suppressed, and resolved records. The dashboard keeps a small operational queue; this page is the full management view.
                </p>
            </div>

            <a
                href="/admin"
                class="inline-flex rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800"
            >
                Back to dashboard
            </a>
        </section>

        <section class="grid gap-3 md:grid-cols-3 xl:grid-cols-6">
            <article class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Active</p>
                <p class="mt-2 text-2xl font-semibold">{{ summary.active }}</p>
            </article>
            <article class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Critical Active</p>
                <p class="mt-2 text-2xl font-semibold text-rose-600 dark:text-rose-300">{{ summary.critical }}</p>
            </article>
            <article class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Snoozed</p>
                <p class="mt-2 text-2xl font-semibold">{{ summary.snoozed }}</p>
            </article>
            <article class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Suppressed</p>
                <p class="mt-2 text-2xl font-semibold">{{ summary.suppressed }}</p>
            </article>
            <article class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Resolved</p>
                <p class="mt-2 text-2xl font-semibold">{{ summary.resolved }}</p>
            </article>
            <article class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">All Records</p>
                <p class="mt-2 text-2xl font-semibold">{{ summary.all }}</p>
            </article>
        </section>

        <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="tab in stateTabs"
                    :key="tab.key"
                    type="button"
                    class="rounded-md px-3 py-2 text-sm font-medium transition"
                    :class="state === tab.key ? 'bg-gray-900 text-white dark:bg-gray-100 dark:text-gray-900' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700'"
                    @click="selectState(tab.key)"
                >
                    {{ tab.label }} ({{ tab.count }})
                </button>
            </div>

            <div class="mt-4 grid gap-3 md:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)_minmax(0,1fr)_auto_auto]">
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search product, SKU, type, or message"
                    class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100"
                    @keyup.enter="applyFilters"
                />
                <select
                    v-model="type"
                    class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100"
                    @change="applyFilters"
                >
                    <option value="">All types</option>
                    <option v-for="option in typeOptions" :key="option.value" :value="option.value">
                        {{ option.label }}
                    </option>
                </select>
                <select
                    v-model="severity"
                    class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100"
                    @change="applyFilters"
                >
                    <option value="">All severities</option>
                    <option v-for="option in severityOptions" :key="option" :value="option">
                        {{ option }}
                    </option>
                </select>
                <Button type="button" variant="outline" @click="applyFilters">Apply</Button>
                <Button type="button" variant="ghost" @click="resetFilters">Reset</Button>
            </div>
        </section>

        <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ selectedCount }} selected</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Select alerts on this page, then apply one action to the selected open alerts.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <select
                        v-model="batchAction"
                        class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100"
                        aria-label="Batch action"
                    >
                        <option value="">Batch action</option>
                        <option value="acknowledge">Acknowledge</option>
                        <option value="snooze">Snooze</option>
                        <option value="suppress">Suppress</option>
                        <option value="resolve">Resolve</option>
                    </select>
                    <Button
                        type="button"
                        :disabled="!batchAction || selectedCount === 0 || batchSubmitting"
                        @click="applyBatchAction"
                    >
                        Apply
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        :disabled="selectedCount === 0 || batchSubmitting"
                        @click="clearSelection"
                    >
                        Clear
                    </Button>
                </div>
            </div>
        </section>

        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                    <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500 dark:bg-gray-950 dark:text-gray-400">
                        <tr>
                            <th class="px-4 py-3">
                                <input
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-950"
                                    :checked="allVisibleSelected"
                                    :disabled="selectableAlertIds.length === 0"
                                    aria-label="Select all open alerts on this page"
                                    @change="toggleAllVisible"
                                />
                            </th>
                            <th class="px-4 py-3">Alert</th>
                            <th class="px-4 py-3">Product</th>
                            <th class="px-4 py-3">Stock</th>
                            <th class="px-4 py-3">Timeline</th>
                            <th class="px-4 py-3">Audit</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-for="alert in alerts.data" :key="alert.id" class="align-top">
                            <td class="px-4 py-4">
                                <input
                                    v-model="selectedIds"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 disabled:cursor-not-allowed disabled:opacity-40 dark:border-gray-700 dark:bg-gray-950"
                                    :value="alert.id"
                                    :disabled="alert.status === 'resolved'"
                                    :aria-label="`Select ${alert.product}`"
                                />
                            </td>

                            <td class="px-4 py-4">
                                <div class="flex flex-wrap gap-2">
                                    <span :class="['rounded-full px-2 py-0.5 text-[11px] font-semibold uppercase', severityClass(alert.severity)]">
                                        {{ alert.severity }}
                                    </span>
                                    <span :class="['rounded-full px-2 py-0.5 text-[11px] font-semibold uppercase', stateClass(alert.state)]">
                                        {{ alert.state }}
                                    </span>
                                </div>
                                <p class="mt-2 font-medium text-gray-900 dark:text-gray-100">{{ alert.type_label }}</p>
                                <p v-if="displayMessage(alert)" class="mt-1 max-w-lg text-sm text-gray-600 dark:text-gray-300">
                                    {{ displayMessage(alert) }}
                                </p>
                                <p v-if="alert.snooze_reason || alert.suppress_reason || alert.resolved_reason" class="mt-2 max-w-lg rounded-md bg-gray-50 px-2 py-1 text-xs text-gray-500 dark:bg-gray-800 dark:text-gray-300">
                                    {{ alert.snooze_reason || alert.suppress_reason || alert.resolved_reason }}
                                </p>
                            </td>

                            <td class="px-4 py-4">
                                <p class="max-w-md font-medium leading-snug text-gray-900 dark:text-gray-100">{{ alert.product }}</p>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">SKU: {{ alert.sku || 'N/A' }}</p>
                                <span :class="['mt-2 inline-flex rounded-full px-2 py-0.5 text-[11px] font-medium', replenishmentClass(alert.replenishment_status)]">
                                    {{ replenishmentLabel(alert.replenishment_status) }}
                                </span>
                            </td>

                            <td class="px-4 py-4 text-gray-600 dark:text-gray-300">
                                <p>On hand: <span class="font-medium text-gray-900 dark:text-gray-100">{{ alert.quantity ?? 'N/A' }}</span></p>
                                <p>Reserved: <span class="font-medium text-gray-900 dark:text-gray-100">{{ alert.reserved ?? 'N/A' }}</span></p>
                                <p>Available: <span class="font-medium text-gray-900 dark:text-gray-100">{{ alert.available ?? 'N/A' }}</span></p>
                            </td>

                            <td class="px-4 py-4 text-xs text-gray-500 dark:text-gray-400">
                                <p>First: {{ formatDate(alert.first_detected_at) }}</p>
                                <p>Last: {{ formatDate(alert.last_seen_at) }}</p>
                                <p v-if="alert.acknowledged_at">Acknowledged: {{ formatDate(alert.acknowledged_at) }}</p>
                                <p v-if="alert.snoozed_until">Snoozed until: {{ formatDate(alert.snoozed_until) }}</p>
                                <p v-if="alert.suppressed_at">Suppressed: {{ formatDate(alert.suppressed_at) }}</p>
                                <p v-if="alert.resolved_at">Resolved: {{ formatDate(alert.resolved_at) }}</p>
                            </td>

                            <td class="px-4 py-4 text-xs text-gray-500 dark:text-gray-400">
                                <div v-if="auditEntries(alert).length" class="space-y-1.5">
                                    <p v-for="[label, entry] in auditEntries(alert)" :key="label">
                                        <span class="font-medium text-gray-700 dark:text-gray-200">{{ label }}:</span>
                                        {{ entry.name }}
                                        <span v-if="entry.at">({{ entry.date_label }} {{ formatDate(entry.at) }})</span>
                                    </p>
                                </div>
                                <p v-else>No staff action yet</p>
                            </td>

                            <td class="px-4 py-4">
                                <div v-if="alert.status !== 'resolved'" class="flex flex-wrap justify-end gap-2">
                                    <Button
                                        v-if="!alert.acknowledged_at && alert.state === 'active'"
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        @click="acknowledge(alert)"
                                    >
                                        Acknowledge
                                    </Button>
                                    <Button
                                        v-if="alert.state === 'active'"
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        @click="openSnoozeDialog(alert)"
                                    >
                                        Snooze
                                    </Button>
                                    <Button
                                        v-if="alert.state !== 'suppressed'"
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        @click="openReasonDialog(alert, 'suppress')"
                                    >
                                        Suppress
                                    </Button>
                                    <Button
                                        v-if="alert.state !== 'active'"
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        @click="reactivate(alert)"
                                    >
                                        Reactivate
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        @click="openReasonDialog(alert, 'resolve')"
                                    >
                                        Resolve
                                    </Button>
                                </div>
                                <p v-else class="text-right text-xs text-gray-500 dark:text-gray-400">No open actions</p>
                            </td>
                        </tr>
                        <tr v-if="alerts.data.length === 0">
                            <td colspan="7" class="px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                                No inventory alerts matched these filters.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="border-t border-gray-200 px-4 py-4 dark:border-gray-800">
                <Pagination :links="alerts.links" />
            </div>
        </section>

        <Dialog :open="batchDialogOpen" @update:open="setBatchDialogOpen">
            <DialogContent class="sm:max-w-md">
                <form class="space-y-4" @submit.prevent="submitBatchAction">
                    <DialogHeader>
                        <DialogTitle>{{ batchDialogTitle }}</DialogTitle>
                        <DialogDescription>{{ batchDialogDescription }}</DialogDescription>
                    </DialogHeader>

                    <div class="rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm dark:border-gray-800 dark:bg-gray-950">
                        <p class="font-medium text-gray-900 dark:text-gray-100">{{ selectedCount }} alert(s) selected</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Only open alerts will be updated.</p>
                    </div>

                    <div v-if="batchAction === 'snooze'" class="space-y-2">
                        <label class="text-sm font-medium text-gray-800 dark:text-gray-200" for="batch-snooze-days">Duration</label>
                        <select
                            id="batch-snooze-days"
                            v-model="batchSnoozeDays"
                            class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100"
                        >
                            <option value="7">7 days</option>
                            <option value="30">30 days</option>
                        </select>
                    </div>

                    <div class="space-y-2">
                        <label class="text-sm font-medium text-gray-800 dark:text-gray-200" for="batch-reason">Reason</label>
                        <textarea
                            id="batch-reason"
                            v-model="batchReason"
                            rows="4"
                            class="min-h-24 w-full resize-y rounded-md border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100"
                            placeholder="Record why these alerts are being updated."
                        />
                    </div>

                    <DialogFooter class="gap-2">
                        <Button type="button" variant="ghost" @click="setBatchDialogOpen(false)">Cancel</Button>
                        <Button type="submit" :disabled="batchSubmitting">{{ batchSubmitLabel }}</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog :open="snoozeDialogOpen" @update:open="setSnoozeDialogOpen">
            <DialogContent class="sm:max-w-md">
                <form class="space-y-4" @submit.prevent="submitSnooze">
                    <DialogHeader>
                        <DialogTitle>Snooze inventory alert</DialogTitle>
                        <DialogDescription>Choose how long this alert should stay out of the active queue.</DialogDescription>
                    </DialogHeader>

                    <div v-if="snoozeAlert" class="rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm dark:border-gray-800 dark:bg-gray-950">
                        <p class="font-medium text-gray-900 dark:text-gray-100">{{ snoozeAlert.product }}</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">SKU: {{ snoozeAlert.sku || 'N/A' }}</p>
                    </div>

                    <div class="space-y-2">
                        <label class="text-sm font-medium text-gray-800 dark:text-gray-200" for="snooze-days">Duration</label>
                        <select
                            id="snooze-days"
                            v-model="snoozeDays"
                            class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100"
                        >
                            <option value="7">7 days</option>
                            <option value="30">30 days</option>
                        </select>
                    </div>

                    <div class="space-y-2">
                        <label class="text-sm font-medium text-gray-800 dark:text-gray-200" for="snooze-reason">Reason</label>
                        <textarea
                            id="snooze-reason"
                            v-model="snoozeReason"
                            rows="4"
                            class="min-h-24 w-full resize-y rounded-md border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100"
                            placeholder="Example: supplier delivery expected next week."
                        />
                    </div>

                    <DialogFooter class="gap-2">
                        <Button type="button" variant="ghost" @click="setSnoozeDialogOpen(false)">Cancel</Button>
                        <Button type="submit" :disabled="snoozeSubmitting">Snooze alert</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog :open="reasonDialogOpen" @update:open="setReasonDialogOpen">
            <DialogContent class="sm:max-w-md">
                <form class="space-y-4" @submit.prevent="submitReasonAction">
                    <DialogHeader>
                        <DialogTitle>{{ reasonDialogTitle }}</DialogTitle>
                        <DialogDescription>{{ reasonDialogDescription }}</DialogDescription>
                    </DialogHeader>

                    <div v-if="reasonAlert" class="rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm dark:border-gray-800 dark:bg-gray-950">
                        <p class="font-medium text-gray-900 dark:text-gray-100">{{ reasonAlert.product }}</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">SKU: {{ reasonAlert.sku || 'N/A' }}</p>
                    </div>

                    <div class="space-y-2">
                        <label class="text-sm font-medium text-gray-800 dark:text-gray-200" for="reason-text">Reason</label>
                        <textarea
                            id="reason-text"
                            v-model="reasonText"
                            rows="4"
                            class="min-h-24 w-full resize-y rounded-md border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100"
                        />
                    </div>

                    <DialogFooter class="gap-2">
                        <Button type="button" variant="ghost" @click="setReasonDialogOpen(false)">Cancel</Button>
                        <Button type="submit" :disabled="reasonSubmitting">{{ reasonSubmitLabel }}</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
