<script setup>
import { formatDate } from '@/lib/datetime'
import { Check, ChevronDown } from 'lucide-vue-next'
import {
    SelectContent,
    SelectGroup,
    SelectIcon,
    SelectItem,
    SelectItemIndicator,
    SelectItemText,
    SelectLabel,
    SelectPortal,
    SelectRoot,
    SelectSeparator,
    SelectTrigger,
    SelectValue,
    SelectViewport,
} from 'reka-ui'
import { computed } from 'vue'

const ALL_SESSIONS = 'all'

const props = defineProps({
    modelValue: {
        type: Number,
        default: null,
    },
    sessions: {
        type: Array,
        default: () => [],
    },
    id: {
        type: String,
        default: undefined,
    },
})

const emit = defineEmits(['update:modelValue'])

const selected = computed({
    get: () => (props.modelValue ? String(props.modelValue) : ALL_SESSIONS),
    set: (value) => emit('update:modelValue', value === ALL_SESSIONS ? null : Number(value)),
})

// The item text doubles as the closed trigger's label, so it carries the
// reference, what was audited and when.
function optionLabel(session) {
    return [session.reference, session.scope_label, formatDate(session.submitted_at, null)]
        .filter(Boolean)
        .join(' · ')
}

function optionDetails(session) {
    return [
        session.scope_type === 'category' ? 'Category audit' : 'Full audit',
        session.source_label,
        `${formatPercent(session.coverage_percentage)} scanned`,
        session.submitted_by ? `by ${session.submitted_by}` : null,
    ]
        .filter(Boolean)
        .join(' · ')
}

function formatPercent(value) {
    const number = Number(value || 0)

    return `${Number.isInteger(number) ? number : number.toFixed(1)}%`
}
</script>

<template>
    <SelectRoot v-model="selected">
        <SelectTrigger
            :id="id"
            class="flex h-10 w-full min-w-0 items-center justify-between gap-2 rounded-md border border-slate-300 bg-white px-3 text-left text-sm outline-none transition focus-visible:border-slate-500 focus-visible:ring-2 focus-visible:ring-slate-200 dark:border-slate-700 dark:bg-slate-950 dark:focus-visible:border-slate-500 dark:focus-visible:ring-slate-700"
        >
            <SelectValue class="min-w-0 flex-1 truncate" placeholder="All sessions" />
            <SelectIcon as-child>
                <ChevronDown class="size-4 shrink-0 text-slate-400" aria-hidden="true" />
            </SelectIcon>
        </SelectTrigger>

        <SelectPortal>
            <SelectContent
                position="popper"
                align="start"
                :side-offset="6"
                class="z-50 max-h-[min(26rem,var(--reka-select-content-available-height))] w-[max(var(--reka-select-trigger-width),24rem)] max-w-[calc(100vw-2rem)] overflow-hidden rounded-lg border border-slate-200 bg-white text-slate-900 shadow-lg dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
            >
                <SelectViewport class="p-1">
                    <SelectItem
                        :value="ALL_SESSIONS"
                        class="relative flex cursor-default select-none flex-col rounded-md py-2 pl-3 pr-9 text-sm outline-none data-[highlighted]:bg-slate-100 dark:data-[highlighted]:bg-slate-800"
                    >
                        <span class="font-medium"><SelectItemText>All sessions</SelectItemText></span>
                        <span class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Every open alert, including system stock scans</span>
                        <SelectItemIndicator class="absolute right-3 top-2.5 inline-flex">
                            <Check class="size-4 text-amber-600 dark:text-amber-400" aria-hidden="true" />
                        </SelectItemIndicator>
                    </SelectItem>

                    <SelectSeparator class="my-1 h-px bg-slate-100 dark:bg-slate-800" />

                    <SelectGroup v-if="sessions.length">
                        <SelectLabel class="px-3 pb-1 pt-2 text-[11px] font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">
                            Submitted audits with open alerts
                        </SelectLabel>

                        <SelectItem
                            v-for="session in sessions"
                            :key="session.id"
                            :value="String(session.id)"
                            class="relative flex cursor-default select-none items-start gap-3 rounded-md py-2 pl-3 pr-9 text-sm outline-none data-[highlighted]:bg-slate-100 dark:data-[highlighted]:bg-slate-800"
                        >
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-medium"><SelectItemText>{{ optionLabel(session) }}</SelectItemText></span>
                                <span class="mt-0.5 block truncate text-xs text-slate-500 dark:text-slate-400">{{ optionDetails(session) }}</span>
                            </span>
                            <span
                                class="mt-0.5 shrink-0 rounded-full px-2 py-0.5 text-xs font-semibold tabular-nums"
                                :class="session.open_count
                                    ? 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-200'
                                    : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'"
                            >
                                {{ session.open_count ? `${session.open_count} open` : 'None open' }}
                            </span>
                            <SelectItemIndicator class="absolute right-3 top-2.5 inline-flex">
                                <Check class="size-4 text-amber-600 dark:text-amber-400" aria-hidden="true" />
                            </SelectItemIndicator>
                        </SelectItem>
                    </SelectGroup>

                    <p v-else class="px-3 py-4 text-center text-xs text-slate-500 dark:text-slate-400">
                        No submitted audit sessions have open alerts.
                    </p>
                </SelectViewport>
            </SelectContent>
        </SelectPortal>
    </SelectRoot>
</template>
