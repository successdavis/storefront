<script setup>
import { X } from 'lucide-vue-next'
import { onBeforeUnmount, onMounted } from 'vue'

const props = defineProps({
    // { url, label, subtitle } or null when closed
    preview: {
        type: Object,
        default: null,
    },
})

const emit = defineEmits(['close'])

function handleKeydown(event) {
    if (event.key === 'Escape' && props.preview) {
        emit('close')
    }
}

onMounted(() => document.addEventListener('keydown', handleKeydown))
onBeforeUnmount(() => document.removeEventListener('keydown', handleKeydown))
</script>

<template>
    <Teleport to="body">
        <div
            v-if="preview"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4"
            role="dialog"
            aria-modal="true"
            :aria-label="`Image of ${preview.label}`"
            @click.self="emit('close')"
        >
            <div class="w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-5 shadow-2xl dark:border-slate-700 dark:bg-slate-900">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <h2 class="truncate text-base font-semibold text-slate-900 dark:text-slate-100">
                            {{ preview.label }}
                        </h2>
                        <p v-if="preview.subtitle" class="mt-1 truncate text-xs text-slate-500 dark:text-slate-400">
                            {{ preview.subtitle }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg p-1 text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100"
                        aria-label="Close image preview"
                        @click="emit('close')"
                    >
                        <X class="h-5 w-5" aria-hidden="true" />
                    </button>
                </div>
                <img
                    :src="preview.url"
                    :alt="preview.label"
                    class="mt-4 max-h-[65vh] w-full rounded-xl object-contain"
                >
            </div>
        </div>
    </Teleport>
</template>
