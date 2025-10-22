<template>
    <div class="fixed inset-0 z-40 flex items-center justify-center">
        <div class="fixed inset-0 bg-black opacity-50" @click="close"></div>

        <div class="z-50 w-[700px] max-h-[90vh] overflow-y-auto rounded-lg bg-white p-6 shadow-lg dark:bg-gray-800">
            <h2 class="mb-4 text-lg font-bold">Shipping & Pickup</h2>

            <form @submit.prevent="emitShipment">
                <div class="grid grid-cols-2 gap-3 mb-8">
                    <div>
                        <label class="block text-sm mb-1">Shipping Method</label>
                        <select v-model="form.shipping_method_id" class="w-full rounded border px-3 py-2" required>
                            <option value="">— Select Method</option>
                            <option v-for="m in methods" :key="m.id" :value="m.id">{{ m.name }}</option>
                        </select>
                    </div>

                    <div v-if="selectedMethodName !== 'Pickup'">
                        <label class="block text-sm mb-1">Receiver's Phone</label>
                        <input v-model="form.address.phone" class="w-full rounded border px-3 py-2" />
                    </div>

                    <div v-if="selectedMethodName === 'Pickup'">
                        <label class="block text-sm mb-1">Pickup Location</label>
                        <select v-model="form.pickup_location_id" class="w-full rounded border px-3 py-2">
                            <option :value="null">— Select Pickup Location</option>
                            <option v-for="p in pickupLocations" :key="p.id" :value="p.id">{{ p.name }} — {{ p.address_line1 }}</option>
                        </select>
                    </div>
                </div>

                <div v-if="selectedMethodName !== 'Pickup'" class="grid grid-cols-2 mb-6 gap-3">
                    <!-- COUNTRY -->
                    <div>
                        <label class="mb-1 block text-sm">Country</label>
                        <select v-model="form.address.country_id" @change="emitLoadStates(form.address.country_id)" class="w-full rounded border border-gray-300 px-3 py-2">
                            <option value="">— Select Country</option>
                            <option v-for="c in countries" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>

                    <!-- STATE -->
                    <div>
                        <label class="mb-1 block text-sm">State</label>
                        <select v-model="form.address.state_id" @change="handleStateChange" class="w-full rounded border border-gray-300 px-3 py-2">
                            <option value="">Select State</option>
                            <option v-for="s in states" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                    </div>

                    <!-- SHIPPING ZONE -->
                    <div>
                        <label class="block text-sm mb-1">Shipping Zone</label>
                        <select v-model="form.shipping_zone_id" class="w-full rounded border px-3 py-2">
                            <option :value="null">— Select Zone</option>
                            <option v-for="z in zones" :key="z.id" :value="z.id">{{ z.name }}</option>
                        </select>
                    </div>

                    <!-- LGA -->
                    <div>
                        <label class="mb-1 block text-sm">LGA</label>
                        <select v-model="form.address.lga_id" class="w-full rounded border border-gray-300 px-3 py-2">
                            <option value="">Select LGA</option>
                            <option v-for="l in lgas" :key="l.id" :value="l.id">{{ l.name }}</option>
                        </select>
                    </div>

                    <div class="col-span-2">
                        <label class="block text-sm mb-3">Address (line1)</label>
                        <input v-model="form.address.line1" class="w-full rounded border px-3 py-2" />
                    </div>

                    <!-- SHIPPING COST (read-only while calculating) -->
                    <div class="col-span-2">
                        <label class="block text-sm mb-3">Shipping Cost (₦)</label>
                        <input type="number" step="0.01" v-model.number="form.shipping_cost" class="w-full rounded border px-3 py-2" :readonly="isCalculating" />
                        <div v-if="isCalculating" class="text-xs text-gray-500 mt-1">Calculating...</div>
                    </div>
                </div>

                <div class="flex gap-2 items-center">
                    <button type="button" class="rounded border px-4 py-2" @click="close">Cancel</button>
                    <button type="submit" class="ml-auto rounded bg-blue-600 px-4 py-2 text-white" :disabled="!canSave">Save Shipment</button>
                </div>

                <div v-if="error" class="mt-3 text-red-500 text-sm">{{ error }}</div>
            </form>
        </div>
    </div>
