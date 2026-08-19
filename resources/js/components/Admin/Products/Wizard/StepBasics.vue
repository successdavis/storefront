<script setup>
import CategoryTree from '@/components/CategoryTree.vue'
import { inject, computed } from 'vue'

const wizard = inject('productWizard')
const { form, brands, categories, serverErr } = wizard

const descriptionLength = computed(() => String(form.description || '').length)

function inputClass(key, extra = '') {
    return [
        'w-full rounded-lg border px-3 py-2.5 text-sm transition',
        'bg-white text-gray-900 dark:bg-gray-950 dark:text-gray-100',
        'focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200 dark:focus:border-blue-400 dark:focus:ring-blue-900',
        serverErr(key) ? 'border-rose-400 bg-rose-50 dark:bg-rose-950/30' : 'border-gray-300 dark:border-gray-700',
        extra,
    ].join(' ')
}
</script>

<template>
    <section class="space-y-4">
        <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h2 class="text-lg font-semibold">Let's start with the basics</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                A clear name, brand and category are what customers search and filter by — get these right and the rest is easy.
            </p>

            <div class="mt-6 space-y-5">
                <div>
                    <label class="block text-sm font-medium" for="wizard-name">
                        Product name <span class="text-rose-500">*</span>
                    </label>
                    <input
                        id="wizard-name"
                        v-model="form.name"
                        type="text"
                        placeholder="e.g. HP LaserJet Pro M404dn Printer"
                        autocomplete="off"
                        :class="inputClass('name', 'mt-1.5 text-base')"
                    />
                    <p v-if="serverErr('name')" class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ serverErr('name') }}</p>
                    <p v-else class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                        Tip: include the brand and model so it's easy to find — customers see this exact name.
                    </p>
                </div>

                <div>
                    <label class="block text-sm font-medium" for="wizard-brand">
                        Brand <span class="text-rose-500">*</span>
                    </label>
                    <select id="wizard-brand" v-model="form.brand_id" :class="inputClass('brand_id', 'mt-1.5')">
                        <option :value="null" disabled>Choose a brand…</option>
                        <option v-for="brand in brands" :key="brand.id" :value="brand.id">{{ brand.name }}</option>
                    </select>
                    <p v-if="serverErr('brand_id')" class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ serverErr('brand_id') }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium" for="wizard-description">
                        Description <span class="text-rose-500">*</span>
                    </label>
                    <textarea
                        id="wizard-description"
                        v-model="form.description"
                        rows="6"
                        placeholder="What is it? What's it great at? Mention key specs, what's in the box, and who it's for."
                        :class="inputClass('description', 'mt-1.5 resize-y')"
                    />
                    <div class="mt-1 flex items-center justify-between text-xs">
                        <p v-if="serverErr('description')" class="text-rose-600 dark:text-rose-400">{{ serverErr('description') }}</p>
                        <p v-else class="text-gray-400 dark:text-gray-500">A couple of short paragraphs works best.</p>
                        <span class="text-gray-400 dark:text-gray-500">{{ descriptionLength }} characters</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h3 class="text-base font-semibold">
                Where should it live? <span class="text-rose-500">*</span>
            </h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Pick every category that fits — the product appears on each category page you tick.
            </p>
            <div class="mt-4 rounded-lg border border-gray-200 p-3 dark:border-gray-800">
                <CategoryTree v-model="form.category_ids" :categories="categories" :expand-all="false" />
            </div>
            <p v-if="serverErr('category_ids')" class="mt-2 text-xs text-rose-600 dark:text-rose-400">{{ serverErr('category_ids') }}</p>
            <p v-else-if="form.category_ids.length" class="mt-2 text-xs text-emerald-600 dark:text-emerald-400">
                {{ form.category_ids.length }} categor{{ form.category_ids.length === 1 ? 'y' : 'ies' }} selected
            </p>
        </div>
    </section>
</template>
