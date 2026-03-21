<script setup lang="ts">
import Pagination from '@/components/Pagination.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

interface AddressRecord {
    id: number;
    label: string;
    recipient_name: string;
    phone: string | null;
    email: string | null;
    line1: string;
    line2: string | null;
    postal_code: string | null;
    is_default: boolean;
    country_id?: number | null;
    state_id?: number | null;
    lga_id?: number | null;
    city_id?: number | null;
    country?: { name: string } | null;
    state?: { name: string } | null;
    lga?: { name: string } | null;
}

const props = defineProps<{
    addresses: { data: AddressRecord[]; links: Array<{ url: string | null; label: string; active: boolean }> };
    countries: Array<{ id: number; name: string }>;
    states: Array<{ id: number; name: string; country_id: number | null }>;
}>();

const editingId = ref<number | null>(null);
const form = useForm({
    label: 'Home',
    recipient_name: '',
    phone: '',
    email: '',
    line1: '',
    line2: '',
    country_id: '',
    state_id: '',
    lga_id: '',
    city_id: '',
    postal_code: '',
    is_default: false,
});

const resetForm = () => {
    editingId.value = null;
    form.reset();
    form.label = 'Home';
    form.is_default = false;
};

const submit = () => {
    if (editingId.value) {
        form.put(`/account/addresses/${editingId.value}`, {
            preserveScroll: true,
            onSuccess: () => resetForm(),
        });
        return;
    }

    form.post('/account/addresses', {
        preserveScroll: true,
        onSuccess: () => resetForm(),
    });
};

const startEdit = (address: AddressRecord) => {
    editingId.value = address.id;
    form.label = address.label;
    form.recipient_name = address.recipient_name;
    form.phone = address.phone ?? '';
    form.email = address.email ?? '';
    form.line1 = address.line1;
    form.line2 = address.line2 ?? '';
    form.country_id = address.country_id ? String(address.country_id) : '';
    form.state_id = address.state_id ? String(address.state_id) : '';
    form.lga_id = address.lga_id ? String(address.lga_id) : '';
    form.city_id = address.city_id ? String(address.city_id) : '';
    form.postal_code = address.postal_code ?? '';
    form.is_default = address.is_default;
};

const removeAddress = (id: number) => router.delete(`/account/addresses/${id}`, { preserveScroll: true });
</script>