</template>

<script setup>
import { reactive, ref, onMounted, computed, watch } from 'vue'
import axios from 'axios'
import { route } from 'ziggy-js'

const emit = defineEmits(['close', 'created', 'load-states', 'load-lgas'])

const props = defineProps({
    countries: { type: Array, default: () => [] },
    states: { type: Array, default: () => [] },
    lgas: { type: Array, default: () => [] },
    items: { type: Array, default: () => [] },
    subtotal: { type: Number, default: 0 },
    initialShipping: { type: Object, default: null },
    methods: { type: Array, default: () => [] },
    zones: { type: Array, default: () => [] },
    pickupLocations: { type: Array, default: () => [] },
})

/* ---------- local state ---------- */

const error = ref(null)
const isCalculating = ref(false)
let calcController = null

const form = reactive({
    shipping_method_id: null,
    shipping_zone_id: null,
    pickup_location_id: null,
    shipping_cost: 0,
    weight: 0,
    subtotal: props.subtotal || 0,
    address: {
        line1: '',
        phone: '',
        country_id: '',
        state_id: '',
        lga_id: ''
    },
})

/* ---------- helper functions ---------- */

// Preselect Nigeria if present in props.countries
function preselectNigeria() {
    try {
        if (!Array.isArray(props.countries) || props.countries.length === 0) {
            // helpful debug — parent sometimes passes empty countries while loading
            console.warn('ShippingModal: countries prop empty or not passed yet.')
            return
        }
        const nigeria = props.countries.find(c => c.name && c.name.toLowerCase() === 'nigeria')
        if (nigeria) {
            form.address.country_id = nigeria.id
            emitLoadStates(nigeria.id)
        }
    } catch (err) {
        console.error('preselectNigeria error', err)
    }
}

function populateFromInitial(initial) {
    if (!initial) return
    try {
        form.shipping_method_id = initial.shipping_method_id ?? form.shipping_method_id
        form.shipping_zone_id = initial.shipping_zone_id ?? form.shipping_zone_id
        form.pickup_location_id = initial.pickup_location_id ?? form.pickup_location_id
        form.shipping_cost = Number(initial.shipping_cost ?? form.shipping_cost)
        form.weight = Number(initial.weight ?? form.weight)
        form.subtotal = Number(initial.subtotal ?? form.subtotal)
        if (initial.address) {
            form.address = {
                line1: initial.address.line1 ?? form.address.line1,
                phone: initial.address.phone ?? form.address.phone,
                country_id: initial.address.country_id ?? form.address.country_id,
                state_id: initial.address.state_id ?? form.address.state_id,
                lga_id: initial.address.lga_id ?? form.address.lga_id
            }
        }
    } catch (err) {
        console.error('populateFromInitial error', err)
    }
}

/* ---------- computed ---------- */

const selectedMethodName = computed(() => {
    const selected = props.methods.find(method => method.id === form.shipping_method_id)
    return selected ? selected.name : ''
})

const canSave = computed(() => {
    if (isCalculating.value) return false
    if (!form.shipping_method_id) return false
    if (selectedMethodName.value === 'Pickup') {
        return !!form.pickup_location_id
    } else {
        return !!form.address.country_id && !!form.address.state_id && !!form.address.lga_id
    }
})

/* ---------- lifecycle & watchers ---------- */

watch(() => props.subtotal, (v) => {
    form.subtotal = v
    if (form.shipping_method_id && form.shipping_zone_id) autoCalculateCost()
})

watch(() => props.items, () => {
    if (form.shipping_method_id && form.shipping_zone_id) autoCalculateCost()
}, { deep: true })

