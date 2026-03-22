<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import { ref, watch } from 'vue'

const props = defineProps<{
    discounts: {
        data: Array<Record<string, any>>
        links: Array<{ url: string | null; label: string; active: boolean }>
    }
    filters: {
        search?: string
        status?: string
        scope?: string
    }
}>()

const search = ref(props.filters?.search ?? '')
const status = ref(props.filters?.status ?? '')
const scope = ref(props.filters?.scope ?? '')

let filterTimeout: number | undefined

watch(search, () => {
    window.clearTimeout(filterTimeout)
    filterTimeout = window.setTimeout(applyFilters, 300)
})

watch([status, scope], () => applyFilters())

function applyFilters() {
    router.get(route('admin.discounts.index'), {
        search: search.value || undefined,
        status: status.value || undefined,
        scope: scope.value || undefined,
    }, {
        preserveState: true,
        replace: true,
    })
}

function toggleStatus(id: number) {
    router.patch(route('admin.discounts.toggle-status', id), {}, { preserveScroll: true })
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
    <Head title="Discounts" />

    <div class="space-y-6 px-5 py-4">
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold text-slate-900 dark:text-slate-100">Discounts</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500 dark:text-slate-400">
                        Manage automatic promotions that can change storefront product pricing or apply at order level without a coupon code.
                    </p>
                </div>

                <Link
                    :href="route('admin.discounts.create')"
                    class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-300"
                >
                    Create discount
                </Link>
            </div>

            <div class="mt-5 grid gap-3 md:grid-cols-[1.3fr_0.8fr_0.8fr]">
                <input v-model="search" type="search" placeholder="Search discounts" class="h-11 rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 placeholder:text-slate-400 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100 dark:placeholder:text-slate-500">
                <select v-model="status" class="h-11 rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                    <option value="">All statuses</option>
                    <option value="active">Active</option>
                    <option value="scheduled">Scheduled</option>
                    <option value="expired">Expired</option>
                    <option value="inactive">Inactive</option>
                </select>
                <select v-model="scope" class="h-11 rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                    <option value="">All scopes</option>
                    <option value="global">Global</option>
                    <option value="category">Category</option>
                    <option value="product">Product</option>
                </select>
            </div>
        </section>

        <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead class="bg-slate-50 dark:bg-slate-950">
                        <tr class="text-left text-xs uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">
                            <th class="px-5 py-4">Discount</th>
                            <th class="px-5 py-4">Scope</th>
                            <th class="px-5 py-4">Behavior</th>
                            <th class="px-5 py-4">Status</th>
                            <th class="px-5 py-4">Schedule</th>
                            <th class="px-5 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white dark:divide-slate-800 dark:bg-slate-900">
                        <tr v-for="discount in discounts.data" :key="discount.id" class="align-top">
                            <td class="px-5 py-4">
                                <div class="font-semibold text-slate-900 dark:text-slate-100">{{ discount.name }}</div>
                                <p v-if="discount.description" class="mt-1 max-w-md text-xs leading-5 text-slate-500 dark:text-slate-400">{{ discount.description }}</p>
                                <div class="mt-2 flex flex-wrap gap-2 text-xs text-slate-500 dark:text-slate-300">
                                    <span class="rounded-full bg-slate-100 px-2.5 py-1 dark:bg-slate-800 dark:text-slate-200">{{ discount.type }}</span>
                                    <span v-if="discount.value !== null" class="rounded-full bg-slate-100 px-2.5 py-1 dark:bg-slate-800 dark:text-slate-200">
                                        {{ discount.type === 'percentage' ? `${discount.value}%` : `NGN ${discount.value}` }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-5 py-4 text-slate-600 dark:text-slate-300">
                                <div class="font-medium text-slate-900 dark:text-slate-100">{{ discount.scope }}</div>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                    {{ discount.product_count ? `${discount.product_count} products` : (discount.category_count ? `${discount.category_count} categories` : 'All products') }}
                                </p>
                            </td>
                            <td class="px-5 py-4 text-slate-600 dark:text-slate-300">
                                <div class="font-medium text-slate-900 dark:text-slate-100">{{ discount.application_method === 'line_item' ? 'Product price markdown' : 'Order total promotion' }}</div>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Priority {{ discount.priority }}</p>
                            </td>
                            <td class="px-5 py-4">
                                <span :class="['inline-flex rounded-full px-2.5 py-1 text-xs font-semibold', badgeClass(discount.status)]">
                                    {{ discount.status }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-xs leading-5 text-slate-500 dark:text-slate-400">
                                <div>{{ discount.starts_at ? new Date(discount.starts_at).toLocaleString() : 'Starts immediately' }}</div>
                                <div class="mt-1">{{ discount.ends_at ? new Date(discount.ends_at).toLocaleString() : 'No end date' }}</div>
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex justify-end gap-2">
                                    <button
                                        type="button"
                                        class="rounded-xl border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:border-slate-500 dark:border-slate-600 dark:text-slate-200 dark:hover:border-slate-400"
                                        @click="toggleStatus(discount.id)"
                                    >
                                        {{ discount.is_active ? 'Deactivate' : 'Activate' }}
                                    </button>
                                    <Link
                                        :href="route('admin.discounts.edit', discount.id)"
                                        class="rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white transition hover:bg-slate-700 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-300"
                                    >
                                        Edit
                                    </Link>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="discounts.data.length === 0">
                            <td colspan="6" class="px-5 py-14 text-center text-sm text-slate-500 dark:text-slate-400">
                                No discounts matched the current filters.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="discounts.links?.length" class="flex flex-wrap gap-2 border-t border-slate-200 px-5 py-4 dark:border-slate-800">
                <button
                    v-for="link in discounts.links"
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
