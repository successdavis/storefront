<script setup lang="ts">
import Pagination from '@/components/Pagination.vue';
import { Head, useForm } from '@inertiajs/vue3';

defineProps<{
    customers: {
        data: Array<{
            id: number;
            name: string;
            email: string | null;
            phone: string | null;
            address: string | null;
            created_at: string | null;
        }>;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    countries: Array<{ id: number; name: string }>;
    states: Array<{ id: number; name: string; country_id: number | null }>;
}>();

const form = useForm({
    name: '',
    email: '',
    phone: '',
    country_id: '',
    state_id: '',
    lga_id: '',
    address: '',
});

const submit = () => {
    form.post('/sales/customers', {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};
</script>

<template>
    <Head title="Sales Customers" />

    <div class="grid gap-6 bg-slate-50 p-6 xl:grid-cols-[1.15fr_0.85fr]">
        <section class="space-y-6">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <h1 class="text-3xl font-semibold tracking-tight text-slate-900">Customer records</h1>
                <p class="mt-2 text-sm text-slate-500">Add and review customer profiles needed for sales-assisted checkout and order handling.</p>
            </div>

            <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div v-if="customers.data.length" class="divide-y divide-slate-200">
                    <div v-for="customer in customers.data" :key="customer.id" class="grid gap-3 px-6 py-4 md:grid-cols-[1.1fr_1fr_1fr]">
                        <div>
                            <p class="font-semibold text-slate-900">{{ customer.name }}</p>
                            <p class="mt-1 text-sm text-slate-500">{{ customer.email || 'No email provided' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Phone</p>
                            <p class="mt-1 text-sm text-slate-900">{{ customer.phone || 'No phone provided' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Address</p>
                            <p class="mt-1 text-sm text-slate-900">{{ customer.address || 'No address provided' }}</p>
                        </div>
                    </div>
                </div>
                <div v-else class="px-6 py-14 text-center text-sm text-slate-500">No customer records yet.</div>
            </div>

            <Pagination :links="customers.links" />
        </section>

        <aside class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-xl font-semibold text-slate-900">Create customer</h2>
            <p class="mt-1 text-sm text-slate-500">Use this for assisted sales and POS activity.</p>

            <form class="mt-6 space-y-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <label class="text-sm font-medium text-slate-700">Full name</label>
                    <input v-model="form.name" type="text" class="h-11 rounded-xl border border-slate-300 px-3 text-sm" />
                    <p v-if="form.errors.name" class="text-xs font-medium text-rose-600">{{ form.errors.name }}</p>
                </div>
                <div class="grid gap-2">
                    <label class="text-sm font-medium text-slate-700">Email</label>
                    <input v-model="form.email" type="email" class="h-11 rounded-xl border border-slate-300 px-3 text-sm" />
                </div>
                <div class="grid gap-2">
                    <label class="text-sm font-medium text-slate-700">Phone</label>
                    <input v-model="form.phone" type="text" class="h-11 rounded-xl border border-slate-300 px-3 text-sm" />
                    <p v-if="form.errors.phone" class="text-xs font-medium text-rose-600">{{ form.errors.phone }}</p>
                </div>
                <div class="grid gap-2">
                    <label class="text-sm font-medium text-slate-700">Address</label>
                    <textarea v-model="form.address" rows="4" class="rounded-xl border border-slate-300 px-3 py-3 text-sm"></textarea>
                </div>
                <button type="submit" class="w-full rounded-xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white" :disabled="form.processing">
                    {{ form.processing ? 'Saving...' : 'Create customer' }}
                </button>
            </form>
        </aside>
    </div>
</template>
