<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { home } from '@/routes';
import { Link, usePage } from '@inertiajs/vue3';

defineProps<{
    title?: string;
    description?: string;
}>();

const page = usePage();
</script>

<template>
    <div class="min-h-svh bg-[linear-gradient(160deg,#f8fafc_0%,#fff7ed_42%,#f1f5f9_100%)]">
        <div class="mx-auto grid min-h-svh max-w-7xl items-stretch gap-8 px-4 py-6 lg:grid-cols-[1.05fr_0.95fr] lg:px-6">
            <section class="relative hidden overflow-hidden rounded-[2rem] border border-slate-200 bg-slate-900 p-10 text-white shadow-2xl lg:flex lg:flex-col">
                <div class="absolute inset-0 bg-[radial-gradient(circle_at_top,_rgba(245,158,11,0.28),_transparent_45%)]" />
                <div class="absolute inset-y-0 right-0 w-1/2 bg-[radial-gradient(circle_at_center,_rgba(255,255,255,0.14),_transparent_65%)]" />

                <Link :href="home()" class="relative z-10 inline-flex items-center gap-3 text-sm font-semibold uppercase tracking-[0.24em] text-slate-200">
                    <AppLogoIcon class="size-9 fill-current text-white" />
                    <span>{{ page.props.name }}</span>
                </Link>

                <div class="relative z-10 mt-20 max-w-lg">
                    <p class="text-sm font-semibold uppercase tracking-[0.28em] text-amber-300">Enterprise Commerce</p>
                    <h1 class="mt-4 text-5xl font-semibold tracking-tight text-white">
                        A trusted storefront experience for serious retail operations.
                    </h1>
                    <p class="mt-6 text-base leading-7 text-slate-300">
                        Secure checkout, operational control, and customer account continuity all live in one production-ready platform.
                    </p>
                </div>

                <div class="relative z-10 mt-auto grid gap-4 md:grid-cols-3">
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-300">Secure</p>
                        <p class="mt-2 text-sm text-slate-100">Fortify authentication, verified accounts, and role-aware access.</p>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-300">Reliable</p>
                        <p class="mt-2 text-sm text-slate-100">Checkout, payment verification, and inventory safeguards built into the flow.</p>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-300">Scalable</p>
                        <p class="mt-2 text-sm text-slate-100">Designed for customers, sales reps, and directors without leaking privileges.</p>
                    </div>
                </div>
            </section>

            <section class="flex items-center justify-center py-6 lg:py-10">
                <div class="w-full max-w-xl rounded-[2rem] border border-white/70 bg-white/90 p-6 shadow-2xl shadow-slate-200/60 backdrop-blur md:p-8">
                    <div class="mb-8 flex flex-col gap-5">
                        <Link :href="home()" class="inline-flex items-center gap-3 text-sm font-semibold uppercase tracking-[0.22em] text-slate-600 lg:hidden">
                            <AppLogoIcon class="size-8 fill-current text-slate-900" />
                            <span>{{ page.props.name }}</span>
                        </Link>

                        <div>
                            <h1 v-if="title" class="text-3xl font-semibold tracking-tight text-slate-900">{{ title }}</h1>
                            <p v-if="description" class="mt-3 text-sm leading-6 text-slate-600">{{ description }}</p>
                        </div>
                    </div>

                    <slot />
                </div>
            </section>
        </div>
    </div>
</template>
