<script setup>
import { Head, Link, useForm, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

const props = defineProps({
    product: Object,
})

const selectedTransactionType = ref('all')
const editingNoteId = ref(null)
const editingNoteValue = ref('')

const noteForm = useForm({
    note: '',
})

const money = (value) => new Intl.NumberFormat('en-NG', {
    style: 'currency',
    currency: 'NGN',
}).format(Number(value || 0))

function badgeClass(value) {
    return value
        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-200'
        : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'
}

function transactionBadgeClass(type) {
    const palette = {
        sales_orders: 'bg-blue-100 text-blue-700 dark:bg-blue-950/40 dark:text-blue-200',
        pos_sales: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-200',
        purchase_orders: 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-200',
        item_receipts: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-200',
        vendor_bills: 'bg-fuchsia-100 text-fuchsia-700 dark:bg-fuchsia-950/40 dark:text-fuchsia-200',
        stock_entries: 'bg-cyan-100 text-cyan-700 dark:bg-cyan-950/40 dark:text-cyan-200',
        stock_adjustments: 'bg-rose-100 text-rose-700 dark:bg-rose-950/40 dark:text-rose-200',
        stock_audits: 'bg-orange-100 text-orange-700 dark:bg-orange-950/40 dark:text-orange-200',
    }

    return palette[type] || 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300'
}

function formatMetric(metric) {
    if (!metric) return '-'
    if (metric.from) return `${money(metric.min)} - ${money(metric.max)}`
    return money(metric.min)
}

const filteredTransactions = computed(() => {
    if (selectedTransactionType.value === 'all') {
        return props.product.transactions || []
    }

    return (props.product.transactions || []).filter((item) => item.type === selectedTransactionType.value)
})

function submitNote() {
    noteForm.post(route('admin.products.notes.store', props.product.id), {
        preserveScroll: true,
        onSuccess: () => noteForm.reset(),
    })
}

function startEditing(note) {
    editingNoteId.value = note.id
    editingNoteValue.value = note.note
}

function cancelEditing() {
    editingNoteId.value = null
    editingNoteValue.value = ''
}

function updateNote(noteId) {
    router.put(route('admin.products.notes.update', [props.product.id, noteId]), {
        note: editingNoteValue.value,
    }, {
        preserveScroll: true,
        onSuccess: cancelEditing,
    })
}

function deleteNote(noteId) {
    if (!confirm('Delete this note?')) return

    router.delete(route('admin.products.notes.destroy', [props.product.id, noteId]), {
        preserveScroll: true,
        onSuccess: () => {
            if (editingNoteId.value === noteId) cancelEditing()
        },
    })
}
</script>

<template>
    <Head :title="product.name" />

    <div class="space-y-6 px-5 py-4">
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <Link :href="route('admin.products.index')" class="text-sm font-medium text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">
                        Back to products
                    </Link>
                    <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-900 dark:text-slate-100">{{ product.name }}</h1>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                        Slug: {{ product.slug }}
                    </p>
                </div>

                <div class="flex gap-3">
                    <Link :href="route('admin.products.edit', product.id)" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-300">
                        Edit product
                    </Link>
                </div>
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
            <div class="space-y-6">
                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                    <div class="flex flex-wrap gap-6">
                        <img
                            v-if="product.image"
                            :src="product.image"
                            :alt="product.name"
                            class="h-48 w-48 rounded-3xl object-cover"
                        />
                        <div
                            v-else
                            class="flex h-48 w-48 items-center justify-center rounded-3xl bg-slate-100 text-sm text-slate-500 dark:bg-slate-800 dark:text-slate-400"
                        >
                            No image
                        </div>

                        <div class="flex-1 space-y-4">
                            <div class="flex flex-wrap gap-2">
                                <span :class="['inline-flex rounded-full px-3 py-1 text-xs font-semibold', badgeClass(product.published)]">
                                    {{ product.published ? 'Published' : 'Draft' }}
                                </span>
                                <span :class="['inline-flex rounded-full px-3 py-1 text-xs font-semibold', badgeClass(product.featured)]">
                                    {{ product.featured ? 'Featured' : 'Not featured' }}
                                </span>
                                <span :class="['inline-flex rounded-full px-3 py-1 text-xs font-semibold', badgeClass(product.on_sale)]">
                                    {{ product.on_sale ? 'On sale' : 'Regular price' }}
                                </span>
                            </div>

                            <dl class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                                <div>
                                    <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Brand</dt>
                                    <dd class="mt-1 text-sm text-slate-900 dark:text-slate-100">{{ product.brand || 'Unassigned' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Categories</dt>
                                    <dd class="mt-1 text-sm text-slate-900 dark:text-slate-100">
                                        {{ product.categories.map((category) => category.name).join(', ') || 'Unassigned' }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Total stock</dt>
                                    <dd class="mt-1 text-sm text-slate-900 dark:text-slate-100">{{ product.total_stock }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Available stock</dt>
                                    <dd class="mt-1 text-sm text-slate-900 dark:text-slate-100">{{ product.available_stock }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Cost</dt>
                                    <dd class="mt-1 text-sm text-slate-900 dark:text-slate-100">{{ formatMetric(product.pricing_summary.cost) }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Sale price</dt>
                                    <dd class="mt-1 text-sm text-slate-900 dark:text-slate-100">{{ formatMetric(product.pricing_summary.sale_price) }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500 dark:text-slate-400">Average cost</dt>
                                    <dd class="mt-1 text-sm text-slate-900 dark:text-slate-100">{{ formatMetric(product.pricing_summary.average_cost) }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                </div>

                <div class="rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
                    <div class="border-b border-slate-200 px-6 py-4 dark:border-slate-800">
                        <div class="flex flex-wrap items-center justify-between gap-4">
                            <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Transaction activity</h2>
                            <select
                                v-model="selectedTransactionType"
                                class="h-11 rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100"
                            >
                                <option
                                    v-for="filter in product.transaction_filters"
                                    :key="filter.value"
                                    :value="filter.value"
                                >
                                    {{ filter.label }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                            <thead class="bg-slate-50 dark:bg-slate-950">
                                <tr class="text-left text-xs uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">
                                    <th class="px-6 py-3">Activity</th>
                                    <th class="px-6 py-3">Reference</th>
                                    <th class="px-6 py-3">Variant</th>
                                    <th class="px-6 py-3">Quantity</th>
                                    <th class="px-6 py-3">Amount</th>
                                    <th class="px-6 py-3">When</th>
                                </tr>
                            </thead>
                            <tbody v-if="filteredTransactions.length" class="divide-y divide-slate-200 dark:divide-slate-800">
                                <tr v-for="transaction in filteredTransactions" :key="transaction.id">
                                    <td class="px-6 py-4">
                                        <div class="flex flex-col gap-2">
                                            <span :class="['inline-flex w-fit rounded-full px-3 py-1 text-xs font-semibold', transactionBadgeClass(transaction.type)]">
                                                {{ transaction.type_label }}
                                            </span>
                                            <span v-if="transaction.status" class="text-xs text-slate-500 dark:text-slate-400">
                                                Status: {{ transaction.status }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <a
                                            v-if="transaction.href"
                                            :href="transaction.href"
                                            class="font-medium text-blue-600 hover:text-blue-500 dark:text-blue-300 dark:hover:text-blue-200"
                                        >
                                            {{ transaction.reference }}
                                        </a>
                                        <span v-else class="font-medium text-slate-900 dark:text-slate-100">{{ transaction.reference }}</span>
                                        <p v-if="transaction.meta" class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ transaction.meta }}</p>
                                    </td>
                                    <td class="px-6 py-4 text-slate-600 dark:text-slate-300">{{ transaction.variant_label }}</td>
                                    <td class="px-6 py-4 text-slate-900 dark:text-slate-100">{{ transaction.quantity ?? '-' }}</td>
                                    <td class="px-6 py-4 text-slate-900 dark:text-slate-100">{{ transaction.amount !== null ? money(transaction.amount) : '-' }}</td>
                                    <td class="px-6 py-4 text-slate-600 dark:text-slate-300">{{ transaction.occurred_at || '-' }}</td>
                                </tr>
                            </tbody>
                            <tbody v-else>
                                <tr>
                                    <td colspan="6" class="px-6 py-8 text-center text-sm text-slate-500 dark:text-slate-400">
                                        No transaction activity found for the selected filter.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
                    <div class="border-b border-slate-200 px-6 py-4 dark:border-slate-800">
                        <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Variants</h2>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                            <thead class="bg-slate-50 dark:bg-slate-950">
                                <tr class="text-left text-xs uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">
                                    <th class="px-6 py-3">Variant</th>
                                    <th class="px-6 py-3">Barcode</th>
                                    <th class="px-6 py-3">Price</th>
                                    <th class="px-6 py-3">Cost</th>
                                    <th class="px-6 py-3">Available</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                                <tr v-for="variant in product.variants" :key="variant.id">
                                    <td class="px-6 py-4">
                                        <div>
                                            <p class="font-medium text-slate-900 dark:text-slate-100">{{ variant.label }}</p>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-slate-600 dark:text-slate-300">{{ variant.barcode || '-' }}</td>
                                    <td class="px-6 py-4 text-slate-900 dark:text-slate-100">
                                        <div>{{ money(variant.price.current) }}</div>
                                        <div v-if="variant.price.has_discount" class="text-xs text-slate-500 line-through dark:text-slate-400">
                                            {{ money(variant.price.regular) }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-slate-900 dark:text-slate-100">
                                        {{ variant.last_purchase_price !== null ? money(variant.last_purchase_price) : '-' }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <span :class="['inline-flex rounded-full px-3 py-1 text-xs font-semibold', badgeClass(variant.stock.is_in_stock)]">
                                            {{ variant.stock.is_in_stock ? 'In stock' : 'Out of stock' }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Product notes</h2>

                    <div class="mt-4 space-y-3">
                        <div
                            v-if="!product.notes_enabled"
                            class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-800 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-200"
                        >
                            Product notes are not available yet on this environment. Run the latest database migrations, then refresh this page.
                        </div>

                        <div v-for="note in product.notes" :key="note.id" class="rounded-2xl bg-slate-50 p-4 dark:bg-slate-950">
                            <template v-if="editingNoteId === note.id">
                                <textarea
                                    v-model="editingNoteValue"
                                    rows="3"
                                    class="w-full rounded-2xl border border-slate-300 bg-white px-3 py-3 text-sm text-slate-900 placeholder:text-slate-400 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100 dark:placeholder:text-slate-500"
                                />
                                <div class="mt-3 flex gap-2">
                                    <button type="button" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-300" @click="updateNote(note.id)">
                                        Save
                                    </button>
                                    <button type="button" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 transition hover:border-slate-500 dark:border-slate-600 dark:text-slate-200 dark:hover:border-slate-400" @click="cancelEditing">
                                        Cancel
                                    </button>
                                </div>
                            </template>
                            <template v-else>
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ note.author }}</p>
                                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ note.created_at || '-' }}</p>
                                    </div>
                                    <div class="flex gap-2">
                                        <button type="button" class="text-xs font-semibold text-blue-600 hover:text-blue-500 dark:text-blue-300 dark:hover:text-blue-200" @click="startEditing(note)">
                                            Edit
                                        </button>
                                        <button type="button" class="text-xs font-semibold text-rose-600 hover:text-rose-500 dark:text-rose-300 dark:hover:text-rose-200" @click="deleteNote(note.id)">
                                            Delete
                                        </button>
                                    </div>
                                </div>
                                <p class="mt-3 whitespace-pre-line text-sm text-slate-600 dark:text-slate-300">{{ note.note }}</p>
                            </template>
                        </div>

                        <div v-if="!product.notes.length" class="rounded-2xl border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">
                            No notes attached to this product yet.
                        </div>

                        <textarea
                            v-model="noteForm.note"
                            rows="4"
                            placeholder="Attach an internal note for this product"
                            class="w-full rounded-2xl border border-slate-300 bg-white px-3 py-3 text-sm text-slate-900 placeholder:text-slate-400 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100 dark:placeholder:text-slate-500"
                            :disabled="!product.notes_enabled"
                        />
                        <button
                            type="button"
                            class="w-full rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700 disabled:opacity-40 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-300"
                            :disabled="noteForm.processing || !noteForm.note || !product.notes_enabled"
                            @click="submitNote"
                        >
                            Add note
                        </button>
                    </div>
                </div>

                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Metadata</h2>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500 dark:text-slate-400">Meta title</dt>
                            <dd class="text-right text-slate-900 dark:text-slate-100">{{ product.meta_title || '-' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500 dark:text-slate-400">Meta description</dt>
                            <dd class="max-w-[16rem] text-right text-slate-900 dark:text-slate-100">{{ product.meta_description || '-' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500 dark:text-slate-400">Created</dt>
                            <dd class="text-right text-slate-900 dark:text-slate-100">{{ product.created_at || '-' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500 dark:text-slate-400">Updated</dt>
                            <dd class="text-right text-slate-900 dark:text-slate-100">{{ product.updated_at || '-' }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </section>
    </div>
</template>
