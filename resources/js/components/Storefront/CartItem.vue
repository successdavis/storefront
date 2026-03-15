<script setup>
import { Link, router } from '@inertiajs/vue3'
import { ref, watch } from 'vue'

const props = defineProps({
    item: {
        type: Object,
        required: true,
    },
})

const quantity = ref(Number(props.item.quantity || 1))

watch(
    () => props.item.quantity,
    (value) => {
        quantity.value = Number(value || 1)
    },
)

const formatter = new Intl.NumberFormat('en-NG', {
    style: 'currency',
    currency: 'NGN',
})

function money(value) {
    return formatter.format(Number(value || 0))
}

function updateQuantity() {
    router.patch(
        route('store.cart.update', props.item.id),
        { quantity: quantity.value },
        { preserveScroll: true },
    )
}

function increment() {
    quantity.value += 1
    updateQuantity()
}

function decrement() {
    if (quantity.value <= 1) {
        removeItem()
        return
    }

    quantity.value -= 1
    updateQuantity()
}

function removeItem() {
    router.delete(route('store.cart.remove', props.item.id), {
        preserveScroll: true,
    })
}
</script>

<template>
    <article class="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:flex-row sm:items-center">
        <img
            v-if="item.product?.image"
            :src="item.product.image"
            :alt="item.product?.name"
            class="h-24 w-24 rounded-xl object-cover"
            loading="lazy"
        >
        <div v-else class="flex h-24 w-24 items-center justify-center rounded-xl bg-slate-100 text-xs text-slate-500">
            No image
        </div>

        <div class="flex-1">
            <Link :href="route('store.product', item.product.slug)" class="text-sm font-semibold text-slate-900 hover:text-slate-600">
                {{ item.product.name }}
            </Link>
            <p class="mt-1 text-xs text-slate-500">{{ item.variant.label }}</p>
            <p class="mt-1 text-sm font-semibold text-slate-800">{{ money(item.variant.price.current) }}</p>
        </div>

        <div class="flex items-center gap-2">
            <button
                type="button"
                class="h-9 w-9 rounded-lg border border-slate-300 text-slate-700 transition hover:border-slate-500"
                @click="decrement"
            >
                -
            </button>
            <input
                v-model.number="quantity"
                type="number"
                min="1"
                class="h-9 w-14 rounded-lg border border-slate-300 text-center text-sm"
                @change="updateQuantity"
            >
            <button
                type="button"
                class="h-9 w-9 rounded-lg border border-slate-300 text-slate-700 transition hover:border-slate-500"
                @click="increment"
            >
                +
            </button>
        </div>

        <div class="space-y-2 text-right sm:min-w-28">
            <p class="text-sm font-bold text-slate-900">{{ money(item.subtotal) }}</p>
            <button
                type="button"
                class="text-xs font-medium text-rose-600 transition hover:text-rose-700"
                @click="removeItem"
            >
                Remove
            </button>
        </div>
    </article>
</template>
