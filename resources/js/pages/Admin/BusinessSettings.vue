<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import { Building2, Printer, ReceiptText, Save } from 'lucide-vue-next'

type BusinessSettingsForm = {
    business_name: string | null
    business_tagline: string | null
    business_email: string | null
    business_phone: string | null
    business_address: string | null
    business_website: string | null
    business_currency: string | null
    business_tax_id: string | null
    business_receipt_footer: string | null
    business_receipt_footer_refund: string | null
    barcode_paper_size: string
    barcode_label_orientation: string
    barcode_label_height_mm: string
    receipt_paper_size: string
}

type PaperOption = {
    value: string
    label: string
}

const props = defineProps<{
    settings: BusinessSettingsForm
    paper_options: {
        barcode: PaperOption[]
        receipt: PaperOption[]
    }
    orientation_options: PaperOption[]
}>()

const form = useForm<BusinessSettingsForm>({
    business_name: props.settings.business_name,
    business_tagline: props.settings.business_tagline,
    business_email: props.settings.business_email,
    business_phone: props.settings.business_phone,
    business_address: props.settings.business_address,
    business_website: props.settings.business_website,
    business_currency: props.settings.business_currency,
    business_tax_id: props.settings.business_tax_id,
    business_receipt_footer: props.settings.business_receipt_footer,
    business_receipt_footer_refund: props.settings.business_receipt_footer_refund,
    barcode_paper_size: props.settings.barcode_paper_size,
    barcode_label_orientation: props.settings.barcode_label_orientation,
    barcode_label_height_mm: props.settings.barcode_label_height_mm,
    receipt_paper_size: props.settings.receipt_paper_size,
})

const identityFields: Array<{ key: keyof BusinessSettingsForm; label: string; type: string; autocomplete?: string }> = [
    { key: 'business_name', label: 'Business name', type: 'text' },
    { key: 'business_tagline', label: 'Tagline', type: 'text' },
    { key: 'business_currency', label: 'Currency', type: 'text' },
    { key: 'business_tax_id', label: 'Tax ID', type: 'text' },
]

const contactFields: Array<{ key: keyof BusinessSettingsForm; label: string; type: string; autocomplete?: string }> = [
    { key: 'business_email', label: 'Email', type: 'email', autocomplete: 'email' },
    { key: 'business_phone', label: 'Phone', type: 'text', autocomplete: 'tel' },
    { key: 'business_website', label: 'Website', type: 'url', autocomplete: 'url' },
]

function submit() {
    form.patch('/admin/business-settings', {
        preserveScroll: true,
    })
}
</script>

