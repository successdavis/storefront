<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import Upsert from "@/Pages/Admin/Brands/Upsert.vue";

const props = defineProps({
    brands: { type: Object, required: true }, // Laravel paginator
    search: { type: String, default: '' }
})

const q = ref(props.search ?? '')

// Debounced search
let t = null
watch(q, (val) => {
    clearTimeout(t)
    t = setTimeout(() => {
        router.get(route('admin.brands.index'), { q: val || undefined }, { preserveState: true, replace: true })
    }, 350)
})

// Toggle top brand
const toggling = reactive({})
function toggleTop(brand) {
    if (toggling[brand.id]) return
    toggling[brand.id] = true
    router.patch(route('admin.brands.toggle-top', brand.id), {}, {
        preserveScroll: true,
        onFinish: () => { toggling[brand.id] = false }
    })
}

// Delete
function destroyBrand(id) {
    if (!confirm('Delete this brand? This cannot be undone.')) return
    router.delete(route('admin.brands.destroy', id), { preserveScroll: true })
}
</script>

<template>
    <div class="space-y-6 text-gray-900 dark:text-gray-100 px-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h1 class="text-2xl font-semibold">Brands</h1>

            <div class="flex gap-3">
                <input
                    v-model="q"
                    type="search"
                    placeholder="Search brands..."
                    class="w-full sm:w-64 rounded-lg border px-3 py-2 text-sm
                 bg-white text-gray-900 placeholder-gray-500 border-gray-300
                 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500
                 dark:bg-gray-900 dark:text-gray-100 dark:placeholder-gray-400 dark:border-gray-700"
                />
                <Link
                    :href="route('admin.brands.create')"
                    class="inline-flex items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                >
                    New Brand
                </Link>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div class="overflow-hidden rounded-xl border bg-white border-gray-200 dark:bg-gray-900 dark:border-gray-700">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr class="text-left text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300">
                        <th class="px-4 py-3">Logo</th>
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Top</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    <tr
                        v-for="brand in brands.data"
                        :key="brand.id"
                        class="hover:bg-gray-50 dark:hover:bg-gray-800"
                    >
                        <td class="px-4 py-3">
                            <div class="h-10 w-10 overflow-hidden rounded bg-gray-100 ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
                                <img v-if="brand.logo_url" :src="brand.logo_url" alt="" class="h-10 w-10 object-cover" />
                            </div>
                        </td>

                        <td class="px-4 py-3">
                            <div class="font-medium text-gray-900 dark:text-gray-100">
                                {{ brand.name }}
                            </div>
                            <div v-if="brand.meta_description" class="text-xs text-gray-500 dark:text-gray-400 line-clamp-1">
                                {{ brand.meta_description }}
                            </div>
                        </td>

                        <td class="px-4 py-3">
                            <button
                                type="button"
                                @click="toggleTop(brand)"
                                :disabled="toggling[brand.id]"
                                class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs transition"
                                :class="brand.top_brand
                    ? 'border-green-600 text-green-700 bg-green-50 dark:border-green-500 dark:text-green-300 dark:bg-green-900/30'
                    : 'border-gray-300 text-gray-600 bg-white hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:bg-gray-900 dark:hover:bg-gray-800'"
                                title="Toggle Top Brand"
                            >
                                <span v-if="brand.top_brand">Yes</span>
                                <span v-else>No</span>
                            </button>
                        </td>

                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-2">
                                <Link
                                    :href="route('admin.brands.edit', brand.id)"
                                    class="rounded-lg border px-3 py-1.5 text-sm transition
                           border-gray-300 text-gray-700 hover:bg-gray-50
                           dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800"
                                >
                                    Edit
                                </Link>

                                <button
                                    class="rounded-lg bg-rose-600 px-3 py-1.5 text-sm text-white hover:bg-rose-700"
                                    @click="destroyBrand(brand.id)"
                                >
                                    Delete
                                </button>
                            </div>
                        </td>
                    </tr>

                    <tr v-if="brands.data.length === 0">
                        <td class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400" colspan="6">
                            No brands found.
                        </td>
                    </tr>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div class="border-t bg-white px-4 py-3 border-gray-100 dark:bg-gray-900 dark:border-gray-800">
                    <nav class="flex flex-wrap gap-2">
                        <Link
                            v-for="link in brands.links"
                            :key="link.url + link.label"
                            :href="link.url || ''"
                            :preserve-state="true"
                            :only="['brands']"
                            as="button"
                            type="button"
                            v-html="link.label"
                            :disabled="!link.url"
                            class="rounded-md px-3 py-1.5 text-sm transition"
                            :class="[
                link.active
                  ? 'bg-indigo-600 text-white'
                  : 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50 dark:bg-gray-900 dark:text-gray-200 dark:border-gray-700 dark:hover:bg-gray-800',
                !link.url && 'opacity-50 cursor-default'
              ]"
                        />
                    </nav>
                </div>
            </div>

            <Upsert />
        </div>
    </div>
</template>


<style scoped>
/* Utility when line-clamp plugin not available */
.line-clamp-1 {
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
