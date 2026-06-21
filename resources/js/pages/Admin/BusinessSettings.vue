<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import { Building2, Printer, ReceiptText, Save } from 'lucide-vue-next'
import { computed, ref } from 'vue'

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

type SectionKey = 'profile' | 'contact_receipt' | 'print'

type ProfileSettingsForm = {
    section: 'profile'
    business_name: string | null
    business_tagline: string | null
    business_currency: string | null
    business_tax_id: string | null
}

type ContactReceiptSettingsForm = {
    section: 'contact_receipt'
    business_email: string | null
    business_phone: string | null
    business_website: string | null
    business_address: string | null
    business_receipt_footer: string | null
    business_receipt_footer_refund: string | null
}

type PrintSettingsForm = {
    section: 'print'
    barcode_paper_size: string
    barcode_label_orientation: string
    barcode_label_height_mm: string
    receipt_paper_size: string
}

type ProfileFieldKey = Exclude<keyof ProfileSettingsForm, 'section'>
type ContactFieldKey = Exclude<keyof ContactReceiptSettingsForm, 'section'>

const props = defineProps<{
    settings: BusinessSettingsForm
    paper_options: {
        barcode: PaperOption[]
        receipt: PaperOption[]
    }
    orientation_options: PaperOption[]
}>()

const activeSection = ref<SectionKey>('profile')

const profileForm = useForm<ProfileSettingsForm>({
    section: 'profile',
    business_name: props.settings.business_name,
    business_tagline: props.settings.business_tagline,
    business_currency: props.settings.business_currency,
    business_tax_id: props.settings.business_tax_id,
})

const contactReceiptForm = useForm<ContactReceiptSettingsForm>({
    section: 'contact_receipt',
    business_email: props.settings.business_email,
    business_phone: props.settings.business_phone,
    business_website: props.settings.business_website,
    business_address: props.settings.business_address,
    business_receipt_footer: props.settings.business_receipt_footer,
    business_receipt_footer_refund: props.settings.business_receipt_footer_refund,
})

const printForm = useForm<PrintSettingsForm>({
    section: 'print',
    barcode_paper_size: props.settings.barcode_paper_size,
    barcode_label_orientation: props.settings.barcode_label_orientation,
    barcode_label_height_mm: props.settings.barcode_label_height_mm,
    receipt_paper_size: props.settings.receipt_paper_size,
})

const sectionTabs = [
    {
        key: 'profile' as SectionKey,
        label: 'Business profile',
        description: 'Name, tagline, currency, and tax details',
        icon: Building2,
    },
    {
        key: 'contact_receipt' as SectionKey,
        label: 'Contact and receipt',
        description: 'Public contact details and receipt notes',
        icon: ReceiptText,
    },
    {
        key: 'print' as SectionKey,
        label: 'Print paper',
        description: 'Barcode labels and POS receipt paper',
        icon: Printer,
    },
]

const activeTab = computed(() => sectionTabs.find(tab => tab.key === activeSection.value) ?? sectionTabs[0])

const profileFields: Array<{ key: ProfileFieldKey; label: string; type: string; autocomplete?: string }> = [
    { key: 'business_name', label: 'Business name', type: 'text' },
    { key: 'business_tagline', label: 'Tagline', type: 'text' },
    { key: 'business_currency', label: 'Currency', type: 'text' },
    { key: 'business_tax_id', label: 'Tax ID', type: 'text' },
]

const contactFields: Array<{ key: ContactFieldKey; label: string; type: string; autocomplete?: string }> = [
    { key: 'business_email', label: 'Email', type: 'email', autocomplete: 'email' },
    { key: 'business_phone', label: 'Phone', type: 'text', autocomplete: 'tel' },
    { key: 'business_website', label: 'Website', type: 'url', autocomplete: 'url' },
]

function submitProfile() {
    profileForm.patch('/admin/business-settings', {
        preserveScroll: true,
    })
}

function submitContactReceipt() {
    contactReceiptForm.patch('/admin/business-settings', {
        preserveScroll: true,
    })
}

function submitPrint() {
    printForm.patch('/admin/business-settings', {
        preserveScroll: true,
    })
}
</script>

