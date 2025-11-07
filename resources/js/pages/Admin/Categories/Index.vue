<!-- resources/js/Pages/Admin/Categories.vue -->
<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import { ref, watch, computed, onMounted } from 'vue'
import Pagination from "@/components/Pagination.vue";

// Props from controller
const props = defineProps({
    categories: { type: Object, required: true }, // Laravel paginator JSON
    search: { type: String, default: '' },
})

// Local state mirrors the server-provided search
const q = ref(props.search ?? '')

// Debounce helper
let t = null
watch(q, (val) => {
    clearTimeout(t)
    t = setTimeout(() => {
        // Keep other query params, preserve scroll, and avoid full reload
        router.get(
            window.location.pathname,
            { q: val || undefined }, // drop param when empty
            { preserveState: true, preserveScroll: true, replace: true }
        )
    }, 400)
})

function deleteCategory(id) {
    if (confirm('Are you sure you want to delete this category?')) {
        router.delete(route('admin.categories.destroy', id))
    }
}

// Expand/collapse children per parent row
const open = ref(new Set())
const toggle = (id) => {
    const s = new Set(open.value)
    s.has(id) ? s.delete(id) : s.add(id)
    open.value = s
}

// Convenience computed values
const items = computed(() => props.categories?.data ?? [])
const meta = computed(() => ({
    total: props.categories?.total ?? 0,
    current_page: props.categories?.current_page ?? 1,
    per_page: props.categories?.per_page ?? 24,
    from: props.categories?.from ?? 0,
    to: props.categories?.to ?? 0,
}))
</script>

