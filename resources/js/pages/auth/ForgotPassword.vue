<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { login } from '@/routes';
import { email } from '@/routes/password';
import { Form, Head } from '@inertiajs/vue3';

defineProps<{
    status?: string;
}>();
</script>

<template>
    <AuthLayout
        title="Forgot your password? 🔑"
        description="No worries! Enter your email and we'll send you a magic link to reset it."
    >
        <Head title="Forgot password" />

        <div
            v-if="status"
            class="mb-6 rounded-xl bg-green-50 dark:bg-green-900/20 p-4 text-center text-sm font-medium text-green-600 dark:text-green-400"
        >
            <div class="flex items-center justify-center gap-2">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
                {{ status }}
            </div>
        </div>

        <div class="space-y-6">
            <Form v-bind="email.form()" v-slot="{ errors, processing }">
                <div class="grid gap-5">
                    <div class="grid gap-2">
                        <Label for="email" class="text-[#1b1b18] dark:text-[#EDEDEC]">Email address</Label>
                        <Input
                            id="email"
                            type="email"
                            name="email"
                            autocomplete="off"
                            autofocus
                            placeholder="your@email.com"
                            class="h-12 rounded-xl border-[#19140020] dark:border-[#3E3E3A] focus:border-[#f53003] focus:ring-[#f53003]/20"
                        />
                        <InputError :message="errors.email" />
                    </div>

                    <Button
                        class="h-12 w-full rounded-xl bg-gradient-to-r from-[#f53003] to-[#F8B803] text-white font-semibold shadow-lg shadow-[#f5300320] hover:shadow-xl hover:shadow-[#f5300340] transition-all hover:scale-[1.02] active:scale-[0.98]"
                        :disabled="processing"
                        data-test="email-password-reset-link-button"
                    >
                        <Spinner v-if="processing" class="mr-2" />
                        {{ processing ? 'Sending...' : 'Send Reset Link' }}
                    </Button>
                </div>
            </Form>

            <div class="text-center text-sm text-[#706f6c] dark:text-[#A1A09A]">
                Remember your password?
                <TextLink 
                    :href="login()"
                    class="text-[#f53003] hover:text-[#F8B803] font-medium transition-colors"
                >
                    Back to login
                </TextLink>
            </div>
        </div>
    </AuthLayout>
</template>
