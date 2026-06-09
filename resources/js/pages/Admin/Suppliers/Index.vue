<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    filters: Record<string, any>;
    suppliers: any;
}>();

const search = ref(props.filters?.search ?? '');
const editing = ref<any | null | undefined>(undefined);
const form = useForm({
    name: '',
    email: '',
    phone: '',
    address: '',
    active: true,
});

function applySearch() {
    router.get(route('admin.suppliers.index'), { search: search.value }, { preserveState: true, replace: true });
}

function openCreate() {
    editing.value = null;
    form.reset();
    form.active = true;
}

function openEdit(supplier: any) {
    editing.value = supplier;
    form.name = supplier.name || '';
    form.email = supplier.email || '';
    form.phone = supplier.phone || '';
    form.address = supplier.address || '';
    form.active = Boolean(supplier.active);
}

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            editing.value = null;
            form.reset();
            form.active = true;
        },
    };

    if (editing.value) {
        form.put(route('admin.suppliers.update', editing.value.id), options);
        return;
    }

    form.post(route('admin.suppliers.store'), options);
}
</script>

<template>
    <Head title="Suppliers" />

    <div class="space-y-6 p-6">
        <section class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-slate-900 dark:text-slate-100">Suppliers</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Manage vendors used for dropshipping and purchasing.</p>
            </div>
            <button class="rounded bg-slate-900 px-4 py-2 text-sm font-semibold text-white dark:bg-slate-100 dark:text-slate-900" @click="openCreate">New Supplier</button>
        </section>

        <section class="flex gap-3 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
            <input v-model="search" placeholder="Search suppliers" class="w-full rounded border border-slate-300 px-3 py-2 dark:border-slate-600 dark:bg-slate-950" @keyup.enter="applySearch" />
            <button class="rounded border border-slate-300 px-4 py-2 dark:border-slate-600" @click="applySearch">Search</button>
        </section>

        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500 dark:bg-slate-800 dark:text-slate-300">
                    <tr>
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Contact</th>
                        <th class="px-4 py-3">Dropship Products</th>
                        <th class="px-4 py-3">Fulfillments</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                    <tr v-for="supplier in suppliers.data" :key="supplier.id">
                        <td class="px-4 py-3">
                            <div class="font-medium text-slate-900 dark:text-slate-100">{{ supplier.name }}</div>
                            <div class="text-xs text-slate-500">{{ supplier.address || 'No address' }}</div>
                        </td>
                        <td class="px-4 py-3">
                            <div>{{ supplier.email || '-' }}</div>
                            <div class="text-xs text-slate-500">{{ supplier.phone || '-' }}</div>
                        </td>
                        <td class="px-4 py-3">{{ supplier.dropship_variants_count ?? 0 }}</td>
                        <td class="px-4 py-3">{{ supplier.dropship_fulfillments_count ?? 0 }}</td>
                        <td class="px-4 py-3">
                            <span :class="['rounded-full px-2.5 py-1 text-xs font-semibold', supplier.active ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-200' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300']">
                                {{ supplier.active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right"><button class="rounded border border-slate-300 px-3 py-1.5 dark:border-slate-600" @click="openEdit(supplier)">Edit</button></td>
                    </tr>
                    <tr v-if="!suppliers.data.length">
                        <td colspan="6" class="px-4 py-10 text-center text-slate-500">No suppliers found.</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <div class="flex flex-wrap gap-2" v-if="suppliers.links">
            <a v-for="link in suppliers.links" :key="link.label" :href="link.url || '#'" v-html="link.label" :class="['rounded border px-3 py-1 text-sm', link.active ? 'bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900' : '']" @click.prevent="link.url && router.visit(link.url, { preserveState: true })" />
        </div>

        <div v-if="editing !== undefined" class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-xl rounded-lg bg-white p-5 shadow-xl dark:bg-slate-900">
                <div class="flex items-start justify-between gap-4">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ editing ? 'Edit Supplier' : 'New Supplier' }}</h2>
                    <button class="rounded px-2 py-1 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800" @click="editing = undefined">x</button>
                </div>

                <div class="mt-5 grid gap-4">
                    <input v-model="form.name" placeholder="Supplier name" class="rounded border border-slate-300 px-3 py-2 dark:border-slate-600 dark:bg-slate-950" />
                    <div class="grid gap-4 md:grid-cols-2">
                        <input v-model="form.email" type="email" placeholder="Email" class="rounded border border-slate-300 px-3 py-2 dark:border-slate-600 dark:bg-slate-950" />
                        <input v-model="form.phone" placeholder="Phone" class="rounded border border-slate-300 px-3 py-2 dark:border-slate-600 dark:bg-slate-950" />
                    </div>
                    <textarea v-model="form.address" rows="3" placeholder="Address" class="rounded border border-slate-300 px-3 py-2 dark:border-slate-600 dark:bg-slate-950" />
                    <label class="flex items-center gap-2 text-sm"><input v-model="form.active" type="checkbox" /> Active supplier</label>
                </div>

                <div class="mt-5 flex justify-end gap-2">
                    <button class="rounded border border-slate-300 px-4 py-2 dark:border-slate-600" @click="editing = undefined">Cancel</button>
                    <button class="rounded bg-slate-900 px-4 py-2 text-white disabled:opacity-50 dark:bg-slate-100 dark:text-slate-900" :disabled="form.processing" @click="submit">Save</button>
                </div>
            </div>
        </div>
    </div>
</template>
