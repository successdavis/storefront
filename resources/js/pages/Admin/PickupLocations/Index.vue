<script setup lang="ts">
import Pagination from '@/components/Pagination.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { ref, watch } from 'vue'

const props = defineProps<{
    filters: { search?: string; status?: string }
    locations: {
        data: Array<{
            id: number
            name: string
            method: string | null
            state: string | null
            lga: string | null
            address_line1: string | null
            phone: string | null
            is_active: boolean
        }>
        links: Array<{ url: string | null; label: string; active: boolean }>
    }
}>()

const search = ref(props.filters?.search ?? '')
const status = ref(props.filters?.status ?? '')

let searchTimeout: ReturnType<typeof setTimeout>

function reload() {
    router.get(route('admin.pickup-locations.index'), {
        search: search.value || undefined,
        status: status.value || undefined,
    }, {
        preserveState: true,
        replace: true,
    })
}

watch(status, reload)
watch(search, () => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(reload, 350)
})

function toggleStatus(id: number) {
    router.patch(route('admin.pickup-locations.toggle-status', id), {}, { preserveScroll: true })
}
</script>

<template>
    <Head title="Pickup Locations" />

    <div class="space-y-6 px-5 py-4">
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold text-slate-900 dark:text-slate-100">Pickup Locations</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500 dark:text-slate-400">
                        Stores and pickup points customers can collect orders from. Locations appear at checkout for buyers in their state or shipping zone; pickup fees are configured as shipping rates.
                    </p>
                </div>

                <Link
                    :href="route('admin.pickup-locations.create')"
                    class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-300"
                >
                    New Pickup Location
                </Link>
            </div>
        </section>

        <section class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="flex flex-wrap items-center gap-3">
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search name or address"
                    class="h-10 min-w-[220px] flex-1 rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 placeholder:text-slate-400 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100 dark:placeholder:text-slate-500"
                >
                <select
                    v-model="status"
                    class="h-10 rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100"
                >
                    <option value="">All statuses</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
        </section>

        <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead class="bg-slate-50 dark:bg-slate-950">
                        <tr class="text-left text-xs uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">
                            <th class="px-5 py-4">Name</th>
                            <th class="px-5 py-4">Method</th>
                            <th class="px-5 py-4">Location</th>
                            <th class="px-5 py-4">Address</th>
                            <th class="px-5 py-4">Phone</th>
                            <th class="px-5 py-4">Status</th>
                            <th class="px-5 py-4 text-right">Actions</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100 bg-white dark:divide-slate-800 dark:bg-slate-900">
                        <tr v-for="location in locations.data" :key="location.id" class="align-top">
                            <td class="px-5 py-4 font-semibold text-slate-900 dark:text-slate-100">{{ location.name }}</td>
                            <td class="px-5 py-4 text-slate-700 dark:text-slate-300">{{ location.method || '-' }}</td>
                            <td class="px-5 py-4 text-slate-700 dark:text-slate-300">
                                {{ [location.lga, location.state].filter(Boolean).join(', ') || '-' }}
                            </td>
                            <td class="px-5 py-4 text-slate-700 dark:text-slate-300">{{ location.address_line1 || '-' }}</td>
                            <td class="px-5 py-4 text-slate-700 dark:text-slate-300">{{ location.phone || '-' }}</td>
                            <td class="px-5 py-4">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                                    :class="location.is_active
                                        ? 'bg-green-100 text-green-800 dark:bg-green-950/40 dark:text-green-200'
                                        : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'"
                                >
                                    {{ location.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <button
                                        type="button"
                                        class="rounded-xl border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:border-slate-500 dark:border-slate-600 dark:text-slate-200 dark:hover:border-slate-400"
                                        @click="toggleStatus(location.id)"
                                    >
                                        {{ location.is_active ? 'Deactivate' : 'Activate' }}
                                    </button>
                                    <Link
                                        :href="route('admin.pickup-locations.edit', location.id)"
                                        class="rounded-xl border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:border-slate-500 dark:border-slate-600 dark:text-slate-200 dark:hover:border-slate-400"
                                    >
                                        Edit
                                    </Link>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="!locations.data.length">
                            <td colspan="7" class="px-5 py-14 text-center text-sm text-slate-500 dark:text-slate-400">
                                No pickup locations yet. Create one so customers can choose pickup at checkout.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <Pagination :links="locations.links" />
    </div>
</template>
