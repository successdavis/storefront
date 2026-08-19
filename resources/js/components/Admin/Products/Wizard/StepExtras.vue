<script setup>
import { HelpCircle, Plus, Ruler, Search, Star, Trash2, Youtube } from 'lucide-vue-next'
import { computed, inject } from 'vue'

const wizard = inject('productWizard')
const { form, serverErr } = wizard

const META_TITLE_IDEAL = 60
const META_DESCRIPTION_IDEAL = 160

const metaTitleLength = computed(() => String(form.meta_title || '').length)
const metaDescriptionLength = computed(() => String(form.meta_description || '').length)

function useNameAsMetaTitle() {
    form.meta_title = String(form.name || '').slice(0, 255)
}

function useDescriptionAsMetaDescription() {
    const text = String(form.description || '').replace(/\s+/g, ' ').trim()
    form.meta_description = text.slice(0, META_DESCRIPTION_IDEAL)
}

function addFaq() {
    form.faqs.push({
        id: null,
        product_variant_id: null,
        question: '',
        answer: '',
        is_active: true,
        position: form.faqs.length,
        slug: null,
        locale: null,
    })
}

function removeFaq(index) {
    form.faqs.splice(index, 1)
    form.faqs.forEach((faq, i) => { faq.position = i })
}

function fieldClass(key, extra = '') {
    return [
        'w-full rounded-lg border px-3 py-2.5 text-sm transition',
        'bg-white text-gray-900 dark:bg-gray-950 dark:text-gray-100',
        'focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200 dark:focus:border-blue-400 dark:focus:ring-blue-900',
        key && serverErr(key) ? 'border-rose-400 bg-rose-50 dark:bg-rose-950/30' : 'border-gray-300 dark:border-gray-700',
        extra,
    ].join(' ')
}
</script>

