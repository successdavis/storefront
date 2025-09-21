<script setup>
const props = defineProps({
    modelValue: { type: Array, default: () => [] },
    variants: { type: Array, default: () => [] },
});
const emit = defineEmits(['update:modelValue']);

function addFaq() {
    const next = [...props.modelValue, {
        id: null, product_variant_id: null, question: '', answer: '',
        is_active: true, position: 0, slug: null, locale: null
    }];
    emit('update:modelValue', next);
}
function removeFaq(i) {
    const next = [...props.modelValue];
    next.splice(i, 1);
    emit('update:modelValue', next);
}
</script>

<template>
    <div class="space-y-3">
        <button class="px-3 py-2 border rounded" @click.prevent="addFaq">Add FAQ</button>
        <div class="space-y-2">
            <div v-for="(f, i) in modelValue" :key="i" class="border rounded p-3 space-y-2">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-2">
                    <input v-model="f.question" placeholder="Question" class="border rounded px-2 py-1 md:col-span-2" />
                    <select v-model="f.product_variant_id" class="border rounded px-2 py-1">
                        <option :value="null">All variants</option>
                        <option v-for="v in variants" :key="v.id ?? i" :value="v.id">{{ v.sku || ('Variant ' + (v.id ?? 'new')) }}</option>
                    </select>
                </div>
                <textarea v-model="f.answer" rows="3" class="w-full border rounded px-2 py-1" placeholder="Answer"></textarea>

                <div class="flex flex-wrap gap-3 items-center">
                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" v-model="f.is_active" />
                        <span>Active</span>
                    </label>
                    <input v-model.number="f.position" type="number" class="border rounded px-2 py-1 w-24" placeholder="Position" />
                    <input v-model="f.slug" class="border rounded px-2 py-1 w-48" placeholder="Slug (optional)" />
                    <input v-model="f.locale" class="border rounded px-2 py-1 w-28" placeholder="Locale" />
                    <div class="ml-auto">
                        <button class="text-red-600" @click.prevent="removeFaq(i)">Remove</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
