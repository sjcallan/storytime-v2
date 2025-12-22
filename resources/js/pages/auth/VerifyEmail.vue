<script setup lang="ts">
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { logout } from '@/routes';
import { send } from '@/routes/verification';
import { Form, Head } from '@inertiajs/vue3';

defineProps<{
    status?: string;
}>();
</script>

<template>
    <AuthLayout
        title="Check Your Email 📬"
        description="We've sent you a verification link. Click the link in the email to verify your account."
    >
        <Head title="Email verification" />

        <div class="space-y-6">
            <!-- Success message when link is resent -->
            <div
                v-if="status === 'verification-link-sent'"
                class="rounded-xl bg-green-50 dark:bg-green-900/20 p-4 text-center"
            >
                <div class="flex flex-col items-center gap-2">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/40">
                        <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <p class="text-sm font-medium text-green-600 dark:text-green-400">
                        A new verification link has been sent!
                    </p>
                </div>
            </div>

            <!-- Illustration -->
            <div class="flex justify-center py-4">
                <div class="relative">
                    <div class="absolute inset-0 bg-gradient-to-r from-[#f53003]/20 to-[#F8B803]/20 rounded-full blur-2xl"></div>
                    <div class="relative w-24 h-24 flex items-center justify-center">
                        <svg class="w-16 h-16 text-[#f53003]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Helpful tips -->
            <div class="rounded-xl bg-[#fff9e6] dark:bg-[#1a0f00]/50 p-4">
                <h4 class="text-sm font-medium text-[#1b1b18] dark:text-[#EDEDEC] mb-2">
                    Didn't receive the email?
                </h4>
                <ul class="text-sm text-[#706f6c] dark:text-[#A1A09A] space-y-1">
                    <li>• Check your spam or junk folder</li>
                    <li>• Make sure you entered the correct email</li>
                    <li>• Click below to resend the verification link</li>
                </ul>
            </div>

            <Form
                v-bind="send.form()"
                class="space-y-4"
                v-slot="{ processing }"
            >
                <Button 
                    :disabled="processing" 
                    class="h-12 w-full rounded-xl bg-gradient-to-r from-[#f53003] to-[#F8B803] text-white font-semibold shadow-lg shadow-[#f5300320] hover:shadow-xl hover:shadow-[#f5300340] transition-all hover:scale-[1.02] active:scale-[0.98]"
                >
                    <Spinner v-if="processing" class="mr-2" />
                    {{ processing ? 'Sending...' : 'Resend Verification Email' }}
                </Button>

                <TextLink
                    :href="logout()"
                    as="button"
                    class="block w-full text-center text-sm text-[#706f6c] dark:text-[#A1A09A] hover:text-[#f53003] transition-colors"
                >
                    Log out and try a different account
                </TextLink>
            </Form>
        </div>
    </AuthLayout>
</template>
