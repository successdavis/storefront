<script setup lang="ts">
import InputError from '@/components/InputError.vue'
import { Link, useForm } from '@inertiajs/vue3'
import axios from 'axios'
import { computed, ref, watch } from 'vue'

interface ShippingRatePayload {
    id?: number
    shipping_method_id?: number | null
    scope_type?: string | null
    shipping_zone_id?: number | null
    state_id?: number | null
    lga_id?: number | null
    rate_type?: string | null
    base_rate?: number | null
    per_kg?: number | null
    surcharge?: number | null
    free_shipping_threshold?: number | null
    estimated_delivery_text?: string | null
    processing_days_min?: number | null
    processing_days_max?: number | null
    transit_days_min?: number | null
    transit_days_max?: number | null
    cutoff_time?: string | null
    business_days_only?: boolean | null
    supports_weekend_delivery?: boolean | null
    min_weight?: number | null
    max_weight?: number | null
    min_subtotal?: number | null
    max_subtotal?: number | null
    sort_order?: number | null
    starts_at?: string | null
    ends_at?: string | null
    is_active?: boolean
}

const props = defineProps<{
    mode: 'create' | 'edit'
    shippingRate: ShippingRatePayload | null
    methods: Array<{ id: number; name: string; method_type: string; is_active: boolean }>
    zones: Array<{ id: number; name: string; state_count: number }>
    states: Array<{ id: number; name: string }>
    lgas: Array<{ id: number; name: string }>
    scopeTypes: Array<{ value: string; label: string }>
    rateTypes: Array<{ value: string; label: string }>
}>()

const isEdit = computed(() => props.mode === 'edit')
const currentLgas = ref(props.lgas ?? [])

const form = useForm({
    shipping_method_id: props.shippingRate?.shipping_method_id ?? null,
    scope_type: props.shippingRate?.scope_type ?? 'zone',
    shipping_zone_id: props.shippingRate?.shipping_zone_id ?? null,
    state_id: props.shippingRate?.state_id ?? null,
    lga_id: props.shippingRate?.lga_id ?? null,
    rate_type: props.shippingRate?.rate_type ?? 'flat',
    base_rate: props.shippingRate?.base_rate ?? 0,
    per_kg: props.shippingRate?.per_kg ?? 0,
    surcharge: props.shippingRate?.surcharge ?? 0,
    free_shipping_threshold: props.shippingRate?.free_shipping_threshold ?? null,
    estimated_delivery_text: props.shippingRate?.estimated_delivery_text ?? '',
    processing_days_min: props.shippingRate?.processing_days_min ?? null,
    processing_days_max: props.shippingRate?.processing_days_max ?? null,
    transit_days_min: props.shippingRate?.transit_days_min ?? null,
    transit_days_max: props.shippingRate?.transit_days_max ?? null,
    cutoff_time: props.shippingRate?.cutoff_time ?? '',
    business_days_only: props.shippingRate?.business_days_only ?? true,
    supports_weekend_delivery: props.shippingRate?.supports_weekend_delivery ?? false,
    min_weight: props.shippingRate?.min_weight ?? null,
    max_weight: props.shippingRate?.max_weight ?? null,
    min_subtotal: props.shippingRate?.min_subtotal ?? null,
    max_subtotal: props.shippingRate?.max_subtotal ?? null,
    sort_order: props.shippingRate?.sort_order ?? 0,
    starts_at: props.shippingRate?.starts_at ?? '',
    ends_at: props.shippingRate?.ends_at ?? '',
    is_active: props.shippingRate?.is_active ?? true,
})

const currentMethod = computed(() => {
    return props.methods.find(method => method.id === Number(form.shipping_method_id || 0)) ?? null
})

const isPickupMethod = computed(() => currentMethod.value?.method_type === 'pickup')
const pageTitle = computed(() => isEdit.value ? 'Edit Shipping Rate' : 'Create Shipping Rate')

watch(() => form.scope_type, (scopeType) => {
    if (scopeType === 'global') {
        form.shipping_zone_id = null
        form.state_id = null
        form.lga_id = null
        currentLgas.value = []
        return
    }

    if (scopeType === 'zone') {
        form.state_id = null
        form.lga_id = null
        currentLgas.value = []
        return
    }

    if (scopeType === 'state') {
        form.shipping_zone_id = null
        form.lga_id = null
        return
    }

    form.shipping_zone_id = null
})

