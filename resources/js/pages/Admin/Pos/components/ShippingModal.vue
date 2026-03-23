<template>
    <div class="fixed inset-0 z-40 flex items-center justify-center">
        <div class="fixed inset-0 bg-black opacity-50" @click="close"></div>

        <div class="z-50 w-[700px] max-h-[90vh] overflow-y-auto rounded-lg bg-white p-6 shadow-lg dark:bg-gray-800">
            <h2 class="mb-4 text-lg font-bold">Shipping & Pickup</h2>

            <form @submit.prevent="emitShipment">
                <!-- Method + Receiver / Pickup -->
                <div class="grid grid-cols-2 gap-3 mb-8">
                    <!-- Shipping Method -->
                    <div>
                        <label class="block text-sm mb-1">Shipping Method</label>
                        <select v-model="form.shipping_method_id" class="w-full rounded border px-3 py-2" required>
                            <option class="dark:text-black"  value="">— Select Method</option>
                            <option class="dark:text-black" v-for="m in methods" :key="m.id" :value="m.id">{{ m.name }}</option>
                        </select>
                    </div>

                    <!-- Receiver's Phone (hidden for Pickup) -->
                    <div v-if="!isPickupMethod">
                        <label class="block text-sm mb-1">Receiver's Phone</label>
                        <input
                            v-model.trim="form.phone"
                            class="w-full rounded border px-3 py-2"
                            placeholder="e.g. +234 800 000 0000"
                        />
                    </div>

                    <!-- Pickup Location (only when Pickup) -->
                    <div v-if="isPickupMethod">
                        <label class="block text-sm mb-1">Pickup Location</label>
                        <select v-model="form.pickup_location_id" class="w-full rounded border px-3 py-2">
                            <option class="dark:text-black" :value="null">— Select Pickup Location</option>
                            <option class="dark:text-black" v-for="p in pickupLocations" :key="p.id" :value="p.id">
                                {{ p.name }} <span v-if="p.address_line1">— {{ p.address_line1 }}</span>
                            </option>
                        </select>
                    </div>
                </div>

                <!-- Address (hidden for Pickup) -->
                <div v-if="!isPickupMethod" class="grid grid-cols-2 mb-6 gap-3">
                    <!-- Country -->
                    <div>
                        <label class="mb-1 block text-sm">Country</label>
                        <select
                            v-model="form.country_id"
                            @change="onCountryChange"
                            class="w-full rounded border border-gray-300 px-3 py-2"
                        >
                            <option class="dark:text-black" value="">— Select Country</option>
                            <option class="dark:text-black" v-for="c in countries" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>

                    <!-- State -->
                    <div>
                        <label class="mb-1 block text-sm">State</label>
                        <select
                            v-model="form.state_id"
                            @change="onStateChange"
                            class="w-full rounded border border-gray-300 px-3 py-2"
                        >
                            <option class="dark:text-black" value="">— Select State</option>
                            <option class="dark:text-black" v-for="s in states" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                    </div>

                    <!-- LGA -->
                    <div>
                        <label class="mb-1 block text-sm">Local Government (LGA)</label>
                        <select v-model="form.lga_id" class="w-full rounded border border-gray-300 px-3 py-2">
                            <option class="dark:text-black" value="">— Select LGA</option>
                            <option class="dark:text-black" v-for="l in lgas" :key="l.id" :value="l.id">{{ l.name }}</option>
                        </select>
                    </div>

                    <!-- Address line1 -->
                    <div class="col-span-2">
                        <label class="block text-sm mb-3">Address (line 1)</label>
                        <input
                            v-model.trim="form.line1"
                            class="w-full rounded border px-3 py-2"
                            placeholder="Street address"
                        />
                    </div>
                </div>

                <!-- Footer -->
                <div class="flex gap-2 items-center">
                    <button type="button" class="rounded border px-4 py-2" @click="close">Cancel</button>
                    <button type="submit" class="ml-auto rounded bg-blue-600 px-4 py-2 text-white" :disabled="!canSave">
                        Save Shipment
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<script setup>
import { reactive, computed, watch } from 'vue'

const emit = defineEmits(['close', 'created', 'load-states', 'load-lgas'])

const props = defineProps({
    // Lists (parent provides and loads them)
    countries: { type: Array, default: () => [] },
    states: { type: Array, default: () => [] },
    lgas: { type: Array, default: () => [] },
    methods: { type: Array, default: () => [] },
    pickupLocations: { type: Array, default: () => [] },

    // Pre-fill (optional)
    initialShipping: { type: Object, default: null },
})

/** Local state */
const form = reactive({
    shipping_method_id: props.initialShipping?.shipping_method_id ?? '',
    pickup_location_id: props.initialShipping?.pickup_location_id ?? null,

    // flattened receiver & address fields
    phone: props.initialShipping?.phone ?? '',
    country_id: props.initialShipping?.country_id ?? '',
    state_id: props.initialShipping?.state_id ?? '',
    lga_id: props.initialShipping?.lga_id ?? '',
    line1: props.initialShipping?.address ?? '',
})

/** Derived: selected method name */
const selectedMethod = computed(() => {
    const m = props.methods.find(m => Number(m.id) === Number(form.shipping_method_id))
    return m ?? null
})

const isPickupMethod = computed(() => {
    const methodType = String(selectedMethod.value?.method_type ?? '').toLowerCase()
    if (methodType !== '') {
        return methodType === 'pickup'
    }

    return String(selectedMethod.value?.name ?? '').toLowerCase() === 'pickup'
})

/** Validation */
const canSave = computed(() => {
    if (!form.shipping_method_id) return false

    // Pickup: only pickup_location is required
    if (isPickupMethod.value) {
        return !!form.pickup_location_id
    }

    // Delivery: require full address + phone
    return !!form.phone && !!form.country_id && !!form.state_id && !!form.lga_id && !!form.line1
})

/** Cascading loaders */
function onCountryChange() {
    form.state_id = ''
    form.lga_id = ''
    emit('load-states', form.country_id)
}

function onStateChange() {
    form.lga_id = ''
    emit('load-lgas', form.state_id)
}

/** If parent updates initialShipping after mount, sync */
watch(() => props.initialShipping, (v) => {
    if (!v) return
    form.shipping_method_id = v.shipping_method_id ?? form.shipping_method_id
    form.pickup_location_id = v.pickup_location_id ?? form.pickup_location_id
    form.phone = v.phone ?? form.phone
    form.country_id = v.country_id ?? form.country_id
    form.state_id = v.state_id ?? form.state_id
    form.lga_id = v.lga_id ?? form.lga_id
    form.line1 = v.address ?? form.line1
})

/** Emit chosen shipping data — server will resolve zone by state_id and price via /checkout/preview */
function emitShipment() {
    if (!canSave.value) return

    const payload = {
        shipping_method_id: form.shipping_method_id,
        pickup_location_id: isPickupMethod.value ? form.pickup_location_id : null,
        phone: isPickupMethod.value ? null : form.phone,
        country_id: isPickupMethod.value ? null : form.country_id,
        state_id: form.state_id,
        lga_id: isPickupMethod.value ? null : form.lga_id,
        address: isPickupMethod.value ? null : form.line1,
    }

    emit('created', payload)
    emit('close')
}

function close() {
    emit('close')
}
</script>
