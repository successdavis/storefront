<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import Pagination from '@/components/Pagination.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { BadgeCheck, Download, UserPen } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    customer: any;
    permissions: {
        can_update: boolean;
        can_change_status: boolean;
        can_send_email: boolean;
        can_manage_notes: boolean;
        can_export: boolean;
    };
}>();

const profileForm = useForm({
    name: props.customer.name || '',
    email: props.customer.email || '',
    phone: props.customer.phone || '',
    address: props.customer.address || '',
    is_vip: Boolean(props.customer.is_vip),
    is_risky: Boolean(props.customer.is_risky),
});

const statusForm = useForm({
    status: props.customer.status || 'active',
    note: '',
});

const emailForm = useForm({
    subject: '',
    message: '',
});

const noteForm = useForm({
    note: '',
});

const editingNoteId = ref<number | null>(null);
const editingNoteValue = ref('');
const customerRouteKey = computed(() => props.customer.route_key || props.customer.customer_slug || props.customer.id);
const showEmailModal = ref(false);
const showVerifyEmailModal = ref(false);
const showStatusModal = ref(false);
const statusOptions = computed(() => {
    const options = [
        {
            value: 'active',
            title: 'Activate',
            description: 'Restore normal access and keep the customer available for checkout.',
            className: 'border-emerald-200 bg-emerald-50 hover:border-emerald-300 hover:bg-emerald-100/70 dark:border-emerald-900 dark:bg-emerald-950/30 dark:hover:bg-emerald-950/50',
            textClass: 'text-emerald-700 dark:text-emerald-200',
        },
        {
            value: 'inactive',
            title: 'Deactivate',
            description: 'Pause the account without applying a harder suspension state.',
            className: 'border-amber-200 bg-amber-50 hover:border-amber-300 hover:bg-amber-100/70 dark:border-amber-900 dark:bg-amber-950/30 dark:hover:bg-amber-950/50',
            textClass: 'text-amber-700 dark:text-amber-200',
        },
        {
            value: 'suspended',
            title: 'Suspend',
            description: 'Restrict the account for fraud review, abuse prevention, or manual intervention.',
            className: 'border-rose-200 bg-rose-50 hover:border-rose-300 hover:bg-rose-100/70 dark:border-rose-900 dark:bg-rose-950/30 dark:hover:bg-rose-950/50',
            textClass: 'text-rose-700 dark:text-rose-200',
        },
    ];

    return options.filter((option) => String(props.customer.status || 'active') !== option.value);
});

function money(value: number, currency = 'NGN') {
    return new Intl.NumberFormat('en-NG', { style: 'currency', currency }).format(Number(value || 0));
}

function ordinalDay(day: number) {
    const remainder = day % 100;

    if (remainder >= 11 && remainder <= 13) {
        return `${day}th`;
    }

    switch (day % 10) {
        case 1:
            return `${day}st`;
        case 2:
            return `${day}nd`;
        case 3:
            return `${day}rd`;
        default:
            return `${day}th`;
    }
}

function formatFriendlyDate(value?: string | null) {
    if (!value) {
        return '-';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '-';
    }

    const month = new Intl.DateTimeFormat('en-GB', { month: 'short' }).format(date);

    return `${ordinalDay(date.getDate())} ${month} ${date.getFullYear()}`;
}

function dateTime(value?: string | null) {
    return formatFriendlyDate(value);
}

function dateOnly(value?: string | null) {
    return formatFriendlyDate(value);
}

function statusBadgeClass(status: string) {
    const normalized = String(status || '').toLowerCase();

    if (normalized.includes('suspend')) return 'bg-rose-100 text-rose-700 dark:bg-rose-950/40 dark:text-rose-200';
    if (normalized.includes('inactive')) return 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-200';

    return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-200';
}

function submitProfile() {
    profileForm.put(route('admin.customers.update', customerRouteKey.value), {
        preserveScroll: true,
        onSuccess: () => {
            showEmailModal.value = false;
        },
    });
}

function openStatusModal(status: string) {
    statusForm.status = status;
    statusForm.clearErrors();
    showStatusModal.value = true;
}

function statusActionLabel(status: string) {
    switch (status) {
        case 'inactive':
            return 'Deactivate';
        case 'suspended':
            return 'Suspend';
        default:
            return 'Activate';
    }
}

function prepareStatusModal() {
    statusForm.clearErrors();
    statusForm.note = '';
    statusForm.status = statusOptions.value[0]?.value || props.customer.status || 'active';
    showStatusModal.value = true;
}

