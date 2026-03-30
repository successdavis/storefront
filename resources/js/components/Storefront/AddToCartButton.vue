<script setup>
import { router, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { ShoppingCart } from 'lucide-vue-next'

const props = defineProps({
    variantId: {
        type: Number,
        default: null,
    },
    quantity: {
        type: Number,
        default: 1,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    fullWidth: {
        type: Boolean,
        default: false,
    },
    label: {
        type: String,
        default: 'Add to Cart',
    },
})

const page = usePage()
const submitting = ref(false)

const isLoggedIn = computed(() => !!page.props.auth?.user)
const isDisabled = computed(() => props.disabled || !props.variantId || submitting.value)

function addToCart() {
    if (!props.variantId || isDisabled.value) {
        return
    }

    if (!isLoggedIn.value) {
        router.visit(route('login'))
        return
    }

    router.post(
        route('store.cart.add'),
        {
            variant_id: props.variantId,
            quantity: props.quantity,
        },
        {
            preserveScroll: true,
            onStart: () => {
                submitting.value = true
            },
            onFinish: () => {
                submitting.value = false
            },
        },
    )
}
</script>

<template>
    <button
        type="button"
        :disabled="isDisabled"
        :class="[
            'inline-flex items-center justify-center gap-2 rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700 disabled:cursor-not-allowed disabled:bg-slate-300',
            fullWidth ? 'w-full' : '',
        ]"
        @click="addToCart"
    >
        <ShoppingCart class="size-4 shrink-0" />
        <span>{{ submitting ? 'Adding...' : label }}</span>
    </button>
</template>
