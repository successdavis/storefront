<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import { ref, watch } from 'vue'

const props = defineProps<{
    methods: {
        data: Array<Record<string, any>>
        links: Array<{ url: string | null; label: string; active: boolean }>
    }
    filters: {
        search?: string
        status?: string
        type?: string
    }
}>()

const search = ref(props.filters?.search ?? '')
const status = ref(props.filters?.status ?? '')
const type = ref(props.filters?.type ?? '')

let filterTimeout: number | undefined

watch(search, () => {
    window.clearTimeout(filterTimeout)
    filterTimeout = window.setTimeout(applyFilters, 300)
})

watch([status, type], () => applyFilters())

function applyFilters() {
    router.get(route('admin.shipping-methods.index'), {
        search: search.value || undefined,
        status: status.value || undefined,
        type: type.value || undefined,
    }, {
        preserveState: true,
        replace: true,
    })
}

function toggleStatus(id: number) {
    router.patch(route('admin.shipping-methods.toggle-status', id), {}, { preserveScroll: true })
}

function badgeClass(active: boolean) {
    return active
        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300'
        : 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-200'
}
</script>

<template>
    <Head title="Shipping Methods" />

    <div class="space-y-6 px-5 py-4">
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold text-slate-900 dark:text-slate-100">Shipping Methods</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500 dark:text-slate-400">
                        Manage the delivery and pickup methods customers can choose from during checkout and POS.
                    </p>
                </div>

                <Link
                    :href="route('admin.shipping-methods.create')"
                    class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-300"
                >
                    Create method
                </Link>
            </div>

            <div class="mt-5 grid gap-3 md:grid-cols-[1.4fr_0.8fr_0.8fr]">
                <input v-model="search" type="search" placeholder="Search methods" class="h-11 rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 placeholder:text-slate-400 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100 dark:placeholder:text-slate-500">
                <select v-model="status" class="h-11 rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                    <option value="">All statuses</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
                <select v-model="type" class="h-11 rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                    <option value="">All types</option>
                    <option value="delivery">Delivery</option>
                    <option value="pickup">Pickup</option>
                </select>
            </div>
        </section>

        <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead class="bg-slate-50 dark:bg-slate-950">
                        <tr class="text-left text-xs uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">
                            <th class="px-5 py-4">Method</th>
                            <th class="px-5 py-4">Type</th>
                            <th class="px-5 py-4">Coverage</th>
                            <th class="px-5 py-4">Order</th>
                            <th class="px-5 py-4">Status</th>
                            <th class="px-5 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white dark:divide-slate-800 dark:bg-slate-900">
                        <tr v-for="method in methods.data" :key="method.id" class="align-top">
                            <td class="px-5 py-4">
                                <div class="font-semibold text-slate-900 dark:text-slate-100">{{ method.name }}</div>
                                <p v-if="method.description" class="mt-1 max-w-md text-xs leading-5 text-slate-500 dark:text-slate-400">{{ method.description }}</p>
                            </td>
                            <td class="px-5 py-4 text-slate-600 dark:text-slate-300">
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold capitalize dark:bg-slate-800 dark:text-slate-200">
                                    {{ method.method_type }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-xs leading-5 text-slate-500 dark:text-slate-400">
                                <div>{{ method.rate_count }} rates</div>
                                <div class="mt-1">{{ method.pickup_location_count }} pickup locations</div>
                            </td>
                            <td class="px-5 py-4 text-slate-600 dark:text-slate-300">{{ method.sort_order }}</td>
                            <td class="px-5 py-4">
                                <span :class="['inline-flex rounded-full px-2.5 py-1 text-xs font-semibold', badgeClass(method.is_active)]">
                                    {{ method.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex justify-end gap-2">
                                    <button
                                        type="button"
                                        class="rounded-xl border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:border-slate-500 dark:border-slate-600 dark:text-slate-200 dark:hover:border-slate-400"
                                        @click="toggleStatus(method.id)"
                                    >
                                        {{ method.is_active ? 'Deactivate' : 'Activate' }}
                                    </button>
                                    <Link
                                        :href="route('admin.shipping-methods.edit', method.id)"
                                        class="rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white transition hover:bg-slate-700 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-300"
                                    >
                                        Edit
                                    </Link>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="methods.data.length === 0">
                            <td colspan="6" class="px-5 py-14 text-center text-sm text-slate-500 dark:text-slate-400">
                                No shipping methods matched the current filters.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="methods.links?.length" class="flex flex-wrap gap-2 border-t border-slate-200 px-5 py-4 dark:border-slate-800">
                <button
                    v-for="link in methods.links"
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