function submitStatusChange() {
    statusForm.patch(route('admin.customers.status', customerRouteKey.value), {
        preserveScroll: true,
        onSuccess: () => {
            showStatusModal.value = false;
            statusForm.reset('note');
        },
    });
}

function sendEmail() {
    emailForm.post(route('admin.customers.email', customerRouteKey.value), {
        preserveScroll: true,
        onSuccess: () => emailForm.reset(),
    });
}

function submitNote() {
    noteForm.post(route('admin.customers.notes.store', customerRouteKey.value), {
        preserveScroll: true,
        onSuccess: () => noteForm.reset(),
    });
}

function beginEdit(note: any) {
    editingNoteId.value = note.id;
    editingNoteValue.value = note.note;
}

function saveEdit() {
    if (!editingNoteId.value) {
        return;
    }

    router.put(route('admin.customers.notes.update', [customerRouteKey.value, editingNoteId.value]), {
        note: editingNoteValue.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            editingNoteId.value = null;
            editingNoteValue.value = '';
        },
    });
}

function deleteNote(noteId: number) {
    if (!confirm('Delete this internal note?')) {
        return;
    }

    router.delete(route('admin.customers.notes.destroy', [customerRouteKey.value, noteId]), {
        preserveScroll: true,
    });
}

function exportCustomer() {
    window.location.href = route('admin.customers.export', { ids: String(props.customer.id) });
}

function markCustomerVerified() {
    router.post(route('admin.customers.mark-verified', customerRouteKey.value), {}, {
        preserveScroll: true,
        onSuccess: () => {
            showVerifyEmailModal.value = false;
        },
    });
}
</script>

