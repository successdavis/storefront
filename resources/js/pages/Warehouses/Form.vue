<script setup>
import { Head, Link, useForm } from "@inertiajs/vue3";
import { ref, onMounted, watch } from "vue";
import axios from "axios";

const props = defineProps({
  warehouse: { type: Object, default: null },
});

// Coerce DB IDs to numbers so selects can match correctly
const num = (v) => (v === null || v === undefined || v === "" ? "" : Number(v));

// Form (no city_id anymore)
const form = useForm({
  name: props.warehouse?.name ?? "",
  code: props.warehouse?.code ?? "",
  address: props.warehouse?.address ?? "",
  country_id: num(props.warehouse?.country_id),
  state_id: num(props.warehouse?.state_id),
  lga_id: num(props.warehouse?.lga_id),
  contact_person: props.warehouse?.contact_person ?? "",
  phone: props.warehouse?.phone ?? "",
  email: props.warehouse?.email ?? "",
  active: props.warehouse?.active ?? true,
});

// Options
const countries = ref([]);
const states = ref([]);
const lgas = ref([]);

// Helpers
async function loadCountries() {
  const { data } = await axios.get(route("locations.countries"));
  countries.value = data;
}

/**
 * Load states for current country.
 * @param {Object} opts
 * @param {boolean} opts.preserve - keep current selection (used during Edit preload)
 */
async function loadStates({ preserve = false } = {}) {
  states.value = [];
  if (!preserve) {
    form.state_id = "";
    form.lga_id = "";
    lgas.value = [];
  }

  if (!form.country_id) return;

  const { data } = await axios.get(route("locations.states", form.country_id));
  states.value = data;

  // If preserving, only clear if the current state_id doesn't exist in loaded list
  if (preserve && form.state_id) {
    const exists = states.value.some((s) => Number(s.id) === Number(form.state_id));
    if (!exists) {
      form.state_id = "";
      form.lga_id = "";
      lgas.value = [];
    }
  }
}

/**
 * Load LGAs for current state.
 * @param {Object} opts
 * @param {boolean} opts.preserve - keep current selection (used during Edit preload)
 */
async function loadLgas({ preserve = false } = {}) {
  lgas.value = [];
  if (!preserve) {
    form.lga_id = "";
  }

  if (!form.state_id) return;

  const { data } = await axios.get(route("locations.lgas", form.state_id));
  lgas.value = data;

  if (preserve && form.lga_id) {
    const exists = lgas.value.some((l) => Number(l.id) === Number(form.lga_id));
    if (!exists) form.lga_id = "";
  }
}

// Cascade on change (not preserving, because user changed parent)
watch(() => form.country_id, () => loadStates({ preserve: false }));
watch(() => form.state_id, () => loadLgas({ preserve: false }));

