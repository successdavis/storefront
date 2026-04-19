<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import { ArrowLeft, Database, Globe2, ShieldCheck } from 'lucide-vue-next'
import { reactive } from 'vue'

const props = defineProps<{
    settings: {
        enabled: boolean
        capture_referrers: boolean
        track_authenticated_pages: boolean
        raw_retention_days: number
        aggregation_refresh_window_days: number
    }
    permissions: { can_manage: boolean }
}>()

const form = reactive({
    enabled: props.settings.enabled,
    capture_referrers: props.settings.capture_referrers,
    track_authenticated_pages: props.settings.track_authenticated_pages,
    raw_retention_days: props.settings.raw_retention_days,
    aggregation_refresh_window_days: props.settings.aggregation_refresh_window_days,
})

function submit() {
    router.patch('/admin/analytics/settings', form, {
        preserveScroll: true,
    })
}
</script>

<template>
    <Head title="Analytics Settings" />

    <div class="min-h-screen space-y-6 bg-slate-100 p-6 dark:bg-slate-950">
        <div class="flex flex-col gap-4 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900 lg:flex-row lg:items-end lg:justify-between">
            <div class="space-y-2">
                <p class="text-sm font-medium uppercase tracking-[0.24em] text-sky-500">Storefront analytics</p>
                <h1 class="text-3xl font-semibold tracking-tight text-slate-950 dark:text-white">Analytics settings</h1>
                <p class="max-w-3xl text-sm text-slate-600 dark:text-slate-300">
                    Control capture behavior, retention, and reporting freshness without touching deploy-time configuration.
                </p>
            </div>

            <Link
                href="/admin/analytics"
                class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition hover:border-sky-300 hover:text-sky-600 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200 dark:hover:border-sky-500/50 dark:hover:text-sky-300"
            >
                <ArrowLeft class="h-4 w-4" />
                Back to overview
            </Link>
        </div>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1.2fr)_minmax(320px,0.8fr)]">
            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="mb-6 space-y-2">
                    <h2 class="text-lg font-semibold text-slate-950 dark:text-white">Capture controls</h2>
                    <p class="text-sm text-slate-600 dark:text-slate-300">
                        These settings affect storefront analytics ingestion only. Admin traffic and internal pages remain excluded.
                    </p>
                </div>

                <div class="grid gap-4">
                    <label class="flex items-center justify-between rounded-2xl border border-slate-200 px-4 py-4 dark:border-slate-800">
                        <div class="space-y-1">
                            <p class="text-sm font-medium text-slate-900 dark:text-slate-100">Enable storefront analytics</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Stops new storefront visit capture while leaving historical reports intact.</p>
                        </div>
                        <input v-model="form.enabled" type="checkbox" class="h-4 w-4 rounded border-slate-400 text-sky-500 focus:ring-sky-500" />
                    </label>

                    <label class="flex items-center justify-between rounded-2xl border border-slate-200 px-4 py-4 dark:border-slate-800">
                        <div class="space-y-1">
                            <p class="text-sm font-medium text-slate-900 dark:text-slate-100">Track signed-in customer pages</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Keeps the authenticated-versus-guest traffic split available in reports.</p>
                        </div>
                        <input v-model="form.track_authenticated_pages" type="checkbox" class="h-4 w-4 rounded border-slate-400 text-sky-500 focus:ring-sky-500" />
                    </label>

                    <label class="flex items-center justify-between rounded-2xl border border-slate-200 px-4 py-4 dark:border-slate-800">
                        <div class="space-y-1">
                            <p class="text-sm font-medium text-slate-900 dark:text-slate-100">Capture external referrers</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">Stores referrer domains only, not the full external URL path.</p>
                        </div>
                        <input v-model="form.capture_referrers" type="checkbox" class="h-4 w-4 rounded border-slate-400 text-sky-500 focus:ring-sky-500" />
                    </label>

                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="space-y-2 text-sm text-slate-600 dark:text-slate-300">
                            <span>Raw retention days</span>
                            <input v-model="form.raw_retention_days" type="number" min="30" max="365" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-sky-400 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100" />
                        </label>

                        <label class="space-y-2 text-sm text-slate-600 dark:text-slate-300">
                            <span>Aggregate refresh window (days)</span>
                            <input v-model="form.aggregation_refresh_window_days" type="number" min="1" max="90" class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-sky-400 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100" />
                        </label>
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-2xl bg-slate-950 px-4 py-3 text-sm font-medium text-white transition hover:bg-slate-800 dark:bg-white dark:text-slate-950 dark:hover:bg-slate-200"
                        @click="submit"
                    >
                        Save analytics settings
                    </button>
                </div>
            </section>

            <section class="space-y-6">
                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="mb-3 flex items-center gap-3">
                        <ShieldCheck class="h-5 w-5 text-emerald-500" />
                        <h2 class="text-lg font-semibold text-slate-950 dark:text-white">Privacy guardrails</h2>
                    </div>
                    <ul class="space-y-3 text-sm text-slate-600 dark:text-slate-300">
                        <li>Visitor identities are tracked with anonymized keys instead of exposing raw IP addresses in reports.</li>
                        <li>Admin routes, internal pages, and common bot traffic stay out of storefront reporting.</li>
                        <li>Referrer capture stores the domain only, keeping payload size and privacy risk low.</li>
                    </ul>
                </div>

                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="mb-3 flex items-center gap-3">
                        <Database class="h-5 w-5 text-sky-500" />
                        <h2 class="text-lg font-semibold text-slate-950 dark:text-white">Aggregation behavior</h2>
                    </div>
                    <p class="text-sm text-slate-600 dark:text-slate-300">
                        The scheduler keeps daily aggregates fresh every 15 minutes and prunes raw page views daily based on the retention window above.
                    </p>
                </div>

                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="mb-3 flex items-center gap-3">
                        <Globe2 class="h-5 w-5 text-violet-500" />
                        <h2 class="text-lg font-semibold text-slate-950 dark:text-white">Operational note</h2>
                    </div>
                    <p class="text-sm text-slate-600 dark:text-slate-300">
                        Geolocation relies on existing browser location hints and trusted proxy headers, so the storefront avoids slow external lookups on live requests.
                    </p>
                </div>
            </section>
        </div>
    </div>
</template>
