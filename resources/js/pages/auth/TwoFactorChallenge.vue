<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    PinInput,
    PinInputGroup,
    PinInputSlot,
} from '@/components/ui/pin-input';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { store } from '@/routes/two-factor/login';
import { Form, Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface AuthConfigContent {
    title: string;
    description: string;
    toggleText: string;
    emoji: string;
}

const authConfigContent = computed<AuthConfigContent>(() => {
    if (showRecoveryInput.value) {
        return {
            title: 'Use Recovery Code',
            description:
                'Enter one of your emergency recovery codes to access your account.',
            toggleText: 'use authentication code instead',
            emoji: '🔑',
        };
    }

    return {
        title: 'Two-Factor Authentication',
        description:
            'Enter the 6-digit code from your authenticator app to continue.',
        toggleText: 'use a recovery code instead',
        emoji: '🔒',
    };
});

const showRecoveryInput = ref<boolean>(false);

const toggleRecoveryMode = (clearErrors: () => void): void => {
    showRecoveryInput.value = !showRecoveryInput.value;
    clearErrors();
    code.value = [];
};

const code = ref<number[]>([]);
const codeValue = computed<string>(() => code.value.join(''));
</script>

<template>
    <AuthLayout
        :title="`${authConfigContent.emoji} ${authConfigContent.title}`"
        :description="authConfigContent.description"
    >
        <Head title="Two-Factor Authentication" />

        <div class="space-y-6">
            <template v-if="!showRecoveryInput">
                <Form
                    v-bind="store.form()"
                    class="space-y-6"
                    reset-on-error
                    @error="code = []"
                    #default="{ errors, processing, clearErrors }"
                >
                    <input type="hidden" name="code" :value="codeValue" />
                    <div
                        class="flex flex-col items-center justify-center space-y-4"
                    >
                        <div class="flex w-full items-center justify-center">
                            <PinInput
                                id="otp"
                                placeholder="○"
                                v-model="code"
                                type="number"
                                otp
                                class="gap-2"
                            >
                                <PinInputGroup class="gap-2">
                                    <PinInputSlot
                                        v-for="(id, index) in 6"
                                        :key="id"
                                        :index="index"
                                        :disabled="processing"
                                        autofocus
                                        class="h-14 w-12 rounded-xl border-2 border-[#19140020] dark:border-[#3E3E3A] text-xl font-bold focus:border-[#f53003] focus:ring-[#f53003]/20"
                                    />
                                </PinInputGroup>
                            </PinInput>
                        </div>
                        <InputError :message="errors.code" />
                    </div>
                    <Button 
                        type="submit" 
                        class="h-12 w-full rounded-xl bg-gradient-to-r from-[#f53003] to-[#F8B803] text-white font-semibold shadow-lg shadow-[#f5300320] hover:shadow-xl hover:shadow-[#f5300340] transition-all hover:scale-[1.02] active:scale-[0.98]" 
                        :disabled="processing"
                    >
                        {{ processing ? 'Verifying...' : 'Verify & Continue' }}
                    </Button>
                    <div class="text-center text-sm text-[#706f6c] dark:text-[#A1A09A]">
                        <span>or you can </span>
                        <button
                            type="button"
                            class="text-[#f53003] hover:text-[#F8B803] font-medium transition-colors"
                            @click="() => toggleRecoveryMode(clearErrors)"
                        >
                            {{ authConfigContent.toggleText }}
                        </button>
                    </div>
                </Form>
            </template>

            <template v-else>
                <Form
                    v-bind="store.form()"
                    class="space-y-6"
                    reset-on-error
                    #default="{ errors, processing, clearErrors }"
                >
                    <div class="grid gap-2">
                        <Input
                            name="recovery_code"
                            type="text"
                            placeholder="Enter your recovery code"
                            :autofocus="showRecoveryInput"
                            required
                            class="h-12 rounded-xl border-[#19140020] dark:border-[#3E3E3A] focus:border-[#f53003] focus:ring-[#f53003]/20 text-center font-mono"
                        />
                        <InputError :message="errors.recovery_code" />
                    </div>
                    <Button 
                        type="submit" 
                        class="h-12 w-full rounded-xl bg-gradient-to-r from-[#f53003] to-[#F8B803] text-white font-semibold shadow-lg shadow-[#f5300320] hover:shadow-xl hover:shadow-[#f5300340] transition-all hover:scale-[1.02] active:scale-[0.98]" 
                        :disabled="processing"
                    >
                        {{ processing ? 'Verifying...' : 'Verify & Continue' }}
                    </Button>

                    <div class="text-center text-sm text-[#706f6c] dark:text-[#A1A09A]">
                        <span>or you can </span>
                        <button
                            type="button"
                            class="text-[#f53003] hover:text-[#F8B803] font-medium transition-colors"
                            @click="() => toggleRecoveryMode(clearErrors)"
                        >
                            {{ authConfigContent.toggleText }}
                        </button>
                    </div>
                </Form>
            </template>
        </div>
    </AuthLayout>
</template>
