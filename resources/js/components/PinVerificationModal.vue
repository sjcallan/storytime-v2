<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    PinInput,
    PinInputGroup,
    PinInputSlot,
} from '@/components/ui/pin-input';
import { verify } from '@/actions/App/Http/Controllers/Settings/PinController';
import { nextTick, ref, useTemplateRef, watch } from 'vue';
import { Lock, ShieldCheck, AlertCircle } from 'lucide-vue-next';
import axios from 'axios';

interface Props {
    redirectUrl?: string;
}

const props = withDefaults(defineProps<Props>(), {
    redirectUrl: undefined,
});

const emit = defineEmits<{
    verified: [];
    cancelled: [];
}>();

const isOpen = defineModel<boolean>('isOpen', { default: false });

const pin = ref<string[]>([]);
const isVerifying = ref(false);
const error = ref<string | null>(null);
const isShaking = ref(false);

const pinInputContainerRef = useTemplateRef('pinInputContainerRef');

const focusPinInput = () => {
    nextTick(() => {
        pinInputContainerRef.value?.querySelector('input')?.focus();
    });
};

const resetState = () => {
    pin.value = [];
    error.value = null;
    isVerifying.value = false;
    isShaking.value = false;
};

const handleVerify = async () => {
    if (pin.value.length !== 4) {
        return;
    }

    isVerifying.value = true;
    error.value = null;

    try {
        const response = await axios.post(verify.url(), {
            pin: pin.value.join(''),
        });

        if (response.data.verified) {
            isOpen.value = false;
            emit('verified');

            if (props.redirectUrl) {
                window.location.href = props.redirectUrl;
            }
        }
    } catch (err: unknown) {
        const axiosError = err as { response?: { data?: { message?: string } } };
        error.value = axiosError.response?.data?.message || 'Invalid PIN. Please try again.';
        isShaking.value = true;
        pin.value = [];

        setTimeout(() => {
            isShaking.value = false;
            focusPinInput();
        }, 500);
    } finally {
        isVerifying.value = false;
    }
};

const handleCancel = () => {
    isOpen.value = false;
    emit('cancelled');
};

watch(isOpen, (open) => {
    if (open) {
        resetState();
        focusPinInput();
    }
});

watch(
    () => pin.value.length,
    (length) => {
        if (length === 4) {
            handleVerify();
        }
    },
);
</script>

<template>
    <Dialog :open="isOpen" @update:open="isOpen = $event">
        <DialogContent
            class="sm:max-w-md"
            :class="{ 'animate-shake': isShaking }"
            @escape-key-down.prevent
            @pointer-down-outside.prevent
            @interact-outside.prevent
        >
            <DialogHeader class="flex flex-col items-center justify-center gap-4">
                <!-- Animated Shield Icon -->
                <div class="relative">
                    <div
                        class="flex h-20 w-20 items-center justify-center rounded-full bg-gradient-to-br from-violet-500/20 to-indigo-500/20"
                    >
                        <div
                            class="flex h-14 w-14 items-center justify-center rounded-full bg-gradient-to-br from-violet-500 to-indigo-600 shadow-lg shadow-violet-500/30"
                        >
                            <ShieldCheck class="h-7 w-7 text-white" />
                        </div>
                    </div>
                    <!-- Decorative ring -->
                    <div
                        class="absolute inset-0 animate-pulse rounded-full border-2 border-violet-500/20"
                    />
                </div>

                <div class="space-y-2 text-center">
                    <DialogTitle class="text-xl font-semibold tracking-tight">
                        Enter Your PIN
                    </DialogTitle>
                    <DialogDescription class="text-muted-foreground">
                        Please enter your 4-digit PIN to access settings
                    </DialogDescription>
                </div>
            </DialogHeader>

            <div class="flex flex-col items-center gap-6 py-4">
                <!-- PIN Input -->
                <div
                    ref="pinInputContainerRef"
                    class="flex flex-col items-center gap-3"
                >
                    <PinInput
                        id="verification-pin"
                        v-model="pin"
                        type="number"
                        placeholder="○"
                        :mask="true"
                        :disabled="isVerifying"
                        class="justify-center"
                    >
                        <PinInputGroup class="gap-3">
                            <PinInputSlot
                                v-for="(_, index) in 4"
                                :key="index"
                                :index="index"
                                class="h-14 w-14 rounded-xl border-2 text-xl font-semibold transition-all duration-200 focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20"
                                :class="{
                                    'border-destructive': error,
                                    'border-violet-500/50': pin[index] && !error,
                                }"
                            />
                        </PinInputGroup>
                    </PinInput>

                    <!-- Error Message -->
                    <Transition
                        enter-active-class="transition ease-out duration-200"
                        enter-from-class="opacity-0 -translate-y-1"
                        enter-to-class="opacity-100 translate-y-0"
                        leave-active-class="transition ease-in duration-150"
                        leave-from-class="opacity-100 translate-y-0"
                        leave-to-class="opacity-0 -translate-y-1"
                    >
                        <div
                            v-if="error"
                            class="flex items-center gap-2 rounded-lg bg-destructive/10 px-3 py-2 text-sm text-destructive"
                        >
                            <AlertCircle class="h-4 w-4" />
                            <span>{{ error }}</span>
                        </div>
                    </Transition>
                </div>

                <!-- Loading indicator -->
                <Transition
                    enter-active-class="transition ease-out duration-200"
                    enter-from-class="opacity-0"
                    enter-to-class="opacity-100"
                    leave-active-class="transition ease-in duration-150"
                    leave-from-class="opacity-100"
                    leave-to-class="opacity-0"
                >
                    <div
                        v-if="isVerifying"
                        class="flex items-center gap-2 text-sm text-muted-foreground"
                    >
                        <Lock class="h-4 w-4 animate-pulse" />
                        <span>Verifying...</span>
                    </div>
                </Transition>

                <!-- Cancel Button -->
                <Button
                    variant="ghost"
                    class="text-muted-foreground hover:text-foreground"
                    @click="handleCancel"
                    :disabled="isVerifying"
                >
                    Cancel
                </Button>
            </div>

            <!-- Bottom decorative element -->
            <div
                class="absolute bottom-0 left-0 right-0 h-1 overflow-hidden rounded-b-lg"
            >
                <div
                    class="h-full w-full bg-gradient-to-r from-violet-500 via-indigo-500 to-violet-500"
                    :class="{ 'animate-pulse': isVerifying }"
                />
            </div>
        </DialogContent>
    </Dialog>
</template>

<style scoped>
@keyframes shake {
    0%, 100% { transform: translateX(0); }
    10%, 30%, 50%, 70%, 90% { transform: translateX(-4px); }
    20%, 40%, 60%, 80% { transform: translateX(4px); }
}

.animate-shake {
    animation: shake 0.5s ease-in-out;
}
</style>


