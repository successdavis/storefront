<script setup>
import { Head, Link, useForm, router } from "@inertiajs/vue3";
import { ref, watch, computed, onMounted, onBeforeUnmount, nextTick } from "vue";
import {
    Save,
    ArrowLeft,
    Search,
    Loader2,
    UserPlus,
    UserCircle2,
    Building2,
} from "lucide-vue-next";
import axios from 'axios';

const props = defineProps({
    staff: {
        type: Object,
        default: null, // { id, employee_id, warehouse_id, role }
    },
    warehouses: {
        type: Array,
        default: () => [],
    },
    roles: {
        type: Array,
        default: () => [], // [{ id, name }]
    },
});

const isEdit = computed(() => !!props.staff?.id);

// --- Inertia form state ---
const form = useForm({
    employee_id: props.staff?.employee_id ?? null,
    warehouse_id: props.staff?.warehouse_id ?? "",
    role: props.staff?.role ?? "",
});

// --- User search state ---
const userQuery = ref("");
const userResults = ref([]);
const userLoading = ref(false);
const showUserDropdown = ref(false);
const highlighted = ref(-1);

const dropdownRef = ref(null);
const inputRef = ref(null);

// Simple debounce
let debounceId;
function debounce(fn, delay = 350) {
    clearTimeout(debounceId);
    debounceId = setTimeout(fn, delay);
}

function fetchUsers() {
    const q = userQuery.value.trim();
    if (!q) {
        userResults.value = [];
        showUserDropdown.value = false;
        return;
    }
    userLoading.value = true;
    router.reload({
        only: [],
        preserveScroll: true,
        // Use axios directly for speed; but Inertia route helper is fine here:
        onSuccess: () => {},
        onFinish: () => {},
    });
    axios
        .get(route("admin.staff.search-user"), { params: { search: q } })
        .then(({ data }) => {
            userResults.value = Array.isArray(data) ? data : [];
            showUserDropdown.value = userResults.value.length > 0;
        })
        .catch(() => {
            userResults.value = [];
            showUserDropdown.value = false;
        })
        .finally(() => {
            userLoading.value = false;
            highlighted.value = -1;
        });
}

watch(userQuery, () => debounce(fetchUsers, 300));

// Keyboard navigation
function onUserKeydown(e) {
    if (!showUserDropdown.value || userResults.value.length === 0) return;

    if (e.key === "ArrowDown") {
        e.preventDefault();
        highlighted.value =
            (highlighted.value + 1) % Math.max(userResults.value.length, 1);
    } else if (e.key === "ArrowUp") {
        e.preventDefault();
        highlighted.value =
            (highlighted.value - 1 + userResults.value.length) %
            Math.max(userResults.value.length, 1);
    } else if (e.key === "Enter") {
        e.preventDefault();
        if (highlighted.value >= 0) {
            selectUser(userResults.value[highlighted.value]);
        }
    } else if (e.key === "Escape") {
        showUserDropdown.value = false;
    }
}

function selectUser(user) {
    form.employee_id = user.id;
    userQuery.value = `${user.name} — ${user.email}`;
    showUserDropdown.value = false;
}

// Click outside to close dropdown
function onClickOutside(e) {
    const el = dropdownRef.value;
    if (!el) return;
    if (!el.contains(e.target) && e.target !== inputRef.value) {
        showUserDropdown.value = false;
    }
}
onMounted(() => document.addEventListener("click", onClickOutside));
onBeforeUnmount(() => document.removeEventListener("click", onClickOutside));

// If editing, lock employee selector (keep visible but disabled)
const employeeLocked = computed(() => isEdit.value);

// Submit
function submit() {
    if (isEdit.value) {
        form.put(route("admin.staff.update", props.staff.id), {
            preserveScroll: true,
        });
    } else {
        form.post(route("admin.staff.store"), {
            preserveScroll: true,
            onSuccess: () => {
                // Clear the form but keep UX context
                form.reset("employee_id", "warehouse_id", "role");
                userQuery.value = "";
                userResults.value = [];
            },
        });
    }
}
</script>

