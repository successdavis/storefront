<script setup lang="ts">
withDefaults(defineProps<{
    timeline: Array<{
        id: number;
        status_type_label: string;
        previous_status_label?: string | null;
        new_status_label: string;
        note?: string | null;
        changed_by?: { name: string } | null;
        created_at?: string | null;
    }>;
    emptyMessage?: string;
    showActor?: boolean;
}>(), {
    emptyMessage: 'No order activity has been recorded yet.',
    showActor: false,
});

function formatDate(value?: string | null) {
    if (!value) return null;
    return new Date(value).toLocaleString();
}
</script>

<template>
    <div class="space-y-4">
        <div v-if="timeline.length" class="space-y-4">
            <div v-for="entry in timeline" :key="entry.id" class="flex gap-4">
                <div class="flex flex-col items-center">
                    <div class="mt-1 h-3 w-3 rounded-full bg-slate-900 dark:bg-slate-100" />
                    <div class="h-full w-px bg-slate-200 dark:bg-slate-800" />
                </div>
                <div class="min-w-0 flex-1 rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">
                            {{ entry.status_type_label }}: {{ entry.new_status_label }}
                        </p>
                        <p v-if="formatDate(entry.created_at)" class="text-xs text-slate-500 dark:text-slate-400">
                            {{ formatDate(entry.created_at) }}
                        </p>
                    </div>
                    <p v-if="entry.previous_status_label" class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        From {{ entry.previous_status_label }}
                    </p>
                    <p v-if="entry.note" class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ entry.note }}</p>
                    <p v-if="showActor && entry.changed_by?.name" class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                        Updated by {{ entry.changed_by.name }}
                    </p>
                </div>
            </div>
        </div>

        <div v-else class="rounded-2xl border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">
            {{ emptyMessage }}
        </div>
    </div>
</template>
