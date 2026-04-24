<script setup lang="ts">
import { Button } from '@/components/ui/button'
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog'
import Pagination from '@/components/Pagination.vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { computed, reactive, ref, watch } from 'vue'

type AccountRow = {
    id: number
    code: string
    name: string
    slug: string
    type: string
    subtype: string | null
    classification: string | null
    is_active: boolean
    is_system: boolean
    allows_manual_entries: boolean
    currency: string | null
    description: string | null
    parent: null | { id: number; code: string; name: string }
}

const props = defineProps<{
    filters: Record<string, any>
    accounts: { data: AccountRow[]; links: any[] }
    parent_options: Array<{ id: number; label: string }>
    type_options: Array<{ value: string; label: string }>
    status_options: Array<{ value: string; label: string }>
}>()

const filters = reactive({
    search: props.filters?.search || '',
    type: props.filters?.type || '',
    status: props.filters?.status || '',
})

const showAccountModal = ref(false)

watch(
    () => ({ ...filters }),
    (value) => {
        router.get(route('admin.accounting.accounts.index'), value, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        })
    },
)

const editingId = ref<number | null>(null)
const form = useForm({
    code: '',
    name: '',
    slug: '',
    type: 'asset',
    subtype: '',
    classification: '',
    parent_id: '',
    is_active: true,
    is_system: false,
    allows_manual_entries: true,
    currency: 'NGN',
    description: '',
})

const isEditing = computed(() => editingId.value !== null)

function resetFormState() {
    editingId.value = null
    form.reset()
    form.clearErrors()
    form.type = 'asset'
    form.is_active = true
    form.is_system = false
    form.allows_manual_entries = true
    form.currency = 'NGN'
}

function openNewAccountModal() {
    resetFormState()
    showAccountModal.value = true
}

function closeAccountModal() {
    showAccountModal.value = false
}

function startEdit(account: AccountRow) {
    resetFormState()
    editingId.value = account.id
    form.code = account.code
    form.name = account.name
    form.slug = account.slug
    form.type = account.type
    form.subtype = account.subtype || ''
    form.classification = account.classification || ''
    form.parent_id = account.parent?.id ? String(account.parent.id) : ''
    form.is_active = account.is_active
    form.is_system = account.is_system
    form.allows_manual_entries = account.allows_manual_entries
    form.currency = account.currency || 'NGN'
    form.description = account.description || ''
    showAccountModal.value = true
}

function submit() {
    const payload = {
        ...form.data(),
        parent_id: form.parent_id ? Number(form.parent_id) : null,
    }

    if (editingId.value) {
        form.transform(() => payload).put(route('admin.accounting.accounts.update', editingId.value), {
            preserveScroll: true,
            onSuccess: () => {
                resetFormState()
                closeAccountModal()
            },
        })
        return
    }

    form.transform(() => payload).post(route('admin.accounting.accounts.store'), {
        preserveScroll: true,
        onSuccess: () => {
            resetFormState()
            closeAccountModal()
        },
    })
}

function toggleAccount(account: AccountRow) {
    router.patch(route('admin.accounting.accounts.toggle', account.id), {}, {
        preserveScroll: true,
    })
}
</script>