<template>
    <Head :title="customer.name" />

    <div class="space-y-6 px-5 py-4">
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <Link :href="route('admin.customers.index')" class="text-sm font-medium text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">
                        Back to customers
                    </Link>
                    <h1 class="mt-3 text-3xl font-semibold tracking-tight text-slate-900 dark:text-slate-100">{{ customer.name }}</h1>
                    <p v-if="customer.customer_slug" class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                        Customer URL key: {{ customer.customer_slug }}
                    </p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <span :class="['inline-flex rounded-full px-2.5 py-1 text-xs font-semibold', statusBadgeClass(customer.status)]">{{ customer.status_label }}</span>
                        <span v-if="customer.email_verified" class="inline-flex rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-200">Verified</span>
                        <span v-else class="inline-flex rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700 dark:bg-amber-950/40 dark:text-amber-200">Unverified</span>
                        <span v-if="customer.is_vip" class="inline-flex rounded-full bg-fuchsia-100 px-2.5 py-1 text-xs font-semibold text-fuchsia-700 dark:bg-fuchsia-950/40 dark:text-fuchsia-200">VIP</span>
                        <span v-if="customer.is_risky" class="inline-flex rounded-full bg-rose-100 px-2.5 py-1 text-xs font-semibold text-rose-700 dark:bg-rose-950/40 dark:text-rose-200">Risk flagged</span>
                        <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ customer.segment.replaceAll('_', ' ') }}</span>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <Dialog v-if="permissions.can_change_status" v-model:open="showStatusModal">
                        <Button
                            type="button"
                            variant="secondary"
                            class="rounded-xl"
                            @click="prepareStatusModal"
                        >
                            Manage status
                        </Button>
                        <DialogContent class="sm:max-w-lg">
                            <DialogHeader class="space-y-3">
                                <DialogTitle>Manage customer status</DialogTitle>
                                <DialogDescription>
                                    Choose the account state you want to apply for {{ customer.name }}. This updates the customer profile immediately and records the admin action.
                                </DialogDescription>
                            </DialogHeader>

                            <div class="flex flex-wrap gap-3">
                                <button
                                    v-for="option in statusOptions"
                                    :key="option.value"
                                    type="button"
                                    :class="[
                                        'min-w-[11rem] flex-1 rounded-2xl border px-4 py-4 text-left transition',
                                        option.className,
                                    ]"
                                    @click="openStatusModal(option.value)"
                                >
                                    <p :class="['text-sm font-semibold', option.textClass]">{{ option.title }}</p>
                                    <p :class="['mt-2 text-xs leading-5', `${option.textClass}/80`]">{{ option.description }}</p>
                                </button>
                            </div>

                            <div class="grid gap-2">
                                <Label for="customer-status-note">Internal note</Label>
                                <textarea
                                    id="customer-status-note"
                                    v-model="statusForm.note"
                                    rows="4"
                                    class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-400 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100 dark:placeholder:text-slate-500 dark:focus:ring-slate-500"
                                    placeholder="Add context for this status change"
                                />
                                <InputError :message="statusForm.errors.note || statusForm.errors.status" />
                            </div>

                            <DialogFooter class="gap-2">
                                <DialogClose as-child>
                                    <Button type="button" variant="secondary">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button type="button" :disabled="statusForm.processing" @click="submitStatusChange">
                                    {{ statusActionLabel(statusForm.status) }} customer
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                    <Dialog v-if="permissions.can_update" v-model:open="showEmailModal">
                        <DialogTrigger as-child>
                            <button
                                type="button"
                                title="Update customer email"
                                aria-label="Update customer email"
                                class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-300 text-slate-700 transition hover:border-slate-400 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-100 dark:hover:bg-slate-800"
                            >
                                <UserPen class="h-4 w-4" />
                            </button>
                        </DialogTrigger>
                        <DialogContent class="sm:max-w-lg">
                            <DialogHeader class="space-y-3">
                                <DialogTitle>Update customer email</DialogTitle>
                                <DialogDescription>
                                    Change the customer’s email address. This uses the existing admin customer update flow and will clear verification if the email changes.
                                </DialogDescription>
                            </DialogHeader>

                            <form class="space-y-5" @submit.prevent="submitProfile">
                                <div class="grid gap-2">
                                    <Label for="customer-email-update">Email address</Label>
                                    <Input
                                        id="customer-email-update"
                                        v-model="profileForm.email"
                                        type="email"
                                        autocomplete="email"
                                        placeholder="customer@example.com"
                                    />
                                    <InputError :message="profileForm.errors.email" />
                                </div>

                                <DialogFooter class="gap-2">
                                    <DialogClose as-child>
                                        <Button
                                            type="button"
                                            variant="secondary"
                                            @click="() => {
                                                profileForm.clearErrors('email')
                                                profileForm.email = customer.email || ''
                                            }"
                                        >
                                            Cancel
                                        </Button>
                                    </DialogClose>
                                    <Button type="submit" :disabled="profileForm.processing">
                                        Save email
                                    </Button>
                                </DialogFooter>
                            </form>
                        </DialogContent>
                    </Dialog>
                    <Dialog v-if="permissions.can_update && !customer.email_verified" v-model:open="showVerifyEmailModal">
                        <DialogTrigger as-child>
                            <button
                                type="button"
                                title="Mark email as verified"
                                aria-label="Mark email as verified"
                                class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-emerald-300 text-emerald-700 transition hover:border-emerald-400 hover:bg-emerald-50 dark:border-emerald-800 dark:text-emerald-200 dark:hover:bg-emerald-950/40"
                            >
                                <BadgeCheck class="h-4 w-4" />
                            </button>
                        </DialogTrigger>
                        <DialogContent class="sm:max-w-md">
                            <DialogHeader class="space-y-3">
                                <DialogTitle>Verify customer email</DialogTitle>
                                <DialogDescription>
                                    This will manually mark <span class="font-medium text-slate-900 dark:text-slate-100">{{ customer.email }}</span> as verified for this customer account.
                                </DialogDescription>
                            </DialogHeader>

                            <DialogFooter class="gap-2">
                                <DialogClose as-child>
                                    <Button type="button" variant="secondary">
                                        Cancel
                                    </Button>
                                </DialogClose>
                                <Button type="button" @click="markCustomerVerified">
                                    Mark as verified
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                    <button
                        v-if="permissions.can_export"
                        type="button"
                        title="Export customer"
                        aria-label="Export customer"
                        class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-300 text-slate-700 transition hover:border-slate-400 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-100 dark:hover:bg-slate-800"
                        @click="exportCustomer"
                    >
                        <Download class="h-4 w-4" />
                    </button>
                </div>
            </div>

            <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div v-for="card in customer.overview_cards" :key="card.key" class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 dark:border-slate-800 dark:bg-slate-950">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">{{ card.label }}</p>
                    <p class="mt-3 text-3xl font-semibold text-slate-900 dark:text-slate-100">
                        <template v-if="['spend', 'aov'].includes(card.key)">{{ money(card.value) }}</template>
                        <template v-else>{{ card.value }}</template>
                    </p>
                </div>
            </div>
        </section>

        <section class="space-y-6">
            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Overview</h2>

                <div class="mt-6 grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                    <div class="space-y-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Email</p>
                            <p class="mt-2 text-sm text-slate-900 dark:text-slate-100">{{ customer.email }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Phone</p>
                            <p class="mt-2 text-sm text-slate-900 dark:text-slate-100">{{ customer.phone || 'No phone provided' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Registered</p>
                            <p class="mt-2 text-sm text-slate-900 dark:text-slate-100">{{ dateTime(customer.registered_at) }}</p>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Last login</p>
                            <p class="mt-2 text-sm text-slate-900 dark:text-slate-100">{{ dateTime(customer.last_login_at) }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Preferred payment method</p>
                            <p class="mt-2 text-sm text-slate-900 dark:text-slate-100">{{ customer.analytics.preferred_payment_method || 'No payment activity yet' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Most purchased category</p>
                            <p class="mt-2 text-sm text-slate-900 dark:text-slate-100">
                                {{ customer.analytics.top_category?.name || 'No category trend yet' }}
                                <span v-if="customer.analytics.top_category" class="text-slate-500 dark:text-slate-400">({{ customer.analytics.top_category.quantity }})</span>
                            </p>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">First order date</p>
                            <p class="mt-2 text-sm text-slate-900 dark:text-slate-100">{{ dateOnly(customer.commerce_summary.first_order_at) }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Last order date</p>
                            <p class="mt-2 text-sm text-slate-900 dark:text-slate-100">{{ dateOnly(customer.commerce_summary.last_order_at) }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Order history</h2>

                <div class="mt-6 grid gap-4 md:grid-cols-5">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 dark:border-slate-800 dark:bg-slate-950">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Total orders</p>
                        <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ customer.commerce_summary.total_orders }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 dark:border-slate-800 dark:bg-slate-950">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Paid</p>
                        <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ customer.commerce_summary.paid_orders }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 dark:border-slate-800 dark:bg-slate-950">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Cancelled</p>
                        <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ customer.commerce_summary.cancelled_orders }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 dark:border-slate-800 dark:bg-slate-950">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Refunded</p>
                        <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ customer.commerce_summary.refunded_orders }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 dark:border-slate-800 dark:bg-slate-950">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Total spend</p>
                        <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ money(customer.commerce_summary.total_spend) }}</p>
                    </div>
                </div>

                <div class="mt-6 overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                        <thead class="bg-slate-50 dark:bg-slate-950">
                            <tr class="text-left text-xs uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">
                                <th class="px-4 py-3">Order</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Payment</th>
                                <th class="px-4 py-3">Channel</th>
                                <th class="px-4 py-3">Placed</th>
                                <th class="px-4 py-3 text-right">Amount</th>
                                <th class="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="order in customer.orders.data" :key="order.id">
                                <td class="px-4 py-3 font-medium text-slate-900 dark:text-slate-100">{{ order.order_number }}</td>
                                <td class="px-4 py-3">
                                    <span :class="['inline-flex rounded-full px-2.5 py-1 text-xs font-semibold', statusBadgeClass(order.status)]">{{ order.status_label }}</span>
                                </td>
                                <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ order.payment_status_label }}</td>
                                <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ order.channel }}</td>
                                <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ dateOnly(order.created_at) }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-slate-900 dark:text-slate-100">{{ money(order.total_amount, order.currency) }}</td>
                                <td class="px-4 py-3 text-right">
                                    <Link :href="route('admin.orders.show', order.id)" class="inline-flex rounded-xl bg-slate-900 px-3 py-2 text-xs font-semibold text-white transition hover:bg-slate-700 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-300">
                                        View order
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="customer.orders.data.length === 0">
                                <td colspan="7" class="px-4 py-10 text-center text-sm text-slate-500 dark:text-slate-400">No orders recorded for this customer yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    <Pagination :links="customer.orders.links" />
                </div>
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Addresses</h2>
                <div class="mt-6 grid gap-4 md:grid-cols-2">
                    <article v-for="address in customer.addresses" :key="address.id" class="rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-950">
                        <div class="flex items-center justify-between gap-3">
                            <p class="font-semibold text-slate-900 dark:text-slate-100">{{ address.label || 'Customer address' }}</p>
                            <span v-if="address.is_default" class="inline-flex rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-200">Default</span>
                        </div>
                        <p class="mt-3 text-sm text-slate-900 dark:text-slate-100">{{ address.recipient_name || customer.name }}</p>
                        <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ address.phone || 'No phone provided' }}</p>
                        <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ address.email || customer.email }}</p>
                        <p class="mt-3 text-sm text-slate-600 dark:text-slate-300">{{ address.line1 }}</p>
                        <p v-if="address.line2" class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ address.line2 }}</p>
                        <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ [address.lga, address.state, address.country].filter(Boolean).join(', ') }}</p>
                    </article>
                    <div v-if="customer.addresses.length === 0" class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-400">
                        No saved addresses yet.
                    </div>
                </div>
            </section>

            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <div class="flex items-center justify-between gap-4">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Credit and receivables</h2>
                    <div class="text-right">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Outstanding balance</p>
                        <p class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100">{{ money(customer.receivables.outstanding_balance) }}</p>
                    </div>
                </div>

                <div class="mt-6 grid gap-4 md:grid-cols-3">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 dark:border-slate-800 dark:bg-slate-950">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Open invoices</p>
                        <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ customer.receivables.open_invoices_count }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 dark:border-slate-800 dark:bg-slate-950">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Overdue balance</p>
                        <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ money(customer.receivables.overdue_balance) }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 dark:border-slate-800 dark:bg-slate-950">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Credit utilization</p>
                        <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ customer.receivables.credit_utilization_percent.toFixed(2) }}%</p>
                    </div>
                </div>

                <div class="mt-6 overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                        <thead class="bg-slate-50 dark:bg-slate-950">
                            <tr class="text-left text-xs uppercase tracking-[0.22em] text-slate-500 dark:text-slate-400">
                                <th class="px-4 py-3">Invoice</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Due date</th>
                                <th class="px-4 py-3 text-right">Debt</th>
                                <th class="px-4 py-3 text-right">Recovered</th>
                                <th class="px-4 py-3 text-right">Outstanding</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="invoice in customer.receivables.invoices" :key="invoice.id">
                                <td class="px-4 py-3 font-medium text-slate-900 dark:text-slate-100">
                                    {{ invoice.invoice_number }}
                                    <p class="mt-1 text-xs font-normal text-slate-500 dark:text-slate-400">{{ invoice.order?.order_number || 'No order link' }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <span :class="['inline-flex rounded-full px-2.5 py-1 text-xs font-semibold', statusBadgeClass(invoice.status)]">{{ invoice.status_label }}</span>
                                </td>
                                <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ dateOnly(invoice.due_date) }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-slate-900 dark:text-slate-100">{{ money(invoice.total_amount) }}</td>
                                <td class="px-4 py-3 text-right text-slate-600 dark:text-slate-300">{{ money(invoice.amount_paid) }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-slate-900 dark:text-slate-100">{{ money(invoice.outstanding_balance) }}</td>
                            </tr>
                            <tr v-if="customer.receivables.invoices.length === 0">
                                <td colspan="6" class="px-4 py-10 text-center text-sm text-slate-500 dark:text-slate-400">No receivable invoices recorded for this customer yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="grid gap-6 xl:grid-cols-2">
                <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Payments</h2>
                    <div class="mt-6 space-y-3">
                        <div v-for="payment in customer.payments" :key="payment.id" class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 dark:border-slate-800 dark:bg-slate-950">
                            <div class="flex items-center justify-between gap-4">
                                <div>
                                    <p class="font-semibold text-slate-900 dark:text-slate-100">{{ payment.order_number || 'Payment' }}</p>
                                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ payment.method }} - {{ payment.status }} <span v-if="payment.source === 'invoice'">&middot; Debt recovery</span></p>
                                </div>
                                <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ money(payment.amount, payment.currency) }}</p>
                            </div>
                            <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">{{ payment.transaction_reference || 'No transaction reference' }}</p>
                        </div>
                        <div v-if="customer.payments.length === 0" class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-400">
                            No payment records found for this customer.
                        </div>
                    </div>
                </section>

                <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Cart insight</h2>
                    <div class="mt-6 space-y-4">
                        <div class="grid gap-4 sm:grid-cols-3">
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 dark:border-slate-800 dark:bg-slate-950">
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Active cart</p>
                                <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ customer.cart.has_active_cart ? 'Yes' : 'No' }}</p>
                            </div>
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 dark:border-slate-800 dark:bg-slate-950">
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Items</p>
                                <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ customer.cart.item_count }}</p>
                            </div>
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 dark:border-slate-800 dark:bg-slate-950">
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Value</p>
                                <p class="mt-2 text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ money(customer.cart.cart_value_estimate) }}</p>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-950">
                            <p class="text-sm text-slate-600 dark:text-slate-300">Last cart update: <span class="font-medium text-slate-900 dark:text-slate-100">{{ dateTime(customer.cart.last_cart_update_at) }}</span></p>
                            <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Abandoned cart candidate: <span class="font-medium text-slate-900 dark:text-slate-100">{{ customer.analytics.abandoned_cart_candidate ? 'Yes' : 'No' }}</span></p>
                            <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Wishlist items: <span class="font-medium text-slate-900 dark:text-slate-100">{{ customer.analytics.wishlist_count }}</span></p>
                            <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Saved for later: <span class="font-medium text-slate-900 dark:text-slate-100">{{ customer.analytics.saved_for_later_count }}</span></p>
                        </div>
                    </div>
                </section>
            </section>
        </section>
    </div>
</template>
