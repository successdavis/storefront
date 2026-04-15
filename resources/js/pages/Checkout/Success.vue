<script setup>
import { Head, Link } from '@inertiajs/vue3'
import StorefrontLayout from '@/layouts/StorefrontLayout.vue'

defineOptions({ layout: StorefrontLayout })

const props = defineProps({
    order: {
        type: Object,
        default: null,
    },
})

const formatter = new Intl.NumberFormat('en-NG', {
    style: 'currency',
    currency: props.order?.currency || 'NGN',
})

function money(value) {
    return formatter.format(Number(value || 0))
}

function label(value, fallback = 'Pending') {
    if (!value) {
        return fallback
    }

    return String(value)
        .replace(/_/g, ' ')
        .replace(/\b\w/g, char => char.toUpperCase())
}
</script>

<template>
    <Head title="Order Success" />

    <section class="mx-auto max-w-4xl">
        <div class="overflow-hidden rounded-[2rem] border border-emerald-200/80 bg-white/95 shadow-[0_24px_80px_-36px_rgba(15,23,42,0.45)] dark:border-emerald-900/70 dark:bg-slate-950/90">
            <div class="border-b border-emerald-100 bg-[radial-gradient(circle_at_top,_rgba(16,185,129,0.18),_rgba(255,255,255,0)_58%)] px-6 py-8 sm:px-8 dark:border-slate-800 dark:bg-[radial-gradient(circle_at_top,_rgba(16,185,129,0.18),_rgba(2,6,23,0)_58%)]">
                <div class="mx-auto flex max-w-3xl flex-col items-center text-center">
                    <div class="flex size-16 items-center justify-center rounded-full bg-emerald-100 text-emerald-700 shadow-inner shadow-emerald-200/70 dark:bg-emerald-500/15 dark:text-emerald-300 dark:shadow-emerald-950/60">
                        <svg class="size-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M20 6 9 17l-5-5" />
                        </svg>
                    </div>

                    <span class="mt-4 inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.24em] text-emerald-700 dark:border-emerald-900/80 dark:bg-emerald-950/40 dark:text-emerald-300">
                        Payment Successful
                    </span>

                    <h1 class="mt-5 text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl dark:text-slate-100">
                        Order confirmed!
                    </h1>

                    <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-600 sm:text-base dark:text-slate-300">
                        Your payment was successful and we've started processing your order.
                    </p>

<!--                    <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">-->
<!--                        We'll keep you updated from your dashboard as your order moves through payment, processing, and delivery.-->
<!--                    </p>-->
                </div>
            </div>

            <div class="grid gap-6 px-6 py-6 sm:px-8 lg:grid-cols-[minmax(0,1.2fr)_minmax(18rem,0.8fr)]">
                <div class="min-w-0 rounded-3xl border border-slate-200 bg-slate-50/80 p-5 dark:border-slate-800 dark:bg-slate-900/70">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500 dark:text-slate-400">Order Summary</p>
                            <p class="mt-2 break-words text-lg font-semibold text-slate-900 sm:break-all dark:text-slate-100">
                                {{ order?.order_number || 'Order received' }}
                            </p>
                        </div>

                        <span class="inline-flex w-fit items-center rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-700 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200">
                            {{ label(order?.status, 'Paid') }}
                        </span>
                    </div>

                    <dl class="mt-5 grid gap-4 sm:grid-cols-3">
                        <div class="rounded-2xl border border-white/80 bg-white p-4 dark:border-slate-800 dark:bg-slate-950/80">
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Payment</dt>
                            <dd class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100">Confirmed</dd>
                        </div>

                        <div class="rounded-2xl border border-white/80 bg-white p-4 dark:border-slate-800 dark:bg-slate-950/80">
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Total</dt>
                            <dd class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100">{{ money(order?.total_amount) }}</dd>
                        </div>

                        <div class="rounded-2xl border border-white/80 bg-white p-4 dark:border-slate-800 dark:bg-slate-950/80">
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Next Step</dt>
                            <dd class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100">Processing</dd>
                        </div>
                    </dl>
                </div>

                <div class="min-w-0 rounded-3xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-950">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500 dark:text-slate-400">What Happens Next</p>

                    <div class="mt-4 space-y-4">
                        <div class="flex gap-3">
                            <div class="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-xs font-bold text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">1</div>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Order review is underway</p>
                                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Our team is confirming your items and preparing the order for fulfillment.</p>
                            </div>
                        </div>

                        <div class="flex gap-3">
                            <div class="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-full bg-amber-100 text-xs font-bold text-amber-700 dark:bg-amber-500/15 dark:text-amber-300">2</div>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Shipping updates will follow</p>
                                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">You'll receive shipment progress and tracking updates once dispatch begins.</p>
                            </div>
                        </div>

                        <div class="flex gap-3">
                            <div class="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-full bg-slate-200 text-xs font-bold text-slate-700 dark:bg-slate-800 dark:text-slate-200">3</div>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Track everything from your account</p>
                                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Visit your orders dashboard anytime to view status changes, timeline updates, and delivery progress.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-center gap-3 border-t border-slate-200 px-6 py-6 sm:px-8 dark:border-slate-800">
                <Link
                    :href="route('store.home')"
                    class="inline-flex min-w-44 items-center justify-center rounded-2xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-700 dark:bg-amber-500 dark:text-slate-950 dark:hover:bg-amber-400"
                >
                    Continue Shopping
                </Link>
                <Link
                    :href="route('orders.index')"
                    class="inline-flex min-w-44 items-center justify-center rounded-2xl border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:text-slate-900 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200 dark:hover:border-slate-500 dark:hover:text-slate-100"
                >
                    View Orders
                </Link>
            </div>
        </div>
    </section>
</template>