watch(() => form.shipping_method_id, () => {
    if (isPickupMethod.value) {
        form.rate_type = 'flat'
        form.base_rate = 0
        form.per_kg = 0
        form.surcharge = 0
        form.free_shipping_threshold = null
    }
})

watch(() => form.state_id, async (stateId) => {
    form.lga_id = null

    if (!stateId || form.scope_type !== 'lga') {
        if (form.scope_type !== 'lga') {
            currentLgas.value = []
        }
        return
    }

    try {
        const { data } = await axios.get(route('locations.lgas', stateId))
        currentLgas.value = data
    } catch (error) {
        currentLgas.value = []
        console.error('Failed loading LGAs for shipping rate form', error)
    }
})

function submit() {
    if (isEdit.value && props.shippingRate?.id) {
        form.put(route('admin.shipping-rates.update', props.shippingRate.id), { preserveScroll: true })
        return
    }

    form.post(route('admin.shipping-rates.store'), { preserveScroll: true })
}
</script>

<template>
    <div class="space-y-6 px-5 py-4">
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ pageTitle }}</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500 dark:text-slate-400">
                        Create geographic shipping rules that checkout and POS can resolve consistently across zones, states, and LGAs.
                    </p>
                </div>

                <Link
                    :href="route('admin.shipping-rates.index')"
                    class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-500 dark:border-slate-600 dark:text-slate-200 dark:hover:border-slate-400"
                >
                    Back to rates
                </Link>
            </div>
        </section>

        <form class="grid gap-6 xl:grid-cols-[1.35fr_0.85fr]" @submit.prevent="submit">
            <div class="space-y-6">
                <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Rule Setup</h2>

                    <div class="mt-5 grid gap-5 md:grid-cols-2">
                        <div>
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Shipping method</label>
                            <select v-model="form.shipping_method_id" class="mt-2 h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                                <option :value="null">Select method</option>
                                <option v-for="method in methods" :key="method.id" :value="method.id">
                                    {{ method.name }}{{ !method.is_active ? ' (Inactive)' : '' }}
                                </option>
                            </select>
                            <InputError :message="form.errors.shipping_method_id" class="mt-2" />
                        </div>

                        <div>
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Scope</label>
                            <select v-model="form.scope_type" class="mt-2 h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                                <option v-for="scope in scopeTypes" :key="scope.value" :value="scope.value">
                                    {{ scope.label }}
                                </option>
                            </select>
                            <InputError :message="form.errors.scope_type" class="mt-2" />
                        </div>

                        <div v-if="form.scope_type === 'zone'">
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Shipping zone</label>
                            <select v-model="form.shipping_zone_id" class="mt-2 h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                                <option :value="null">Select zone</option>
                                <option v-for="zone in zones" :key="zone.id" :value="zone.id">
                                    {{ zone.name }}
                                </option>
                            </select>
                            <InputError :message="form.errors.shipping_zone_id" class="mt-2" />
                        </div>

                        <div v-if="form.scope_type === 'state' || form.scope_type === 'lga'">
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-200">State</label>
                            <select v-model="form.state_id" class="mt-2 h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                                <option :value="null">Select state</option>
                                <option v-for="state in states" :key="state.id" :value="state.id">
                                    {{ state.name }}
                                </option>
                            </select>
                            <InputError :message="form.errors.state_id" class="mt-2" />
                        </div>

                        <div v-if="form.scope_type === 'lga'">
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-200">LGA</label>
                            <select v-model="form.lga_id" class="mt-2 h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                                <option :value="null">Select LGA</option>
                                <option v-for="lga in currentLgas" :key="lga.id" :value="lga.id">
                                    {{ lga.name }}
                                </option>
                            </select>
                            <InputError :message="form.errors.lga_id" class="mt-2" />
                        </div>

                        <div>
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Rate type</label>
                            <select v-model="form.rate_type" :disabled="isPickupMethod" class="mt-2 h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 disabled:opacity-70 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                                <option v-for="type in rateTypes" :key="type.value" :value="type.value">
                                    {{ type.label }}
                                </option>
                            </select>
                            <InputError :message="form.errors.rate_type" class="mt-2" />
                        </div>

                        <div>
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Estimated delivery text</label>
                            <input v-model="form.estimated_delivery_text" type="text" class="mt-2 h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                            <InputError :message="form.errors.estimated_delivery_text" class="mt-2" />
                        </div>

                        <div>
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Processing days (min)</label>
                            <input v-model="form.processing_days_min" type="number" min="0" step="1" class="mt-2 h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                            <InputError :message="form.errors.processing_days_min" class="mt-2" />
                        </div>

                        <div>
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Processing days (max)</label>
                            <input v-model="form.processing_days_max" type="number" min="0" step="1" class="mt-2 h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                            <InputError :message="form.errors.processing_days_max" class="mt-2" />
                        </div>

                        <div>
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Transit days (min)</label>
                            <input v-model="form.transit_days_min" type="number" min="0" step="1" class="mt-2 h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                            <InputError :message="form.errors.transit_days_min" class="mt-2" />
                        </div>

                        <div>
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Transit days (max)</label>
                            <input v-model="form.transit_days_max" type="number" min="0" step="1" class="mt-2 h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                            <InputError :message="form.errors.transit_days_max" class="mt-2" />
                        </div>

                        <div>
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Daily cutoff time</label>
                            <input v-model="form.cutoff_time" type="time" class="mt-2 h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                            <InputError :message="form.errors.cutoff_time" class="mt-2" />
                        </div>
                    </div>

                    <div class="mt-5 grid gap-4 md:grid-cols-2">
                        <label class="flex items-center gap-3 rounded-xl border border-slate-300 px-4 py-3 text-sm text-slate-700 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-200">
                            <input v-model="form.business_days_only" type="checkbox" class="h-4 w-4 rounded border-slate-300 dark:border-slate-600 dark:bg-slate-900">
                            Use business days only
                        </label>

                        <label class="flex items-center gap-3 rounded-xl border border-slate-300 px-4 py-3 text-sm text-slate-700 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-200">
                            <input v-model="form.supports_weekend_delivery" type="checkbox" class="h-4 w-4 rounded border-slate-300 dark:border-slate-600 dark:bg-slate-900">
                            Supports weekend delivery
                        </label>
                    </div>
                </section>

                <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Pricing and Thresholds</h2>

                    <div class="mt-5 grid gap-5 md:grid-cols-2">
                        <div>
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Base rate (NGN)</label>
                            <input v-model="form.base_rate" type="number" min="0" step="0.01" :readonly="isPickupMethod" class="mt-2 h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 read-only:bg-slate-50 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100 dark:read-only:bg-slate-900">
                            <InputError :message="form.errors.base_rate" class="mt-2" />
                        </div>

                        <div>
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Per kg (NGN)</label>
                            <input v-model="form.per_kg" type="number" min="0" step="0.01" :readonly="isPickupMethod" class="mt-2 h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 read-only:bg-slate-50 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100 dark:read-only:bg-slate-900">
                            <InputError :message="form.errors.per_kg" class="mt-2" />
                        </div>

                        <div>
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Surcharge (NGN)</label>
                            <input v-model="form.surcharge" type="number" min="0" step="0.01" :readonly="isPickupMethod" class="mt-2 h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 read-only:bg-slate-50 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100 dark:read-only:bg-slate-900">
                            <InputError :message="form.errors.surcharge" class="mt-2" />
                        </div>

                        <div>
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Free shipping threshold (NGN)</label>
                            <input v-model="form.free_shipping_threshold" type="number" min="0" step="0.01" :readonly="isPickupMethod" class="mt-2 h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 read-only:bg-slate-50 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100 dark:read-only:bg-slate-900">
                            <InputError :message="form.errors.free_shipping_threshold" class="mt-2" />
                        </div>

                        <div>
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Minimum weight (kg)</label>
                            <input v-model="form.min_weight" type="number" min="0" step="0.001" :readonly="isPickupMethod" class="mt-2 h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 read-only:bg-slate-50 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100 dark:read-only:bg-slate-900">
                            <InputError :message="form.errors.min_weight" class="mt-2" />
                        </div>

                        <div>
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Maximum weight (kg)</label>
                            <input v-model="form.max_weight" type="number" min="0" step="0.001" :readonly="isPickupMethod" class="mt-2 h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 read-only:bg-slate-50 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100 dark:read-only:bg-slate-900">
                            <InputError :message="form.errors.max_weight" class="mt-2" />
                        </div>

                        <div>
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Minimum subtotal (NGN)</label>
                            <input v-model="form.min_subtotal" type="number" min="0" step="0.01" class="mt-2 h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                            <InputError :message="form.errors.min_subtotal" class="mt-2" />
                        </div>

                        <div>
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Maximum subtotal (NGN)</label>
                            <input v-model="form.max_subtotal" type="number" min="0" step="0.01" class="mt-2 h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                            <InputError :message="form.errors.max_subtotal" class="mt-2" />
                        </div>
                    </div>
                </section>
            </div>

            <aside class="space-y-6">
                <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Scheduling and Status</h2>

                    <div class="mt-5 space-y-5">
                        <div>
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Starts at</label>
                            <input v-model="form.starts_at" type="datetime-local" class="mt-2 h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                            <InputError :message="form.errors.starts_at" class="mt-2" />
                        </div>

                        <div>
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Ends at</label>
                            <input v-model="form.ends_at" type="datetime-local" class="mt-2 h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                            <InputError :message="form.errors.ends_at" class="mt-2" />
                        </div>

                        <div>
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Sort order</label>
                            <input v-model="form.sort_order" type="number" min="0" step="1" class="mt-2 h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                            <InputError :message="form.errors.sort_order" class="mt-2" />
                        </div>

                        <div>
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Status</label>
                            <label class="mt-2 flex h-11 items-center gap-3 rounded-xl border border-slate-300 px-4 text-sm text-slate-700 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-200">
                                <input v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-slate-300 dark:border-slate-600 dark:bg-slate-900">
                                Active and available for calculation
                            </label>
                            <InputError :message="form.errors.is_active" class="mt-2" />
                        </div>
                    </div>
                </section>

                <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Rule Summary</h2>
                    <dl class="mt-4 space-y-3 text-sm text-slate-600 dark:text-slate-300">
                        <div class="flex justify-between gap-4">
                            <dt>Method</dt>
                            <dd class="font-semibold text-slate-900 dark:text-slate-100">{{ currentMethod?.name || 'Not selected' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt>Scope</dt>
                            <dd class="font-semibold text-slate-900 capitalize dark:text-slate-100">{{ form.scope_type }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt>Pricing</dt>
                            <dd class="font-semibold text-slate-900 dark:text-slate-100">{{ isPickupMethod ? 'Pickup = 0.00' : form.rate_type }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt>Status</dt>
                            <dd class="font-semibold text-slate-900 dark:text-slate-100">{{ form.is_active ? 'Active' : 'Inactive' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt>Delivery window</dt>
                            <dd class="font-semibold text-slate-900 dark:text-slate-100">
                                {{ form.processing_days_min ?? 0 }}-{{ form.processing_days_max ?? form.processing_days_min ?? 0 }} proc / {{ form.transit_days_min ?? 0 }}-{{ form.transit_days_max ?? form.transit_days_min ?? 0 }} transit
                            </dd>
                        </div>
                    </dl>

                    <p class="mt-4 text-xs leading-5 text-slate-500 dark:text-slate-400">
                        Exact LGA rules override state rules, state rules override zone rules, and zone rules override global fallbacks.
                    </p>
                </section>
            </aside>

            <div class="xl:col-span-2 flex flex-wrap justify-end gap-3">
                <Link
                    :href="route('admin.shipping-rates.index')"
                    class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-500 dark:border-slate-600 dark:text-slate-200 dark:hover:border-slate-400"
                >
                    Cancel
                </Link>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-700 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-300 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    {{ form.processing ? 'Saving...' : (isEdit ? 'Update rate' : 'Create rate') }}
                </button>
            </div>
        </form>
    </div>
</template>
