<script setup lang="ts">
import InputError from '@/components/InputError.vue'
import { Link, useForm } from '@inertiajs/vue3'
import { computed } from 'vue'

interface ShippingMethodPayload {
    id?: number
    name?: string | null
    description?: string | null
    method_type?: string | null
    processing_days_min?: number | null
    processing_days_max?: number | null
    transit_days_min?: number | null
    transit_days_max?: number | null
    cutoff_time?: string | null
    business_days_only?: boolean
    supports_weekend_delivery?: boolean
    sort_order?: number | null
    is_active?: boolean
}

const props = defineProps<{
    mode: 'create' | 'edit'
    shippingMethod: ShippingMethodPayload | null
    methodTypes: Array<{ value: string; label: string }>
}>()

const isEdit = computed(() => props.mode === 'edit')

const form = useForm({
    name: props.shippingMethod?.name ?? '',
    description: props.shippingMethod?.description ?? '',
    method_type: props.shippingMethod?.method_type ?? 'delivery',
    processing_days_min: props.shippingMethod?.processing_days_min ?? null,
    processing_days_max: props.shippingMethod?.processing_days_max ?? null,
    transit_days_min: props.shippingMethod?.transit_days_min ?? null,
    transit_days_max: props.shippingMethod?.transit_days_max ?? null,
    cutoff_time: props.shippingMethod?.cutoff_time ?? '',
    business_days_only: props.shippingMethod?.business_days_only ?? true,
    supports_weekend_delivery: props.shippingMethod?.supports_weekend_delivery ?? false,
    sort_order: props.shippingMethod?.sort_order ?? 0,
    is_active: props.shippingMethod?.is_active ?? true,
})

const pageTitle = computed(() => isEdit.value ? 'Edit Shipping Method' : 'Create Shipping Method')

function submit() {
    if (isEdit.value && props.shippingMethod?.id) {
        form.put(route('admin.shipping-methods.update', props.shippingMethod.id), { preserveScroll: true })
        return
    }

    form.post(route('admin.shipping-methods.store'), { preserveScroll: true })
}
</script>

<template>
    <div class="space-y-6 px-5 py-4">
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ pageTitle }}</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500 dark:text-slate-400">
                        Configure delivery and pickup methods that checkout and POS can expose to customers.
                    </p>
                </div>

                <Link
                    :href="route('admin.shipping-methods.index')"
                    class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-500 dark:border-slate-600 dark:text-slate-200 dark:hover:border-slate-400"
                >
                    Back to methods
                </Link>
            </div>
        </section>

        <form class="grid gap-6 xl:grid-cols-[1.2fr_0.8fr]" @submit.prevent="submit">
            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Method Setup</h2>

                <div class="mt-5 grid gap-5 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Name</label>
                        <input v-model="form.name" type="text" class="mt-2 h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                        <InputError :message="form.errors.name" class="mt-2" />
                    </div>

                    <div class="md:col-span-2">
                        <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Description</label>
                        <textarea v-model="form.description" rows="4" class="mt-2 w-full rounded-2xl border border-slate-300 bg-white px-3 py-3 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100" />
                        <InputError :message="form.errors.description" class="mt-2" />
                    </div>

                    <div>
                        <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Method type</label>
                        <select v-model="form.method_type" class="mt-2 h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                            <option v-for="option in methodTypes" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </option>
                        </select>
                        <InputError :message="form.errors.method_type" class="mt-2" />
                    </div>

                    <div>
                        <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Sort order</label>
                        <input v-model="form.sort_order" type="number" min="0" step="1" class="mt-2 h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                        <InputError :message="form.errors.sort_order" class="mt-2" />
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

                    <div class="md:col-span-2">
                        <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Status</label>
                        <label class="mt-2 flex h-11 items-center gap-3 rounded-xl border border-slate-300 px-4 text-sm text-slate-700 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-200">
                            <input v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-slate-300 dark:border-slate-600 dark:bg-slate-900">
                            Active and selectable at checkout
                        </label>
                        <InputError :message="form.errors.is_active" class="mt-2" />
                    </div>

                    <div class="md:col-span-2 grid gap-4 md:grid-cols-2">
                        <label class="flex items-center gap-3 rounded-xl border border-slate-300 px-4 py-3 text-sm text-slate-700 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-200">
                            <input v-model="form.business_days_only" type="checkbox" class="h-4 w-4 rounded border-slate-300 dark:border-slate-600 dark:bg-slate-900">
                            Use business days only
                        </label>

                        <label class="flex items-center gap-3 rounded-xl border border-slate-300 px-4 py-3 text-sm text-slate-700 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-200">
                            <input v-model="form.supports_weekend_delivery" type="checkbox" class="h-4 w-4 rounded border-slate-300 dark:border-slate-600 dark:bg-slate-900">
                            Supports weekend delivery
                        </label>
                    </div>
                </div>
            </section>

            <aside class="space-y-6">
                <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">How this works</h2>
                    <dl class="mt-4 space-y-3 text-sm text-slate-600 dark:text-slate-300">
                        <div class="flex justify-between gap-4">
                            <dt>Type</dt>
                            <dd class="font-semibold text-slate-900 capitalize dark:text-slate-100">{{ form.method_type }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt>Status</dt>
                            <dd class="font-semibold text-slate-900 dark:text-slate-100">{{ form.is_active ? 'Active' : 'Inactive' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt>Order</dt>
                            <dd class="font-semibold text-slate-900 dark:text-slate-100">{{ form.sort_order }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt>Timing</dt>
                            <dd class="font-semibold text-slate-900 dark:text-slate-100">
                                {{ form.processing_days_min ?? 0 }}-{{ form.processing_days_max ?? form.processing_days_min ?? 0 }} proc / {{ form.transit_days_min ?? 0 }}-{{ form.transit_days_max ?? form.transit_days_min ?? 0 }} transit
                            </dd>
                        </div>
                    </dl>

                    <p class="mt-4 text-xs leading-5 text-slate-500 dark:text-slate-400">
                        Pickup methods always resolve to zero shipping cost during checkout. Delivery methods rely on active shipping rate rules.
                    </p>
                </section>
            </aside>

            <div class="xl:col-span-2 flex flex-wrap justify-end gap-3">
                <Link
                    :href="route('admin.shipping-methods.index')"
                    class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-500 dark:border-slate-600 dark:text-slate-200 dark:hover:border-slate-400"
                >
                    Cancel
                </Link>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-700 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-300 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    {{ form.processing ? 'Saving...' : (isEdit ? 'Update method' : 'Create method') }}
                </button>
            </div>
        </form>
    </div>
</template>
