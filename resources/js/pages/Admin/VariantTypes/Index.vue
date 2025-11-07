<script setup>
import { Link, router } from '@inertiajs/vue3';

const props = defineProps({
    types: Object, // Laravel pagination payload
});

function destroyType(id) {
    if (!confirm('Delete this variant type and all its values?')) return;
    router.delete(`/admin/variant-types/${id}`, {
        preserveScroll: true,
    });
}
</script>

<template>
    <div class="p-6 w-full mx-auto text-gray-900 dark:text-gray-100">

        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-semibold">Variant Types</h1>

            <Link
                href="/admin/variant-types/create"
                class="inline-flex items-center rounded-md border px-3 py-2 text-sm font-medium
               hover:bg-gray-50
               dark:border-gray-700 dark:hover:bg-gray-800 dark:bg-gray-900"
            >
                New Variant Type
            </Link>
        </div>

        <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                <thead class="bg-gray-50 dark:bg-gray-800">
                <tr>
                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-700 dark:text-gray-300">Name</th>
                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-700 dark:text-gray-300">Values</th>
                    <th class="px-4 py-3 text-left text-sm font-medium text-gray-700 dark:text-gray-300">Created</th>
                    <th class="px-4 py-3 text-right text-sm font-medium text-gray-700 dark:text-gray-300">Actions</th>
                </tr>
                </thead>

                <tbody class="divide-y divide-gray-100 dark:divide-gray-800 bg-white dark:bg-gray-900">
                <tr
                    v-for="t in props.types.data"
                    :key="t.id"
                    class="hover:bg-gray-50 dark:hover:bg-gray-800"
                >
                    <td class="px-4 py-3">{{ t.name }}</td>
                    <td class="px-4 py-3">{{ t.values_count }}</td>
                    <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ t.created_at }}</td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-end gap-2">
                            <Link
                                :href="`/admin/variant-types/${t.id}/edit`"
                                class="rounded border px-2 py-1 text-sm
                         hover:bg-gray-50
                         dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800"
                            >
                                Edit
                            </Link>

                            <button
                                class="rounded border px-2 py-1 text-sm
                         hover:bg-red-50
                         dark:border-gray-700 dark:hover:bg-red-900/30 dark:text-gray-200"
                                @click="destroyType(t.id)"
                            >
                                Delete
                            </button>
                        </div>
                    </td>
                </tr>

                <tr v-if="props.types.data.length === 0">
                    <td class="px-4 py-6 text-center text-gray-500 dark:text-gray-400" colspan="4">
                        No variant types yet.
                    </td>
                </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="mt-4 flex gap-2">
            <Link
                v-for="link in props.types.links"
                :key="link.label"
                :href="link.url || '#'"
                v-html="link.label"
                :class="[
          'rounded border px-3 py-1 text-sm',
          link.active
            ? 'bg-gray-100 font-semibold dark:bg-gray-800'
            : 'hover:bg-gray-50 dark:hover:bg-gray-800',
          !link.url ? 'opacity-50 pointer-events-none' : '',
          'dark:border-gray-700 dark:text-gray-200'
        ]"
            />
        </div>

    </div>
</template>

