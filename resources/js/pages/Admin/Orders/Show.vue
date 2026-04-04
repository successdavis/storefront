<script setup lang="ts">
import OrderProgressTracker from '@/components/Orders/OrderProgressTracker.vue';
import OrderTimeline from '@/components/Orders/OrderTimeline.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{ order: any }>();

const actionForm = useForm({
    action: props.order.available_actions?.[0]?.key || '',
    note: '',
    payment_amount: '',
    payment_method: 'transfer',
    transaction_reference: '',
    courier_name: '',
    tracking_number: '',
    tracking_url: '',
});

const noteForm = useForm({ note: '' });
const notificationForm = useForm({ notification: 'placed' });
const selectedAction = computed(() => props.order.available_actions?.find((action: any) => action.key === actionForm.action) || null);

const money = (value: number, currency = 'NGN') =>
    new Intl.NumberFormat('en-NG', { style: 'currency', currency }).format(Number(value || 0));

function badgeClass(status: string) {
    const normalized = String(status || '').toLowerCase();
    if (normalized.includes('cancel') || normalized.includes('fail')) return 'bg-rose-100 text-rose-700 dark:bg-rose-950/40 dark:text-rose-200';
    if (normalized.includes('deliver') || normalized.includes('complete') || normalized.includes('paid')) return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-200';
    if (normalized.includes('ship') || normalized.includes('ready')) return 'bg-blue-100 text-blue-700 dark:bg-blue-950/40 dark:text-blue-200';
    if (normalized.includes('process') || normalized.includes('pack') || normalized.includes('pending')) return 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-200';
    return 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200';
}

function submitAction() {
    actionForm.patch(route('admin.orders.status', props.order.id), { preserveScroll: true });
}

function submitNote() {
    noteForm.post(route('admin.orders.notes.store', props.order.id), {
        preserveScroll: true,
        onSuccess: () => noteForm.reset(),
    });
}

function resend(notification: string) {
    notificationForm.notification = notification;
    notificationForm.post(route('admin.orders.notifications.resend', props.order.id), { preserveScroll: true });
}
</script>

