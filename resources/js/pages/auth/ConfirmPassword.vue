<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { store } from '@/routes/password/confirm';
import { Form, Head } from '@inertiajs/vue3';
</script>

<template>
    <AuthLayout
        title="Confirm Your Password 🛡️"
        description="This is a secure area. Please enter your password to continue."
    >
        <Head title="Confirm password" />

        <div class="space-y-6">
            <!-- Security notice -->
            <div class="rounded-xl bg-[#fff2f2] dark:bg-[#1D0002]/50 p-4">
                <div class="flex items-start gap-3">
                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-[#f53003]/10 shrink-0">
                        <svg class="w-4 h-4 text-[#f53003]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                    </div>
                    <p class="text-sm text-[#706f6c] dark:text-[#A1A09A]">
                        For your security, we need to verify it's really you before continuing with this action.
                    </p>
                </div>
            </div>

            <Form
                v-bind="store.form()"
                reset-on-success
                v-slot="{ errors, processing }"
            >
                <div class="space-y-5">
                    <div class="grid gap-2">
                        <Label for="password" class="text-[#1b1b18] dark:text-[#EDEDEC]">Password</Label>
                        <Input
                            id="password"
                            type="password"
                            name="password"
                            required
                            autocomplete="current-password"
                            autofocus
                            placeholder="Enter your password"
                            class="h-12 rounded-xl border-[#19140020] dark:border-[#3E3E3A] focus:border-[#f53003] focus:ring-[#f53003]/20"
                        />
                        <InputError :message="errors.password" />
                    </div>

                    <Button
                        class="h-12 w-full rounded-xl bg-gradient-to-r from-[#f53003] to-[#F8B803] text-white font-semibold shadow-lg shadow-[#f5300320] hover:shadow-xl hover:shadow-[#f5300340] transition-all hover:scale-[1.02] active:scale-[0.98]"
                        :disabled="processing"
                        data-test="confirm-password-button"
                    >
                        <Spinner v-if="processing" class="mr-2" />
                        {{ processing ? 'Confirming...' : 'Confirm Password' }}
                    </Button>
                </div>
            </Form>
        </div>
    </AuthLayout>
</template>
