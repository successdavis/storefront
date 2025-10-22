<template>
  <div class="fixed inset-0 z-40 flex items-center justify-center">
    <div class="fixed inset-0 bg-black opacity-50" @click="emitClose"></div>

    <div class="z-50 w-[600px] max-h-[90vh] overflow-y-auto rounded-lg bg-white p-6 shadow-lg dark:bg-gray-800">
      <h2 class="mb-4 text-lg font-bold">Add New Customer</h2>

      <form @submit.prevent="onSave">
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="mb-1 block text-sm">Full Name</label>
            <input v-model="localNewCustomer.name" required class="w-full rounded border border-gray-300 px-3 py-2" />
          </div>

          <div>
            <label class="mb-1 block text-sm">Email</label>
            <input v-model="localNewCustomer.email" type="email" class="w-full rounded border border-gray-300 px-3 py-2" />
          </div>

          <div>
            <label class="mb-1 block text-sm">Phone</label>
            <input v-model="localNewCustomer.phone" required class="w-full rounded border border-gray-300 px-3 py-2" />
          </div>

          <div>
            <label class="mb-1 block text-sm">Country</label>
            <select v-model="localNewCustomer.country_id" @change="emitLoadStates(localNewCustomer.country_id)" required class="w-full rounded border border-gray-300 px-3 py-2">
              <option value="">Select Country</option>
              <option v-for="c in countries" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </div>

          <div>
            <label class="mb-1 block text-sm">State</label>
            <select v-model="localNewCustomer.state_id" @change="emitLoadLgas(localNewCustomer.state_id)" required class="w-full rounded border border-gray-300 px-3 py-2">
              <option value="">Select State</option>
              <option v-for="s in states" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>
          </div>

          <div>
            <label class="mb-1 block text-sm">LGA</label>
            <select v-model="localNewCustomer.lga_id" required class="w-full rounded border border-gray-300 px-3 py-2">
              <option value="">Select LGA</option>
              <option v-for="l in lgas" :key="l.id" :value="l.id">{{ l.name }}</option>
            </select>
          </div>

          <div>
            <label class="mb-1 block text-sm">Gender</label>
            <select v-model="localNewCustomer.gender" class="w-full rounded border border-gray-300 px-3 py-2">
              <option value="">Select Gender</option>
              <option>Male</option>
              <option>Female</option>
            </select>
          </div>

          <div class="col-span-2">
            <label class="mb-1 block text-sm">Shipping Address</label>
            <textarea v-model="localNewCustomer.address" class="w-full rounded border border-gray-300 px-3 py-2"></textarea>
          </div>
        </div>

        <div class="mt-5 flex justify-end gap-2">
          <button type="button" @click="emitClose" class="rounded border border-gray-300 px-4 py-2 hover:bg-gray-100">Cancel</button>
          <button type="submit" class="rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700">Save Customer</button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { reactive, toRefs } from 'vue'

const props = defineProps({
  countries: { type: Array, default: () => [] },
  states: { type: Array, default: () => [] },
  lgas: { type: Array, default: () => [] },
  newCustomer: { type: Object, default: () => ({}) },
})
const emit = defineEmits(['close', 'save', 'load-states', 'load-lgas'])

// create a local copy of newCustomer to avoid mutating parent props directly
const localNewCustomer = reactive({ ...props.newCustomer })

function emitClose() {
  emit('close')
}

// when saving, emit 'save' with the local data
async function onSave() {
  emit('save', { ...localNewCustomer })
}

// pass up requests to load states/lgas
function emitLoadStates(countryId) {
  emit('load-states', countryId)
}
function emitLoadLgas(stateId) {
  emit('load-lgas', stateId)
}
</script>
