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
  <div class="p-6 max-w-5xl mx-auto">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-semibold">Variant Types</h1>
      <Link
        href="/admin/variant-types/create"
        class="inline-flex items-center rounded-md border px-3 py-2 text-sm font-medium hover:bg-gray-50"
      >
        New Variant Type
      </Link>
    </div>

    <div class="overflow-hidden rounded-lg border">
      <table class="min-w-full divide-y">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-3 text-left text-sm font-medium">Name</th>
            <th class="px-4 py-3 text-left text-sm font-medium">Values</th>
            <th class="px-4 py-3 text-left text-sm font-medium">Created</th>
            <th class="px-4 py-3 text-right text-sm font-medium">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y bg-white">
          <tr v-for="t in props.types.data" :key="t.id">
            <td class="px-4 py-3">{{ t.name }}</td>
            <td class="px-4 py-3">{{ t.values_count }}</td>
            <td class="px-4 py-3 text-gray-600">{{ t.created_at }}</td>
            <td class="px-4 py-3">
              <div class="flex items-center justify-end gap-2">
                <Link
                  :href="`/admin/variant-types/${t.id}/edit`"
                  class="rounded border px-2 py-1 text-sm hover:bg-gray-50"
                >
                  Edit
                </Link>
                <button
                  class="rounded border px-2 py-1 text-sm hover:bg-red-50"
                  @click="destroyType(t.id)"
                >
                  Delete
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="props.types.data.length === 0">
            <td class="px-4 py-6 text-center text-gray-500" colspan="4">
              No variant types yet.
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Simple pagination -->
    <div class="mt-4 flex gap-2">
      <Link
        v-for="link in props.types.links"
        :key="link.label"
        :href="link.url || '#'"
        v-html="link.label"
        :class="[
          'rounded border px-3 py-1 text-sm',
          link.active ? 'bg-gray-100 font-semibold' : 'hover:bg-gray-50',
          !link.url ? 'opacity-50 pointer-events-none' : ''
        ]"
      />
    </div>
  </div>
</template>
