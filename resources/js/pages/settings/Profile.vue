<script setup lang="ts">
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import { send } from '@/routes/verification';
import { Form, Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

import DeleteUser from '@/components/DeleteUser.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useInitials } from '@/composables/useInitials';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { Camera, Trash2, Loader2 } from 'lucide-vue-next';

interface Props {
    mustVerifyEmail: boolean;
    status?: string;
}

defineProps<Props>();

const page = usePage();
const user = computed(() => page.props.auth.user);

const { getInitials } = useInitials();

const photoInput = ref<HTMLInputElement | null>(null);
const photoPreview = ref<string | null>(null);
const uploadingPhoto = ref(false);
const deletingPhoto = ref(false);
const photoError = ref<string | null>(null);

const selectNewPhoto = () => {
    photoInput.value?.click();
};

const updatePhotoPreview = () => {
    const photo = photoInput.value?.files?.[0];
    if (!photo) return;

    photoError.value = null;

    // Validate file type
    const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    if (!validTypes.includes(photo.type)) {
        photoError.value = 'Please select a JPG, PNG, GIF, or WebP image.';
        return;
    }

    // Validate file size (2MB)
    if (photo.size > 2 * 1024 * 1024) {
        photoError.value = 'The photo must not be larger than 2MB.';
        return;
    }

    const reader = new FileReader();
    reader.onload = (e) => {
        photoPreview.value = e.target?.result as string;
    };
    reader.readAsDataURL(photo);
};

const uploadPhoto = () => {
    if (!photoInput.value?.files?.[0]) return;

    uploadingPhoto.value = true;
    photoError.value = null;

    const formData = new FormData();
    formData.append('photo', photoInput.value.files[0]);

    router.post(ProfileController.updatePhoto.url(), formData, {
        preserveScroll: true,
        onSuccess: () => {
            photoPreview.value = null;
            if (photoInput.value) {
                photoInput.value.value = '';
            }
        },
        onError: (errors) => {
            photoError.value = errors.photo || 'Failed to upload photo.';
        },
        onFinish: () => {
            uploadingPhoto.value = false;
        },
    });
};

const deletePhoto = () => {
    deletingPhoto.value = true;
    photoError.value = null;

    router.delete(ProfileController.destroyPhoto.url(), {
        preserveScroll: true,
        onFinish: () => {
            deletingPhoto.value = false;
            photoPreview.value = null;
        },
    });
};

const clearPhotoFileInput = () => {
    if (photoInput.value) {
        photoInput.value.value = '';
    }
    photoPreview.value = null;
    photoError.value = null;
};
</script>

<template>
    <AppLayout>
        <Head title="Account settings" />

        <SettingsLayout>


            <!-- Profile Information Section -->
            <div class="flex flex-col space-y-6">
                <HeadingSmall
                    title="Account information"
                    description="Update your name and email address"
                />

                <Form
                    v-bind="ProfileController.update.form()"
                    class="space-y-6"
                    v-slot="{ errors, processing, recentlySuccessful }"
                >
                    <div class="grid gap-2">
                        <Label for="name">Name</Label>
                        <Input
                            id="name"
                            class="mt-1 block w-full"
                            name="name"
                            :default-value="user.name"
                            required
                            autocomplete="name"
                            placeholder="Full name"
                        />
                        <InputError class="mt-2" :message="errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="email">Email address</Label>
                        <Input
                            id="email"
                            type="email"
                            class="mt-1 block w-full"
                            name="email"
                            :default-value="user.email"
                            required
                            autocomplete="username"
                            placeholder="Email address"
                        />
                        <InputError class="mt-2" :message="errors.email" />
                    </div>

                    <div v-if="mustVerifyEmail && !user.email_verified_at">
                        <p class="-mt-4 text-sm text-muted-foreground">
                            Your email address is unverified.
                            <Link
                                :href="send()"
                                as="button"
                                class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                            >
                                Click here to resend the verification email.
                            </Link>
                        </p>

                        <div
                            v-if="status === 'verification-link-sent'"
                            class="mt-2 text-sm font-medium text-green-600"
                        >
                            A new verification link has been sent to your email
                            address.
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        <Button
                            :disabled="processing"
                            data-test="update-profile-button"
                            >Save</Button
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

            <DeleteUser />
        </SettingsLayout>
    </AppLayout>
</template>
