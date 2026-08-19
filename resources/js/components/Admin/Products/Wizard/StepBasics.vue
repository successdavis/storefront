<script setup>
import CategoryTree from '@/components/CategoryTree.vue'
import axios from 'axios'
import { Plus } from 'lucide-vue-next'
import { computed, inject, ref } from 'vue'

const wizard = inject('productWizard')
const { form, brands, categories, serverErr, applyQuickBrand, applyQuickCategory } = wizard

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

/* ---- Inline "new brand" ---- */
const newBrandOpen = ref(false)
const newBrandName = ref('')
const newBrandSaving = ref(false)
const newBrandError = ref('')

function toggleNewBrand() {
    newBrandOpen.value = !newBrandOpen.value
    newBrandError.value = ''
    if (!newBrandOpen.value) newBrandName.value = ''
}

async function createBrand() {
    const name = newBrandName.value.trim()
    if (!name || newBrandSaving.value) return

    newBrandSaving.value = true
    newBrandError.value = ''
    try {
        const { data } = await axios.post('/admin/brands/quick', { name })
        applyQuickBrand(data.brands, data.brand.id)
        newBrandOpen.value = false
        newBrandName.value = ''
    } catch (error) {
        newBrandError.value = error.response?.data?.errors?.name?.[0]
            || "Couldn't create the brand — please try again."
    } finally {
        newBrandSaving.value = false
    }
}

/* ---- Inline "new category" ---- */
const newCategoryOpen = ref(false)
const newCategoryName = ref('')
const newCategoryParentId = ref(null)
const newCategorySaving = ref(false)
const newCategoryError = ref('')

const parentOptions = computed(() => {
    const out = []
    const walk = (nodes, depth) => {
        for (const node of nodes || []) {
            out.push({ id: node.id, label: `${'— '.repeat(depth)}${node.name}` })
            walk(node.children, depth + 1)
        }
    }
    walk(categories.value, 0)
    return out
})

function toggleNewCategory() {
    newCategoryOpen.value = !newCategoryOpen.value
    newCategoryError.value = ''
    if (!newCategoryOpen.value) {
        newCategoryName.value = ''
        newCategoryParentId.value = null
    }
}

async function createCategory() {
    const name = newCategoryName.value.trim()
    if (!name || newCategorySaving.value) return

    newCategorySaving.value = true
    newCategoryError.value = ''
    try {
        const { data } = await axios.post('/admin/categories/quick', {
            name,
            parent_id: newCategoryParentId.value,
        })
        applyQuickCategory(data.categories, data.category.id)
        newCategoryOpen.value = false
        newCategoryName.value = ''
        newCategoryParentId.value = null
    } catch (error) {
        newCategoryError.value = error.response?.data?.errors?.name?.[0]
            || error.response?.data?.errors?.parent_id?.[0]
            || "Couldn't create the category — please try again."
    } finally {
        newCategorySaving.value = false
    }
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
                    <div class="flex items-center justify-between">
                        <label class="block text-sm font-medium" for="wizard-brand">
                            Brand <span class="text-rose-500">*</span>
                        </label>
                        <button
                            type="button"
                            class="inline-flex items-center gap-1 text-xs font-medium text-blue-600 hover:underline dark:text-blue-400"
                            @click="toggleNewBrand"
                        >
                            <Plus class="h-3.5 w-3.5" aria-hidden="true" />
                            {{ newBrandOpen ? 'Cancel' : 'New brand' }}
                        </button>
                    </div>
                    <select id="wizard-brand" v-model="form.brand_id" :class="inputClass('brand_id', 'mt-1.5')">
                        <option :value="null" disabled>Choose a brand…</option>
                        <option v-for="brand in brands" :key="brand.id" :value="brand.id">{{ brand.name }}</option>
                    </select>
                    <p v-if="serverErr('brand_id')" class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ serverErr('brand_id') }}</p>

                    <div
                        v-if="newBrandOpen"
                        class="mt-2 rounded-lg border border-blue-200 bg-blue-50/50 p-3 dark:border-blue-900 dark:bg-blue-950/20"
                    >
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300" for="wizard-new-brand">
                            Brand name
                        </label>
                        <div class="mt-1.5 flex gap-2">
                            <input
                                id="wizard-new-brand"
                                v-model="newBrandName"
                                type="text"
                                placeholder="e.g. Logitech"
                                autocomplete="off"
                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100"
                                @keydown.enter.prevent="createBrand"
                            />
                            <button
                                type="button"
                                class="shrink-0 rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-blue-500 dark:hover:bg-blue-400"
                                :disabled="!newBrandName.trim() || newBrandSaving"
                                @click="createBrand"
                            >
                                {{ newBrandSaving ? 'Adding…' : 'Add' }}
                            </button>
                        </div>
                        <p v-if="newBrandError" class="mt-1.5 text-xs text-rose-600 dark:text-rose-400">{{ newBrandError }}</p>
                        <p v-else class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">
                            Created instantly and selected for this product. Logo and details can be added later under Brands.
                        </p>
                    </div>
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
            <div class="flex items-center justify-between">
                <h3 class="text-base font-semibold">
                    Where should it live? <span class="text-rose-500">*</span>
                </h3>
                <button
                    type="button"
                    class="inline-flex items-center gap-1 text-xs font-medium text-blue-600 hover:underline dark:text-blue-400"
                    @click="toggleNewCategory"
                >
                    <Plus class="h-3.5 w-3.5" aria-hidden="true" />
                    {{ newCategoryOpen ? 'Cancel' : 'New category' }}
                </button>
            </div>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Pick every category that fits — the product appears on each category page you tick.
            </p>

            <div
                v-if="newCategoryOpen"
                class="mt-3 rounded-lg border border-blue-200 bg-blue-50/50 p-3 dark:border-blue-900 dark:bg-blue-950/20"
            >
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300" for="wizard-new-category">
                            Category name
                        </label>
                        <input
                            id="wizard-new-category"
                            v-model="newCategoryName"
                            type="text"
                            placeholder="e.g. Gaming Consoles"
                            autocomplete="off"
                            class="mt-1.5 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100"
                            @keydown.enter.prevent="createCategory"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300" for="wizard-new-category-parent">
                            Inside <span class="font-normal text-gray-400">(optional)</span>
                        </label>
                        <select
                            id="wizard-new-category-parent"
                            v-model="newCategoryParentId"
                            class="mt-1.5 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100"
                        >
                            <option :value="null">Top level</option>
                            <option v-for="option in parentOptions" :key="option.id" :value="option.id">
                                {{ option.label }}
                            </option>
                        </select>
                    </div>
                </div>
                <div class="mt-3 flex items-center justify-between gap-3">
                    <p v-if="newCategoryError" class="text-xs text-rose-600 dark:text-rose-400">{{ newCategoryError }}</p>
                    <p v-else class="text-xs text-gray-500 dark:text-gray-400">
                        Created instantly and ticked for this product.
                    </p>
                    <button
                        type="button"
                        class="shrink-0 rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-blue-500 dark:hover:bg-blue-400"
                        :disabled="!newCategoryName.trim() || newCategorySaving"
                        @click="createCategory"
                    >
                        {{ newCategorySaving ? 'Adding…' : 'Add category' }}
                    </button>
                </div>
            </div>

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
