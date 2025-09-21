Here is my current vuejs create product page, however I want you to change the design layout to the reference image I have provided here, presently on my vue component, a product belongs to a category, however this logic has change from the backend, a product now belongs to more than one category. I love the ui of this page to look like the reference image :
<script setup>
import { useForm, router } from '@inertiajs/vue3';
import VariantMatrix from '@/Components/Admin/Products/VariantMatrix.vue';
import ProductImageGallery from '@/Components/Admin/Products/ProductImageGallery.vue';
import ProductFaqEditor from '@/Components/Admin/Products/ProductFaqEditor.vue';
import {computed, watch} from 'vue';

const props = defineProps({
    product: Object,          // ProductResource or null
    categories: Array,
    brands: Array,
    variantTypes: Array       // [{id, name, values: [{id, value}]}]
});

const isEdit = computed(() => !!props.product);

const p = computed(() => props.product?.data ?? props.product ?? null)
const productId = computed(() => p.value?.id ?? null)
const form = useForm({
    category_id: p.value?.category_id ?? null,
    brand_id: p.value?.brand_id ?? null,
    name: p.value?.name ?? '',
    slug: p.value?.slug ?? '',
    meta_title: p.value?.meta_title ?? '',
    meta_description: p.value?.meta_description ?? '',
    youtube_video_url: p.value?.youtube_video_url ?? '',
    cash_on_delivery: p.value?.cash_on_delivery ?? false,
    featured: p.value?.featured ?? false,
    weight: p.value?.weight ?? null,
    weight_unit: p.value?.weight_unit ?? null,
    description: p.value?.description ?? '',
    is_active: p.value?.is_active ?? true,
    length: p.value?.length ?? null,
    width: p.value?.width ?? null,
    height: p.value?.height ?? null,
    images: p.value?.images ?? [],
    faqs: p.value?.faqs ?? [],
    variants: p.value?.variants ?? [],
})

function save() {
    if (productId.value) {
        // EDIT
        form.put(route('products.update', { product: productId.value }), {
            preserveScroll: true,
        })
    } else {
        // CREATE
        form.post(route('products.store'), {
            preserveScroll: true,
        })
    }
}
</script>

<template>
    <div class="p-6 space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold">{{ isEdit ? 'Edit product' : 'Create product' }}</h1>
            <div class="flex gap-2">
                <a :href="route('admin.products.index')" class="px-3 py-2 border rounded">Back</a>
                <button @click="save" class="px-4 py-2 bg-blue-600 text-white rounded" :disabled="form.processing">
                    {{ form.processing ? 'Saving...' : 'Save' }}
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white border rounded p-4 space-y-4">
                    <h2 class="font-semibold">Details</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm">Name</label>
                            <input v-model="form.name" class="w-full border rounded px-3 py-2" />
                            <div v-if="form.errors.name" class="text-red-600 text-sm">{{ form.errors.name }}</div>
                        </div>
                        <div>
                            <label class="block text-sm">Slug</label>
                            <input v-model="form.slug" class="w-full border rounded px-3 py-2" placeholder="auto if blank" />
                            <div v-if="form.errors.slug" class="text-red-600 text-sm">{{ form.errors.slug }}</div>
                        </div>
                        <div>
                            <label class="block text-sm">Category</label>
                            <select v-model="form.category_id" class="w-full border rounded px-3 py-2">
                                <option :value="null">None</option>
                                <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm">Brand</label>
                            <select v-model="form.brand_id" class="w-full border rounded px-3 py-2">
                                <option :value="null">None</option>
                                <option v-for="b in brands" :key="b.id" :value="b.id">{{ b.name }}</option>
                            </select>
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm">Description</label>
                            <textarea v-model="form.description" rows="4" class="w-full border rounded px-3 py-2"></textarea>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm">Weight</label>
                            <input v-model.number="form.weight" type="number" step="0.001" class="w-full border rounded px-3 py-2" />
                        </div>
                        <div>
                            <label class="block text-sm">Weight unit</label>
                            <select v-model="form.weight_unit" class="w-full border rounded px-3 py-2">
                                <option :value="null">—</option>
                                <option value="g">g</option>
                                <option value="kg">kg</option>
                                <option value="lb">lb</option>
                                <option value="oz">oz</option>
                            </select>
                        </div>
                        <div class="grid grid-cols-3 gap-2">
                            <div>
                                <label class="block text-sm">L (cm)</label>
                                <input v-model.number="form.length" type="number" step="0.01" class="w-full border rounded px-3 py-2" />
                            </div>
                            <div>
                                <label class="block text-sm">W (cm)</label>
                                <input v-model.number="form.width" type="number" step="0.01" class="w-full border rounded px-3 py-2" />
                            </div>
                            <div>
                                <label class="block text-sm">H (cm)</label>
                                <input v-model.number="form.height" type="number" step="0.01" class="w-full border rounded px-3 py-2" />
                            </div>
                        </div>

                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm">Cash on delivery</label>
                            <input type="checkbox" v-model="form.cash_on_delivery" />
                        </div>
                        <div>
                            <label class="block text-sm">Featured</label>
                            <input type="checkbox" v-model="form.featured" />
                        </div>
                        <div>
                            <label class="block text-sm">Active</label>
                            <input type="checkbox" v-model="form.is_active" />
                        </div>
                    </div>
                </div>

                <div class="bg-white border rounded p-4 space-y-4">
                    <h2 class="font-semibold">Variants</h2>
                    <VariantMatrix v-model="form.variants" :variant-types="variantTypes" />
                    <div v-if="form.errors['variants']" class="text-red-600 text-sm">{{ form.errors['variants'] }}</div>
                </div>

                <div class="bg-white border rounded p-4 space-y-4">
                    <h2 class="font-semibold">FAQs</h2>
                    <ProductFaqEditor v-model="form.faqs" :variants="form.variants" />
                </div>
            </div>

            <div class="space-y-6">
                <div class="bg-white border rounded p-4 space-y-4">
                    <h2 class="font-semibold">Media</h2>
                    <ProductImageGallery v-model="form.images" />
                </div>

                <div class="bg-white border rounded p-4 space-y-4">
                    <h2 class="font-semibold">Meta</h2>
                    <label class="block text-sm">Meta title</label>
                    <input v-model="form.meta_title" class="w-full border rounded px-3 py-2" />
                    <label class="block text-sm">Meta description</label>
                    <textarea v-model="form.meta_description" rows="3" class="w-full border rounded px-3 py-2"></textarea>
                    <label class="block text-sm">YouTube URL</label>
                    <input v-model="form.youtube_video_url" class="w-full border rounded px-3 py-2" />
                </div>
            </div>
        </div>
    </div>
</template>


