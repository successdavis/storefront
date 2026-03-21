<script setup lang="ts">
import Pagination from '@/components/Pagination.vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps<{
    listType: string;
    counts: { wishlist: number; saved_for_later: number };
    savedItems: {
        data: Array<{
            id: number;
            list_type: string;
            quantity: number;
            snapshot: { price: number | null; currency: string; product_name: string | null; variant_label: string | null };
            availability: { is_available: boolean; message: string | null };
            product: { name: string | null; slug: string | null; image: string | null };
            variant: { id: number | null; label: string | null; price: { current: number } | null };
        }>;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
}>();

const isWishlist = props.listType === 'wishlist';
const tabs = [
    { key: 'wishlist', title: 'Wishlist', href: '/account/wishlist', count: props.counts.wishlist },
    { key: 'saved_for_later', title: 'Saved for Later', href: '/account/saved-for-later', count: props.counts.saved_for_later },
];

const money = (value: number | null, currency = 'NGN') =>
    new Intl.NumberFormat('en-NG', { style: 'currency', currency }).format(Number(value || 0));

const moveToCart = (id: number) => router.post(`/account/saved-items/${id}/move-to-cart`, {}, { preserveScroll: true });
const removeItem = (id: number) => router.delete(`/account/saved-items/${id}`, { preserveScroll: true });
const moveToWishlist = (id: number) => router.post(`/account/saved-items/${id}/move-to-wishlist`, {}, { preserveScroll: true });
const moveToSaved = (id: number) => router.post(`/account/saved-items/${id}/move-to-saved-for-later`, {}, { preserveScroll: true });
</script>

<template>
    <Head :title="isWishlist ? 'Wishlist' : 'Saved for Later'" />

    <div class="space-y-6 bg-slate-50 p-6">
        <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <h1 class="text-3xl font-semibold tracking-tight text-slate-900">{{ isWishlist ? 'Wishlist' : 'Saved for Later' }}</h1>
            <p class="mt-2 text-sm text-slate-500">
                {{ isWishlist ? 'Keep favorites close so you can compare and buy later.' : 'Hold items for later without losing the exact variant you selected.' }}
            </p>
            <div class="mt-5 flex flex-wrap gap-3">
                <Link
                    v-for="tab in tabs"
                    :key="tab.key"
                    :href="tab.href"
                    :class="[
                        'inline-flex items-center gap-2 rounded-full border px-4 py-2 text-sm font-semibold transition',
                        tab.key === listType ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-300 bg-white text-slate-700 hover:border-slate-500',
                    ]"
                >
                    <span>{{ tab.title }}</span>
                    <span class="rounded-full bg-black/10 px-2 py-0.5 text-xs" :class="tab.key === listType ? 'bg-white/20 text-white' : 'text-slate-600'">{{ tab.count }}</span>
                </Link>
            </div>
        </section>

        <section v-if="savedItems.data.length" class="grid gap-4 lg:grid-cols-2">
            <article v-for="item in savedItems.data" :key="item.id" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex gap-4">
                    <img v-if="item.product.image" :src="item.product.image" :alt="item.product.name ?? 'Product image'" class="h-24 w-24 rounded-2xl object-cover" />
                    <div v-else class="flex h-24 w-24 items-center justify-center rounded-2xl bg-slate-100 text-xs text-slate-500">No image</div>

                    <div class="flex-1">
                        <p class="font-semibold text-slate-900">{{ item.product.name || item.snapshot.product_name }}</p>
                        <p class="mt-1 text-sm text-slate-500">{{ item.variant.label || item.snapshot.variant_label }}</p>
                        <p class="mt-2 text-sm font-semibold text-slate-900">{{ money(item.variant.price?.current ?? item.snapshot.price, item.snapshot.currency) }}</p>
                        <p class="mt-2 text-sm text-slate-500">Saved quantity: {{ item.quantity }}</p>
                        <p v-if="item.availability.message" class="mt-2 rounded-xl bg-amber-50 px-3 py-2 text-xs font-medium text-amber-800">{{ item.availability.message }}</p>
                    </div>
                </div>

                <div class="mt-5 flex flex-wrap gap-3">
                    <button
                        type="button"
                        class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:bg-slate-300"
                        :disabled="!item.availability.is_available"
                        @click="moveToCart(item.id)"
                    >
                        Move to Cart
                    </button>
                    <button
                        v-if="isWishlist"
                        type="button"
                        class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700"
                        @click="moveToSaved(item.id)"
                    >
                        Save for Later
                    </button>
                    <button
                        v-else
                        type="button"
                        class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700"
                        @click="moveToWishlist(item.id)"
                    >
                        Move to Wishlist
                    </button>
                    <button type="button" class="rounded-xl border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-600" @click="removeItem(item.id)">
                        Remove
                    </button>
                </div>
            </article>
        </section>

        <section v-else class="rounded-3xl border border-slate-200 bg-white px-6 py-16 text-center shadow-sm">
            <h2 class="text-lg font-semibold text-slate-900">{{ isWishlist ? 'Your wishlist is empty' : 'No items are saved for later' }}</h2>
            <p class="mt-2 text-sm text-slate-500">Products you save will appear here with live availability checks.</p>
            <Link href="/store" class="mt-4 inline-flex rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Browse products</Link>
        </section>

        <Pagination :links="savedItems.links" />
    </div>
</template>