<template>
    <Head title="Saved Addresses" />

    <div class="grid gap-6 bg-slate-50 p-6 xl:grid-cols-[1.1fr_0.9fr]">
        <section class="space-y-6">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <h1 class="text-3xl font-semibold tracking-tight text-slate-900">Saved addresses</h1>
                <p class="mt-2 text-sm text-slate-500">Reuse delivery details across future checkouts and keep your preferred destination marked as default.</p>
            </div>

            <div v-if="addresses.data.length" class="space-y-4">
                <article v-for="address in addresses.data" :key="address.id" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <div class="flex items-center gap-2">
                                <h2 class="text-lg font-semibold text-slate-900">{{ address.label }}</h2>
                                <span v-if="address.is_default" class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">Default</span>
                            </div>
                            <p class="mt-2 text-sm font-medium text-slate-900">{{ address.recipient_name }}</p>
                            <p class="mt-1 text-sm text-slate-600">{{ address.line1 }}<span v-if="address.line2">, {{ address.line2 }}</span></p>
                            <p class="mt-1 text-sm text-slate-600">{{ [address.lga?.name, address.state?.name, address.country?.name].filter(Boolean).join(', ') }}</p>
                            <p v-if="address.phone" class="mt-1 text-sm text-slate-600">{{ address.phone }}</p>
                        </div>
                        <div class="flex gap-2">
                            <button type="button" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700" @click="startEdit(address)">Edit</button>
                            <button type="button" class="rounded-xl border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-600" @click="removeAddress(address.id)">Remove</button>
                        </div>
                    </div>
                </article>
            </div>

            <div v-else class="rounded-3xl border border-slate-200 bg-white px-6 py-14 text-center shadow-sm">
                <h2 class="text-lg font-semibold text-slate-900">No saved addresses yet</h2>
                <p class="mt-2 text-sm text-slate-500">Save delivery details once so checkout is faster next time.</p>
            </div>

            <Pagination :links="addresses.links" />
        </section>

        <aside class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold text-slate-900">{{ editingId ? 'Edit address' : 'Add a new address' }}</h2>
                    <p class="mt-1 text-sm text-slate-500">Keep your most-used shipping details ready.</p>
                </div>
                <button v-if="editingId" type="button" class="text-sm font-semibold text-slate-600" @click="resetForm">Cancel</button>
            </div>

            <form class="mt-6 space-y-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <label class="text-sm font-medium text-slate-700">Label</label>
                    <input v-model="form.label" type="text" class="h-11 rounded-xl border border-slate-300 px-3 text-sm" />
                    <p v-if="form.errors.label" class="text-xs font-medium text-rose-600">{{ form.errors.label }}</p>
                </div>

                <div class="grid gap-2">
                    <label class="text-sm font-medium text-slate-700">Recipient name</label>
                    <input v-model="form.recipient_name" type="text" class="h-11 rounded-xl border border-slate-300 px-3 text-sm" />
                    <p v-if="form.errors.recipient_name" class="text-xs font-medium text-rose-600">{{ form.errors.recipient_name }}</p>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="grid gap-2">
                        <label class="text-sm font-medium text-slate-700">Phone</label>
                        <input v-model="form.phone" type="text" class="h-11 rounded-xl border border-slate-300 px-3 text-sm" />
                    </div>
                    <div class="grid gap-2">
                        <label class="text-sm font-medium text-slate-700">Email</label>
                        <input v-model="form.email" type="email" class="h-11 rounded-xl border border-slate-300 px-3 text-sm" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <label class="text-sm font-medium text-slate-700">Address line 1</label>
                    <input v-model="form.line1" type="text" class="h-11 rounded-xl border border-slate-300 px-3 text-sm" />
                    <p v-if="form.errors.line1" class="text-xs font-medium text-rose-600">{{ form.errors.line1 }}</p>
                </div>

                <div class="grid gap-2">
                    <label class="text-sm font-medium text-slate-700">Address line 2</label>
                    <input v-model="form.line2" type="text" class="h-11 rounded-xl border border-slate-300 px-3 text-sm" />
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="grid gap-2">
                        <label class="text-sm font-medium text-slate-700">Country</label>
                        <select v-model="form.country_id" class="h-11 rounded-xl border border-slate-300 px-3 text-sm">
                            <option value="">Select country</option>
                            <option v-for="country in countries" :key="country.id" :value="country.id">{{ country.name }}</option>
                        </select>
                    </div>
                    <div class="grid gap-2">
                        <label class="text-sm font-medium text-slate-700">State</label>
                        <select v-model="form.state_id" class="h-11 rounded-xl border border-slate-300 px-3 text-sm">
                            <option value="">Select state</option>
                            <option v-for="state in states" :key="state.id" :value="state.id">{{ state.name }}</option>
                        </select>
                    </div>
                </div>

                <div class="grid gap-2">
                    <label class="text-sm font-medium text-slate-700">Postal code</label>
                    <input v-model="form.postal_code" type="text" class="h-11 rounded-xl border border-slate-300 px-3 text-sm" />
                </div>

                <label class="flex items-center gap-3 rounded-2xl bg-slate-50 px-4 py-3 text-sm text-slate-700">
                    <input v-model="form.is_default" type="checkbox" class="h-4 w-4 rounded border-slate-300" />
                    Make this my default address
                </label>

                <button type="submit" class="w-full rounded-xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white" :disabled="form.processing">
                    {{ form.processing ? 'Saving...' : editingId ? 'Update address' : 'Save address' }}
                </button>
            </form>
        </aside>
    </div>
</template>
