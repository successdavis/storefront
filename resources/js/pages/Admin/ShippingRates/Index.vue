<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import { ref, watch } from 'vue'

const props = defineProps<{
    rates: {
        data: Array<Record<string, any>>
        links: Array<{ url: string | null; label: string; active: boolean }>
    }
    methods: Array<{ id: number; name: string }>
    filters: {
        search?: string
        status?: string
        method_id?: string | number
        scope?: string
    }
}>()

const search = ref(props.filters?.search ?? '')
const status = ref(props.filters?.status ?? '')
const methodId = ref(props.filters?.method_id ?? '')
const scope = ref(props.filters?.scope ?? '')

let filterTimeout: number | undefined

watch(search, () => {
    window.clearTimeout(filterTimeout)
    filterTimeout = window.setTimeout(applyFilters, 300)
})

watch([status, methodId, scope], () => applyFilters())

function applyFilters() {
    router.get(route('admin.shipping-rates.index'), {
        search: search.value || undefined,
        status: status.value || undefined,
        method_id: methodId.value || undefined,
        scope: scope.value || undefined,
    }, {
        preserveState: true,
        replace: true,
    })
}

function toggleStatus(id: number) {
    router.patch(route('admin.shipping-rates.toggle-status', id), {}, { preserveScroll: true })
}

function badgeClass(statusLabel: string) {
    return {
        Active: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
        Scheduled: 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
        Expired: 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300',
        Inactive: 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-200',
    }[statusLabel] || 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-200'
}
</script>

<template>
    <Head title="Shipping Rates" />

    <div class="space-y-6 px-5 py-4">
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold text-slate-900 dark:text-slate-100">Shipping Rates</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500 dark:text-slate-400">
                        Configure geographic delivery rules with safe precedence across global, zone, state, and LGA scopes.
                    </p>
                </div>

                <Link
                    :href="route('admin.shipping-rates.create')"
                    class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-300"
                >
                    Create rate
                </Link>
            </div>

            <div class="mt-5 grid gap-3 xl:grid-cols-[1.2fr_0.7fr_0.9fr_0.8fr]">
                <input v-model="search" type="search" placeholder="Search by method or ETA" class="h-11 rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 placeholder:text-slate-400 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100 dark:placeholder:text-slate-500">
                <select v-model="status" class="h-11 rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                    <option value="">All statuses</option>
                    <option value="active">Active</option>
                    <option value="scheduled">Scheduled</option>
                    <option value="expired">Expired</option>
                    <option value="inactive">Inactive</option>
                </select>
                <select v-model="methodId" class="h-11 rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                    <option value="">All methods</option>
                    <option v-for="method in methods" :key="method.id" :value="method.id">
                        {{ method.name }}
                    </option>
                </select>
                <select v-model="scope" class="h-11 rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                    <option value="">All scopes</option>
                    <option value="global">Global</option>
                    <option value="zone">Zone</option>
                    <option value="state">State</option>
                    <option value="lga">LGA</option>
                </select>
            </div>
        </section>

        <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead class="bg-slate-50 dark:bg-slate-950">
                        <tr class="text-left text-xs uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">
                            <th class="px-5 py-4">Method</th>
                            <th class="px-5 py-4">Scope</th>
                            <th class="px-5 py-4">Pricing</th>
                            <th class="px-5 py-4">Thresholds</th>
                            <th class="px-5 py-4">Status</th>
                            <th class="px-5 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white dark:divide-slate-800 dark:bg-slate-900">
                        <tr v-for="rate in rates.data" :key="rate.id" class="align-top">
                            <td class="px-5 py-4">
                                <div class="font-semibold text-slate-900 dark:text-slate-100">{{ rate.method.name }}</div>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                    {{ rate.estimated_delivery_text || 'No ETA text configured' }}
                                </p>
                            </td>
                            <td class="px-5 py-4 text-slate-600 dark:text-slate-300">
                                <div class="font-medium text-slate-900 dark:text-slate-100">{{ rate.scope }}</div>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ rate.scope_meta }}</p>
                            </td>
                            <td class="px-5 py-4 text-xs leading-5 text-slate-500 dark:text-slate-400">
                                <div>{{ rate.rate_type }} | Base: NGN {{ rate.base_rate }}</div>
                                <div class="mt-1">Per kg: NGN {{ rate.per_kg }} | Surcharge: NGN {{ rate.surcharge }}</div>
                                <div v-if="rate.free_shipping_threshold !== null" class="mt-1">Free after NGN {{ rate.free_shipping_threshold }}</div>
                            </td>
                            <td class="px-5 py-4 text-xs leading-5 text-slate-500 dark:text-slate-400">
                                <div>Weight: {{ rate.min_weight ?? 'Any' }} - {{ rate.max_weight ?? 'Any' }}</div>
                                <div class="mt-1">Subtotal: {{ rate.min_subtotal ?? 'Any' }} - {{ rate.max_subtotal ?? 'Any' }}</div>
                                <div class="mt-1">Order {{ rate.sort_order }}</div>
                            </td>
                            <td class="px-5 py-4">
                                <span :class="['inline-flex rounded-full px-2.5 py-1 text-xs font-semibold', badgeClass(rate.status)]">
                                    {{ rate.status }}
                                </span>
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex justify-end gap-2">
                                    <button
                                        type="button"
                                        class="rounded-xl border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:border-slate-500 dark:border-slate-600 dark:text-slate-200 dark:hover:border-slate-400"
                                        @click="toggleStatus(rate.id)"
                                    >
                                        {{ rate.is_active ? 'Deactivate' : 'Activate' }}
                                    </button>
                                    <Link
                                        :href="route('admin.shipping-rates.edit', rate.id)"
                                        class="rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white transition hover:bg-slate-700 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-300"
                                    >
                                        Edit
                                    </Link>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="rates.data.length === 0">
                            <td colspan="6" class="px-5 py-14 text-center text-sm text-slate-500 dark:text-slate-400">
                                No shipping rates matched the current filters.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="rates.links?.length" class="flex flex-wrap gap-2 border-t border-slate-200 px-5 py-4 dark:border-slate-800">
                <button
                    v-for="link in rates.links"
                    :key="`${link.label}-${link.url}`"
                    type="button"
                    :disabled="!link.url"
                    v-html="link.label"
                    :class="[
                        'rounded-lg border px-3 py-1.5 text-sm transition',
                        link.active ? 'border-slate-900 bg-slate-900 text-white dark:border-slate-100 dark:bg-slate-100 dark:text-slate-900' : 'border-slate-300 bg-white text-slate-700 hover:border-slate-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200 dark:hover:border-slate-500',
                        !link.url ? 'cursor-not-allowed opacity-40' : '',
                    ]"
                    @click="link.url && router.visit(link.url, { preserveState: true })"
                />
            </div>
        </section>
    </div>
</template>