<template>
    <Head title="Business Settings" />

    <div class="space-y-6 px-5 py-4 text-slate-900 dark:text-slate-100">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Business Settings</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Manage business details, receipt text, and print paper sizes.
            </p>
        </div>

        <div class="grid items-start gap-6 lg:grid-cols-[280px_minmax(0,1fr)]">
            <aside class="self-start rounded-lg border border-slate-200 bg-white p-2 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <nav class="flex gap-2 overflow-x-auto lg:flex-col lg:overflow-visible" aria-label="Business settings sections">
                    <button
                        v-for="tab in sectionTabs"
                        :key="tab.key"
                        type="button"
                        class="flex min-w-48 items-center gap-3 rounded-md px-3 py-3 text-left transition lg:min-w-0"
                        :class="activeSection === tab.key
                            ? 'bg-slate-950 text-white dark:bg-white dark:text-slate-950'
                            : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'"
                        @click="activeSection = tab.key"
                    >
                        <component :is="tab.icon" class="size-4 shrink-0" />
                        <span class="text-sm font-semibold">{{ tab.label }}</span>
                    </button>
                </nav>
            </aside>

            <section class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <div class="border-b border-slate-200 p-5 dark:border-slate-700">
                    <div class="flex items-start gap-3">
                        <component :is="activeTab.icon" class="mt-1 size-5 text-slate-500 dark:text-slate-300" />
                        <div>
                            <h2 class="text-lg font-semibold">{{ activeTab.label }}</h2>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ activeTab.description }}</p>
                        </div>
                    </div>
                </div>

                <form v-if="activeSection === 'profile'" class="space-y-6 p-5" @submit.prevent="submitProfile">
                    <div class="grid gap-4 md:grid-cols-2">
                        <label v-for="field in profileFields" :key="field.key" class="grid gap-2 text-sm">
                            <span class="font-medium text-slate-700 dark:text-slate-200">{{ field.label }}</span>
                            <input
                                v-model="profileForm[field.key]"
                                :type="field.type"
                                class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200 dark:border-slate-700 dark:bg-slate-950 dark:focus:border-slate-400 dark:focus:ring-slate-800"
                            />
                            <span v-if="profileForm.errors[field.key]" class="text-xs text-red-600">{{ profileForm.errors[field.key] }}</span>
                        </label>
                    </div>

                    <div class="flex items-center justify-between border-t border-slate-200 pt-5 dark:border-slate-700">
                        <Transition
                            enter-active-class="transition ease-out"
                            enter-from-class="opacity-0"
                            leave-active-class="transition ease-in"
                            leave-to-class="opacity-0"
                        >
                            <p v-if="profileForm.recentlySuccessful" class="text-sm font-medium text-emerald-600">
                                Business profile saved.
                            </p>
                        </Transition>

                        <button
                            type="submit"
                            class="ml-auto inline-flex items-center justify-center gap-2 rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-white dark:text-slate-950 dark:hover:bg-slate-200"
                            :disabled="profileForm.processing"
                        >
                            <Save class="size-4" />
                            {{ profileForm.processing ? 'Saving...' : 'Save business profile' }}
                        </button>
                    </div>
                </form>

                <form v-else-if="activeSection === 'contact_receipt'" class="space-y-6 p-5" @submit.prevent="submitContactReceipt">
                    <div class="grid gap-4 md:grid-cols-3">
                        <label v-for="field in contactFields" :key="field.key" class="grid gap-2 text-sm">
                            <span class="font-medium text-slate-700 dark:text-slate-200">{{ field.label }}</span>
                            <input
                                v-model="contactReceiptForm[field.key]"
                                :autocomplete="field.autocomplete"
                                :type="field.type"
                                class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200 dark:border-slate-700 dark:bg-slate-950 dark:focus:border-slate-400 dark:focus:ring-slate-800"
                            />
                            <span v-if="contactReceiptForm.errors[field.key]" class="text-xs text-red-600">{{ contactReceiptForm.errors[field.key] }}</span>
                        </label>
                    </div>

                    <div class="grid gap-4 md:grid-cols-3">
                        <label class="grid gap-2 text-sm md:col-span-3">
                            <span class="font-medium text-slate-700 dark:text-slate-200">Address</span>
                            <textarea
                                v-model="contactReceiptForm.business_address"
                                rows="3"
                                class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200 dark:border-slate-700 dark:bg-slate-950 dark:focus:border-slate-400 dark:focus:ring-slate-800"
                            />
                            <span v-if="contactReceiptForm.errors.business_address" class="text-xs text-red-600">{{ contactReceiptForm.errors.business_address }}</span>
                        </label>

                        <label class="grid gap-2 text-sm">
                            <span class="font-medium text-slate-700 dark:text-slate-200">Receipt footer</span>
                            <textarea
                                v-model="contactReceiptForm.business_receipt_footer"
                                rows="4"
                                class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200 dark:border-slate-700 dark:bg-slate-950 dark:focus:border-slate-400 dark:focus:ring-slate-800"
                            />
                            <span v-if="contactReceiptForm.errors.business_receipt_footer" class="text-xs text-red-600">{{ contactReceiptForm.errors.business_receipt_footer }}</span>
                        </label>

                        <label class="grid gap-2 text-sm md:col-span-2">
                            <span class="font-medium text-slate-700 dark:text-slate-200">Refund note</span>
                            <textarea
                                v-model="contactReceiptForm.business_receipt_footer_refund"
                                rows="4"
                                class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200 dark:border-slate-700 dark:bg-slate-950 dark:focus:border-slate-400 dark:focus:ring-slate-800"
                            />
                            <span v-if="contactReceiptForm.errors.business_receipt_footer_refund" class="text-xs text-red-600">{{ contactReceiptForm.errors.business_receipt_footer_refund }}</span>
                        </label>
                    </div>

                    <div class="flex items-center justify-between border-t border-slate-200 pt-5 dark:border-slate-700">
                        <Transition
                            enter-active-class="transition ease-out"
                            enter-from-class="opacity-0"
                            leave-active-class="transition ease-in"
                            leave-to-class="opacity-0"
                        >
                            <p v-if="contactReceiptForm.recentlySuccessful" class="text-sm font-medium text-emerald-600">
                                Contact and receipt settings saved.
                            </p>
                        </Transition>

                        <button
                            type="submit"
                            class="ml-auto inline-flex items-center justify-center gap-2 rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-white dark:text-slate-950 dark:hover:bg-slate-200"
                            :disabled="contactReceiptForm.processing"
                        >
                            <Save class="size-4" />
                            {{ contactReceiptForm.processing ? 'Saving...' : 'Save contact and receipt' }}
                        </button>
                    </div>
                </form>

                <form v-else class="space-y-6 p-5" @submit.prevent="submitPrint">
                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="grid gap-2 text-sm">
                            <span class="font-medium text-slate-700 dark:text-slate-200">Barcode label paper</span>
                            <select
                                v-model="printForm.barcode_paper_size"
                                class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200 dark:border-slate-700 dark:bg-slate-950 dark:focus:border-slate-400 dark:focus:ring-slate-800"
                            >
                                <option v-for="option in paper_options.barcode" :key="option.value" :value="option.value">
                                    {{ option.label }}
                                </option>
                            </select>
                            <span v-if="printForm.errors.barcode_paper_size" class="text-xs text-red-600">{{ printForm.errors.barcode_paper_size }}</span>
                        </label>

                        <label class="grid gap-2 text-sm">
                            <span class="font-medium text-slate-700 dark:text-slate-200">Barcode orientation</span>
                            <select
                                v-model="printForm.barcode_label_orientation"
                                class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm capitalize outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200 dark:border-slate-700 dark:bg-slate-950 dark:focus:border-slate-400 dark:focus:ring-slate-800"
                            >
                                <option v-for="option in orientation_options" :key="option.value" :value="option.value">
                                    {{ option.label }}
                                </option>
                            </select>
                            <span v-if="printForm.errors.barcode_label_orientation" class="text-xs text-red-600">{{ printForm.errors.barcode_label_orientation }}</span>
                        </label>

                        <label class="grid gap-2 text-sm">
                            <span class="font-medium text-slate-700 dark:text-slate-200">Barcode height (mm)</span>
                            <input
                                v-model="printForm.barcode_label_height_mm"
                                type="number"
                                min="10"
                                max="500"
                                step="1"
                                class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200 dark:border-slate-700 dark:bg-slate-950 dark:focus:border-slate-400 dark:focus:ring-slate-800"
                            />
                            <span v-if="printForm.errors.barcode_label_height_mm" class="text-xs text-red-600">{{ printForm.errors.barcode_label_height_mm }}</span>
                        </label>

                        <label class="grid gap-2 text-sm">
                            <span class="font-medium text-slate-700 dark:text-slate-200">POS receipt paper</span>
                            <select
                                v-model="printForm.receipt_paper_size"
                                class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none transition focus:border-slate-500 focus:ring-2 focus:ring-slate-200 dark:border-slate-700 dark:bg-slate-950 dark:focus:border-slate-400 dark:focus:ring-slate-800"
                            >
                                <option v-for="option in paper_options.receipt" :key="option.value" :value="option.value">
                                    {{ option.label }}
                                </option>
                            </select>
                            <span v-if="printForm.errors.receipt_paper_size" class="text-xs text-red-600">{{ printForm.errors.receipt_paper_size }}</span>
                        </label>
                    </div>

                    <div class="flex items-center justify-between border-t border-slate-200 pt-5 dark:border-slate-700">
                        <Transition
                            enter-active-class="transition ease-out"
                            enter-from-class="opacity-0"
                            leave-active-class="transition ease-in"
                            leave-to-class="opacity-0"
                        >
                            <p v-if="printForm.recentlySuccessful" class="text-sm font-medium text-emerald-600">
                                Print paper settings saved.
                            </p>
                        </Transition>

                        <button
                            type="submit"
                            class="ml-auto inline-flex items-center justify-center gap-2 rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-white dark:text-slate-950 dark:hover:bg-slate-200"
                            :disabled="printForm.processing"
                        >
                            <Save class="size-4" />
                            {{ printForm.processing ? 'Saving...' : 'Save print paper' }}
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </div>
</template>
