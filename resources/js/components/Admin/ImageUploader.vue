<template>
    <div class="border rounded p-4">
        <input type="file" multiple @change="onFiles" />
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-3">
            <div v-for="img in images" :key="img.id" class="border rounded p-2">
                <img :src="src(img.path)" class="w-full h-32 object-cover" />
                <input v-model="img.alt" @blur="update(img)" placeholder="Alt text" class="mt-2 w-full border rounded px-2 py-1" />
                <div class="flex justify-between items-center mt-2 text-xs">
                    <label class="inline-flex items-center gap-1">
                        <input type="checkbox" v-model="img.is_primary" @change="update(img)"> Primary
                    </label>
                    <button @click="destroy(img)" class="px-2 py-1 border rounded">Delete</button>
                </div>
            </div>
        </div>
    </div>
</template>
<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
const props = defineProps({ productId: Number, initial: Array })
const images = ref(props.initial || [])


function src(path){ return `/storage/${path}` }
function onFiles(e){
    const form = new FormData()
    for (const f of e.target.files) form.append('images[]', f)
    router.post(route('admin.products.images.store', props.productId), form, {
        onSuccess: ({props}) => { /* inertia partials not used here */ },
        preserveScroll: true,
        preserveState: true,
        onFinish: async () => {
            const res = await fetch(route('admin.products.images.index', props.productId))
            images.value = await res.json()
        }
    })
}
async function update(img){
    await fetch(route('admin.products.images.update', { product: props.productId, image: img.id }), {
        method: 'PUT', headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
        body: JSON.stringify({ alt: img.alt, is_primary: img.is_primary, sort_order: img.sort_order || 0 })
    })
}
async function destroy(img){
    await fetch(route('admin.products.images.destroy', { product: props.productId, image: img.id }), {
        method: 'DELETE', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
    })
    images.value = images.value.filter(i => i.id !== img.id)
}
</script>
