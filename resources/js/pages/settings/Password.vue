<script setup lang="ts">
import PasswordController from '@/actions/App/Http/Controllers/Settings/PasswordController';
import PinController from '@/actions/App/Http/Controllers/Settings/PinController';
import InputError from '@/components/InputError.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { Form, Head, usePage, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import {
    PinInput,
    PinInputGroup,
    PinInputSlot,
} from '@/components/ui/pin-input';
import type { AppPageProps } from '@/types';
import { ShieldCheck, ShieldOff, Lock } from 'lucide-vue-next';

const page = usePage<AppPageProps>();
const hasPin = computed(() => page.props.auth.hasPin);

const pinValue = ref<number[]>([]);
const pinConfirmValue = ref<number[]>([]);

const removingPin = ref(false);

function handleRemovePin() {
    removingPin.value = true;
    router.delete(PinController.destroy.url(), {
        preserveScroll: true,
        onFinish: () => {
            removingPin.value = false;
        },
    });
}
</script>

<template>
    <AppLayout>
        <Head title="Password settings" />

        <SettingsLayout>
            <div class="space-y-8">
                <!-- Password Section -->
                <div class="space-y-6">
                    <HeadingSmall
                        title="Update password"
                        description="Ensure your account is using a long, random password to stay secure"
                    />

                    <Form
                        v-bind="PasswordController.update.form()"
                        :options="{
                            preserveScroll: true,
                        }"
                        reset-on-success
                        :reset-on-error="[
                            'password',
                            'password_confirmation',
                            'current_password',
                        ]"
                        class="space-y-6"
                        v-slot="{ errors, processing, recentlySuccessful }"
                    >
                        <div class="grid gap-2">
                            <Label for="current_password">Current password</Label>
                            <Input
                                id="current_password"
                                name="current_password"
                                type="password"
                                class="mt-1 block w-full"
                                autocomplete="current-password"
                                placeholder="Current password"
                            />
                            <InputError :message="errors.current_password" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="password">New password</Label>
                            <Input
                                id="password"
                                name="password"
                                type="password"
                                class="mt-1 block w-full"
                                autocomplete="new-password"
                                placeholder="New password"
                            />
                            <InputError :message="errors.password" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="password_confirmation"
                                >Confirm password</Label
                            >
                            <Input
                                id="password_confirmation"
                                name="password_confirmation"
                                type="password"
                                class="mt-1 block w-full"
                                autocomplete="new-password"
                                placeholder="Confirm password"
                            />
                            <InputError :message="errors.password_confirmation" />
                        </div>

                        <div class="flex items-center gap-4">
                            <Button
                                :disabled="processing"
                                data-test="update-password-button"
                                >Save password</Button
                            >

                            <Transition
                                enter-active-class="transition ease-in-out"
                                enter-from-class="opacity-0"
                                leave-active-class="transition ease-in-out"
                                leave-to-class="opacity-0"
                            >
                                <p
                                    v-show="recentlySuccessful"
                                    class="text-sm text-neutral-600"
                                >
                                    Saved.
                                </p>
                            </Transition>
                        </div>
                    </Form>
                </div>

                <Separator />

                <!-- PIN Section -->
                <div class="space-y-6">
                    <HeadingSmall
                        title="Settings PIN"
                        description="Set a 4-digit PIN to protect access to your account settings"
                    />

                    <!-- PIN Status Badge -->
                    <div
                        class="flex items-center gap-3 rounded-lg border p-4"
                        :class="hasPin ? 'border-emerald-500/30 bg-emerald-500/5' : 'border-border bg-muted/30'"
                    >
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-full"
                            :class="hasPin ? 'bg-emerald-500/20 text-emerald-600 dark:text-emerald-400' : 'bg-muted text-muted-foreground'"
                        >
                            <ShieldCheck v-if="hasPin" class="h-5 w-5" />
                            <ShieldOff v-else class="h-5 w-5" />
                        </div>
                        <div class="flex-1">
                            <p class="font-medium" :class="hasPin ? 'text-emerald-700 dark:text-emerald-300' : 'text-foreground'">
                                {{ hasPin ? 'PIN Protection Enabled' : 'No PIN Set' }}
                            </p>
                            <p class="text-sm text-muted-foreground">
                                {{
                                    hasPin
                                        ? 'Your settings are protected with a 4-digit PIN'
                                        : 'Add a PIN to protect your settings from unauthorized access'
                                }}
                            </p>
                        </div>
                    </div>

                    <!-- Set/Update PIN Form -->
                    <Form
                        v-bind="PinController.store.form()"
                        :options="{
                            preserveScroll: true,
                        }"
                        reset-on-success
                        class="space-y-6"
                        v-slot="{ errors, processing, recentlySuccessful }"
                        @success="pinValue = []; pinConfirmValue = []"
                    >
                        <div class="grid gap-4">
                            <div class="grid gap-2">
                                <Label>{{ hasPin ? 'New PIN' : 'Enter PIN' }}</Label>
                                <p class="text-sm text-muted-foreground">
                                    Enter a 4-digit PIN code
                                </p>
                                <PinInput
                                    id="pin"
                                    v-model="pinValue"
                                    type="number"
                                    placeholder="○"
                                    :mask="true"
                                    class="justify-start"
                                >
                                    <PinInputGroup>
                                        <PinInputSlot
                                            v-for="(id, index) in 4"
                                            :key="id"
                                            :index="index"
                                            class="h-12 w-12 text-lg"
                                        />
                                    </PinInputGroup>
                                </PinInput>
                                <input type="hidden" name="pin" :value="pinValue.join('')" />
                                <InputError :message="errors.pin" />
                            </div>

                            <div class="grid gap-2">
                                <Label>Confirm PIN</Label>
                                <p class="text-sm text-muted-foreground">
                                    Re-enter your PIN to confirm
                                </p>
                                <PinInput
                                    id="pin_confirmation"
                                    v-model="pinConfirmValue"
                                    type="number"
                                    placeholder="○"
                                    :mask="true"
                                    class="justify-start"
                                >
                                    <PinInputGroup>
                                        <PinInputSlot
                                            v-for="(id, index) in 4"
                                            :key="id"
                                            :index="index"
                                            class="h-12 w-12 text-lg"
                                        />
                                    </PinInputGroup>
                                </PinInput>
                                <input type="hidden" name="pin_confirmation" :value="pinConfirmValue.join('')" />
                                <InputError :message="errors.pin_confirmation" />
                            </div>
                        </div>

                        <div class="flex items-center gap-4">
                            <Button
                                type="submit"
                                :disabled="processing || pinValue.length !== 4 || pinConfirmValue.length !== 4"
                            >
                                <Lock class="mr-2 h-4 w-4" />
                                {{ hasPin ? 'Update PIN' : 'Set PIN' }}
                            </Button>

                            <Button
                                v-if="hasPin"
                                type="button"
                                variant="outline"
                                :disabled="removingPin"
                                @click="handleRemovePin"
                            >
                                <ShieldOff class="mr-2 h-4 w-4" />
                                Remove PIN
                            </Button>

                            <Transition
                                enter-active-class="transition ease-in-out"
                                enter-from-class="opacity-0"
                                leave-active-class="transition ease-in-out"
                                leave-to-class="opacity-0"
                            >
                                <p
                                    v-show="recentlySuccessful"
                                    class="text-sm text-emerald-600 dark:text-emerald-400"
                                >
                                    PIN saved successfully.
                                </p>
                            </Transition>
                        </div>
                    </Form>
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