<template>
    <Head :title="`Order ${order.order_number}`" />

    <div class="space-y-6 px-5 py-4">
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <Link :href="route('admin.orders.index')" class="text-sm font-medium text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">Back to orders</Link>
                    <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-900 dark:text-slate-100">{{ order.order_number }}</h1>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Placed {{ order.created_at ? new Date(order.created_at).toLocaleString() : 'recently' }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <span :class="['inline-flex rounded-full px-3 py-1 text-xs font-semibold', badgeClass(order.status_label)]">{{ order.status_label }}</span>
                    <span :class="['inline-flex rounded-full px-3 py-1 text-xs font-semibold', badgeClass(order.payment_status_label)]">Payment: {{ order.payment_status_label }}</span>
                    <span :class="['inline-flex rounded-full px-3 py-1 text-xs font-semibold', badgeClass(order.fulfillment_status_label)]">Fulfillment: {{ order.fulfillment_status_label }}</span>
                </div>
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-[1.6fr_1fr]">
            <div class="space-y-6">
                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Customer-facing progress</h2>
                    <div class="mt-4"><OrderProgressTracker :tracker="order.tracker" /></div>
                </div>

                <div class="rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
                    <div class="border-b border-slate-200 px-6 py-4 dark:border-slate-800"><h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Items</h2></div>
                    <div class="divide-y divide-slate-200 dark:divide-slate-800">
                        <div v-for="item in order.items" :key="item.id" class="flex flex-wrap gap-4 px-6 py-5">
                            <img v-if="item.product.image" :src="item.product.image" :alt="item.product.name ?? 'Product image'" class="h-20 w-20 rounded-2xl object-cover" />
                            <div v-else class="flex h-20 w-20 items-center justify-center rounded-2xl bg-slate-100 text-xs text-slate-500 dark:bg-slate-800 dark:text-slate-400">No image</div>
                            <div class="flex-1"><p class="font-semibold text-slate-900 dark:text-slate-100">{{ item.product.name }}</p><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ item.variant.label || item.variant.sku }}</p><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Quantity: {{ item.quantity }}</p></div>
                            <div class="text-right"><p class="text-sm text-slate-500 dark:text-slate-400">{{ money(item.price, order.currency) }} each</p><p class="mt-1 text-base font-semibold text-slate-900 dark:text-slate-100">{{ money(item.subtotal, order.currency) }}</p></div>
                        </div>
                    </div>
                </div>

                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Order timeline</h2>
                    <div class="mt-4"><OrderTimeline :timeline="order.timeline" show-actor /></div>
                </div>
            </div>

            <div class="space-y-6">
                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Order actions</h2>
                    <div v-if="order.available_actions.length" class="mt-4 space-y-4">
                        <div>
                            <label class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Action</label>
                            <select v-model="actionForm.action" class="mt-2 h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100"><option v-for="action in order.available_actions" :key="action.key" :value="action.key">{{ action.label }}</option></select>
                            <p v-if="selectedAction" class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ selectedAction.description }}</p>
                        </div>
                        <div v-if="actionForm.action === 'mark_payment_paid'" class="grid gap-3 md:grid-cols-2">
                            <input v-model="actionForm.payment_amount" type="number" min="0.01" step="0.01" placeholder="Payment amount" class="h-11 rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                            <select v-model="actionForm.payment_method" class="h-11 rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100"><option value="cash">Cash</option><option value="card">Card</option><option value="transfer">Transfer</option><option value="wallet">Wallet</option><option value="paypal">PayPal</option><option value="stripe">Stripe</option><option value="cheque">Cheque</option></select>
                            <input v-model="actionForm.transaction_reference" type="text" placeholder="Transaction reference" class="h-11 rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100 md:col-span-2">
                        </div>
                        <div v-if="actionForm.action === 'mark_shipped'" class="grid gap-3 md:grid-cols-2">
                            <input v-model="actionForm.courier_name" type="text" placeholder="Courier name" class="h-11 rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                            <input v-model="actionForm.tracking_number" type="text" placeholder="Tracking number" class="h-11 rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                            <input v-model="actionForm.tracking_url" type="url" placeholder="Tracking URL" class="h-11 rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100 md:col-span-2">
                        </div>
                        <textarea v-model="actionForm.note" rows="3" placeholder="Optional note or reason" class="w-full rounded-2xl border border-slate-300 bg-white px-3 py-3 text-sm text-slate-900 placeholder:text-slate-400 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100 dark:placeholder:text-slate-500" />
                        <button type="button" class="w-full rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700 disabled:opacity-40 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-300" :disabled="actionForm.processing || !actionForm.action" @click="submitAction">Apply action</button>
                    </div>
                    <div v-else class="mt-4 rounded-2xl border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">No further actions are available for this order.</div>
                </div>

                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Order summary</h2>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div class="flex justify-between text-slate-600 dark:text-slate-300"><dt>Subtotal</dt><dd>{{ money(order.subtotal, order.currency) }}</dd></div>
                        <div class="flex justify-between text-slate-600 dark:text-slate-300"><dt>Shipping</dt><dd>{{ money(order.shipping_total, order.currency) }}</dd></div>
                        <div class="flex justify-between text-slate-600 dark:text-slate-300"><dt>Tax</dt><dd>{{ money(order.tax_total, order.currency) }}</dd></div>
                        <div class="flex justify-between text-slate-600 dark:text-slate-300"><dt>Discount</dt><dd>-{{ money(order.discount, order.currency) }}</dd></div>
                        <div class="flex justify-between border-t border-slate-200 pt-3 text-base font-semibold text-slate-900 dark:border-slate-800 dark:text-slate-100"><dt>Total</dt><dd>{{ money(order.total_amount, order.currency) }}</dd></div>
                    </dl>
                    <div v-if="order.payments.length" class="mt-5 space-y-3">
                        <div v-for="payment in order.payments" :key="payment.id" class="rounded-2xl bg-slate-50 p-4 dark:bg-slate-950">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ payment.method }}</p>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ payment.reference || "No reference supplied" }}</p>
                                </div>
                                <span :class="['inline-flex rounded-full px-3 py-1 text-xs font-semibold', badgeClass(payment.status_label)]">{{ payment.status_label }}</span>
                            </div>
                            <p class="mt-3 text-sm text-slate-600 dark:text-slate-300">{{ money(payment.amount, payment.currency) }}</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900"><h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Customer</h2><div class="mt-4 space-y-2 text-sm text-slate-600 dark:text-slate-300"><p class="font-medium text-slate-900 dark:text-slate-100">{{ order.customer?.name || 'Walk-in customer' }}</p><p>{{ order.customer?.email || 'No email' }}</p><p>{{ order.customer?.phone || 'No phone number' }}</p></div></div>

                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Shipment</h2>
                    <div v-if="order.shipment" class="mt-4 space-y-3 text-sm text-slate-600 dark:text-slate-300">
                        <div class="flex justify-between"><span>Type</span><span class="font-medium text-slate-900 dark:text-slate-100">{{ order.shipment.type_label }}</span></div>
                        <div class="flex justify-between"><span>Status</span><span class="font-medium text-slate-900 dark:text-slate-100">{{ order.shipment.status_label }}</span></div>
                        <div v-if="order.shipment.method" class="flex justify-between"><span>Method</span><span>{{ order.shipment.method }}</span></div>
                        <div v-if="order.shipment.courier_name" class="flex justify-between"><span>Courier</span><span>{{ order.shipment.courier_name }}</span></div>
                        <div v-if="order.shipment.tracking_number" class="flex justify-between"><span>Tracking</span><span>{{ order.shipment.tracking_number }}</span></div>
                        <div v-if="order.shipment.addresses?.length" class="space-y-3">
                            <div v-for="address in order.shipment.addresses" :key="`${address.type}-${address.line1}`" class="rounded-2xl bg-slate-50 p-4 dark:bg-slate-950">
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">{{ address.type_label }}</p>
                                <p class="mt-2 text-sm font-medium text-slate-900 dark:text-slate-100">{{ address.name }}</p>
                                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ address.line1 }}<span v-if="address.line2">, {{ address.line2 }}</span></p>
                                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ [address.lga, address.state, address.country].filter(Boolean).join(", ") }}</p>
                            </div>
                        </div>
                        <div v-if="order.shipment.pickup" class="rounded-2xl bg-slate-50 p-4 dark:bg-slate-950"><p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Pickup details</p><p class="mt-2 text-sm font-medium text-slate-900 dark:text-slate-100">{{ order.shipment.pickup.location?.name || 'Pickup location' }}</p><p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Reference: {{ order.shipment.pickup.reference }}</p></div>
                    </div>
                    <p v-else class="mt-4 text-sm text-slate-500 dark:text-slate-400">No shipment has been attached to this order.</p>
                </div>

                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900"><h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Notifications</h2><div class="mt-4 space-y-3"><button v-for="notification in order.notification_actions" :key="notification.key" type="button" class="w-full rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-500 dark:border-slate-600 dark:text-slate-200 dark:hover:border-slate-400" @click="resend(notification.key)">{{ notification.label }}</button></div></div>

                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Internal notes</h2>
                    <div class="mt-4 space-y-3">
                        <div v-for="note in order.notes" :key="note.id" class="rounded-2xl bg-slate-50 p-4 dark:bg-slate-950"><div class="flex flex-wrap items-center justify-between gap-2"><p class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ note.author?.name || 'System' }}</p><p class="text-xs text-slate-500 dark:text-slate-400">{{ note.created_at ? new Date(note.created_at).toLocaleString() : '' }}</p></div><p class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ note.note }}</p></div>
                        <textarea v-model="noteForm.note" rows="3" placeholder="Add an internal note for staff only" class="w-full rounded-2xl border border-slate-300 bg-white px-3 py-3 text-sm text-slate-900 placeholder:text-slate-400 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100 dark:placeholder:text-slate-500" />
                        <button type="button" class="w-full rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700 disabled:opacity-40 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-300" :disabled="noteForm.processing || !noteForm.note" @click="submitNote">Save note</button>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>