<template>
    <section class="space-y-4">
        <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h2 class="text-lg font-semibold">Finishing touches</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                All of this is optional — skip anything that doesn't apply and come back to it any time.
            </p>
        </div>

        <!-- Shipping & dimensions -->
        <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h3 class="flex items-center gap-2 text-base font-semibold">
                <Ruler class="h-4 w-4 text-gray-400" aria-hidden="true" /> Size & weight
            </h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Used to work out shipping costs.</p>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div class="grid grid-cols-[1fr_auto] gap-2">
                    <div>
                        <label class="block text-sm font-medium" for="extras-weight">Weight</label>
                        <input
                            id="extras-weight"
                            v-model.number="form.weight"
                            type="number" min="0" step="0.001"
                            :class="fieldClass('weight', 'mt-1.5')"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium" for="extras-weight-unit">Unit</label>
                        <select id="extras-weight-unit" v-model="form.weight_unit" :class="fieldClass('weight_unit', 'mt-1.5 w-24')">
                            <option :value="null">—</option>
                            <option value="g">g</option>
                            <option value="kg">kg</option>
                            <option value="lb">lb</option>
                            <option value="oz">oz</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-3 gap-2">
                    <div>
                        <label class="block text-sm font-medium" for="extras-length">L (cm)</label>
                        <input id="extras-length" v-model.number="form.length" type="number" min="0" step="0.01" :class="fieldClass('length', 'mt-1.5')" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium" for="extras-width">W (cm)</label>
                        <input id="extras-width" v-model.number="form.width" type="number" min="0" step="0.01" :class="fieldClass('width', 'mt-1.5')" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium" for="extras-height">H (cm)</label>
                        <input id="extras-height" v-model.number="form.height" type="number" min="0" step="0.01" :class="fieldClass('height', 'mt-1.5')" />
                    </div>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap gap-5 border-t border-gray-100 pt-4 dark:border-gray-800">
                <label class="inline-flex items-center gap-2 text-sm">
                    <input v-model="form.cash_on_delivery" type="checkbox" class="h-4 w-4 rounded border-gray-300" />
                    Allow cash on delivery
                </label>
                <label class="inline-flex items-center gap-2 text-sm">
                    <input v-model="form.featured" type="checkbox" class="h-4 w-4 rounded border-gray-300" />
                    <span class="inline-flex items-center gap-1">
                        <Star class="h-3.5 w-3.5 text-amber-500" aria-hidden="true" /> Feature on the homepage
                    </span>
                </label>
            </div>
        </div>

        <!-- SEO -->
        <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h3 class="flex items-center gap-2 text-base font-semibold">
                <Search class="h-4 w-4 text-gray-400" aria-hidden="true" /> Search engine listing
            </h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                How the product appears on Google. Leave blank and sensible defaults are used.
            </p>

            <div class="mt-4 space-y-4">
                <div>
                    <div class="flex items-center justify-between">
                        <label class="block text-sm font-medium" for="extras-meta-title">Search title</label>
                        <button
                            v-if="form.name && form.meta_title !== form.name"
                            type="button"
                            class="text-xs text-blue-600 underline dark:text-blue-400"
                            @click="useNameAsMetaTitle"
                        >
                            Use product name
                        </button>
                    </div>
                    <input
                        id="extras-meta-title"
                        v-model="form.meta_title"
                        type="text" maxlength="255"
                        :class="fieldClass('meta_title', 'mt-1.5')"
                    />
                    <p class="mt-1 text-xs" :class="metaTitleLength > META_TITLE_IDEAL ? 'text-amber-600 dark:text-amber-400' : 'text-gray-400 dark:text-gray-500'">
                        {{ metaTitleLength }}/{{ META_TITLE_IDEAL }} characters — Google trims anything longer.
                    </p>
                </div>
                <div>
                    <div class="flex items-center justify-between">
                        <label class="block text-sm font-medium" for="extras-meta-description">Search description</label>
                        <button
                            v-if="form.description"
                            type="button"
                            class="text-xs text-blue-600 underline dark:text-blue-400"
                            @click="useDescriptionAsMetaDescription"
                        >
                            Summarise from description
                        </button>
                    </div>
                    <textarea
                        id="extras-meta-description"
                        v-model="form.meta_description"
                        rows="3" maxlength="255"
                        :class="fieldClass('meta_description', 'mt-1.5 resize-y')"
                    />
                    <p class="mt-1 text-xs" :class="metaDescriptionLength > META_DESCRIPTION_IDEAL ? 'text-amber-600 dark:text-amber-400' : 'text-gray-400 dark:text-gray-500'">
                        {{ metaDescriptionLength }}/{{ META_DESCRIPTION_IDEAL }} characters recommended.
                    </p>
                </div>

                <!-- Search preview -->
                <div v-if="form.meta_title || form.name" class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-950">
                    <p class="text-[11px] uppercase tracking-wide text-gray-400 dark:text-gray-500">Preview</p>
                    <p class="mt-1 truncate text-base text-blue-700 dark:text-blue-400">{{ form.meta_title || form.name }}</p>
                    <p class="line-clamp-2 text-sm text-gray-600 dark:text-gray-300">
                        {{ form.meta_description || String(form.description || '').slice(0, META_DESCRIPTION_IDEAL) || 'Your description will appear here.' }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Video -->
        <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h3 class="flex items-center gap-2 text-base font-semibold">
                <Youtube class="h-4 w-4 text-gray-400" aria-hidden="true" /> Product video
            </h3>
            <input
                v-model="form.youtube_video_url"
                type="url"
                placeholder="https://www.youtube.com/watch?v=…"
                :class="fieldClass('youtube_video_url', 'mt-3')"
            />
            <p v-if="serverErr('youtube_video_url')" class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ serverErr('youtube_video_url') }}</p>
        </div>

        <!-- FAQs -->
        <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex items-center justify-between">
                <h3 class="flex items-center gap-2 text-base font-semibold">
                    <HelpCircle class="h-4 w-4 text-gray-400" aria-hidden="true" /> Common questions
                </h3>
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800"
                    @click="addFaq"
                >
                    <Plus class="h-4 w-4" aria-hidden="true" /> Add question
                </button>
            </div>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Answer questions before they're asked — warranty, compatibility, what's included.
            </p>

            <div v-if="form.faqs.length" class="mt-4 space-y-3">
                <div v-for="(faq, i) in form.faqs" :key="i" class="rounded-lg border border-gray-200 p-4 dark:border-gray-800">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 flex-1 space-y-2">
                            <input
                                v-model="faq.question"
                                type="text"
                                :placeholder="`Question ${i + 1}, e.g. Does it come with a warranty?`"
                                :class="fieldClass(null)"
                            />
                            <textarea
                                v-model="faq.answer"
                                rows="2"
                                placeholder="Your answer…"
                                :class="fieldClass(null, 'resize-y')"
                            />
                        </div>
                        <button
                            type="button"
                            class="rounded-md border border-gray-200 p-2 text-gray-400 transition hover:border-rose-300 hover:text-rose-600 dark:border-gray-700 dark:hover:border-rose-700"
                            title="Remove question"
                            aria-label="Remove question"
                            @click="removeFaq(i)"
                        >
                            <Trash2 class="h-4 w-4" aria-hidden="true" />
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
