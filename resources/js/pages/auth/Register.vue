<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthBase from '@/layouts/AuthLayout.vue';
import { login } from '@/routes';
import { store } from '@/routes/register';
import { Form, Head } from '@inertiajs/vue3';
</script>

<template>
    <AuthBase
        title="Join the Adventure! ✨"
        description="Create your account and start writing amazing stories"
    >
        <Head title="Register" />

        <Form
            v-bind="store.form()"
            :reset-on-success="['password', 'password_confirmation']"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-6"
        >
            <div class="grid gap-5">
                <div class="grid gap-2">
                    <Label for="name" class="text-[#1b1b18] dark:text-[#EDEDEC]">Your Name</Label>
                    <Input
                        id="name"
                        type="text"
                        required
                        autofocus
                        :tabindex="1"
                        autocomplete="name"
                        name="name"
                        placeholder="What should we call you?"
                        class="h-12 rounded-xl border-[#19140020] dark:border-[#3E3E3A] focus:border-[#f53003] focus:ring-[#f53003]/20"
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="email" class="text-[#1b1b18] dark:text-[#EDEDEC]">Email address</Label>
                    <Input
                        id="email"
                        type="email"
                        required
                        :tabindex="2"
                        autocomplete="email"
                        name="email"
                        placeholder="your@email.com"
                        class="h-12 rounded-xl border-[#19140020] dark:border-[#3E3E3A] focus:border-[#f53003] focus:ring-[#f53003]/20"
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="password" class="text-[#1b1b18] dark:text-[#EDEDEC]">Password</Label>
                    <Input
                        id="password"
                        type="password"
                        required
                        :tabindex="3"
                        autocomplete="new-password"
                        name="password"
                        placeholder="Create a secret password"
                        class="h-12 rounded-xl border-[#19140020] dark:border-[#3E3E3A] focus:border-[#f53003] focus:ring-[#f53003]/20"
                    />
                    <InputError :message="errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation" class="text-[#1b1b18] dark:text-[#EDEDEC]">Confirm password</Label>
                    <Input
                        id="password_confirmation"
                        type="password"
                        required
                        :tabindex="4"
                        autocomplete="new-password"
                        name="password_confirmation"
                        placeholder="Type your password again"
                        class="h-12 rounded-xl border-[#19140020] dark:border-[#3E3E3A] focus:border-[#f53003] focus:ring-[#f53003]/20"
                    />
                    <InputError :message="errors.password_confirmation" />
                </div>

                <Button
                    type="submit"
                    class="mt-2 h-12 w-full rounded-xl bg-gradient-to-r from-[#f53003] to-[#F8B803] text-white font-semibold shadow-lg shadow-[#f5300320] hover:shadow-xl hover:shadow-[#f5300340] transition-all hover:scale-[1.02] active:scale-[0.98]"
                    tabindex="5"
                    :disabled="processing"
                    data-test="register-user-button"
                >
                    <Spinner v-if="processing" class="mr-2" />
                    {{ processing ? 'Creating your account...' : 'Begin Your Story' }}
                </Button>
            </div>

            <div class="text-center text-sm text-[#706f6c] dark:text-[#A1A09A]">
                Already have an account?
                <TextLink
                    :href="login()"
                    class="text-[#f53003] hover:text-[#F8B803] font-medium transition-colors"
                    :tabindex="6"
                >
                    Log in
                </TextLink>
            </div>
        </Form>
    </AuthBase>
</template>
