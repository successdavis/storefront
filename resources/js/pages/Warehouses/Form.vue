<script setup>
import { Head, Link, useForm } from "@inertiajs/vue3";

const props = defineProps({
    warehouse: {
        type: Object,
        default: null,
    },
});

// Initial form state for Create or Edit
const form = useForm({
    name: props.warehouse?.name ?? "",
    code: props.warehouse?.code ?? "",
    address: props.warehouse?.address ?? "",
    city: props.warehouse?.city ?? "",
    state: props.warehouse?.state ?? "",
    country: props.warehouse?.country ?? "Nigeria",
    contact_person: props.warehouse?.contact_person ?? "",
    phone: props.warehouse?.phone ?? "",
    email: props.warehouse?.email ?? "",
    active: props.warehouse?.active ?? true,
});

function submit() {
    if (props.warehouse?.id) {
        form.put(route("admin.warehouses.update", props.warehouse.id));
    } else {
        form.post(route("admin.warehouses.store"));
    }
}
</script>

<template>
    <Head :title="props.warehouse ? 'Edit Warehouse' : 'Create Warehouse'" />

    <div class="max-w-4xl mx-auto p-6 text-gray-900 dark:text-gray-100">
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-semibold">
                {{ props.warehouse ? "Edit Warehouse" : "Create Warehouse" }}
            </h1>

            <Link
                :href="route('admin.warehouses.index')"
                class="inline-flex items-center px-4 py-2 rounded-lg border border-gray-300 text-gray-700 bg-white hover:bg-gray-50
               dark:bg-gray-800 dark:text-gray-200 dark:border-gray-700 dark:hover:bg-gray-700"
            >
                Back
            </Link>
        </div>

        <!-- Card -->
        <div
            class="rounded-xl border border-gray-200 bg-white shadow-sm p-6
             dark:border-gray-700 dark:bg-gray-900"
        >
            <form @submit.prevent="submit" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Name -->
                <div class="space-y-1">
                    <label class="block text-sm font-medium">Name <span class="text-red-500">*</span></label>
                    <input
                        v-model="form.name"
                        type="text"
                        required
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500
                   bg-white text-gray-900
                   dark:bg-gray-800 dark:text-gray-100 dark:border-gray-700 dark:focus:ring-indigo-500 dark:focus:border-indigo-500"
                        placeholder="Main Warehouse"
                    />
                    <div v-if="form.errors.name" class="text-sm text-red-500">{{ form.errors.name }}</div>
                </div>

                <!-- Code -->
                <div class="space-y-1">
                    <label class="block text-sm font-medium">Code <span class="text-red-500">*</span></label>
                    <input
                        v-model="form.code"
                        type="text"
                        required
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500
                   bg-white text-gray-900
                   dark:bg-gray-800 dark:text-gray-100 dark:border-gray-700 dark:focus:ring-indigo-500 dark:focus:border-indigo-500"
                        placeholder="WH-NG-01"
                    />
                    <div v-if="form.errors.code" class="text-sm text-red-500">{{ form.errors.code }}</div>
                </div>

                <!-- Address (full width) -->
                <div class="space-y-1 md:col-span-2">
                    <label class="block text-sm font-medium">Address</label>
                    <textarea
                        v-model="form.address"
                        rows="3"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500
                   bg-white text-gray-900
                   dark:bg-gray-800 dark:text-gray-100 dark:border-gray-700 dark:focus:ring-indigo-500 dark:focus:border-indigo-500"
                        placeholder="Street, Area, Landmark"
                    />
                    <div v-if="form.errors.address" class="text-sm text-red-500">{{ form.errors.address }}</div>
                </div>

                <!-- City -->
                <div class="space-y-1">
                    <label class="block text-sm font-medium">City</label>
                    <input
                        v-model="form.city"
                        type="text"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500
                   bg-white text-gray-900
                   dark:bg-gray-800 dark:text-gray-100 dark:border-gray-700 dark:focus:ring-indigo-500 dark:focus:border-indigo-500"
                        placeholder="Lagos"
                    />
                    <div v-if="form.errors.city" class="text-sm text-red-500">{{ form.errors.city }}</div>
                </div>

                <!-- State -->
                <div class="space-y-1">
                    <label class="block text-sm font-medium">State</label>
                    <input
                        v-model="form.state"
                        type="text"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500
                   bg-white text-gray-900
                   dark:bg-gray-800 dark:text-gray-100 dark:border-gray-700 dark:focus:ring-indigo-500 dark:focus:border-indigo-500"
                        placeholder="Lagos State"
                    />
                    <div v-if="form.errors.state" class="text-sm text-red-500">{{ form.errors.state }}</div>
                </div>

                <!-- Country -->
                <div class="space-y-1">
                    <label class="block text-sm font-medium">Country</label>
                    <input
                        v-model="form.country"
                        type="text"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500
                   bg-white text-gray-900
                   dark:bg-gray-800 dark:text-gray-100 dark:border-gray-700 dark:focus:ring-indigo-500 dark:focus:border-indigo-500"
                        placeholder="Nigeria"
                    />
                    <div v-if="form.errors.country" class="text-sm text-red-500">{{ form.errors.country }}</div>
                </div>

                <!-- Contact Person -->
                <div class="space-y-1">
                    <label class="block text-sm font-medium">Contact Person</label>
                    <input
                        v-model="form.contact_person"
                        type="text"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500
                   bg-white text-gray-900
                   dark:bg-gray-800 dark:text-gray-100 dark:border-gray-700 dark:focus:ring-indigo-500 dark:focus:border-indigo-500"
                        placeholder="Jane Doe"
                    />
                    <div v-if="form.errors.contact_person" class="text-sm text-red-500">
                        {{ form.errors.contact_person }}
                    </div>
                </div>

                <!-- Phone -->
                <div class="space-y-1">
                    <label class="block text-sm font-medium">Phone</label>
                    <input
                        v-model="form.phone"
                        type="text"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500
                   bg-white text-gray-900
                   dark:bg-gray-800 dark:text-gray-100 dark:border-gray-700 dark:focus:ring-indigo-500 dark:focus:border-indigo-500"
                        placeholder="+234 800 000 0000"
                    />
                    <div v-if="form.errors.phone" class="text-sm text-red-500">{{ form.errors.phone }}</div>
                </div>

                <!-- Email -->
                <div class="space-y-1">
                    <label class="block text-sm font-medium">Email</label>
                    <input
                        v-model="form.email"
                        type="email"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500
                   bg-white text-gray-900
                   dark:bg-gray-800 dark:text-gray-100 dark:border-gray-700 dark:focus:ring-indigo-500 dark:focus:border-indigo-500"
                        placeholder="warehouse@example.com"
                    />
                    <div v-if="form.errors.email" class="text-sm text-red-500">{{ form.errors.email }}</div>
                </div>

                <!-- Active -->
                <div class="space-y-1">
                    <label class="block text-sm font-medium">Status</label>
                    <select
                        v-model="form.active"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500
                   bg-white text-gray-900
                   dark:bg-gray-800 dark:text-gray-100 dark:border-gray-700 dark:focus:ring-indigo-500 dark:focus:border-indigo-500"
                    >
                        <option :value="true">Active</option>
                        <option :value="false">Inactive</option>
                    </select>
                    <div v-if="form.errors.active" class="text-sm text-red-500">{{ form.errors.active }}</div>
                </div>

                <!-- Submit -->
                <div class="md:col-span-2 flex justify-end pt-2">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="inline-flex items-center px-6 py-2 rounded-lg
                   bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-60 disabled:cursor-not-allowed
                   dark:bg-indigo-500 dark:hover:bg-indigo-600"
                    >
                        {{ props.warehouse ? "Update Warehouse" : "Create Warehouse" }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
