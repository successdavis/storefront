<script setup lang="ts">
import AuthenticatedSessionController from '@/actions/App/Http/Controllers/Auth/AuthenticatedSessionController';
import AuthGoogleButton from '@/components/auth/AuthGoogleButton.vue';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { register } from '@/routes';
import { request } from '@/routes/password';
import { Form, Head, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    LoaderCircle,
    LockKeyhole,
    Mail,
    ShieldCheck,
    TriangleAlert,
} from 'lucide-vue-next';

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();

const page = usePage();
</script>

<script lang="ts">
export default {
    layout: AuthBase,
};
</script>

<template>
    <Head title="Log in" />

    <div class="mx-auto w-full max-w-xl">
        <div class="rounded-3xl border border-slate-200/80 bg-white/95 p-6 shadow-xl shadow-slate-200/40 backdrop-blur sm:p-8">
            <!-- Header -->
            <div class="mb-8 space-y-3 text-center">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-900 text-white shadow-lg shadow-slate-300/40">
                    <ShieldCheck class="h-7 w-7" />
                </div>

                <div class="space-y-1.5">
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                        Welcome back
                    </h1>
                    <p class="text-sm leading-6 text-slate-500 sm:text-base">
                        Sign in to access your dashboard, manage orders, and continue your workflow.
                    </p>
                </div>
            </div>

            <!-- Status -->
            <Alert
                v-if="status"
                class="mb-5 rounded-2xl border-emerald-200 bg-emerald-50 text-emerald-800"
            >
                <AlertDescription class="text-sm font-medium">
                    {{ status }}
                </AlertDescription>
            </Alert>

            <!-- Error -->
            <Alert
                v-if="page.props.errors?.google || page.props.flash?.error"
                variant="destructive"
                class="mb-5 rounded-2xl border-rose-200 bg-rose-50 text-rose-700"
            >
                <TriangleAlert class="h-4 w-4" />
                <AlertDescription class="text-sm font-medium">
                    {{ page.props.errors?.google || page.props.flash?.error }}
                </AlertDescription>
            </Alert>

            <Form
                v-bind="AuthenticatedSessionController.store.form()"
                :reset-on-success="['password']"
                v-slot="{ errors, processing }"
                class="space-y-6"
            >
                <!-- Google auth -->
                <div class="space-y-4">
                    <AuthGoogleButton class="h-15" label="Continue with Google" />

                    <div class="flex items-center gap-3">
                        <div class="h-px flex-1 bg-slate-200" />
                        <span class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-400">
                            or continue with email
                        </span>
                        <div class="h-px flex-1 bg-slate-200" />
                    </div>
                </div>

                <!-- Email -->
                <div class="space-y-2.5">
                    <Label
                        for="email"
                        class="text-sm font-semibold text-slate-700"
                    >
                        Email address
                    </Label>

                    <div class="relative">
                        <Mail class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <Input
                            id="email"
                            type="email"
                            name="email"
                            required
                            autofocus
                            :tabindex="1"
                            autocomplete="email"
                            placeholder="name@company.com"
                            class="h-15 rounded-2xl border-slate-200 dark:bg-white bg-white pl-11 text-sm shadow-sm transition focus-visible:ring-2 focus-visible:ring-slate-900/20"
                        />
                    </div>

                    <InputError :message="errors.email" />
                </div>

                <!-- Password -->
                <div class="space-y-2.5">
                    <div class="flex items-center justify-between gap-4">
                        <Label
                            for="password"
                            class="text-sm font-semibold text-slate-700"
                        >
                            Password
                        </Label>

                        <TextLink
                            v-if="canResetPassword"
                            :href="request()"
                            class="text-sm font-medium text-slate-600 transition hover:text-slate-900"
                            :tabindex="5"
                        >
                            Forgot password?
                        </TextLink>
                    </div>

                    <div class="relative">
                        <LockKeyhole class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <Input
                            id="password"
                            type="password"
                            name="password"
                            required
                            :tabindex="2"
                            autocomplete="current-password"
                            placeholder="Enter your password"
                            class="h-15 rounded-2xl border-slate-200 bg-white dark:bg-white pl-11 text-sm shadow-sm transition focus-visible:ring-2 focus-visible:ring-slate-900/20"
                        />
                    </div>

                    <InputError :message="errors.password" />
                </div>

                <!-- Remember me -->
                <div class="flex items-center justify-between">
                    <Label
                        for="remember"
                        class="flex cursor-pointer items-center gap-3 text-sm font-medium text-slate-600"
                    >
                        <Checkbox id="remember" name="remember" :tabindex="3" />
                        <span>Remember me for 30 days</span>
                    </Label>
                </div>

                <!-- Submit -->
                <Button
                    type="submit"
                    :tabindex="4"
                    :disabled="processing"
                    data-test="login-button"
                    class="group h-15 w-full rounded-2xl bg-slate-900 text-sm font-semibold text-white shadow-lg shadow-slate-300/40 transition hover:bg-slate-800 disabled:opacity-70"
                >
                    <LoaderCircle
                        v-if="processing"
                        class="mr-2 h-4 w-4 animate-spin"
                    />
                    <span>{{ processing ? 'Signing in...' : 'Sign in' }}</span>
                    <ArrowRight
                        v-if="!processing"
                        class="ml-2 h-4 w-4 transition-transform group-hover:translate-x-0.5"
                    />
                </Button>

                <!-- Footer -->
                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 text-center text-sm leading-6 text-slate-600">
                    Don’t have an account?
                    <TextLink
                        :href="register()"
                        :tabindex="5"
                        class="ml-1 font-semibold text-slate-900"
                    >
                        Create an account
                    </TextLink>
                </div>
            </Form>
        </div>
    </div>
</template>
