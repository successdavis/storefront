<script setup>
import { Head, Link, router } from "@inertiajs/vue3";
import Swal from "sweetalert2";

const props = defineProps({
    terminals: Object,
});

function deleteTerminal(id) {
    Swal.fire({
        title: "Are you sure?",
        text: "This POS terminal will be deleted",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#2563eb",
        cancelButtonColor: "#d33",
        confirmButtonText: "Yes, delete it!",
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route("pos-terminals.destroy", id));
        }
    });
}
</script>

<template>
    <Head title="POS Terminals" />

    <div class="p-6 space-y-4 text-gray-800 dark:text-gray-100 bg-gray-50 dark:bg-gray-900 min-h-screen">

        <div class="flex justify-between items-center">
            <h1 class="text-xl font-bold">POS Terminals</h1>

            <Link
                :href="route('admin.pos-terminals.create')"
                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition"
            >
                + New Terminal
            </Link>
        </div>

        <div class="overflow-hidden rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
            <table class="w-full">
                <thead>
                <tr class="bg-gray-200 dark:bg-gray-800 text-left text-sm uppercase">
                    <th class="p-3 border border-gray-200 dark:border-gray-700">Name</th>
                    <th class="p-3 border border-gray-200 dark:border-gray-700">Warehouse</th>
                    <th class="p-3 border border-gray-200 dark:border-gray-700">Location</th>
                    <th class="p-3 border border-gray-200 dark:border-gray-700 text-center w-32">Actions</th>
                </tr>
                </thead>

                <tbody>
                <tr
                    v-for="terminal in terminals.data"
                    :key="terminal.id"
                    class="hover:bg-gray-100 dark:hover:bg-gray-800 transition"
                >
                    <td class="p-3 border border-gray-200 dark:border-gray-700">
                        {{ terminal.name }}
                    </td>
                    <td class="p-3 border border-gray-200 dark:border-gray-700">
                        {{ terminal.warehouse }}
                    </td>
                    <td class="p-3 border border-gray-200 dark:border-gray-700">
                        {{ terminal.location }}
                    </td>
                    <td class="p-3 border border-gray-200 dark:border-gray-700 text-center space-x-2">
                        <Link
                            :href="route('admin.pos-terminals.edit', terminal.id)"
                            class="text-blue-600 dark:text-blue-400 hover:underline"
                        >Edit</Link>

                        <button
                            class="text-red-600 dark:text-red-400 hover:underline"
                            @click="deleteTerminal(terminal.id)"
                        >
                            Delete
                        </button>
                    </td>
                </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="mt-4 flex justify-end space-x-2">
            <template v-for="link in terminals.links" :key="link.label">

                <!-- Disabled -->
                <span
                    v-if="!link.url"
                    class="px-3 py-1 border rounded text-gray-400 dark:text-gray-600 border-gray-300 dark:border-gray-700 cursor-not-allowed"
                    v-html="link.label"
                />

                <!-- Enabled -->
                <Link
                    v-else
                    :href="link.url"
                    class="px-3 py-1 border rounded transition border-gray-300 dark:border-gray-700 hover:bg-gray-200 dark:hover:bg-gray-800"
                    :class="{ 'bg-blue-600 text-white border-blue-600': link.active }"
                    v-html="link.label"
                />
            </template>
        </div>
    </div>
</template>
