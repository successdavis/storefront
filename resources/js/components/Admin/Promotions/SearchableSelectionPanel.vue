<script setup lang="ts">
import { computed, ref } from 'vue'

interface Option {
    id: number
    label: string
    meta?: string | null
}

const props = withDefaults(defineProps<{
    title: string
    description?: string
    options: Option[]
    modelValue: number[]
    searchPlaceholder?: string
    emptyLabel?: string
}>(), {
    description: '',
    searchPlaceholder: 'Search options',
    emptyLabel: 'No matching records found.',
})

const emit = defineEmits<{
    (event: 'update:modelValue', value: number[]): void
}>()

const search = ref('')

const filteredOptions = computed(() => {
    const term = search.value.trim().toLowerCase()
    if (term === '') {
        return props.options
    }

    return props.options.filter((option) =>
        `${option.label} ${option.meta ?? ''}`.toLowerCase().includes(term),
    )
})

function toggleOption(optionId: number) {
    const selected = new Set(props.modelValue || [])

    if (selected.has(optionId)) {
        selected.delete(optionId)
    } else {
        selected.add(optionId)
    }

    emit('update:modelValue', Array.from(selected))
}

function clearAll() {
    emit('update:modelValue', [])
}
</script>

<template>
    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-900">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ title }}</h3>
                <p v-if="description" class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">{{ description }}</p>
            </div>
            <button
                v-if="modelValue.length"
                type="button"
                class="text-xs font-semibold text-slate-500 transition hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-100"
                @click="clearAll"
            >
                Clear all
            </button>
        </div>

        <input
            v-model="search"
            type="search"
            :placeholder="searchPlaceholder"
            class="mt-3 h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 placeholder:text-slate-400 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100 dark:placeholder:text-slate-500"
        >

        <div class="mt-3 max-h-60 space-y-2 overflow-y-auto pr-1">
            <label
                v-for="option in filteredOptions"
                :key="option.id"
                class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm transition hover:border-slate-400 dark:border-slate-700 dark:bg-slate-950 dark:hover:border-slate-500"
            >
                <input
                    :checked="modelValue.includes(option.id)"
                    type="checkbox"
                    class="mt-0.5 h-4 w-4 rounded border-slate-300 dark:border-slate-600 dark:bg-slate-900"
                    @change="toggleOption(option.id)"
                >
                <span class="min-w-0 flex-1">
                    <span class="block font-medium text-slate-900 dark:text-slate-100">{{ option.label }}</span>
                    <span v-if="option.meta" class="mt-1 block truncate text-xs text-slate-500 dark:text-slate-400">{{ option.meta }}</span>
                </span>
            </label>

            <div
                v-if="filteredOptions.length === 0"
                class="rounded-xl border border-dashed border-slate-300 bg-white px-4 py-6 text-center text-xs text-slate-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-400"
            >
                {{ emptyLabel }}
            </div>
        </div>
    </div>
</template>
