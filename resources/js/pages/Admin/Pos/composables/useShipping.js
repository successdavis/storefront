// resources/js/Pages/Admin/Pos/composables/useShipping.js
import { ref } from 'vue'
import axios from 'axios'
import { route } from 'ziggy-js'

export function useShipping() {
    const methods = ref([])
    const zones = ref([])
    const pickupLocations = ref([])

    async function loadMethods() {
        const res = await axios.get(route('shipping.methods'))
        methods.value = res.data
    }
    async function loadZones() {
        const res = await axios.get(route('shipping.zones'))
        zones.value = res.data
    }
    async function loadPickupLocations() {
        const res = await axios.get(route('shipping.pickup_locations'))
        pickupLocations.value = res.data
    }

    async function create(payload) {
        const res = await axios.post(route('shipping.create'), payload)
        return res.data
    }

    return { methods, zones, pickupLocations, loadMethods, loadZones, loadPickupLocations, calculate, create }
}