// Preload on Edit (preserving)
onMounted(async () => {
  await loadCountries();

  if (form.country_id) {
    await loadStates({ preserve: true });
  }
  if (form.state_id) {
    await loadLgas({ preserve: true });
  }
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
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm p-6 dark:border-gray-700 dark:bg-gray-900">
      <form @submit.prevent="submit" class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <!-- Name -->
        <div class="space-y-1">
          <label class="block text-sm font-medium">Name <span class="text-red-500">*</span></label>
          <input
            v-model="form.name"
            type="text"
            required
            placeholder="Main Warehouse"
            class="w-full rounded-lg border border-gray-300 px-3 py-2 outline-none
                   bg-white text-gray-900
                   focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500
                   dark:bg-gray-800 dark:text-gray-100 dark:border-gray-700"
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
            placeholder="WH-NG-01"
            class="w-full rounded-lg border border-gray-300 px-3 py-2 outline-none
                   bg-white text-gray-900
                   focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500
                   dark:bg-gray-800 dark:text-gray-100 dark:border-gray-700"
          />
          <div v-if="form.errors.code" class="text-sm text-red-500">{{ form.errors.code }}</div>
        </div>

        <!-- Country -->
        <div class="space-y-1">
          <label class="block text-sm font-medium">Country <span class="text-red-500">*</span></label>
          <select
            v-model="form.country_id"
            class="w-full rounded-lg border border-gray-300 px-3 py-2 outline-none
                   bg-white text-gray-900
                   focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500
                   dark:bg-gray-800 dark:text-gray-100 dark:border-gray-700"
          >
            <option value="">Select country</option>
            <option v-for="c in countries" :key="c.id" :value="Number(c.id)">
              {{ c.name }}
            </option>
          </select>
          <div v-if="form.errors.country_id" class="text-sm text-red-500">{{ form.errors.country_id }}</div>
        </div>

        <!-- State -->
        <div class="space-y-1">
          <label class="block text-sm font-medium">State <span class="text-red-500">*</span></label>
          <select
            v-model="form.state_id"
            :disabled="states.length === 0"
            class="w-full rounded-lg border border-gray-300 px-3 py-2 outline-none
                   bg-white text-gray-900
                   focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500
                   disabled:bg-gray-100 disabled:text-gray-400
                   dark:bg-gray-800 dark:text-gray-100 dark:border-gray-700 dark:disabled:bg-gray-900 dark:disabled:text-gray-600"
          >
            <option value="">Select state</option>
            <option v-for="s in states" :key="s.id" :value="Number(s.id)">
              {{ s.name }}
            </option>
          </select>
          <div v-if="form.errors.state_id" class="text-sm text-red-500">{{ form.errors.state_id }}</div>
        </div>

        <!-- LGA -->
        <div class="space-y-1">
          <label class="block text-sm font-medium">LGA <span class="text-red-500">*</span></label>
          <select
            v-model="form.lga_id"
            :disabled="lgas.length === 0"
            class="w-full rounded-lg border border-gray-300 px-3 py-2 outline-none
                   bg-white text-gray-900
                   focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500
                   disabled:bg-gray-100 disabled:text-gray-400
                   dark:bg-gray-800 dark:text-gray-100 dark:border-gray-700 dark:disabled:bg-gray-900 dark:disabled:text-gray-600"
          >
            <option value="">Select LGA</option>
            <option v-for="l in lgas" :key="l.id" :value="Number(l.id)">
              {{ l.name }}
            </option>
          </select>
          <div v-if="form.errors.lga_id" class="text-sm text-red-500">{{ form.errors.lga_id }}</div>
        </div>

        <!-- Address -->
        <div class="space-y-1 md:col-span-2">
          <label class="block text-sm font-medium">Address</label>
          <textarea
            v-model="form.address"
            rows="3"
            class="w-full rounded-lg border border-gray-300 px-3 py-2 outline-none
                   bg-white text-gray-900
                   focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500
                   dark:bg-gray-800 dark:text-gray-100 dark:border-gray-700"
          ></textarea>
          <div v-if="form.errors.address" class="text-sm text-red-500">{{ form.errors.address }}</div>
        </div>

        <!-- Contact Person -->
        <div class="space-y-1">
          <label class="block text-sm font-medium">Contact Person</label>
          <input
            v-model="form.contact_person"
            placeholder="John Doe"
            class="w-full rounded-lg border border-gray-300 px-3 py-2 outline-none
                   bg-white text-gray-900
                   focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500
                   dark:bg-gray-800 dark:text-gray-100 dark:border-gray-700"
          />
          <div v-if="form.errors.contact_person" class="text-sm text-red-500">{{ form.errors.contact_person }}</div>
        </div>

        <!-- Phone -->
        <div class="space-y-1">
          <label class="block text-sm font-medium">Phone</label>
          <input
            v-model="form.phone"
            placeholder="+234..."
            class="w-full rounded-lg border border-gray-300 px-3 py-2 outline-none
                   bg-white text-gray-900
                   focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500
                   dark:bg-gray-800 dark:text-gray-100 dark:border-gray-700"
          />
          <div v-if="form.errors.phone" class="text-sm text-red-500">{{ form.errors.phone }}</div>
        </div>

        <!-- Email -->
        <div class="space-y-1">
          <label class="block text-sm font-medium">Email</label>
          <input
            v-model="form.email"
            type="email"
            placeholder="warehouse@example.com"
            class="w-full rounded-lg border border-gray-300 px-3 py-2 outline-none
                   bg-white text-gray-900
                   focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500
                   dark:bg-gray-800 dark:text-gray-100 dark:border-gray-700"
          />
          <div v-if="form.errors.email" class="text-sm text-red-500">{{ form.errors.email }}</div>
        </div>

        <!-- Active -->
        <div class="space-y-1">
          <label class="block text-sm font-medium">Status</label>
          <select
            v-model="form.active"
            class="w-full rounded-lg border border-gray-300 px-3 py-2 outline-none
                   bg-white text-gray-900
                   focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500
                   dark:bg-gray-800 dark:text-gray-100 dark:border-gray-700"
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
