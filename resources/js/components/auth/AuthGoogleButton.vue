<script setup lang="ts">
defineProps<{
    label: string;
}>();
</script>

<template>
    <a
        href="/auth/google/redirect"
        class="inline-flex w-full items-center justify-center gap-3 rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:bg-slate-50"
    >
        <svg class="h-5 w-5" viewBox="0 0 24 24" aria-hidden="true">
            <path fill="#EA4335" d="M12 10.2v3.9h5.5c-.2 1.3-1.6 3.9-5.5 3.9-3.3 0-6-2.8-6-6.2s2.7-6.2 6-6.2c1.9 0 3.2.8 3.9 1.5l2.7-2.7C17.2 2.8 14.8 1.8 12 1.8 6.6 1.8 2.2 6.3 2.2 12s4.4 10.2 9.8 10.2c5.7 0 9.4-4 9.4-9.6 0-.6-.1-1.1-.2-1.5H12Z" />
            <path fill="#34A853" d="M2.2 12c0 5.7 4.4 10.2 9.8 10.2 5.7 0 9.4-4 9.4-9.6 0-.6-.1-1.1-.2-1.5H12v3.9h5.5c-.2 1.3-1.6 3.9-5.5 3.9-3.3 0-6-2.8-6-6.2H2.2Z" opacity=".15" />
            <path fill="#FBBC05" d="M4.4 7.1 7.6 9.4C8.5 7.2 10.1 5.8 12 5.8c1.9 0 3.2.8 3.9 1.5l2.7-2.7C17.2 2.8 14.8 1.8 12 1.8 8.2 1.8 4.9 4 4.4 7.1Z" />
            <path fill="#4285F4" d="M12 22.2c2.6 0 4.9-.9 6.5-2.5l-3-2.4c-.8.6-1.9 1-3.5 1-3.9 0-5.3-2.6-5.5-3.9l-3.2 2.5c1.7 3.3 5 5.3 8.7 5.3Z" />
        </svg>
        {{ label }}
    </a>
</template>
