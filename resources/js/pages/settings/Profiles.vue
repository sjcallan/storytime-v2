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
import { Textarea } from '@/components/ui/textarea';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Camera, ExternalLink, Image, Loader2, Pencil, Plus, Sparkles, Star, Trash2, Upload, UserCircle, Wand2 } from 'lucide-vue-next';
import { ref, computed } from 'vue';
import axios from 'axios';

interface Profile {
    id: string;
    name: string;
    avatar: string | null;
    profile_image_path: string | null;
    profile_image_prompt: string | null;
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

// Edit modal image state
const editPhotoInput = ref<HTMLInputElement | null>(null);
const editImageDescription = ref('');
const isGeneratingImage = ref(false);
const generationError = ref<string | null>(null);

// Example prompts for AI profile images
const examplePrompts = [
    'A brave young adventurer with flowing hair and determined eyes',
    'A wise elder with kind eyes and silver hair',
    'A playful child with freckles and a big smile',
    'A mysterious figure with a hood and glowing eyes',
    'A nature-loving character surrounded by leaves and flowers',
    'A tech-savvy hero with futuristic gear',
];

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
    editImageDescription.value = profile.profile_image_prompt || '';
    editErrors.value = {};
    generationError.value = null;
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

const selectEditPhoto = () => {
    editPhotoInput.value?.click();
};

const handleEditPhotoChange = () => {
    const photo = editPhotoInput.value?.files?.[0];
    if (!photo || !selectedProfile.value) return;

    const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    if (!validTypes.includes(photo.type)) {
        generationError.value = 'Please select a JPG, PNG, GIF, or WebP image.';
        return;
    }

    if (photo.size > 2 * 1024 * 1024) {
        generationError.value = 'The photo must not be larger than 2MB.';
        return;
    }

    uploadingPhoto.value = true;
    generationError.value = null;

    const formData = new FormData();
    formData.append('image', photo);

    router.post(ProfilesController.updateImage.url(selectedProfile.value.id), formData, {
        preserveScroll: true,
        onSuccess: (page: any) => {
            // Sync selectedProfile with updated profiles from server
            const updatedProfile = page.props.profiles?.find((p: Profile) => p.id === selectedProfile.value?.id);
            if (updatedProfile && selectedProfile.value) {
                selectedProfile.value.avatar = updatedProfile.avatar;
                selectedProfile.value.profile_image_path = updatedProfile.profile_image_path;
            }
        },
        onError: (errors) => {
            generationError.value = errors.image || 'Failed to upload photo.';
        },
        onFinish: () => {
            uploadingPhoto.value = false;
            if (editPhotoInput.value) {
                editPhotoInput.value.value = '';
            }
        },
    });
};

const handleGenerateImage = async () => {
    if (!selectedProfile.value || editImageDescription.value.length < 10) {
        generationError.value = 'Please provide a description of at least 10 characters.';
        return;
    }

    isGeneratingImage.value = true;
    generationError.value = null;

    try {
        const response = await axios.post(
            ProfilesController.generateImage.url(selectedProfile.value.id),
            { description: editImageDescription.value }
        );

        if (response.data.success) {
            // Update selectedProfile reactively for the modal
            selectedProfile.value.avatar = response.data.avatar;
            selectedProfile.value.profile_image_prompt = editImageDescription.value;
            
            // Update the profile in the profiles list for the grid
            const profileIndex = props.profiles.findIndex(p => p.id === selectedProfile.value?.id);
            if (profileIndex !== -1) {
                props.profiles[profileIndex].avatar = response.data.avatar;
                props.profiles[profileIndex].profile_image_prompt = editImageDescription.value;
            }
        } else {
            generationError.value = response.data.error || 'Failed to generate image.';
        }
    } catch (error: any) {
        console.error('Failed to generate profile image:', error);
        generationError.value = error.response?.data?.error || 'An error occurred while generating the image.';
    } finally {
        isGeneratingImage.value = false;
    }
};

const handleRemoveEditPhoto = () => {
    if (!selectedProfile.value) return;
    
    router.delete(ProfilesController.destroyImage.url(selectedProfile.value.id), {
        preserveScroll: true,
        onSuccess: (page: any) => {
            // Sync selectedProfile with updated profiles from server
            const updatedProfile = page.props.profiles?.find((p: Profile) => p.id === selectedProfile.value?.id);
            if (updatedProfile && selectedProfile.value) {
                selectedProfile.value.avatar = updatedProfile.avatar;
                selectedProfile.value.profile_image_path = updatedProfile.profile_image_path;
                selectedProfile.value.profile_image_prompt = updatedProfile.profile_image_prompt;
            }
        },
    });
};

const useExamplePrompt = (prompt: string) => {
    editImageDescription.value = prompt;
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
                                    <AvatarFallback class="text-lg font-medium bg-linear-to-br from-violet-500 to-purple-600 text-white">
                                        {{ getInitials(profile.name) }}
                                    </AvatarFallback>
                                </Avatar>

                                <!-- Edit profile overlay -->
                                <button
                                    type="button"
                                    @click="openEditDialog(profile)"
                                    class="absolute inset-0 flex items-center justify-center rounded-full bg-black/50 opacity-0 transition-opacity group-hover:opacity-100 focus:opacity-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-ring cursor-pointer"
                                    aria-label="Edit profile"
                                >
                                    <Pencil class="h-5 w-5 text-white" />
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
                                        class="cursor-pointer"
                                        @click="openEditDialog(profile)"
                                    >
                                        Edit
                                    </Button>
                                    <Button
                                        v-if="!profile.is_default"
                                        variant="outline"
                                        size="sm"
                                        class="cursor-pointer"
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
                                        class="text-destructive hover:bg-destructive/10 hover:text-destructive cursor-pointer"
                                        @click="deletePhoto(profile)"
                                    >
                                        Remove Photo
                                    </Button>
                                    <Button
                                        v-if="!profile.is_default"
                                        variant="ghost"
                                        size="sm"
                                        class="text-destructive hover:bg-destructive/10 hover:text-destructive cursor-pointer"
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
                <DialogContent class="sm:max-w-lg max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle class="flex items-center gap-2">
                            <UserCircle class="h-5 w-5 text-violet-500" />
                            Edit Profile
                        </DialogTitle>
                        <DialogDescription>
                            Update the profile name, age group, and profile image
                        </DialogDescription>
                    </DialogHeader>

                    <!-- Hidden file input for photo upload -->
                    <input
                        ref="editPhotoInput"
                        type="file"
                        class="hidden"
                        accept="image/jpeg,image/jpg,image/png,image/gif,image/webp"
                        @change="handleEditPhotoChange"
                    />

                    <div class="space-y-4 py-4">
                        <!-- Profile Name -->
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

                        <!-- Age Group -->
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

                        <!-- Profile Image Section -->
                        <Collapsible class="space-y-2" :default-open="!!selectedProfile?.avatar">
                            <CollapsibleTrigger class="flex w-full items-center justify-between rounded-lg border border-border/50 bg-muted/30 px-3 py-2 text-sm font-medium hover:bg-muted/50 transition-colors">
                                <div class="flex items-center gap-2">
                                    <Image class="h-4 w-4 text-violet-500" />
                                    Profile Image
                                    <span v-if="selectedProfile?.avatar" class="text-xs text-muted-foreground">(Active)</span>
                                </div>
                                <span class="text-xs text-muted-foreground">Click to expand</span>
                            </CollapsibleTrigger>
                            
                            <CollapsibleContent class="space-y-4 pt-2">
                                <!-- Current Profile Image Preview -->
                                <div class="flex items-center gap-4">
                                    <a
                                        v-if="selectedProfile?.avatar"
                                        :href="selectedProfile.avatar"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="relative block cursor-pointer group"
                                        title="Click to view full image"
                                    >
                                        <Avatar class="h-20 w-20 rounded-full ring-2 ring-border transition-all group-hover:ring-violet-500 group-hover:ring-offset-2">
                                            <AvatarImage
                                                :src="selectedProfile.avatar"
                                                :alt="selectedProfile?.name"
                                                class="object-cover"
                                            />
                                        </Avatar>
                                        <div class="absolute inset-0 flex items-center justify-center rounded-full bg-black/40 opacity-0 transition-opacity group-hover:opacity-100">
                                            <ExternalLink class="h-5 w-5 text-white" />
                                        </div>
                                    </a>
                                    <div v-else class="relative">
                                        <Avatar class="h-20 w-20 rounded-full ring-2 ring-border">
                                            <AvatarFallback class="text-xl font-medium bg-linear-to-br from-violet-500 to-purple-600 text-white">
                                                {{ getInitials(selectedProfile?.name || '') }}
                                            </AvatarFallback>
                                        </Avatar>
                                    </div>
                                    <div class="flex flex-col gap-2">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            @click="selectEditPhoto"
                                            :disabled="uploadingPhoto || isGeneratingImage"
                                        >
                                            <Upload v-if="!uploadingPhoto" class="mr-2 h-4 w-4" />
                                            <Loader2 v-else class="mr-2 h-4 w-4 animate-spin" />
                                            Upload Photo
                                        </Button>
                                        <Button
                                            v-if="selectedProfile?.avatar"
                                            variant="ghost"
                                            size="sm"
                                            class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                            @click="handleRemoveEditPhoto"
                                            :disabled="uploadingPhoto || isGeneratingImage"
                                        >
                                            <Trash2 class="mr-2 h-4 w-4" />
                                            Remove
                                        </Button>
                                    </div>
                                </div>

                                <!-- AI Image Generation -->
                                <div class="space-y-3 rounded-lg border border-dashed border-violet-300/50 bg-violet-50/30 dark:bg-violet-950/20 p-3">
                                    <div class="flex items-center gap-2 text-sm font-medium">
                                        <Sparkles class="h-4 w-4 text-violet-500" />
                                        Generate with AI
                                        <Badge variant="secondary" class="text-xs">Graphic Novel Style</Badge>
                                    </div>
                                    
                                    <div class="space-y-2">
                                        <Label for="edit-image-description" class="text-xs text-muted-foreground">
                                            Describe your character
                                        </Label>
                                        <Textarea
                                            id="edit-image-description"
                                            v-model="editImageDescription"
                                            placeholder="e.g., A brave young adventurer with flowing hair and determined eyes..."
                                            rows="2"
                                            class="resize-none text-sm"
                                            :disabled="isGeneratingImage"
                                        />
                                    </div>

                                    <!-- Example Prompts -->
                                    <div class="flex flex-wrap gap-1.5">
                                        <button
                                            v-for="(prompt, index) in examplePrompts.slice(0, 3)"
                                            :key="index"
                                            class="text-xs px-2 py-0.5 rounded-full bg-violet-100 dark:bg-violet-900/40 text-violet-700 dark:text-violet-300 hover:bg-violet-200 dark:hover:bg-violet-800/50 transition-colors truncate max-w-[140px]"
                                            @click="useExamplePrompt(prompt)"
                                            :disabled="isGeneratingImage"
                                            :title="prompt"
                                        >
                                            {{ prompt.slice(0, 25) }}...
                                        </button>
                                    </div>

                                    <!-- Generate Button -->
                                    <Button
                                        @click="handleGenerateImage"
                                        :disabled="editImageDescription.length < 10 || isGeneratingImage"
                                        variant="outline"
                                        size="sm"
                                        class="w-full border-violet-300 hover:bg-violet-100 dark:hover:bg-violet-900/30"
                                    >
                                        <Wand2 v-if="!isGeneratingImage" class="h-4 w-4 mr-2 text-violet-500" />
                                        <Loader2 v-else class="h-4 w-4 mr-2 animate-spin" />
                                        {{ isGeneratingImage ? 'Generating... (30-60s)' : 'Generate Avatar' }}
                                    </Button>
                                </div>

                                <!-- Error Message -->
                                <div v-if="generationError" class="rounded-lg bg-destructive/10 p-2 text-xs text-destructive">
                                    {{ generationError }}
                                </div>
                            </CollapsibleContent>
                        </Collapsible>
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

