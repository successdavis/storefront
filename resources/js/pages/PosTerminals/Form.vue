<script setup>
import { Head, Link, useForm } from "@inertiajs/vue3";

const props = defineProps({
    terminal: { type: Object, default: null },
    warehouses: Array,
});

const form = useForm({
    name: props.terminal?.name || "",
    warehouse_id: props.terminal?.warehouse_id || "",
    location: props.terminal?.location || "",
});

function submit() {
    if (props.terminal) {
        form.put(route("admin.pos-terminals.update", props.terminal.id));
    } else {
        form.post(route("admin.pos-terminals.store"));
    }
}
</script>


<template>
    <Head :title="props.terminal ? 'Edit POS Terminal' : 'Create POS Terminal'" />

    <div class="p-8 min-h-screen bg-gray-50 dark:bg-gray-900 text-gray-800 dark:text-gray-200">

        <div class="flex justify-between items-center mb-6">
            <h1 class="text-xl font-bold">
                {{ props.terminal ? "Edit POS Terminal" : "Create POS Terminal" }}
            </h1>

            <Link
                :href="route('admin.pos-terminals.index')"
                class="text-gray-600 dark:text-gray-300 hover:underline"
            >
                ← Back
            </Link>
        </div>

        <form @submit.prevent="submit" class="space-y-5">

            <!-- Name -->
            <div>
                <label class="block text-sm font-medium mb-1">Terminal Name</label>
                <input
                    v-model="form.name"
                    type="text"
                    class="w-full border rounded-lg p-2 bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-600 placeholder-gray-400 dark:placeholder-gray-500 focus:ring-blue-500 focus:border-blue-500"
                    placeholder="e.g. Storefront Terminal"
                />
                <div v-if="form.errors.name" class="text-red-500 text-sm">
                    {{ form.errors.name }}
                </div>
            </div>

            <!-- Warehouse -->
            <div>
                <label class="block text-sm font-medium mb-1">Warehouse</label>
                <select
                    v-model="form.warehouse_id"
                    class="w-full border rounded-lg p-2 bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-600 focus:ring-blue-500 focus:border-blue-500"
                >
                    <option value="" disabled>-- Select Warehouse --</option>
                    <option
                        v-for="wh in warehouses"
                        :key="wh.id"
                        :value="wh.id"
                    >
                        {{ wh.name }}
                    </option>
                </select>
                <div v-if="form.errors.warehouse_id" class="text-red-500 text-sm">
                    {{ form.errors.warehouse_id }}
                </div>
            </div>

            <!-- Location -->
            <div>
                <label class="block text-sm font-medium mb-1">Location (optional)</label>
                <textarea
                    v-model="form.location"
                    class="w-full border rounded-lg p-2 bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-600 placeholder-gray-400 dark:placeholder-gray-500 focus:ring-blue-500 focus:border-blue-500"
                    placeholder="Terminal exact location"
                ></textarea>
                <div v-if="form.errors.location" class="text-red-500 text-sm">
                    {{ form.errors.location }}
                </div>
            </div>

            <!-- Submit -->
            <button
                type="submit"
                class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2 rounded-lg transition disabled:opacity-75"
                :disabled="form.processing"
            >
                {{ form.processing ? "Saving..." : (props.terminal ? "Update Terminal" : "Create Terminal") }}
            </button>

        </form>
    </div>
</template>
