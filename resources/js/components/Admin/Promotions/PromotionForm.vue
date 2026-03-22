<script setup lang="ts">
import InputError from '@/components/InputError.vue'
import SearchableSelectionPanel from '@/components/Admin/Promotions/SearchableSelectionPanel.vue'
import { Link, useForm } from '@inertiajs/vue3'
import { computed } from 'vue'

interface Option {
    id: number
    label: string
    meta?: string | null
}

interface PromotionPayload {
    id?: number
    name?: string | null
    description?: string | null
    code?: string | null
    type?: string | null
    application_method?: string | null
    value?: number | null
    min_order_amount?: number | null
    usage_limit?: number | null
    usage_limit_per_user?: number | null
    starts_at?: string | null
    ends_at?: string | null
    customer_scope?: string | null
    priority?: number | null
    is_active?: boolean
    category_ids?: number[]
    product_ids?: number[]
    selected_customer_ids?: number[]
}

const props = defineProps<{
    mode: 'create' | 'edit'
    formType: 'discount' | 'coupon'
    promotion: PromotionPayload | null
    categories: Option[]
    products: Option[]
    customers: Option[]
}>()

const isCoupon = computed(() => props.formType === 'coupon')
const isEdit = computed(() => props.mode === 'edit')

const form = useForm({
    name: props.promotion?.name ?? '',
    description: props.promotion?.description ?? '',
    code: props.promotion?.code ?? '',
    type: props.promotion?.type ?? 'percentage',
    application_method: props.promotion?.application_method ?? (isCoupon.value ? 'order_total' : 'line_item'),
    value: props.promotion?.value ?? null,
    min_order_amount: props.promotion?.min_order_amount ?? null,
    usage_limit: props.promotion?.usage_limit ?? null,
    usage_limit_per_user: props.promotion?.usage_limit_per_user ?? null,
    starts_at: props.promotion?.starts_at ?? '',
    ends_at: props.promotion?.ends_at ?? '',
    customer_scope: props.promotion?.customer_scope ?? 'all',
    priority: props.promotion?.priority ?? 0,
    is_active: props.promotion?.is_active ?? true,
    category_ids: props.promotion?.category_ids ?? [],
    product_ids: props.promotion?.product_ids ?? [],
    selected_customer_ids: props.promotion?.selected_customer_ids ?? [],
})

const pageTitle = computed(() => {
    if (isCoupon.value) {
        return isEdit.value ? 'Edit Coupon' : 'Create Coupon'
    }

    return isEdit.value ? 'Edit Discount' : 'Create Discount'
})

const helperCopy = computed(() => {
    return isCoupon.value
        ? 'Coupons remain order-level promotions. Use scope selectors to decide whether the code applies globally, by category, or by product.'
        : 'Leave category and product scope empty for a system-wide discount. Product selections still override category selections at pricing time.'
})

const showOrderControls = computed(() => isCoupon.value || form.application_method === 'order_total')
const showPriority = computed(() => !isCoupon.value && form.application_method === 'line_item')
const showValue = computed(() => form.type !== 'free_shipping')
const isLineItem = computed(() => !isCoupon.value && form.application_method === 'line_item')

function submit() {
    const routeName = isCoupon.value
        ? (isEdit.value ? 'admin.coupons.update' : 'admin.coupons.store')
        : (isEdit.value ? 'admin.discounts.update' : 'admin.discounts.store')
    const routeParam = props.promotion?.id

    if (isEdit.value && routeParam) {
        form.put(route(routeName, routeParam), { preserveScroll: true })
        return
    }

    form.post(route(routeName), { preserveScroll: true })
}
</script>

