<script setup lang="ts">
import RegisteredUserController from '@/actions/App/Http/Controllers/Auth/RegisteredUserController';
import AuthGoogleButton from '@/components/auth/AuthGoogleButton.vue';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { login } from '@/routes';
import { Form, Head, usePage } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

const page = usePage();
</script>

<script lang="ts">
// 👇 tell Inertia to use AuthBase instead of the global layout
export default {
  layout: AuthBase
}
</script>

<template>
    <AuthBase
        title="Create your account"
        description="Open a secure customer account for checkout, saved lists, and ongoing order visibility."
    >
        <Head title="Register" />

        <div
            v-if="page.props.errors?.google || page.props.flash?.error"
            class="mb-4 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700"
        >
            {{ page.props.errors?.google || page.props.flash?.error }}
        </div>

        <Form
            v-bind="RegisteredUserController.store.form()"
            :reset-on-success="['password', 'password_confirmation']"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-6"
        >
            <div class="space-y-4">
                <AuthGoogleButton label="Sign up with Google" />
                <div class="flex items-center gap-3">
                    <div class="h-px flex-1 bg-slate-200" />
                    <span class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">or create an account with email</span>
                    <div class="h-px flex-1 bg-slate-200" />
                </div>
            </div>

            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="name">Name</Label>
                    <Input
                        id="name"
                        type="text"
                        required
                        autofocus
                        :tabindex="1"
                        autocomplete="name"
                        name="name"
                        placeholder="Full name"
                        class="h-12 rounded-2xl border-slate-300"
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="email">Email address</Label>
                    <Input
                        id="email"
                        type="email"
                        required
                        :tabindex="2"
                        autocomplete="email"
                        name="email"
                        placeholder="name@company.com"
                        class="h-12 rounded-2xl border-slate-300"
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">Password</Label>
                    <Input
                        id="password"
                        type="password"
                        required
                        :tabindex="3"
                        autocomplete="new-password"
                        name="password"
                        placeholder="Create a strong password"
                        class="h-12 rounded-2xl border-slate-300"
                    />
                    <InputError :message="errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">Confirm password</Label>
                    <Input
                        id="password_confirmation"
                        type="password"
                        required
                        :tabindex="4"
                        autocomplete="new-password"
                        name="password_confirmation"
                        placeholder="Re-enter your password"
                        class="h-12 rounded-2xl border-slate-300"
                    />
                    <InputError :message="errors.password_confirmation" />
                </div>

                <Button
                    type="submit"
                    class="mt-2 h-12 w-full rounded-2xl bg-slate-900 text-white hover:bg-slate-800"
                    tabindex="5"
                    :disabled="processing"
                    data-test="register-user-button"
                >
                    <LoaderCircle
                        v-if="processing"
                        class="h-4 w-4 animate-spin"
                    />
                    Create account
                </Button>
            </div>

            <div class="rounded-2xl bg-slate-50 px-4 py-3 text-center text-sm text-muted-foreground">
                Already have an account?
                <TextLink
                    :href="login()"
                    class="underline underline-offset-4"
                    :tabindex="6"
                    >Log in</TextLink
                >
            </div>
        </Form>
    </AuthBase>
</template>