<template>
    <Head title="Chart of Accounts" />

    <div class="min-h-screen space-y-6 bg-slate-100 p-6 dark:bg-slate-950">
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                <div class="space-y-2">
                    <p class="text-sm font-medium uppercase tracking-[0.24em] text-sky-500">Accounting</p>
                    <h1 class="text-3xl font-semibold tracking-tight text-slate-950 dark:text-white">Chart of Accounts</h1>
                    <p class="max-w-3xl text-sm text-slate-600 dark:text-slate-300">
                        Maintain the enterprise chart of accounts that powers journal posting and financial statements.
                    </p>
                </div>

                <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_180px_180px_auto]">
                    <input v-model="filters.search" type="search" placeholder="Search accounts" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100" />
                    <select v-model="filters.type" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                        <option v-for="option in type_options" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                    <select v-model="filters.status" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                        <option v-for="option in status_options" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                    <Dialog v-model:open="showAccountModal">
                        <DialogTrigger as-child>
                            <Button class="rounded-2xl px-4 py-3 text-sm font-semibold" @click="openNewAccountModal">New account</Button>
                        </DialogTrigger>
                        <DialogContent class="sm:max-w-2xl">
                            <form class="space-y-6" @submit.prevent="submit">
                                <DialogHeader class="space-y-2">
                                    <DialogTitle>{{ isEditing ? 'Edit account' : 'New account' }}</DialogTitle>
                                    <DialogDescription>Create or maintain the ledger structure safely.</DialogDescription>
                                </DialogHeader>

                                <div class="grid gap-4 md:grid-cols-2">
                                    <input v-model="form.code" type="text" placeholder="Account code" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100" />
                                    <input v-model="form.name" type="text" placeholder="Account name" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100" />
                                    <input v-model="form.slug" type="text" placeholder="Slug (optional)" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 md:col-span-2" />
                                    <select v-model="form.type" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                                        <option value="asset">Asset</option>
                                        <option value="liability">Liability</option>
                                        <option value="equity">Equity</option>
                                        <option value="income">Income</option>
                                        <option value="expense">Expense</option>
                                    </select>
                                    <input v-model="form.subtype" type="text" placeholder="Subtype (optional)" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100" />
                                    <input v-model="form.classification" type="text" placeholder="Classification (optional)" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100" />
                                    <select v-model="form.parent_id" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                                        <option value="">No parent</option>
                                        <option v-for="option in parent_options" :key="option.id" :value="String(option.id)">{{ option.label }}</option>
                                    </select>
                                    <input v-model="form.currency" type="text" placeholder="Currency" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 md:col-span-2" />
                                    <textarea v-model="form.description" rows="3" placeholder="Description" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 md:col-span-2" />
                                    <label class="inline-flex items-center gap-3 text-sm text-slate-700 dark:text-slate-300">
                                        <input v-model="form.allows_manual_entries" type="checkbox" class="rounded border-slate-300 text-sky-600 dark:border-slate-600" />
                                        Allow manual entries
                                    </label>
                                    <label class="inline-flex items-center gap-3 text-sm text-slate-700 dark:text-slate-300">
                                        <input v-model="form.is_active" type="checkbox" class="rounded border-slate-300 text-sky-600 dark:border-slate-600" />
                                        Active
                                    </label>
                                </div>

                                <div v-if="Object.keys(form.errors).length" class="rounded-2xl border border-rose-300 bg-rose-50 p-4 text-sm text-rose-700 dark:border-rose-900/60 dark:bg-rose-950/30 dark:text-rose-300">
                                    <div v-for="(error, key) in form.errors" :key="key">{{ error }}</div>
                                </div>

                                <DialogFooter class="gap-2">
                                    <Button type="button" variant="secondary" @click="closeAccountModal">Cancel</Button>
                                    <Button type="submit" :disabled="form.processing">{{ isEditing ? 'Update account' : 'Create account' }}</Button>
                                </DialogFooter>
                            </form>
                        </DialogContent>
                    </Dialog>
                </div>
            </div>
        </section>

        <section>
            <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                        <thead class="bg-slate-50 dark:bg-slate-950">
                            <tr class="text-left text-xs uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400">
                                <th class="px-6 py-4">Account</th>
                                <th class="px-6 py-4">Type</th>
                                <th class="px-6 py-4">Parent</th>
                                <th class="px-6 py-4">Status</th>
                                <th class="px-6 py-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="account in accounts.data" :key="account.id">
                                <td class="px-6 py-4">
                                    <div class="font-semibold text-slate-900 dark:text-slate-100">{{ account.code }} · {{ account.name }}</div>
                                    <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                        <span>{{ account.slug }}</span>
                                        <span v-if="account.subtype"> · {{ account.subtype }}</span>
                                        <span v-if="account.is_system"> · System</span>
                                        <span v-if="!account.allows_manual_entries"> · Auto only</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-slate-700 dark:text-slate-300">{{ account.type }}</td>
                                <td class="px-6 py-4 text-slate-700 dark:text-slate-300">{{ account.parent ? `${account.parent.code} · ${account.parent.name}` : '-' }}</td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" :class="account.is_active ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300' : 'bg-rose-100 text-rose-800 dark:bg-rose-950/40 dark:text-rose-300'">
                                        {{ account.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex justify-end gap-2">
                                        <button type="button" class="rounded-2xl border border-slate-200 px-3 py-2 text-xs font-medium text-slate-700 transition hover:border-sky-300 hover:text-sky-600 dark:border-slate-700 dark:text-slate-200 dark:hover:border-sky-500 dark:hover:text-sky-300" @click="startEdit(account)">
                                            Edit
                                        </button>
                                        <button type="button" class="rounded-2xl border border-slate-200 px-3 py-2 text-xs font-medium text-slate-700 transition hover:border-slate-400 dark:border-slate-700 dark:text-slate-200 dark:hover:border-slate-500" @click="toggleAccount(account)">
                                            {{ account.is_active ? 'Deactivate' : 'Activate' }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!accounts.data.length">
                                <td colspan="5" class="px-6 py-16 text-center text-sm text-slate-500 dark:text-slate-400">No accounts match the current filters.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="px-6 py-4">
                    <Pagination :links="accounts.links" />
                </div>
            </div>
        </section>
    </div>
</template>