<template>
    <Head title="Categories" />

    <div class="min-h-screen bg-gray-50 dark:bg-gray-900 dark:text-gray-100">
        <div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

            <!-- Header -->
            <header class="mb-6 flex justify-between items-center">
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                    All Categories
                </h1>

                <div class="mt-1 text-sm">
                    <Link
                        href="/admin/categories/create"
                        class="text-white bg-blue-700 hover:bg-blue-800 focus:outline-none focus:ring-4 focus:ring-blue-300 font-medium rounded-full text-sm px-5 py-2.5 dark:bg-blue-600 dark:hover:bg-blue-700 dark:focus:ring-blue-800"
                    >
                        Add New Category
                    </Link>
                </div>
            </header>

            <!-- Search -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-4 mb-6 border border-gray-100 dark:border-gray-700">
                <label for="q" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Search by name
                </label>

                <div class="mt-2 flex gap-2">
                    <input
                        id="q"
                        v-model="q"
                        type="search"
                        autocomplete="off"
                        placeholder="Type to filter categories"
                        class="w-full px-3 py-2 rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 focus:border-indigo-500 focus:ring-indigo-500"
                    />

                    <button
                        v-if="q"
                        @click="q=''"
                        type="button"
                        class="px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700"
                    >
                        Clear
                    </button>
                </div>
            </div>

            <!-- Summary -->
            <div class="flex items-center justify-between mb-3">
                <div class="text-sm text-gray-600 dark:text-gray-400">
                    <span v-if="meta.total">
                        Showing {{ meta.from }}–{{ meta.to }} of {{ meta.total }}
                    </span>
                    <span v-else>No categories found</span>
                </div>
            </div>

            <!-- List -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">

                    <!-- Table head -->
                    <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Name</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Parent Category</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Products</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Order</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Banner</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Icon</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Cover Image</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Featured</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                    </thead>

                    <!-- Table body -->
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    <tr
                        v-for="cat in items"
                        :key="cat.id"
                        class="align-top hover:bg-gray-50 dark:hover:bg-gray-700/40 transition"
                    >
                        <td class="px-4 py-4">
                            <div class="flex items-center gap-3">

                                <button
                                    v-if="cat.children_count"
                                    @click="toggle(cat.id)"
                                    type="button"
                                    class="inline-flex items-center justify-center w-7 h-7 rounded-md border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700"
                                    :aria-expanded="open.has(cat.id) ? 'true' : 'false'"
                                    :aria-controls="`children-${cat.id}`"
                                >
                                    <svg v-if="open.has(cat.id)" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M5 10a1 1 0 011-1h8a1 1 0 110 2H6a1 1 0 01-1-1z" clip-rule="evenodd" />
                                    </svg>
                                    <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                        <path d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z"/>
                                    </svg>
                                </button>

                                <div>
                                    <div class="font-medium text-gray-900 dark:text-gray-100">{{ cat.name }}</div>
                                    <div v-if="cat.description" class="text-sm text-gray-500 dark:text-gray-400 line-clamp-2 max-w-xl">
                                        {{ cat.description }}
                                    </div>
                                </div>
                            </div>

                            <!-- Children list -->
                            <div
                                v-if="cat.children && cat.children.length"
                                v-show="open.has(cat.id)"
                                :id="`children-${cat.id}`"
                                class="mt-3 border-t border-gray-100 dark:border-gray-700 pt-3"
                            >
                                <ul class="grid sm:grid-cols-2 lg:grid-cols-3 gap-2">
                                    <li
                                        v-for="child in cat.children"
                                        :key="child.id"
                                        class="flex items-center justify-between rounded-lg border border-gray-200 dark:border-gray-600 px-3 py-2"
                                    >
                                        <span class="text-sm text-gray-800 dark:text-gray-300 truncate">
                                            {{ child.name }}
                                        </span>
                                        <span class="inline-flex items-center text-xs px-2 py-1 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                                            {{ child.products_count }} products
                                        </span>
                                    </li>
                                </ul>
                            </div>
                        </td>

                        <td class="px-4 py-4">
                            <span class="inline-flex items-center text-sm px-2 py-1 rounded-full bg-indigo-50 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300">
                              parent cat
                            </span>
                        </td>

                        <td class="px-4 py-4">
                            <span class="inline-flex items-center text-sm px-2 py-1 rounded-full bg-indigo-50 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300">
                              {{ cat.products_count }}
                            </span>
                        </td>

                        <td class="px-4 py-4">
                            <span class="inline-flex items-center text-sm px-2 py-1 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                              order
                            </span>
                        </td>

                        <td class="px-4 py-4">
                            <span class="inline-flex items-center text-sm px-2 py-1 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                              Banner
                            </span>
                        </td>

                        <td class="px-4 py-4">
                            <span class="inline-flex items-center text-sm px-2 py-1 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                              Icon
                            </span>
                        </td>

                        <td class="px-4 py-4">
                            <span class="inline-flex items-center text-sm px-2 py-1 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                              Cover Image
                            </span>
                        </td>

                        <td class="px-4 py-4">
                            <label class="inline-flex items-center cursor-pointer">
                                <input type="checkbox" value="" class="sr-only peer" checked>
                                <div class="relative w-11 h-6 bg-gray-600 rounded-full peer dark:bg-gray-700 peer-focus:ring-4 peer-focus:ring-green-300 dark:peer-focus:ring-green-800 peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-green-600 dark:peer-checked:bg-green-600"></div>
                            </label>
                        </td>

                        <td class="px-4 py-4 text-right">
                            <div class="inline-flex gap-2">
                                <Link
                                    :href="`/admin/categories/${cat.id}/edit`"
                                    class="px-2 rounded-full py-2 text-xs border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700"
                                >
                                    ✏️
                                </Link>

                                <Button
                                    @click="deleteCategory(cat.id)"
                                    class="px-2 py-2 text-sm rounded-full border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700"
                                >
                                    🗑️
                                </Button>
                            </div>
                        </td>
                    </tr>

                    <!-- Empty state -->
                    <tr v-if="!items.length">
                        <td colspan="9" class="px-4 py-12">
                            <div class="text-center">
                                <div class="text-gray-900 dark:text-gray-100 font-medium">No categories found</div>
                                <p class="text-gray-600 dark:text-gray-400 text-sm mt-1">
                                    Try a different search term.
                                </p>
                            </div>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="mt-6">
                <Pagination :links="categories.links" />
            </div>
        </div>
    </div>
</template>

