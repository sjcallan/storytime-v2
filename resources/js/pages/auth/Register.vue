<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TermsModal from '@/components/TermsModal.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthBase from '@/layouts/AuthLayout.vue';
import { login } from '@/routes';
import { store } from '@/routes/register';
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps<{
    termsContent: string;
}>();

const termsAccepted = ref(false);
const termsModalOpen = ref(false);

const openTermsModal = () => {
    termsModalOpen.value = true;
};
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

                <!-- Terms Checkbox -->
                <div class="grid gap-2">
                    <div class="flex items-start gap-3">
                        <input
                            id="terms"
                            type="checkbox"
                            name="terms"
                            value="1"
                            :tabindex="5"
                            :checked="termsAccepted"
                            class="mt-1.5 h-4 w-4 shrink-0 rounded border border-[#19140020] dark:border-[#3E3E3A] text-[#f53003] focus:ring-[#f53003]/20 focus:ring-2 cursor-pointer accent-[#f53003]"
                            @change="termsAccepted = ($event.target as HTMLInputElement).checked"
                        />
                        <div class="flex-1">
                            <Label
                                for="terms"
                                class="text-sm leading-relaxed text-[#706f6c] dark:text-[#A1A09A] cursor-pointer"
                            >
                                I agree to the
                                <button
                                    type="button"
                                    class="text-[#f53003] hover:text-[#F8B803] font-medium transition-colors underline underline-offset-2"
                                    @click.prevent="openTermsModal"
                                >
                                    Terms of Use
                                </button>
                            </Label>
                        </div>
                    </div>
                    <InputError :message="errors.terms" />
                </div>

                <Button
                    type="submit"
                    class="mt-2 h-12 w-full rounded-xl bg-gradient-to-r from-[#f53003] to-[#F8B803] text-white font-semibold shadow-lg shadow-[#f5300320] hover:shadow-xl hover:shadow-[#f5300340] transition-all hover:scale-[1.02] active:scale-[0.98]"
                    tabindex="6"
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
                    :tabindex="7"
                >
                    Log in
                </TextLink>
            </div>
        </Form>

        <!-- Terms Modal -->
        <TermsModal v-model:open="termsModalOpen" :content="termsContent" />
    </AuthBase>
</template>
