<script setup>
import { computed, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog'
import { AlertTriangle, Check, Clock3, PauseCircle, RotateCcw, ShieldOff } from 'lucide-vue-next'

const props = defineProps({
    alerts: {
        type: [Array, Object],
        required: true,
    },
})

const selectedTab = ref('active')
const openSnoozeAlertId = ref(null)
const snoozeDialogOpen = ref(false)
const snoozeReason = ref('')
const snoozeSubmitting = ref(false)
const pendingSnoozeAlert = ref(null)
const pendingSnoozeDays = ref(null)
const lists = ref({ active: [], snoozed: [], suppressed: [] })
const summary = ref({ active: 0, critical: 0, snoozed: 0, suppressed: 0 })

watch(
    () => props.alerts,
    value => {
        if (Array.isArray(value)) {
            lists.value = { active: value, snoozed: [], suppressed: [] }
            summary.value = {
                active: value.length,
                critical: value.filter(alert => alert.severity === 'critical').length,
                snoozed: 0,
                suppressed: 0,
            }
            return
        }

        lists.value = {
            active: Array.isArray(value?.active) ? value.active : [],
            snoozed: Array.isArray(value?.snoozed) ? value.snoozed : [],
            suppressed: Array.isArray(value?.suppressed) ? value.suppressed : [],
        }

        summary.value = {
            active: Number(value?.summary?.active ?? lists.value.active.length),
            critical: Number(value?.summary?.critical ?? lists.value.active.filter(alert => alert.severity === 'critical').length),
            snoozed: Number(value?.summary?.snoozed ?? lists.value.snoozed.length),
            suppressed: Number(value?.summary?.suppressed ?? lists.value.suppressed.length),
        }
    },
    { immediate: true, deep: true },
)

const tabs = computed(() => [
    { key: 'active', label: 'Active', count: summary.value.active },
    { key: 'snoozed', label: 'Snoozed', count: summary.value.snoozed },
    { key: 'suppressed', label: 'Suppressed', count: summary.value.suppressed },
])

const currentAlerts = computed(() => lists.value[selectedTab.value] || [])
const pendingSnoozeLabel = computed(() => {
    if (!pendingSnoozeDays.value) {
        return ''
    }

    return pendingSnoozeDays.value === 1 ? '1 day' : `${pendingSnoozeDays.value} days`
})

function severityClass(severity) {
    return {
        critical: 'bg-rose-100 text-rose-700 dark:bg-rose-950/40 dark:text-rose-200',
        high: 'bg-orange-100 text-orange-700 dark:bg-orange-950/40 dark:text-orange-200',
        medium: 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-200',
        low: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
    }[severity] || 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200'
}

function replenishmentClass(status) {
    return {
        paused: 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-200',
        discontinued: 'bg-rose-100 text-rose-700 dark:bg-rose-950/40 dark:text-rose-200',
        reorderable: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-200',
    }[status] || 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200'
}

function replenishmentLabel(status) {
    return {
        paused: 'Paused',
        discontinued: 'Discontinued',
        reorderable: 'Reorderable',
    }[status] || 'Unknown'
}

function formatDate(value) {
    if (!value) return 'N/A'
    return new Date(value).toLocaleString()
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

function postAlertAction(alert, action, payload = {}) {
    router.post(`/admin/inventory-alerts/${alert.id}/${action}`, payload, {
        preserveScroll: true,
    })
}

function acknowledge(alert) {
    postAlertAction(alert, 'acknowledge')
}

function toggleSnoozeOptions(alert) {
    openSnoozeAlertId.value = openSnoozeAlertId.value === alert.id ? null : alert.id
}

function openSnoozeDialog(alert, days) {
    openSnoozeAlertId.value = null
    pendingSnoozeAlert.value = alert
    pendingSnoozeDays.value = days
    snoozeReason.value = alert.snooze_reason || ''
    snoozeDialogOpen.value = true
}

function resetSnoozeDialog() {
    if (snoozeSubmitting.value) {
        return
    }

    pendingSnoozeAlert.value = null
    pendingSnoozeDays.value = null
    snoozeReason.value = ''
}

function setSnoozeDialogOpen(open) {
    snoozeDialogOpen.value = open

    if (!open) {
        resetSnoozeDialog()
    }
}

function submitSnooze() {
    if (!pendingSnoozeAlert.value || !pendingSnoozeDays.value) {
        return
    }

    const until = new Date()
    until.setDate(until.getDate() + pendingSnoozeDays.value)

    snoozeSubmitting.value = true

    router.post(`/admin/inventory-alerts/${pendingSnoozeAlert.value.id}/snooze`, {
        snoozed_until: until.toISOString(),
        snooze_reason: snoozeReason.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            snoozeDialogOpen.value = false
            pendingSnoozeAlert.value = null
            pendingSnoozeDays.value = null
            snoozeReason.value = ''
        },
        onFinish: () => {
            snoozeSubmitting.value = false
        },
    })
}

function suppress(alert) {
    const reason = window.prompt('Why should this alert be suppressed?', alert.suppress_reason || '')
    if (reason === null) return

    postAlertAction(alert, 'suppress', {
        suppress_reason: reason,
    })
}

function resolve(alert) {
    const reason = window.prompt('Resolution note', 'Condition reviewed and resolved.')
    if (reason === null) return

    postAlertAction(alert, 'close', {
        resolved_reason: reason,
    })
}

function reactivate(alert) {
    postAlertAction(alert, 'unsuppress')
}
</script>

<template>
    <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
            <div>
                <div class="flex items-center gap-2">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100">Inventory Alerts</h3>
                    <a
                        href="/admin/inventory-alerts"
                        class="text-xs font-medium text-blue-700 hover:underline dark:text-blue-300"
                    >
                        See All
                    </a>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400">Actionable stock exceptions, with snoozed and suppressed items kept out of the main queue.</p>
            </div>

            <div class="flex flex-wrap gap-2 text-xs">
                <span class="rounded-full bg-gray-100 px-2.5 py-1 font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-200">
                    {{ summary.active }} active
                </span>
                <span class="rounded-full bg-rose-100 px-2.5 py-1 font-medium text-rose-700 dark:bg-rose-950/40 dark:text-rose-200">
                    {{ summary.critical }} critical
                </span>
            </div>
        </div>

        <div class="mb-4 grid grid-cols-3 rounded-lg bg-gray-100 p-1 text-xs font-medium dark:bg-gray-800">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                type="button"
                class="rounded-md px-2 py-2 transition"
                :class="selectedTab === tab.key ? 'bg-white text-gray-900 shadow-sm dark:bg-gray-950 dark:text-gray-100' : 'text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200'"
                @click="selectedTab = tab.key"
            >
                {{ tab.label }} ({{ tab.count }})
            </button>
        </div>

        <div v-if="currentAlerts.length" class="max-h-[440px] space-y-3 overflow-y-auto pr-1">
            <article
                v-for="alert in currentAlerts"
                :key="alert.id"
                class="rounded-lg border border-gray-200 p-3 dark:border-gray-800"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0 space-y-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <span :class="['inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide', severityClass(alert.severity)]">
                                {{ alert.severity }}
                            </span>
                            <span class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ alert.type_label || alert.type }}</span>
                            <span
                                v-if="alert.acknowledged_at"
                                class="inline-flex items-center gap-1 rounded-full bg-blue-100 px-2 py-0.5 text-[11px] font-medium text-blue-700 dark:bg-blue-950/40 dark:text-blue-200"
                            >
                                <Check class="h-3 w-3" />
                                Acknowledged
                            </span>
                        </div>

                        <div>
                            <div class="break-words text-sm font-semibold leading-snug text-gray-900 dark:text-gray-100">{{ alert.product }}</div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">SKU: {{ alert.sku || 'N/A' }}</div>
                        </div>

                        <p v-if="displayMessage(alert)" class="text-sm text-gray-700 dark:text-gray-300">{{ displayMessage(alert) }}</p>

                        <div class="flex flex-wrap gap-2 text-[11px] text-gray-500 dark:text-gray-400">
                            <span :class="['rounded-full px-2 py-0.5 font-medium', replenishmentClass(alert.replenishment_status)]">
                                {{ replenishmentLabel(alert.replenishment_status) }}
                            </span>
                            <span>First: {{ formatDate(alert.first_detected_at) }}</span>
                            <span>Last: {{ formatDate(alert.last_seen_at) }}</span>
                            <span v-if="alert.snoozed_until" class="inline-flex items-center gap-1">
                                <Clock3 class="h-3 w-3" />
                                Until {{ formatDate(alert.snoozed_until) }}
                            </span>
                        </div>
                    </div>

                    <AlertTriangle class="mt-0.5 h-5 w-5 flex-shrink-0 text-amber-500" />
                </div>

                <div v-if="alert.snooze_reason || alert.suppress_reason || alert.replenishment_note" class="mt-3 rounded-md bg-gray-50 px-3 py-2 text-xs text-gray-600 dark:bg-gray-800/70 dark:text-gray-300">
                    {{ alert.snooze_reason || alert.suppress_reason || alert.replenishment_note }}
                </div>

                <div class="mt-3 flex flex-wrap gap-2">
                    <button
                        v-if="selectedTab === 'active' && !alert.acknowledged_at"
                        type="button"
                        class="rounded-md border border-blue-200 px-2.5 py-1.5 text-xs font-medium text-blue-700 hover:bg-blue-50 dark:border-blue-900 dark:text-blue-200 dark:hover:bg-blue-950/40"
                        @click="acknowledge(alert)"
                    >
                        Acknowledge
                    </button>
                    <div
                        v-if="selectedTab === 'active'"
                        class="relative"
                    >
                        <button
                            type="button"
                            class="inline-flex items-center gap-1 rounded-md border border-amber-200 px-2.5 py-1.5 text-xs font-medium text-amber-700 hover:bg-amber-50 dark:border-amber-900 dark:text-amber-200 dark:hover:bg-amber-950/40"
                            @click="toggleSnoozeOptions(alert)"
                        >
                            <Clock3 class="h-3.5 w-3.5" />
                            Snooze
                        </button>

                        <div
                            v-if="openSnoozeAlertId === alert.id"
                            class="absolute left-0 top-full z-20 mt-1 min-w-32 rounded-md border border-gray-200 bg-white p-1 shadow-lg dark:border-gray-700 dark:bg-gray-900"
                        >
                            <button
                                type="button"
                                class="block w-full rounded px-2.5 py-1.5 text-left text-xs font-medium text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-800"
                                @click="openSnoozeDialog(alert, 7)"
                            >
                                7 days
                            </button>
                            <button
                                type="button"
                                class="block w-full rounded px-2.5 py-1.5 text-left text-xs font-medium text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-800"
                                @click="openSnoozeDialog(alert, 30)"
                            >
                                30 days
                            </button>
                        </div>
                    </div>
                    <button
                        v-if="selectedTab !== 'suppressed'"
                        type="button"
                        class="inline-flex items-center gap-1 rounded-md border border-purple-200 px-2.5 py-1.5 text-xs font-medium text-purple-700 hover:bg-purple-50 dark:border-purple-900 dark:text-purple-200 dark:hover:bg-purple-950/40"
                        @click="suppress(alert)"
                    >
                        <ShieldOff class="h-3.5 w-3.5" />
                        Suppress
                    </button>
                    <button
                        v-if="selectedTab !== 'active'"
                        type="button"
                        class="inline-flex items-center gap-1 rounded-md border border-emerald-200 px-2.5 py-1.5 text-xs font-medium text-emerald-700 hover:bg-emerald-50 dark:border-emerald-900 dark:text-emerald-200 dark:hover:bg-emerald-950/40"
                        @click="reactivate(alert)"
                    >
                        <RotateCcw class="h-3.5 w-3.5" />
                        Reactivate
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1 rounded-md bg-gray-900 px-2.5 py-1.5 text-xs font-medium text-white hover:bg-gray-800 dark:bg-gray-100 dark:text-gray-900 dark:hover:bg-gray-200"
                        @click="resolve(alert)"
                    >
                        <PauseCircle class="h-3.5 w-3.5" />
                        Resolve
                    </button>
                </div>
            </article>
        </div>

        <div v-else class="rounded-lg border border-dashed border-gray-300 px-3 py-8 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
            No {{ selectedTab }} inventory alerts.
        </div>

        <a
            href="/admin/inventory/discrepancies"
            class="mt-4 inline-flex text-xs font-medium text-blue-700 hover:underline dark:text-blue-300"
        >
            Review discrepancy dashboard
        </a>

        <Dialog :open="snoozeDialogOpen" @update:open="setSnoozeDialogOpen">
            <DialogContent class="sm:max-w-md">
                <form class="space-y-4" @submit.prevent="submitSnooze">
                    <DialogHeader class="space-y-2">
                        <DialogTitle>Snooze inventory alert</DialogTitle>
                        <DialogDescription>
                            Hide this alert from the active queue for {{ pendingSnoozeLabel }}. It will return automatically when the snooze period expires if the condition still exists.
                        </DialogDescription>
                    </DialogHeader>

                    <div v-if="pendingSnoozeAlert" class="rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm dark:border-gray-800 dark:bg-gray-950">
                        <p class="font-medium text-gray-900 dark:text-gray-100">{{ pendingSnoozeAlert.product }}</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">SKU: {{ pendingSnoozeAlert.sku || 'N/A' }}</p>
                    </div>

                    <div class="space-y-2">
                        <label for="inventory-alert-snooze-reason" class="text-sm font-medium text-gray-800 dark:text-gray-200">
                            Reason for snoozing
                        </label>
                        <textarea
                            id="inventory-alert-snooze-reason"
                            v-model="snoozeReason"
                            rows="4"
                            class="min-h-24 w-full resize-y rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 outline-none transition placeholder:text-gray-400 focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100 dark:placeholder:text-gray-500"
                            placeholder="Example: supplier delivery expected next week."
                        />
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Optional, but useful for explaining why this alert is temporarily hidden.
                        </p>
                    </div>

                    <DialogFooter class="gap-2">
                        <Button type="button" variant="ghost" @click="setSnoozeDialogOpen(false)">
                            Cancel
                        </Button>
                        <Button type="submit" :disabled="snoozeSubmitting">
                            Snooze for {{ pendingSnoozeLabel }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </section>
</template>
