<script setup lang="ts">
import InputError from '@/components/InputError.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import axios from 'axios'
import { computed, ref, watch } from 'vue'

interface PickupLocationPayload {
    id?: number
    name?: string | null
    shipping_method_id?: number | null
    state_id?: number | null
    lga_id?: number | null
    address_line1?: string | null
    address_line2?: string | null
    phone?: string | null
    email?: string | null
    lead_time_hours?: number | null
    is_active?: boolean
}

const props = defineProps<{
    mode: 'create' | 'edit'
    pickupLocation: PickupLocationPayload | null
    methods: Array<{ id: number; name: string; is_active: boolean }>
    states: Array<{ id: number; name: string }>
    lgas: Array<{ id: number; name: string }>
}>()

const isEdit = computed(() => props.mode === 'edit')
const pageTitle = computed(() => (isEdit.value ? 'Edit Pickup Location' : 'Create Pickup Location'))
const currentLgas = ref(props.lgas ?? [])

const form = useForm({
    name: props.pickupLocation?.name ?? '',
    shipping_method_id: props.pickupLocation?.shipping_method_id ?? (props.methods.length === 1 ? props.methods[0].id : null),
    state_id: props.pickupLocation?.state_id ?? null,
    lga_id: props.pickupLocation?.lga_id ?? null,
    address_line1: props.pickupLocation?.address_line1 ?? '',
    address_line2: props.pickupLocation?.address_line2 ?? '',
    phone: props.pickupLocation?.phone ?? '',
    email: props.pickupLocation?.email ?? '',
    lead_time_hours: props.pickupLocation?.lead_time_hours ?? null,
    is_active: props.pickupLocation?.is_active ?? true,
})

watch(() => form.state_id, async (stateId, previousStateId) => {
    if (previousStateId !== undefined && stateId !== previousStateId) {
        form.lga_id = null
    }

    if (!stateId) {
        currentLgas.value = []
        return
    }

    try {
        const { data } = await axios.get(route('locations.lgas', stateId))
        currentLgas.value = data
    } catch (error) {
        currentLgas.value = []
        console.error('Failed loading LGAs for pickup location form', error)
    }
})

function submit() {
    if (isEdit.value && props.pickupLocation?.id) {
        form.put(route('admin.pickup-locations.update', props.pickupLocation.id), { preserveScroll: true })
        return
    }

    form.post(route('admin.pickup-locations.store'), { preserveScroll: true })
}
</script>

<template>
    <Head :title="pageTitle" />

    <div class="space-y-6 px-5 py-4">
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ pageTitle }}</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500 dark:text-slate-400">
                        A store or collection point customers can pick orders up from. It is offered at checkout to buyers in its state or shipping zone; add a pickup shipping rate to charge a fee for this location.
                    </p>
                </div>

                <Link
                    :href="route('admin.pickup-locations.index')"
                    class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-500 dark:border-slate-600 dark:text-slate-200 dark:hover:border-slate-400"
                >
                    Back to pickup locations
                </Link>
            </div>
        </section>

        <form class="space-y-6" @submit.prevent="submit">
            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Location Details</h2>

                <div v-if="!methods.length" class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-200">
                    No pickup-type shipping method exists yet. Create a shipping method with type "Pickup" first.
                </div>

                <div class="mt-5 grid gap-5 md:grid-cols-2">
                    <div>
                        <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Name</label>
                        <input
                            v-model="form.name"
                            type="text"
                            placeholder="e.g. Ikom Store"
                            class="mt-2 h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100"
                        >
                        <InputError :message="form.errors.name" class="mt-2" />
                    </div>

                    <div>
                        <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Pickup shipping method</label>
                        <select v-model="form.shipping_method_id" class="mt-2 h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                            <option :value="null">Select method</option>
                            <option v-for="method in methods" :key="method.id" :value="method.id">
                                {{ method.name }}{{ !method.is_active ? ' (Inactive)' : '' }}
                            </option>
                        </select>
                        <InputError :message="form.errors.shipping_method_id" class="mt-2" />
                    </div>

                    <div>
                        <label class="text-sm font-medium text-slate-700 dark:text-slate-200">State</label>
                        <select v-model="form.state_id" class="mt-2 h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                            <option :value="null">Select state</option>
                            <option v-for="state in states" :key="state.id" :value="state.id">{{ state.name }}</option>
                        </select>
                        <InputError :message="form.errors.state_id" class="mt-2" />
                    </div>

                    <div>
                        <label class="text-sm font-medium text-slate-700 dark:text-slate-200">LGA (optional)</label>
                        <select v-model="form.lga_id" :disabled="!form.state_id" class="mt-2 h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 disabled:opacity-60 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                            <option :value="null">All LGAs</option>
                            <option v-for="lga in currentLgas" :key="lga.id" :value="lga.id">{{ lga.name }}</option>
                        </select>
                        <InputError :message="form.errors.lga_id" class="mt-2" />
                    </div>

                    <div class="md:col-span-2">
                        <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Address line 1</label>
                        <input
                            v-model="form.address_line1"
                            type="text"
                            placeholder="Street address of the store / pickup point"
                            class="mt-2 h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100"
                        >
                        <InputError :message="form.errors.address_line1" class="mt-2" />
                    </div>

                    <div class="md:col-span-2">
                        <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Address line 2 (optional)</label>
                        <input
                            v-model="form.address_line2"
                            type="text"
                            class="mt-2 h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100"
                        >
                        <InputError :message="form.errors.address_line2" class="mt-2" />
                    </div>

                    <div>
                        <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Phone (optional)</label>
                        <input
                            v-model="form.phone"
                            type="text"
                            class="mt-2 h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100"
                        >
                        <InputError :message="form.errors.phone" class="mt-2" />
                    </div>

                    <div>
                        <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Email (optional)</label>
                        <input
                            v-model="form.email"
                            type="email"
                            class="mt-2 h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100"
                        >
                        <InputError :message="form.errors.email" class="mt-2" />
                    </div>

                    <div>
                        <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Lead time (hours, optional)</label>
                        <input
                            v-model="form.lead_time_hours"
                            type="number"
                            min="0"
                            max="720"
                            placeholder="How long before the order is ready"
                            class="mt-2 h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100"
                        >
                        <InputError :message="form.errors.lead_time_hours" class="mt-2" />
                    </div>

                    <label class="flex items-center gap-3 text-sm font-medium text-slate-700 dark:text-slate-200">
                        <input v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-slate-300">
                        <span>Active (visible at checkout)</span>
                    </label>
                </div>
            </section>

            <div class="flex items-center gap-3">
                <button
                    type="submit"
                    :disabled="form.processing || !methods.length"
                    class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-700 disabled:opacity-50 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-300"
                >
                    {{ form.processing ? 'Saving...' : (isEdit ? 'Save changes' : 'Create pickup location') }}
                </button>
                <span v-if="form.recentlySuccessful" class="text-sm font-medium text-green-600 dark:text-green-400">Saved.</span>
            </div>
        </form>
    </div>
</template>
