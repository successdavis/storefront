<script setup>
import { Head, Link, router } from "@inertiajs/vue3";
import { ref, computed, watch } from "vue";
import {
    PlusCircle,
    Search,
    Pencil,
    Trash2,
    UserCircle2,
    Building2,
} from "lucide-vue-next";

const props = defineProps({
    staff: { type: Array, default: () => [] }, // from controller: EmployeeWarehouse with relations
});

const q = ref("");
const filtered = computed(() => {
    const term = q.value.trim().toLowerCase();
    if (!term) return props.staff;
    return props.staff.filter((s) => {
        const name = s.employee?.name ?? "";
        const email = s.employee?.email ?? "";
        const wh = s.warehouse?.name ?? "";
        const role = s.employee?.roles?.[0]?.name ?? s.role ?? "";
        return [name, email, wh, role].some((v) =>
            String(v).toLowerCase().includes(term)
        );
    });
});

function destroyRow(id) {
    if (!confirm("Remove this staff assignment? This cannot be undone.")) return;
    router.delete(route("admin.staff.destroy", id), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Staff" />
    <div class=" px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                    Staff
                </h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Assign users to warehouses and roles.
                </p>
            </div>
            <Link
                :href="route('admin.staff.create')"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-transparent bg-indigo-600 text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:focus:ring-offset-0"
            >
                <PlusCircle class="w-5 h-5" />
                New Staff
            </Link>
        </div>

        <div
            class="mb-4 flex items-center gap-2 rounded-xl border bg-white dark:bg-gray-900 dark:border-gray-800 px-3 py-2"
        >
            <Search class="w-5 h-5 text-gray-400" />
            <input
                v-model="q"
                type="search"
                placeholder="Search by name, email, warehouse or role..."
                class="w-full bg-transparent outline-none text-gray-900 dark:text-gray-100 placeholder:text-gray-400"
            />
        </div>

        <div
            class="overflow-hidden rounded-2xl border bg-white dark:bg-gray-900 dark:border-gray-800"
        >
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                <thead class="bg-gray-50 dark:bg-gray-800/50">
                <tr class="text-left text-xs font-medium uppercase tracking-wider">
                    <th class="px-4 py-3 text-gray-600 dark:text-gray-300">Employee</th>
                    <th class="px-4 py-3 text-gray-600 dark:text-gray-300">Warehouse</th>
                    <th class="px-4 py-3 text-gray-600 dark:text-gray-300">Role</th>
                    <th class="px-4 py-3 text-right text-gray-600 dark:text-gray-300">
                        Actions
                    </th>
                </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                <tr v-if="filtered.length === 0">
                    <td colspan="4" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                        No staff found.
                    </td>
                </tr>

                <tr
                    v-for="row in filtered"
                    :key="row.id"
                    class="hover:bg-gray-50 dark:hover:bg-gray-800/60"
                >
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-3">
                            <UserCircle2 class="w-6 h-6 text-gray-400" />
                            <div>
                                <div class="font-medium text-gray-900 dark:text-gray-100">
                                    {{ row.employee?.name || "—" }}
                                </div>
                                <div class="text-xs text-gray-500">
                                    {{ row.employee?.email || "" }}
                                </div>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2">
                            <Building2 class="w-5 h-5 text-gray-400" />
                            <span class="text-gray-900 dark:text-gray-100">{{
                                    row.warehouse?.name || "—"
                                }}</span>
                        </div>
                    </td>
                    <td class="px-4 py-3">
              <span
                  class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300"
              >
                {{ row.employee?.role || row.role || "—" }}
              </span>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-end gap-2">
                            <Link
                                :href="route('admin.staff.edit', row.id)"
                                class="inline-flex items-center gap-2 rounded-lg px-3 py-1.5 text-sm border dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/60"
                            >
                                <Pencil class="w-4 h-4" />
                                Edit
                            </Link>
                            <button
                                type="button"
                                @click="destroyRow(row.id)"
                                class="inline-flex items-center gap-2 rounded-lg px-3 py-1.5 text-sm text-red-600 border border-red-200 hover:bg-red-50 dark:text-red-400 dark:border-red-900/50 dark:hover:bg-red-950/40"
                            >
                                <Trash2 class="w-4 h-4" />
                                Delete
                            </button>
                        </div>
                    </td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