<template>
    <Head title="Business Settings" />

    <div class="space-y-6 px-5 py-4 text-slate-900 dark:text-slate-100">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Business Settings</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Manage business details, receipt text, and print paper sizes.
                </p>
            </div>

            <button
                type="submit"
                form="business-settings-form"
                class="inline-flex items-center justify-center gap-2 rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-white dark:text-slate-950 dark:hover:bg-slate-200"
                :disabled="form.processing"
            >
                <Save class="size-4" />
                {{ form.processing ? 'Saving...' : 'Save settings' }}
            </button>
        </div>

        <form id="business-settings-form" class="space-y-6" @submit.prevent="submit">
            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <div class="mb-5 flex items-center gap-3">
                    <Building2 class="size-5 text-slate-500 dark:text-slate-300" />
                    <h2 class="text-lg font-semibold">Business profile</h2>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <label v-for="field in identityFields" :key="field.key" class="grid gap-2 text-sm">
                        <span class="font-medium text-slate-700 dark:text-slate-200">{{ field.label }}</span>
                        <input
                            v-model="form[field.key]"
                            :type="field.type"
                            class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200 dark:border-slate-700 dark:bg-slate-950 dark:focus:border-slate-400 dark:focus:ring-slate-800"
                        />
                        <span v-if="form.errors[field.key]" class="text-xs text-red-600">{{ form.errors[field.key] }}</span>
                    </label>
                </div>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <div class="mb-5 flex items-center gap-3">
                    <ReceiptText class="size-5 text-slate-500 dark:text-slate-300" />
                    <h2 class="text-lg font-semibold">Contact and receipt text</h2>
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                    <label v-for="field in contactFields" :key="field.key" class="grid gap-2 text-sm">
                        <span class="font-medium text-slate-700 dark:text-slate-200">{{ field.label }}</span>
                        <input
                            v-model="form[field.key]"
                            :autocomplete="field.autocomplete"
                            :type="field.type"
                            class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200 dark:border-slate-700 dark:bg-slate-950 dark:focus:border-slate-400 dark:focus:ring-slate-800"
                        />
                        <span v-if="form.errors[field.key]" class="text-xs text-red-600">{{ form.errors[field.key] }}</span>
                    </label>
                </div>

                <div class="mt-4 grid gap-4 md:grid-cols-3">
                    <label class="grid gap-2 text-sm md:col-span-3">
                        <span class="font-medium text-slate-700 dark:text-slate-200">Address</span>
                        <textarea
                            v-model="form.business_address"
                            rows="3"
                            class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200 dark:border-slate-700 dark:bg-slate-950 dark:focus:border-slate-400 dark:focus:ring-slate-800"
                        />
                        <span v-if="form.errors.business_address" class="text-xs text-red-600">{{ form.errors.business_address }}</span>
                    </label>

                    <label class="grid gap-2 text-sm">
                        <span class="font-medium text-slate-700 dark:text-slate-200">Receipt footer</span>
                        <textarea
                            v-model="form.business_receipt_footer"
                            rows="4"
                            class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200 dark:border-slate-700 dark:bg-slate-950 dark:focus:border-slate-400 dark:focus:ring-slate-800"
                        />
                        <span v-if="form.errors.business_receipt_footer" class="text-xs text-red-600">{{ form.errors.business_receipt_footer }}</span>
                    </label>

                    <label class="grid gap-2 text-sm md:col-span-2">
                        <span class="font-medium text-slate-700 dark:text-slate-200">Refund note</span>
                        <textarea
                            v-model="form.business_receipt_footer_refund"
                            rows="4"
                            class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200 dark:border-slate-700 dark:bg-slate-950 dark:focus:border-slate-400 dark:focus:ring-slate-800"
                        />
                        <span v-if="form.errors.business_receipt_footer_refund" class="text-xs text-red-600">{{ form.errors.business_receipt_footer_refund }}</span>
                    </label>
                </div>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <div class="mb-5 flex items-center gap-3">
                    <Printer class="size-5 text-slate-500 dark:text-slate-300" />
                    <h2 class="text-lg font-semibold">Print paper</h2>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <label class="grid gap-2 text-sm">
                        <span class="font-medium text-slate-700 dark:text-slate-200">Barcode label paper</span>
                        <select
                            v-model="form.barcode_paper_size"
                            class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200 dark:border-slate-700 dark:bg-slate-950 dark:focus:border-slate-400 dark:focus:ring-slate-800"
                        >
                            <option v-for="option in paper_options.barcode" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </option>
                        </select>
                        <span v-if="form.errors.barcode_paper_size" class="text-xs text-red-600">{{ form.errors.barcode_paper_size }}</span>
                    </label>

                    <label class="grid gap-2 text-sm">
                        <span class="font-medium text-slate-700 dark:text-slate-200">Barcode orientation</span>
                        <select
                            v-model="form.barcode_label_orientation"
                            class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm capitalize outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200 dark:border-slate-700 dark:bg-slate-950 dark:focus:border-slate-400 dark:focus:ring-slate-800"
                        >
                            <option v-for="option in orientation_options" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </option>
                        </select>
                        <span v-if="form.errors.barcode_label_orientation" class="text-xs text-red-600">{{ form.errors.barcode_label_orientation }}</span>
                    </label>

                    <label class="grid gap-2 text-sm">
                        <span class="font-medium text-slate-700 dark:text-slate-200">Barcode height (mm)</span>
                        <input
                            v-model="form.barcode_label_height_mm"
                            type="number"
                            min="10"
                            max="500"
                            step="1"
                            class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200 dark:border-slate-700 dark:bg-slate-950 dark:focus:border-slate-400 dark:focus:ring-slate-800"
                        />
                        <span v-if="form.errors.barcode_label_height_mm" class="text-xs text-red-600">{{ form.errors.barcode_label_height_mm }}</span>
                    </label>

                    <label class="grid gap-2 text-sm">
                        <span class="font-medium text-slate-700 dark:text-slate-200">POS receipt paper</span>
                        <select
                            v-model="form.receipt_paper_size"
                            class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200 dark:border-slate-700 dark:bg-slate-950 dark:focus:border-slate-400 dark:focus:ring-slate-800"
                        >
                            <option v-for="option in paper_options.receipt" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </option>
                        </select>
                        <span v-if="form.errors.receipt_paper_size" class="text-xs text-red-600">{{ form.errors.receipt_paper_size }}</span>
                    </label>
                </div>
            </section>

            <Transition
                enter-active-class="transition ease-out"
                enter-from-class="opacity-0"
                leave-active-class="transition ease-in"
                leave-to-class="opacity-0"
            >
                <p v-if="form.recentlySuccessful" class="text-sm font-medium text-emerald-600">
                    Saved.
                </p>
            </Transition>
        </form>
    </div>
</template>
