<script setup lang="ts">
import ProfilesController from '@/actions/App/Http/Controllers/Settings/ProfilesController';
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useInitials } from '@/composables/useInitials';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { Form, Head, router } from '@inertiajs/vue3';
import { Camera, Loader2, Plus, Star, Trash2, UserCircle } from 'lucide-vue-next';
import { ref, computed } from 'vue';

interface Profile {
    id: string;
    name: string;
    avatar: string | null;
    profile_image_path: string | null;
    age_group: string;
    age_group_label: string;
    is_default: boolean;
    created_at: string;
}

interface AgeGroup {
    label: string;
    range: string;
    emoji: string;
}

interface Props {
    profiles: Profile[];
    ageGroups: Record<string, AgeGroup>;
}

const props = defineProps<Props>();

const { getInitials } = useInitials();

const isCreateDialogOpen = ref(false);
const isEditDialogOpen = ref(false);
const isDeleteDialogOpen = ref(false);
const selectedProfile = ref<Profile | null>(null);

const photoInput = ref<HTMLInputElement | null>(null);
const uploadingPhoto = ref(false);
const photoError = ref<string | null>(null);

const newProfileName = ref('');
const newProfileAgeGroup = ref('8');
const editProfileName = ref('');
const editProfileAgeGroup = ref('8');
const createErrors = ref<Record<string, string>>({});
const editErrors = ref<Record<string, string>>({});
const isCreating = ref(false);
const isUpdating = ref(false);
const isDeleting = ref(false);
const isSettingDefault = ref(false);

const ageGroupOptions = computed(() => {
    return Object.entries(props.ageGroups).map(([value, data]) => ({
        value,
        label: `${data.emoji} ${data.label}`,
        range: data.range,
    }));
});

const openCreateDialog = () => {
    newProfileName.value = '';
    newProfileAgeGroup.value = '8';
    createErrors.value = {};
    isCreateDialogOpen.value = true;
};

const openEditDialog = (profile: Profile) => {
    selectedProfile.value = profile;
    editProfileName.value = profile.name;
    editProfileAgeGroup.value = profile.age_group;
    editErrors.value = {};
    isEditDialogOpen.value = true;
};

const openDeleteDialog = (profile: Profile) => {
    selectedProfile.value = profile;
    isDeleteDialogOpen.value = true;
};

const createProfile = () => {
    isCreating.value = true;
    createErrors.value = {};

    router.post(
        ProfilesController.store.url(),
        {
            name: newProfileName.value,
            age_group: newProfileAgeGroup.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                isCreateDialogOpen.value = false;
                newProfileName.value = '';
                newProfileAgeGroup.value = '8';
            },
            onError: (errors) => {
                createErrors.value = errors;
            },
            onFinish: () => {
                isCreating.value = false;
            },
        },
    );
};

const updateProfile = () => {
    if (!selectedProfile.value) return;

    isUpdating.value = true;
    editErrors.value = {};

    router.patch(
        ProfilesController.update.url(selectedProfile.value.id),
        {
            name: editProfileName.value,
            age_group: editProfileAgeGroup.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                isEditDialogOpen.value = false;
                selectedProfile.value = null;
            },
            onError: (errors) => {
                editErrors.value = errors;
            },
            onFinish: () => {
                isUpdating.value = false;
            },
        },
    );
};

const deleteProfile = () => {
    if (!selectedProfile.value) return;

    isDeleting.value = true;

    router.delete(ProfilesController.destroy.url(selectedProfile.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            isDeleteDialogOpen.value = false;
            selectedProfile.value = null;
        },
        onFinish: () => {
            isDeleting.value = false;
        },
    });
};

const setDefaultProfile = (profile: Profile) => {
    isSettingDefault.value = true;

    router.post(ProfilesController.setDefault.url(profile.id), {}, {
        preserveScroll: true,
        onFinish: () => {
            isSettingDefault.value = false;
        },
    });
};

const selectNewPhoto = (profile: Profile) => {
    selectedProfile.value = profile;
    photoInput.value?.click();
};

const updatePhotoPreview = () => {
    const photo = photoInput.value?.files?.[0];
    if (!photo || !selectedProfile.value) return;

    photoError.value = null;

    const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    if (!validTypes.includes(photo.type)) {
        photoError.value = 'Please select a JPG, PNG, GIF, or WebP image.';
        return;
    }

    if (photo.size > 2 * 1024 * 1024) {
        photoError.value = 'The photo must not be larger than 2MB.';
        return;
    }

    uploadPhoto();
};

