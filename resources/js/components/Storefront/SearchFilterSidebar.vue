<script setup>
import { Checkbox } from '@/components/ui/checkbox'
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible'
import { ChevronDown, RotateCcw, SlidersHorizontal, Tags } from 'lucide-vue-next'

defineProps({
    priceRange: {
        type: Object,
        required: true,
    },
    toggleFilters: {
        type: Array,
        default: () => [],
    },
    filterGroups: {
        type: Array,
        default: () => [],
    },
    minPriceDraft: {
        type: [String, Number],
        default: '',
    },
    maxPriceDraft: {
        type: [String, Number],
        default: '',
    },
})

const emit = defineEmits([
    'update:minPriceDraft',
    'update:maxPriceDraft',
    'applyPrice',
    'toggleOption',
    'toggleFlag',
    'clearAll',
])

function optionId(groupKey, value) {
    return `${groupKey}-${String(value).replace(/\s+/g, '-').toLowerCase()}`
}
</script>

<template>
    <div class="space-y-5">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Filters</p>
                <p class="text-xs text-slate-500 dark:text-slate-400">Refine the current search context.</p>
            </div>

            <button
                type="button"
                class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 transition hover:border-slate-300 hover:text-slate-900 dark:border-slate-700 dark:text-slate-300 dark:hover:border-slate-500 dark:hover:text-slate-100"
                @click="emit('clearAll')"
            >
                <RotateCcw class="size-3.5" />
                Clear all
            </button>
        </div>

        <section class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950">
            <div class="flex items-center gap-2 text-sm font-semibold text-slate-900 dark:text-slate-100">
                <SlidersHorizontal class="size-4" />
                Price Range
            </div>

            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                Available from NGN {{ Number(priceRange.min || 0).toLocaleString() }} to NGN {{ Number(priceRange.max || 0).toLocaleString() }}
            </p>

            <div class="mt-4 grid grid-cols-2 gap-3">
                <label class="space-y-1">
                    <span class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Min</span>
                    <input
                        :value="minPriceDraft"
                        type="number"
                        min="0"
                        step="0.01"
                        class="h-11 w-full rounded-2xl border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-200 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-100 dark:focus:border-amber-400 dark:focus:ring-amber-400/20"
                        @input="emit('update:minPriceDraft', $event.target.value)"
                    >
                </label>

                <label class="space-y-1">
                    <span class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Max</span>
                    <input
                        :value="maxPriceDraft"
                        type="number"
                        min="0"
                        step="0.01"
                        class="h-11 w-full rounded-2xl border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-200 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-100 dark:focus:border-amber-400 dark:focus:ring-amber-400/20"
                        @input="emit('update:maxPriceDraft', $event.target.value)"
                    >
                </label>
            </div>

            <button
                type="button"
                class="mt-4 inline-flex h-11 w-full items-center justify-center rounded-2xl bg-slate-900 px-4 text-sm font-semibold text-white transition hover:bg-slate-700 dark:bg-amber-500 dark:text-slate-950 dark:hover:bg-amber-400"
                @click="emit('applyPrice')"
            >
                Apply Price
            </button>
        </section>

        <section class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950">
            <div class="flex items-center gap-2 text-sm font-semibold text-slate-900 dark:text-slate-100">
                <Tags class="size-4" />
                Quick Filters
            </div>

            <div class="mt-4 space-y-3">
                <label
                    v-for="toggle in toggleFilters"
                    :key="toggle.key"
                    class="flex cursor-pointer items-start gap-3 rounded-2xl border border-slate-100 px-3 py-3 transition hover:border-slate-200 dark:border-slate-800 dark:hover:border-slate-700"
                >
                    <Checkbox
                        :checked="toggle.selected"
                        @update:checked="emit('toggleFlag', toggle.key)"
                    />
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-medium text-slate-800 dark:text-slate-100">{{ toggle.label }}</span>
                        <span class="mt-1 block text-xs text-slate-500 dark:text-slate-400">{{ toggle.count }} matching products</span>
                    </span>
                </label>
            </div>
        </section>

        <section
            v-for="group in filterGroups"
            :key="group.key"
            class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-950"
        >
            <Collapsible :default-open="true">
                <CollapsibleTrigger class="flex w-full items-center justify-between gap-3 text-left">
                    <div>
                        <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ group.label }}</p>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ group.options.length }} options</p>
                    </div>
                    <ChevronDown class="size-4 text-slate-400 transition data-[state=open]:rotate-180" />
                </CollapsibleTrigger>

                <CollapsibleContent class="pt-4">
                    <div class="space-y-3">
                        <label
                            v-for="option in group.options"
                            :key="optionId(group.key, option.value)"
                            :for="optionId(group.key, option.value)"
                            class="flex cursor-pointer items-start gap-3 rounded-2xl border border-slate-100 px-3 py-3 transition hover:border-slate-200 dark:border-slate-800 dark:hover:border-slate-700"
                        >
                            <Checkbox
                                :id="optionId(group.key, option.value)"
                                :checked="option.selected"
                                @update:checked="emit('toggleOption', group.key, option.value)"
                            />
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-medium text-slate-800 dark:text-slate-100">{{ option.label }}</span>
                                <span class="mt-1 block text-xs text-slate-500 dark:text-slate-400">{{ option.count }} matching products</span>
                            </span>
                        </label>
                    </div>
                </CollapsibleContent>
            </Collapsible>
        </section>
    </div>
</template>
