<script setup>
import { CheckCircle2, CircleAlert, Eye, EyeOff, Pencil } from 'lucide-vue-next'
import { computed, inject } from 'vue'

const props = defineProps({
    steps: { type: Array, required: true },
    validateStep: { type: Function, required: true },
})

const wizard = inject('productWizard')
const { form, mode, brands, categories, variantTypes, activeVariants, goToStep } = wizard

const brandName = computed(() => brands.value.find(brand => brand.id === form.brand_id)?.name || '—')

function flattenCategories(nodes, out = []) {
    for (const node of nodes || []) {
        out.push({ id: String(node.id), name: node.name })
        flattenCategories(node.children, out)
    }
    return out
}

const categoryNames = computed(() => {
    const all = flattenCategories(categories.value)
    return (form.category_ids || [])
        .map(id => all.find(category => category.id === String(id))?.name)
        .filter(Boolean)
})

const valueNameMap = computed(() => {
    const map = new Map()
    for (const type of variantTypes.value || []) {
        for (const value of type.values || []) {
            map.set(String(value.id), value.value)
        }
    }
    return map
})

function variantLabel(row) {
    const names = (row.value_ids || []).map(id => valueNameMap.value.get(String(id))).filter(Boolean)
    return names.length ? names.join(' / ') : 'Default'
}

const totalStock = computed(() => activeVariants.value.reduce((sum, row) => sum + (parseInt(row.quantity, 10) || 0), 0))

const money = value => (value === null || value === '' || value === undefined || Number.isNaN(Number(value))
    ? '—'
    : Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }))

const sections = computed(() => [
    {
        step: 0,
        title: 'The basics',
        ok: props.validateStep(0).length === 0,
        rows: [
            ['Name', form.name || '—'],
            ['Brand', brandName.value],
            ['Categories', categoryNames.value.join(', ') || '—'],
            ['Description', String(form.description || '').slice(0, 160) + (String(form.description || '').length > 160 ? '…' : '') || '—'],
        ],
    },
    {
        step: 3,
        title: 'Details & SEO',
        ok: props.validateStep(3).length === 0,
        rows: [
            ['Search title', form.meta_title || 'Default (product name)'],
            ['Search description', form.meta_description ? `${String(form.meta_description).slice(0, 80)}…` : 'Default'],
            ['Video', form.youtube_video_url || 'None'],
            ['FAQs', form.faqs.length ? `${form.faqs.length} question(s)` : 'None'],
            ['Weight / size', [form.weight ? `${form.weight}${form.weight_unit || ''}` : null, form.length && form.width && form.height ? `${form.length}×${form.width}×${form.height} cm` : null].filter(Boolean).join(' · ') || 'Not set'],
            ['Flags', [form.featured ? 'Featured' : null, form.cash_on_delivery ? 'Cash on delivery' : null].filter(Boolean).join(' · ') || 'None'],
        ],
    },
])
</script>