<template>
    <Head :title="isEdit ? 'Edit Staff' : 'New Staff'" />
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header -->
        <div class="mb-6 flex items-start justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                    {{ isEdit ? "Edit Staff" : "Create Staff" }}
                </h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Assign a user to a warehouse and role.
                </p>
            </div>
            <Link
                :href="route('admin.staff.index')"
                class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/60"
            >
                <ArrowLeft class="w-4 h-4" />
                Back
            </Link>
        </div>

        <!-- Card -->
        <form
            @submit.prevent="submit"
            class="rounded-2xl border bg-white p-4 sm:p-6 dark:bg-gray-900 dark:border-gray-800"
        >
            <!-- Employee -->
            <div class="mb-5">
                <label
                    for="employee"
                    class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1"
                >Employee</label
                >

                <!-- Search input -->
                <div
                    class="relative"
                    ref="dropdownRef"
                >
                    <div
                        class="flex items-center gap-2 rounded-xl border px-3 py-2 dark:border-gray-800 focus-within:ring-2 focus-within:ring-indigo-500"
                        :class="employeeLocked ? 'bg-gray-50 dark:bg-gray-800/30' : 'bg-white dark:bg-transparent'"
                    >
                        <Search class="w-5 h-5 text-gray-400" />
                        <input
                            id="employee"
                            ref="inputRef"
                            v-model="userQuery"
                            :disabled="employeeLocked"
                            :placeholder="employeeLocked ? 'Employee locked for editing' : 'Search name or email...'"
                            @keydown="onUserKeydown"
                            autocomplete="off"
                            class="w-full bg-transparent outline-none text-gray-900 dark:text-gray-100 placeholder:text-gray-400 disabled:cursor-not-allowed"
                        />
                        <Loader2
                            v-if="userLoading"
                            class="w-5 h-5 animate-spin text-gray-400"
                        />
                        <UserPlus v-else class="w-5 h-5 text-gray-400" />
                    </div>

                    <!-- Dropdown -->
                    <div
                        v-if="showUserDropdown"
                        class="absolute z-20 mt-2 w-full overflow-hidden rounded-xl border bg-white dark:bg-gray-900 dark:border-gray-800 shadow-lg"
                        role="listbox"
                        aria-labelledby="employee"
                    >
                        <template v-if="userResults.length">
                            <button
                                v-for="(u, i) in userResults"
                                :key="u.id"
                                type="button"
                                @click="selectUser(u)"
                                :class="[
                  'w-full flex items-start gap-3 px-3 py-2 text-left hover:bg-gray-50 dark:hover:bg-gray-800/60',
                  i === highlighted ? 'bg-gray-50 dark:bg-gray-800/60' : ''
                ]"
                                role="option"
                                :aria-selected="i === highlighted"
                            >
                                <UserCircle2 class="w-5 h-5 text-gray-400 mt-0.5" />
                                <div>
                                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                        {{ u.name }}
                                    </div>
                                    <div class="text-xs text-gray-500">{{ u.email }}</div>
                                </div>
                            </button>
                        </template>
                        <div
                            v-else
                            class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400"
                        >
                            No matches.
                        </div>
                    </div>
                </div>

                <!-- Validation -->
                <p
                    v-if="form.errors.employee_id"
                    class="mt-1 text-sm text-red-600"
                >
                    {{ form.errors.employee_id }}
                </p>
            </div>

            <!-- Warehouse -->
            <div class="mb-5">
                <label
                    for="warehouse"
                    class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1"
                >Warehouse</label
                >
                <div
                    class="flex items-center gap-2 rounded-xl border px-3 py-2 dark:border-gray-800"
                >
                    <Building2 class="w-5 h-5 text-gray-400" />
                    <select
                        id="warehouse"
                        v-model="form.warehouse_id"
                        class="w-full bg-transparent outline-none text-gray-900 dark:text-gray-100"
                    >
                        <option disabled value="">Select a warehouse…</option>
                        <option
                            v-for="w in warehouses"
                            :key="w.id"
                            :value="w.id"
                            class="bg-white dark:bg-gray-900"
                        >
                            {{ w.name }}
                        </option>
                    </select>
                </div>
                <p v-if="form.errors.warehouse_id" class="mt-1 text-sm text-red-600">
                    {{ form.errors.warehouse_id }}
                </p>
            </div>

            <!-- Role -->
            <div class="mb-6">
                <label
                    for="role"
                    class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1"
                >Role</label
                >
                <div class="rounded-xl border px-3 py-2 dark:border-gray-800">
                    <select
                        id="role"
                        v-model="form.role"
                        class="w-full bg-transparent outline-none text-gray-900 dark:text-gray-100"
                    >
                        <option disabled value="">Select a role…</option>
                        <option
                            v-for="r in roles"
                            :key="r.id"
                            :value="r.name"
                            class="bg-white dark:bg-gray-900"
                        >
                            {{ r.name }}
                        </option>
                    </select>
                </div>
                <p v-if="form.errors.role" class="mt-1 text-sm text-red-600">
                    {{ form.errors.role }}
                </p>
            </div>

            <!-- Hidden bind for employee_id to trigger validation -->
            <input type="hidden" :value="form.employee_id" name="employee_id" />

            <!-- Actions -->
            <div class="flex items-center justify-end gap-3">
                <Link
                    :href="route('admin.staff.index')"
                    class="inline-flex items-center gap-2 rounded-lg px-4 py-2 border dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/60"
                >
                    Cancel
                </Link>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="inline-flex items-center gap-2 rounded-xl px-4 py-2 bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-60 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:focus:ring-offset-0"
                >
                    <Save class="w-5 h-5" />
                    <span>{{ isEdit ? "Save Changes" : "Create Staff" }}</span>
                </button>
            </div>
        </form>
    </div>
</template>

<style scoped>
/* Improve dropdown stacking in complex layouts */
</style>
