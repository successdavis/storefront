import { ref, onMounted } from 'vue'
import axios from 'axios'
import { route } from 'ziggy-js'

export function useCustomers() {
    const customers = ref([])
    const selectedCustomer = ref('')
    const showCustomerModal = ref(false)

    const newCustomer = ref({
        name: '',
        email: '',
        phone: '',
        country_id: '',
        state_id: '',
        lga_id: '',
        address: '',
        gender: '',
    })

    const countries = ref([])
    const states = ref([])
    const lgas = ref([])

    onMounted(async () => {
        const res = await axios.get(route('admin.customers.list'))
        customers.value = res.data

        const c = await axios.get(route('locations.countries'))
        countries.value = c.data
    })

    const loadStates = async () => {
        if (!newCustomer.value.country_id) return
        const res = await axios.get(route('locations.states', newCustomer.value.country_id))
        states.value = res.data
    }

    const loadLgas = async () => {
        if (!newCustomer.value.state_id) return
        const res = await axios.get(route('locations.lgas', newCustomer.value.state_id))
        lgas.value = res.data
    }

    const handleCustomerChange = () => {
        if (selectedCustomer.value === '__add_new__') {
            showCustomerModal.value = true
            selectedCustomer.value = ''
        }
    }

    const submitNewCustomer = async () => {
        try {
            const res = await axios.post(route('admin.customers.store'), newCustomer.value)
            const newCust = res.data
            customers.value.push(newCust)
            selectedCustomer.value = newCust.id
            showCustomerModal.value = false
            Object.keys(newCustomer.value).forEach((k) => (newCustomer.value[k] = ''))
        } catch (err) {
            alert('Failed to create customer.')
            console.error(err)
        }
    }

    return {
        customers,
        selectedCustomer,
        showCustomerModal,
        newCustomer,
        countries,
        states,
        lgas,
        loadStates,
        loadLgas,
        handleCustomerChange,
        submitNewCustomer,
    }
}