<template>
    <section class="space-y-4">
        <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h2 class="text-lg font-semibold">One last look</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Check everything over, choose whether to publish, and you're done.
            </p>
        </div>

        <!-- Basics + extras summaries -->
        <div v-for="section in sections" :key="section.step" class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex items-center justify-between">
                <h3 class="flex items-center gap-2 text-base font-semibold">
                    <CheckCircle2 v-if="section.ok" class="h-4 w-4 text-emerald-500" aria-hidden="true" />
                    <CircleAlert v-else class="h-4 w-4 text-amber-500" aria-hidden="true" />
                    {{ section.title }}
                </h3>
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 text-sm font-medium text-blue-600 hover:underline dark:text-blue-400"
                    @click="goToStep(section.step)"
                >
                    <Pencil class="h-3.5 w-3.5" aria-hidden="true" /> Edit
                </button>
            </div>
            <dl class="mt-3 space-y-1.5 text-sm">
                <div v-for="[label, value] in section.rows" :key="label" class="grid gap-1 sm:grid-cols-[160px_1fr]">
                    <dt class="text-gray-500 dark:text-gray-400">{{ label }}</dt>
                    <dd class="min-w-0 break-words font-medium text-gray-900 dark:text-gray-100">{{ value }}</dd>
                </div>
            </dl>
        </div>

        <!-- Pricing summary -->
        <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex items-center justify-between">
                <h3 class="flex items-center gap-2 text-base font-semibold">
                    <CheckCircle2 v-if="validateStep(1).length === 0" class="h-4 w-4 text-emerald-500" aria-hidden="true" />
                    <CircleAlert v-else class="h-4 w-4 text-amber-500" aria-hidden="true" />
                    Pricing & stock
                    <span class="text-xs font-normal text-gray-400">({{ mode === 'simple' ? 'simple product' : 'with options' }})</span>
                </h3>
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 text-sm font-medium text-blue-600 hover:underline dark:text-blue-400"
                    @click="goToStep(1)"
                >
                    <Pencil class="h-3.5 w-3.5" aria-hidden="true" /> Edit
                </button>
            </div>

            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-left text-xs uppercase tracking-wide text-gray-500 dark:border-gray-800 dark:text-gray-400">
                            <th class="py-2 pr-4">Variant</th>
                            <th class="py-2 pr-4">SKU</th>
                            <th class="py-2 pr-4">Price</th>
                            <th class="py-2 pr-4">Cost</th>
                            <th class="py-2 pr-4">Stock</th>
                            <th class="py-2">Fulfilment</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-for="(row, i) in activeVariants" :key="i">
                            <td class="py-2 pr-4 font-medium">{{ variantLabel(row) }}</td>
                            <td class="py-2 pr-4 text-gray-500 dark:text-gray-400">{{ row.sku || 'auto' }}</td>
                            <td class="py-2 pr-4">{{ money(row.regular_price) }}</td>
                            <td class="py-2 pr-4 text-gray-500 dark:text-gray-400">{{ money(row.last_purchase_price) }}</td>
                            <td class="py-2 pr-4">{{ row.fulfillment_type === 'dropshipping' ? '—' : (parseInt(row.quantity, 10) || 0) }}</td>
                            <td class="py-2 capitalize text-gray-500 dark:text-gray-400">{{ row.fulfillment_type || 'stocked' }}</td>
                        </tr>
                        <tr v-if="!activeVariants.length">
                            <td colspan="6" class="py-3 text-center text-gray-500 dark:text-gray-400">No variants yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-if="totalStock" class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ totalStock }} unit(s) will be recorded as opening stock.</p>
        </div>

        <!-- Photos summary -->
        <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex items-center justify-between">
                <h3 class="flex items-center gap-2 text-base font-semibold">
                    <CheckCircle2 v-if="form.images.length" class="h-4 w-4 text-emerald-500" aria-hidden="true" />
                    <CircleAlert v-else class="h-4 w-4 text-amber-500" aria-hidden="true" />
                    Photos
                </h3>
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 text-sm font-medium text-blue-600 hover:underline dark:text-blue-400"
                    @click="goToStep(2)"
                >
                    <Pencil class="h-3.5 w-3.5" aria-hidden="true" /> Edit
                </button>
            </div>
            <div v-if="form.images.length" class="mt-3 flex flex-wrap gap-2">
                <img
                    v-for="(img, i) in form.images"
                    :key="i"
                    :src="img._preview"
                    :alt="img.alt || `Photo ${i + 1}`"
                    class="h-16 w-16 rounded-lg border object-cover dark:border-gray-700"
                    :class="img.is_primary ? 'ring-2 ring-blue-500' : ''"
                />
            </div>
            <p v-else class="mt-2 text-sm text-amber-600 dark:text-amber-400">
                No photos yet — the product will show a placeholder until you add some.
            </p>
        </div>

        <!-- Publish choice -->
        <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h3 class="text-base font-semibold">Ready to go live?</h3>
            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                <button
                    type="button"
                    class="flex items-start gap-3 rounded-xl border-2 p-4 text-left transition"
                    :class="form.is_active
                        ? 'border-emerald-500 bg-emerald-50/60 dark:border-emerald-400 dark:bg-emerald-950/30'
                        : 'border-gray-200 hover:border-gray-300 dark:border-gray-800 dark:hover:border-gray-700'"
                    :aria-pressed="form.is_active"
                    @click="form.is_active = true"
                >
                    <Eye class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600 dark:text-emerald-400" aria-hidden="true" />
                    <span>
                        <span class="block text-sm font-semibold">Publish immediately</span>
                        <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">Customers can see and buy it right away.</span>
                    </span>
                </button>
                <button
                    type="button"
                    class="flex items-start gap-3 rounded-xl border-2 p-4 text-left transition"
                    :class="!form.is_active
                        ? 'border-gray-500 bg-gray-50 dark:border-gray-400 dark:bg-gray-800/60'
                        : 'border-gray-200 hover:border-gray-300 dark:border-gray-800 dark:hover:border-gray-700'"
                    :aria-pressed="!form.is_active"
                    @click="form.is_active = false"
                >
                    <EyeOff class="mt-0.5 h-5 w-5 shrink-0 text-gray-500 dark:text-gray-400" aria-hidden="true" />
                    <span>
                        <span class="block text-sm font-semibold">Save as draft</span>
                        <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">Hidden from the store until you publish it.</span>
                    </span>
                </button>
            </div>
        </div>
    </section>
</template>
