<script setup>
import { Link, router } from '@inertiajs/vue3'
import { ref, watch } from 'vue'
import { route } from 'ziggy-js';

const props = defineProps({
    item: {
        type: Object,
        required: true,
    },
})

const quantity = ref(Number(props.item.quantity || 1))
const errorMessage = ref(null)

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
    errorMessage.value = null

    router.patch(
        route('store.cart.update', { variant: props.item.variant_id }),
        { quantity: quantity.value },
        {
            preserveScroll: true,
            onError: (errors) => {
                if (errors.quantity) {
                    errorMessage.value = errors.quantity
                } else {
                    errorMessage.value = 'Unable to update quantity.'
                }
            }
        }
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
    router.delete(
        route('store.cart.remove', { variant: props.item.variant_id }),
        { preserveScroll: true }
    )
}

function saveForLater() {
    router.post(
        route('store.cart.save-for-later', { variant: props.item.variant_id }),
        {},
        { preserveScroll: true },
    )
}
</script>

<template>
    <article
        :class="[
            'flex flex-col gap-4 rounded-2xl border bg-white p-4 shadow-sm sm:flex-row sm:items-center',
            item.availability?.is_available === false ? 'border-rose-200 bg-rose-50/40' : 'border-slate-200',
        ]"
    >
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
            <Link
                v-if="item.product?.slug"
                :href="route('store.product', item.product.slug)"
                class="text-sm font-semibold text-slate-900 hover:text-slate-600"
            >
                {{ item.product.name }}
            </Link>
            <p v-else class="text-sm font-semibold text-slate-900">
                {{ item.product?.name }}
            </p>
            <p class="mt-1 text-xs text-slate-500">{{ item.variant.label }}</p>
            <div class="mt-1 flex flex-wrap items-center gap-2">
                <p class="text-sm font-semibold text-slate-800">{{ money(item.variant.price.current) }}</p>
                <p v-if="item.variant.price.has_discount" class="text-xs text-slate-400 line-through">
                    {{ money(item.variant.price.regular) }}
                </p>
                <span
                    v-if="item.variant.price.has_discount"
                    class="rounded-full bg-rose-100 px-2 py-0.5 text-[11px] font-semibold text-rose-700"
                >
                    On Sale
                </span>
            </div>

            <p
                v-if="item.availability?.message"
                :class="[
                    'mt-2 rounded-xl px-3 py-2 text-xs font-medium',
                    item.availability?.is_available === false
                        ? 'bg-rose-100 text-rose-700'
                        : 'bg-amber-100 text-amber-700',
                ]"
            >
                {{ item.availability.message }}
            </p>

            <p
                v-if="item.availability?.included_in_totals === false"
                class="mt-2 text-xs font-medium text-slate-500"
            >
                This item is not included in the order total until the issue is fixed.
            </p>

            <p v-if="errorMessage" class="mt-2 text-xs font-medium text-red-600">
                {{ errorMessage }}
            </p>
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
                class="block text-xs font-medium text-slate-600 transition hover:text-slate-800"
                @click="saveForLater"
            >
                Save for later
            </button>
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