watch(() => props.initialShipping, (newVal) => {
    if (newVal) populateFromInitial(newVal)
})

watch(
    () => props.countries,
    (newVal) => {
        if (newVal && newVal.length) {
            preselectNigeria()
        }
    },
    { immediate: true } // run right away if already populated
)

// Watch shipping method change → recalc
watch(() => form.shipping_method_id, (newVal, oldVal) => {
    if (newVal && newVal !== oldVal && form.shipping_zone_id) {
        autoCalculateCost()
    }
})

// Watch shipping zone change → recalc
watch(() => form.shipping_zone_id, (newVal, oldVal) => {
    if (newVal && newVal !== oldVal && form.shipping_method_id) {
        autoCalculateCost()
    }
})

// Watch state change (after zone lookup) → recalc
watch(() => form.address.state_id, (newVal, oldVal) => {
    if (newVal && newVal !== oldVal && form.shipping_method_id && form.shipping_zone_id) {
        autoCalculateCost()
    }
})

/* ---------- loaders and actions ---------- */

function emitLoadStates(countryId) {
    emit('load-states', countryId)
}
function emitLoadLgas(stateId) {
    emit('load-lgas', stateId)
}

async function handleStateChange() {
    emitLoadLgas(form.address.state_id)
    try {
        const res = await axios.get(route('shipping.zone_by_state', form.address.state_id))
        form.shipping_zone_id = res.data.zone_id || null
    } catch {
        form.shipping_zone_id = null
    }
}

async function autoCalculateCost() {
    if (!form.shipping_method_id || !form.shipping_zone_id) {
        form.shipping_cost = 0
        return
    }

    if (calcController) try { calcController.abort() } catch {}
    calcController = new AbortController()
    isCalculating.value = true
    error.value = null

    const payload = {
        shipping_method_id: form.shipping_method_id,
        shipping_zone_id: form.shipping_zone_id,
        weight: form.weight,
        subtotal: form.subtotal,
        items: Array.isArray(props.items) ? props.items.map((it) => ({
            variant_id: it.variant_id,
            product_id: it.product_id,
            quantity: it.quantity,
            weight: it.weight ?? 0,
            price: it.price,
        })) : [],
    }

    try {
        const res = await axios.post(route('shipping.calculate'), payload, { signal: calcController.signal })
        const data = res?.data?.data ?? res?.data
        if (!data || (data.total === null || data.total === undefined)) {
            form.shipping_cost = 0
            error.value = 'No available shipping rate for selected method / zone.'
            return
        }
        form.shipping_cost = Number(data.total)
        error.value = null
    } catch (err) {
        form.shipping_cost = 0
        if (err.name === 'CanceledError') {
            // ignore aborted
        } else if (err.response) {
            error.value = err.response.data.message || 'Server error calculating shipping.'
        } else if (err.request) {
            error.value = 'No response from server. Check network.'
        } else {
            error.value = `Unexpected: ${err.message}`
        }
        console.error('autoCalculateCost error:', err)
    } finally {
        isCalculating.value = false
        calcController = null
    }
}

function close() {
    error.value = null
    emit('close')
}

function emitShipment() {
    if (!canSave.value) {
        error.value = 'Please complete required fields before saving.'
        return
    }

    const payload = {
        shipping_method_id: form.shipping_method_id,
        shipping_method_name: props.methods.find(m => m.id === form.shipping_method_id)?.name || null,
        shipping_zone_id: form.shipping_zone_id,
        pickup_location_id: form.pickup_location_id,
        shipping_cost: Number(form.shipping_cost || 0),
        weight: Number(form.weight || 0),
        subtotal: Number(form.subtotal || 0),
        address: { ...form.address },
        items: Array.isArray(props.items) ? props.items.map((it) => ({
            variant_id: it.variant_id,
            product_id: it.product_id,
            quantity: it.quantity,
            weight: it.weight ?? 0,
            price: it.price,
        })) : [],
    }

    emit('created', payload)
    close()
}
</script
