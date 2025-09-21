<script setup>
import { router, usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    products: Object,
    filters: Object,
});

const search = ref(props.filters?.search ?? '');
const activeOnly = ref(!!props.filters?.active_only);

watch([search, activeOnly], ([s, a]) => {
    router.get(route('products.index'), { search: s, active_only: a ? 1 : 0 }, { preserveState: true, replace: true });
});
</script>

<template>
    <div class="p-6 space-y-4">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold">Products</h1>
            <a :href="route('products.create')" class="px-4 py-2 bg-blue-600 text-white rounded">New Product</a>
        </div>

        <div class="flex gap-3">
            <input v-model="search" placeholder="Search name or slug" class="border rounded px-3 py-2 w-64" />
            <label class="inline-flex items-center gap-2">
                <input type="checkbox" v-model="activeOnly" />
                <span>Active only</span>
            </label>
        </div>

        <div class="bg-white border rounded">
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                <tr>
                    <th class="p-3 text-left">Name</th>
                    <th class="p-3 text-left">Slug</th>
                    <th class="p-3 text-left">Category</th>
                    <th class="p-3 text-left">Brand</th>
                    <th class="p-3 text-left">Active</th>
                    <th class="p-3"></th>
                </tr>
                </thead>
                <tbody>
                <tr v-for="p in props.products.data" :key="p.id" class="border-t">
                    <td class="p-3">{{ p.name }}</td>
                    <td class="p-3">{{ p.slug }}</td>
                    <td class="p-3">{{ p.category ?? '—' }}</td>
                    <td class="p-3">{{ p.brand ?? '—' }}</td>
                    <td class="p-3">
                        <span :class="p.is_active ? 'text-green-700' : 'text-gray-500'">{{ p.is_active ? 'Yes' : 'No' }}</span>
                    </td>
                    <td class="p-3 text-right">
                        <a :href="route('products.edit', p.id)" class="text-blue-600 hover:underline">Edit</a>
                    </td>
                </tr>
                <tr v-if="props.products.data.length === 0">
                    <td class="p-3 text-center text-gray-500" colspan="6">No products found.</td>
                </tr>
                </tbody>
            </table>
        </div>

        <div class="flex gap-2" v-if="props.products.links">
            <a v-for="l in props.products.links" :key="l.url + l.label"
               :href="l.url || '#'"
               v-html="l.label"
               :class="['px-3 py-1 border rounded', l.active ? 'bg-blue-600 text-white' : '']"
               @click.prevent="l.url && router.visit(l.url, { preserveState: true })" />
        </div>
    </div>
</template>
