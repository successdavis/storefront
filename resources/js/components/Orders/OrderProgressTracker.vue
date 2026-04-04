<script setup lang="ts">
const props = defineProps<{
    tracker: {
        steps: Array<{
            key: string;
            label: string;
            status: 'complete' | 'current' | 'upcoming' | string;
            timestamp?: string | null;
        }>;
        state?: {
            kind: string;
            label: string;
            description: string;
        } | null;
    };
}>();

function stepClasses(status: string) {
    if (status === 'complete') {
        return 'border-emerald-500 bg-emerald-500 text-white';
    }

    if (status === 'current') {
        return 'border-slate-900 bg-slate-900 text-white dark:border-slate-100 dark:bg-slate-100 dark:text-slate-900';
    }

    return 'border-slate-300 bg-white text-slate-400 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-600';
}

function connectorClasses(status: string) {
    return status === 'complete'
        ? 'bg-emerald-500 dark:bg-emerald-400'
        : 'bg-slate-200 dark:bg-slate-800';
}

function stateClasses(kind?: string) {
    if (kind === 'cancelled') {
        return 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900/40 dark:bg-rose-950/40 dark:text-rose-200';
    }

    if (kind === 'refunded') {
        return 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900/40 dark:bg-amber-950/40 dark:text-amber-200';
    }

    return 'border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-200';
}

function formatDate(value?: string | null) {
    if (!value) return null;
    return new Date(value).toLocaleString();
}
</script>

<template>
    <div class="space-y-4">
        <div class="grid gap-4 md:grid-cols-5">
            <div v-for="(step, index) in tracker.steps" :key="step.key" class="relative flex gap-3 md:block">
                <div class="flex flex-col items-center md:items-start">
                    <div :class="['flex h-10 w-10 items-center justify-center rounded-full border-2 text-sm font-semibold transition', stepClasses(step.status)]">
                        {{ index + 1 }}
                    </div>
                    <div v-if="index < tracker.steps.length - 1" :class="['mt-2 h-12 w-0.5 md:hidden', connectorClasses(step.status)]" />
                </div>

                <div class="min-w-0 flex-1 md:pt-3">
                    <div class="hidden md:block">
                        <div v-if="index < tracker.steps.length - 1" :class="['absolute left-[calc(50%+1.75rem)] right-[-50%] top-5 h-0.5', connectorClasses(step.status)]" />
                    </div>
                    <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ step.label }}</p>
                    <p class="mt-1 text-xs uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">{{ step.status }}</p>
                    <p v-if="formatDate(step.timestamp)" class="mt-2 text-xs text-slate-500 dark:text-slate-400">{{ formatDate(step.timestamp) }}</p>
                </div>
            </div>
        </div>

        <div
            v-if="tracker.state"
            :class="['rounded-2xl border px-4 py-3 text-sm', stateClasses(tracker.state.kind)]"
        >
            <p class="font-semibold">{{ tracker.state.label }}</p>
            <p class="mt-1">{{ tracker.state.description }}</p>
        </div>
    </div>
</template>