<template>
    <div class="space-y-6 px-5 py-4">
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold text-slate-900 dark:text-slate-100">{{ pageTitle }}</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500 dark:text-slate-400">
                        {{ helperCopy }}
                    </p>
                </div>

                <Link
                    :href="isCoupon ? route('admin.coupons.index') : route('admin.discounts.index')"
                    class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-500 dark:border-slate-600 dark:text-slate-200 dark:hover:border-slate-400"
                >
                    Back to list
                </Link>
            </div>
        </section>

        <form class="space-y-6" @submit.prevent="submit">
            <section class="grid gap-6 xl:grid-cols-[1.25fr_0.9fr]">
                <div class="space-y-6">
                    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                        <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Promotion Setup</h2>

                        <div class="mt-5 grid gap-5 md:grid-cols-2">
                            <div class="md:col-span-2">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Name</label>
                                <input v-model="form.name" type="text" class="mt-2 h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 placeholder:text-slate-400 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100 dark:placeholder:text-slate-500">
                                <InputError :message="form.errors.name" class="mt-2" />
                            </div>

                            <div class="md:col-span-2">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Description</label>
                                <textarea v-model="form.description" rows="3" class="mt-2 w-full rounded-2xl border border-slate-300 bg-white px-3 py-3 text-sm text-slate-900 placeholder:text-slate-400 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100 dark:placeholder:text-slate-500" />
                                <InputError :message="form.errors.description" class="mt-2" />
                            </div>

                            <div v-if="isCoupon">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Coupon code</label>
                                <input v-model="form.code" type="text" class="mt-2 h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm uppercase tracking-[0.2em] text-slate-900 placeholder:text-slate-400 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100 dark:placeholder:text-slate-500">
                                <InputError :message="form.errors.code" class="mt-2" />
                            </div>

                            <div>
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Discount type</label>
                                <select v-model="form.type" class="mt-2 h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                                    <option value="percentage">Percentage</option>
                                    <option value="fixed_amount">Fixed amount</option>
                                    <option value="free_shipping">Free shipping</option>
                                </select>
                                <InputError :message="form.errors.type" class="mt-2" />
                            </div>

                            <div v-if="!isCoupon">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Pricing behavior</label>
                                <select v-model="form.application_method" class="mt-2 h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                                    <option value="line_item">Product price markdown</option>
                                    <option value="order_total">Order total promotion</option>
                                </select>
                                <InputError :message="form.errors.application_method" class="mt-2" />
                            </div>

                            <div v-if="showValue">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-200">{{ form.type === 'percentage' ? 'Value (%)' : 'Value (NGN)' }}</label>
                                <input v-model="form.value" type="number" min="0.01" step="0.01" class="mt-2 h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                                <InputError :message="form.errors.value" class="mt-2" />
                            </div>

                            <div v-if="showPriority">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Priority</label>
                                <input v-model="form.priority" type="number" min="0" step="1" class="mt-2 h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                                <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Used only when multiple line-item discounts share the same scope specificity.</p>
                                <InputError :message="form.errors.priority" class="mt-2" />
                            </div>

                            <div>
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Status</label>
                                <label class="mt-2 flex h-11 items-center gap-3 rounded-xl border border-slate-300 px-4 text-sm text-slate-700 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-200">
                                    <input v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-slate-300 dark:border-slate-600 dark:bg-slate-900">
                                    Active
                                </label>
                                <InputError :message="form.errors.is_active" class="mt-2" />
                            </div>
                        </div>
                    </div>

                    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                        <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Schedule and Rules</h2>

                        <div class="mt-5 grid gap-5 md:grid-cols-2">
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
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Customer scope</label>
                                <select v-model="form.customer_scope" class="mt-2 h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                                    <option value="all">All customers</option>
                                    <option value="new_customers">New customers only</option>
                                    <option value="selected_customers">Selected customers</option>
                                </select>
                                <InputError :message="form.errors.customer_scope" class="mt-2" />
                            </div>

                            <div v-if="showOrderControls">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Minimum order amount (NGN)</label>
                                <input v-model="form.min_order_amount" type="number" min="0" step="0.01" class="mt-2 h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                                <InputError :message="form.errors.min_order_amount" class="mt-2" />
                            </div>

                            <div v-if="showOrderControls">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Global usage limit</label>
                                <input v-model="form.usage_limit" type="number" min="1" step="1" class="mt-2 h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                                <InputError :message="form.errors.usage_limit" class="mt-2" />
                            </div>

                            <div v-if="showOrderControls">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Per-user usage limit</label>
                                <input v-model="form.usage_limit_per_user" type="number" min="1" step="1" class="mt-2 h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 dark:border-slate-600 dark:bg-slate-950 dark:text-slate-100">
                                <InputError :message="form.errors.usage_limit_per_user" class="mt-2" />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="space-y-6">
                    <SearchableSelectionPanel
                        v-model="form.category_ids"
                        title="Category scope"
                        description="Pick one or more categories. Leave empty to keep this promotion global or product-specific."
                        :options="categories"
                        search-placeholder="Search categories"
                    />
                    <InputError :message="form.errors.category_ids" class="mt-2" />

                    <SearchableSelectionPanel
                        v-model="form.product_ids"
                        title="Product scope"
                        description="Choose products for a targeted promotion. Leave empty to apply globally or by category."
                        :options="products"
                        search-placeholder="Search products"
                    />
                    <InputError :message="form.errors.product_ids" class="mt-2" />

                    <SearchableSelectionPanel
                        v-if="form.customer_scope === 'selected_customers'"
                        v-model="form.selected_customer_ids"
                        title="Selected customers"
                        description="These customers will be eligible to use this promotion."
                        :options="customers"
                        search-placeholder="Search customers"
                    />
                    <InputError :message="form.errors.selected_customer_ids" class="mt-2" />

                    <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                        <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">How this will apply</h2>
                        <dl class="mt-4 space-y-3 text-sm text-slate-600 dark:text-slate-300">
                            <div class="flex justify-between gap-4">
                                <dt>Mode</dt>
                                <dd class="font-semibold text-slate-900 dark:text-slate-100">
                                    {{ isCoupon ? 'Coupon code' : (isLineItem ? 'Product price markdown' : 'Order total promotion') }}
                                </dd>
                            </div>
                            <div class="flex justify-between gap-4">
                                <dt>Scope</dt>
                                <dd class="font-semibold text-slate-900 dark:text-slate-100">
                                    {{ form.product_ids.length ? 'Product' : (form.category_ids.length ? 'Category' : 'Global') }}
                                </dd>
                            </div>
                            <div class="flex justify-between gap-4">
                                <dt>Status</dt>
                                <dd class="font-semibold text-slate-900 dark:text-slate-100">{{ form.is_active ? 'Active' : 'Inactive' }}</dd>
                            </div>
                        </dl>
                    </section>
                </div>
            </section>

            <div class="flex flex-wrap justify-end gap-3">
                <Link
                    :href="isCoupon ? route('admin.coupons.index') : route('admin.discounts.index')"
                    class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-500 dark:border-slate-600 dark:text-slate-200 dark:hover:border-slate-400"
                >
                    Cancel
                </Link>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-700 dark:bg-slate-100 dark:text-slate-900 dark:hover:bg-slate-300 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    {{ form.processing ? 'Saving...' : (isEdit ? 'Update promotion' : 'Create promotion') }}
                </button>
            </div>
        </form>
    </div>
</template>