const uploadPhoto = () => {
    if (!photoInput.value?.files?.[0] || !selectedProfile.value) return;

    uploadingPhoto.value = true;
    photoError.value = null;

    const formData = new FormData();
    formData.append('image', photoInput.value.files[0]);

    router.post(ProfilesController.updateImage.url(selectedProfile.value.id), formData, {
        preserveScroll: true,
        onError: (errors) => {
            photoError.value = errors.image || 'Failed to upload photo.';
        },
        onFinish: () => {
            uploadingPhoto.value = false;
            if (photoInput.value) {
                photoInput.value.value = '';
            }
        },
    });
};

const deletePhoto = (profile: Profile) => {
    router.delete(ProfilesController.destroyImage.url(profile.id), {
        preserveScroll: true,
    });
};

const getAgeGroupEmoji = (ageGroup: string): string => {
    return props.ageGroups[ageGroup]?.emoji || '👤';
};
</script>

<template>
    <AppLayout>
        <Head title="Manage Profiles" />

        <SettingsLayout>
            <div class="space-y-6">
                <div class="flex items-center justify-between">
                    <HeadingSmall
                        title="Manage Profiles"
                        description="Create and manage viewer profiles for personalized content"
                    />
                    <Button @click="openCreateDialog" size="sm" class="cursor-pointer">
                        <Plus class="mr-2 h-4 w-4" />
                        Add Profile
                    </Button>
                </div>

                <!-- Hidden file input -->
                <input
                    ref="photoInput"
                    type="file"
                    class="hidden"
                    accept="image/jpeg,image/jpg,image/png,image/gif,image/webp"
                    @change="updatePhotoPreview"
                />

                <!-- Profiles Grid -->
                <div class="grid gap-4 sm:grid-cols-2">
                    <div
                        v-for="profile in profiles"
                        :key="profile.id"
                        class="group relative rounded-xl border border-border bg-card p-4 transition-all hover:shadow-md"
                        :class="{ 'ring-2 ring-primary/50': profile.is_default }"
                    >
                        <div class="flex items-start gap-4">
                            <!-- Profile Avatar -->
                            <div class="relative">
                                <Avatar class="h-16 w-16 rounded-full ring-2 ring-border">
                                    <AvatarImage
                                        v-if="profile.avatar"
                                        :src="profile.avatar"
                                        :alt="profile.name"
                                        class="object-cover"
                                    />
                                    <AvatarFallback class="text-lg font-medium bg-gradient-to-br from-violet-500 to-purple-600 text-white">
                                        {{ getInitials(profile.name) }}
                                    </AvatarFallback>
                                </Avatar>

                                <!-- Photo upload overlay -->
                                <button
                                    type="button"
                                    @click="selectNewPhoto(profile)"
                                    :disabled="uploadingPhoto"
                                    class="absolute inset-0 flex items-center justify-center rounded-full bg-black/50 opacity-0 transition-opacity group-hover:opacity-100 focus:opacity-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                    aria-label="Change profile photo"
                                >
                                    <Loader2 v-if="uploadingPhoto && selectedProfile?.id === profile.id" class="h-5 w-5 animate-spin text-white" />
                                    <Camera v-else class="h-5 w-5 text-white" />
                                </button>
                            </div>

                            <!-- Profile Info -->
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <h3 class="font-semibold truncate">{{ profile.name }}</h3>
                                    <Badge v-if="profile.is_default" variant="secondary" class="shrink-0">
                                        <Star class="mr-1 h-3 w-3" />
                                        Default
                                    </Badge>
                                </div>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    {{ getAgeGroupEmoji(profile.age_group) }} {{ profile.age_group_label }} ({{ ageGroups[profile.age_group]?.range }})
                                </p>

                                <!-- Action Buttons -->
                                <div class="mt-3 flex flex-wrap items-center gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        @click="openEditDialog(profile)"
                                    >
                                        Edit
                                    </Button>
                                    <Button
                                        v-if="!profile.is_default"
                                        variant="outline"
                                        size="sm"
                                        @click="setDefaultProfile(profile)"
                                        :disabled="isSettingDefault"
                                    >
                                        <Star class="mr-1 h-3 w-3" />
                                        Set Default
                                    </Button>
                                    <Button
                                        v-if="profile.avatar"
                                        variant="ghost"
                                        size="sm"
                                        class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                        @click="deletePhoto(profile)"
                                    >
                                        Remove Photo
                                    </Button>
                                    <Button
                                        v-if="!profile.is_default"
                                        variant="ghost"
                                        size="sm"
                                        class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                        @click="openDeleteDialog(profile)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <InputError v-if="photoError" :message="photoError" />

                <!-- Empty State -->
                <div
                    v-if="profiles.length === 0"
                    class="flex flex-col items-center justify-center rounded-xl border border-dashed border-border p-12 text-center"
                >
                    <UserCircle class="h-12 w-12 text-muted-foreground/50" />
                    <h3 class="mt-4 font-semibold">No profiles yet</h3>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Create your first profile to get started
                    </p>
                    <Button @click="openCreateDialog" class="mt-4">
                        <Plus class="mr-2 h-4 w-4" />
                        Create Profile
                    </Button>
                </div>
            </div>

            <!-- Create Profile Dialog -->
            <Dialog v-model:open="isCreateDialogOpen">
                <DialogContent class="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Create Profile</DialogTitle>
                        <DialogDescription>
                            Add a new viewer profile with age-appropriate content settings
                        </DialogDescription>
                    </DialogHeader>

                    <div class="space-y-4 py-4">
                        <div class="space-y-2">
                            <Label for="create-name">Profile Name</Label>
                            <Input
                                id="create-name"
                                v-model="newProfileName"
                                placeholder="e.g., Kids, Teens, Family"
                                @keyup.enter="createProfile"
                            />
                            <InputError :message="createErrors.name" />
                        </div>

                        <div class="space-y-2">
                            <Label for="create-age-group">Age Group</Label>
                            <Select v-model="newProfileAgeGroup">
                                <SelectTrigger>
                                    <SelectValue placeholder="Select age group" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="option in ageGroupOptions"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }} ({{ option.range }})
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="createErrors.age_group" />
                        </div>
                    </div>

                    <DialogFooter>
                        <Button variant="outline" @click="isCreateDialogOpen = false">
                            Cancel
                        </Button>
                        <Button @click="createProfile" :disabled="isCreating || !newProfileName">
                            <Loader2 v-if="isCreating" class="mr-2 h-4 w-4 animate-spin" />
                            Create Profile
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- Edit Profile Dialog -->
            <Dialog v-model:open="isEditDialogOpen">
                <DialogContent class="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Edit Profile</DialogTitle>
                        <DialogDescription>
                            Update the profile name and age group settings
                        </DialogDescription>
                    </DialogHeader>

                    <div class="space-y-4 py-4">
                        <div class="space-y-2">
                            <Label for="edit-name">Profile Name</Label>
                            <Input
                                id="edit-name"
                                v-model="editProfileName"
                                placeholder="Profile name"
                                @keyup.enter="updateProfile"
                            />
                            <InputError :message="editErrors.name" />
                        </div>

                        <div class="space-y-2">
                            <Label for="edit-age-group">Age Group</Label>
                            <Select v-model="editProfileAgeGroup">
                                <SelectTrigger>
                                    <SelectValue placeholder="Select age group" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="option in ageGroupOptions"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }} ({{ option.range }})
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="editErrors.age_group" />
                        </div>
                    </div>

                    <DialogFooter>
                        <Button variant="outline" @click="isEditDialogOpen = false">
                            Cancel
                        </Button>
                        <Button @click="updateProfile" :disabled="isUpdating || !editProfileName">
                            <Loader2 v-if="isUpdating" class="mr-2 h-4 w-4 animate-spin" />
                            Save Changes
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- Delete Profile Dialog -->
            <Dialog v-model:open="isDeleteDialogOpen">
                <DialogContent class="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Delete Profile</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to delete "{{ selectedProfile?.name }}"? This action cannot be undone.
                        </DialogDescription>
                    </DialogHeader>

                    <DialogFooter>
                        <Button variant="outline" @click="isDeleteDialogOpen = false">
                            Cancel
                        </Button>
                        <Button variant="destructive" @click="deleteProfile" :disabled="isDeleting">
                            <Loader2 v-if="isDeleting" class="mr-2 h-4 w-4 animate-spin" />
                            Delete Profile
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </SettingsLayout>
    </AppLayout>
</template>

