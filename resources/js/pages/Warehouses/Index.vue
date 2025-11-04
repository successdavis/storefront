<script setup>
import { Head, Link, router } from "@inertiajs/vue3";
import { ref, watch } from "vue";

const props = defineProps({
    warehouses: Object,
    filters: Object,
});

const search = ref(props.filters?.search ?? "");

// debounce search
let timeout = null;
watch(search, (value) => {
    clearTimeout(timeout);
    timeout = setTimeout(() => {
        router.get(
            route("admin.warehouses.index"),
            { search: value || "" },
            { preserveState: true, replace: true }
        );
    }, 300);
});

function deleteWarehouse(id) {
    if (confirm("Are you sure you want to delete this warehouse?")) {
        router.delete(route("admin.warehouses.destroy", id));
    }
}
</script>

<template>
    <Head title="Warehouses" />

    <div class="p-6 space-y-6 text-gray-900 dark:text-gray-100">

        <!-- heading -->
        <div class="flex justify-between items-center">
            <h1 class="text-2xl font-bold">Warehouses</h1>

            <Link
                :href="route('admin.warehouses.create')"
                class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-md dark:bg-indigo-500 dark:hover:bg-indigo-600"
            >
                + Add Warehouse
            </Link>
        </div>

        <!-- search -->
        <input
            v-model="search"
            type="text"
            placeholder="Search warehouse..."
            class="border rounded-md px-4 py-2 w-full
             bg-white text-gray-800
             dark:bg-gray-800 dark:text-gray-100 dark:border-gray-700"
        />

        <!-- Table -->
        <div class="overflow-x-auto rounded-lg shadow">
            <table class="min-w-full border-collapse text-left
                   bg-white dark:bg-gray-900 dark:border-gray-700">
                <thead class="bg-gray-100 dark:bg-gray-800 border-b dark:border-gray-700">
                <tr>
                    <th class="px-4 py-2">Name</th>
                    <th class="px-4 py-2">Code</th>
                    <th class="px-4 py-2">City</th>
                    <th class="px-4 py-2">Status</th>
                    <th class="px-4 py-2 text-right">Actions</th>
                </tr>
                </thead>

                <tbody>
                <tr
                    v-for="item in props.warehouses?.data ?? []"
                    :key="item.id"
                    class="border-t hover:bg-gray-50 dark:hover:bg-gray-800
                   dark:border-gray-700"
                >
                    <td class="px-4 py-2">{{ item.name }}</td>
                    <td class="px-4 py-2">{{ item.code }}</td>
                    <td class="px-4 py-2">{{ item.city }}</td>

                    <td class="px-4 py-2">
              <span
                  class="px-3 py-1 rounded-full text-xs"
                  :class="item.active
                  ? 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300'
                  : 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300'"
              >
                {{ item.active ? "Active" : "Inactive" }}
              </span>
                    </td>

                    <td class="px-4 py-2 text-right">
                        <div class="flex justify-end gap-2">
                            <Link
                                :href="route('admin.warehouses.edit', item.id)"
                                class="px-3 py-1 text-sm bg-blue-500 hover:bg-blue-600 text-white rounded-md
                        dark:bg-blue-600 dark:hover:bg-blue-700"
                            >
                                Edit
                            </Link>

                            <button
                                @click="deleteWarehouse(item.id)"
                                class="px-3 py-1 text-sm bg-red-500 hover:bg-red-600 text-white rounded-md
                         dark:bg-red-600 dark:hover:bg-red-700"
                            >
                                Delete
                            </button>
                        </div>
                    </td>
                </tr>

                <tr v-if="(props.warehouses?.data ?? []).length === 0">
                    <td colspan="5" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">
                        No warehouses found.
                    </td>
                </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="flex items-center gap-2 flex-wrap">
            <template v-for="(link, i) in props.warehouses?.links ?? []" :key="i">
        <span
            v-if="!link.url"
            v-html="link.label"
            class="px-3 py-1 border rounded-md text-gray-400 dark:border-gray-700"
        />
                <Link
                    v-else
                    :href="link.url"
                    preserve-scroll
                    v-html="link.label"
                    class="px-3 py-1 border rounded-md
                 dark:border-gray-700 dark:hover:bg-gray-800"
                    :class="{
            'bg-indigo-600 text-white dark:bg-indigo-500 dark:text-white': link.active,
          }"
                />
            </template>
        </div>
    </div>
</template>
