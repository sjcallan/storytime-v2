<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { ArrowLeft, User, Calendar, MapPin, Sparkles, MessageCircle, Pencil, RefreshCw, X, Check, Upload } from 'lucide-vue-next';
import { Spinner } from '@/components/ui/spinner';
import { apiFetch } from '@/composables/ApiFetch';
import type { Character } from './types';
import CharacterChatModal from './CharacterChatModal.vue';

interface Props {
    character: Character;
    isSinglePageMode?: boolean;
    bookId?: string;
}

type ApiFetchFn = (
    request: string,
    method?: string,
    data?: Record<string, unknown> | FormData | null,
    isFormData?: boolean | null,
) => Promise<{ data: unknown; error: unknown }>;

const requestApiFetch = apiFetch as ApiFetchFn;

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'back'): void;
    (e: 'characterUpdated', character: Character): void;
}>();

const showChatModal = ref(false);
const isEditing = ref(false);
const isSaving = ref(false);
const isRegeneratingPortrait = ref(false);
const isUploadingPortrait = ref(false);
const editErrors = ref<Record<string, string>>({});
const portraitUploadError = ref<string | null>(null);
const photoInput = ref<HTMLInputElement | null>(null);

// Edit form data
const editForm = ref({
    name: '',
    age: '',
    gender: '',
    nationality: '',
    description: '',
    backstory: '',
});

// Gender options matching CreateStoryModal
const genderOptions = [
    { value: 'male', label: '👦 Boy' },
    { value: 'female', label: '👧 Girl' },
    { value: 'non-binary', label: '🌟 Non-binary' },
    { value: 'other', label: '✨ Other' },
];

// Initialize edit form when entering edit mode
const startEditing = () => {
    editForm.value = {
        name: props.character.name || '',
        age: props.character.age || '',
        gender: props.character.gender || '',
        nationality: props.character.nationality || '',
        description: props.character.description || '',
        backstory: props.character.backstory || '',
    };
    editErrors.value = {};
    isEditing.value = true;
};

const cancelEditing = () => {
    isEditing.value = false;
    editErrors.value = {};
};

// Save character changes
const saveCharacter = async () => {
    if (!editForm.value.name.trim()) {
        editErrors.value = { name: 'Please give your character a name!' };
        return;
    }

    isSaving.value = true;
    editErrors.value = {};

    try {
        const { data, error } = await requestApiFetch(
            `/api/characters/${props.character.id}`,
            'PUT',
            {
                name: editForm.value.name,
                age: editForm.value.age || null,
                gender: editForm.value.gender || null,
                nationality: editForm.value.nationality || null,
                description: editForm.value.description || null,
                backstory: editForm.value.backstory || null,
            }
        );

        if (error) {
            const message = typeof error === 'object' && error !== null && 'message' in error
                ? (error as { message: string }).message
                : 'Failed to save changes. Please try again.';
            editErrors.value = { general: message };
            return;
        }

        if (data && typeof data === 'object') {
            // Emit the updated character to the parent
            emit('characterUpdated', data as Character);
            isEditing.value = false;
        }
    } catch {
        editErrors.value = { general: 'An unexpected error occurred. Please try again.' };
    } finally {
        isSaving.value = false;
    }
};

// Regenerate portrait
const regeneratePortrait = async () => {
    isRegeneratingPortrait.value = true;

    try {
        const { error } = await requestApiFetch(
            `/api/characters/${props.character.id}/regenerate-portrait`,
            'POST'
        );

        if (error) {
            console.error('Failed to regenerate portrait:', error);
        }
        // Portrait update will come through websocket, no need to handle response
    } catch (err) {
        console.error('Error regenerating portrait:', err);
    } finally {
        // Keep loading state until we receive the websocket update
        // The portrait_image prop will change when the parent receives the update
    }
};

// Trigger the hidden file input for portrait upload
const triggerPhotoUpload = () => {
    photoInput.value?.click();
};

// Handle file selection for portrait upload
const handlePhotoSelected = () => {
    const file = photoInput.value?.files?.[0];
    if (!file) {
        return;
    }

    portraitUploadError.value = null;

    // Validate file type
    const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    if (!validTypes.includes(file.type)) {
        portraitUploadError.value = 'Please select a JPG, PNG, GIF, or WebP image.';
        return;
    }

    // Validate file size (2MB max)
    if (file.size > 2 * 1024 * 1024) {
        portraitUploadError.value = 'The photo must not be larger than 2MB.';
        return;
    }

    uploadPortrait(file);
};

// Upload the portrait image
const uploadPortrait = async (file: File) => {
    isUploadingPortrait.value = true;
    portraitUploadError.value = null;

    const formData = new FormData();
    formData.append('image', file);

    try {
        const { data, error } = await requestApiFetch(
            `/api/characters/${props.character.id}/upload-portrait`,
            'POST',
            formData,
            true
        );

        if (error) {
            const message = typeof error === 'object' && error !== null && 'message' in error
                ? (error as { message: string }).message
                : 'Failed to upload photo. Please try again.';
            portraitUploadError.value = message;
            return;
        }

        if (data && typeof data === 'object') {
            // Emit the updated character to the parent
            emit('characterUpdated', data as Character);
        }
    } catch {
        portraitUploadError.value = 'An unexpected error occurred. Please try again.';
    } finally {
        isUploadingPortrait.value = false;
        // Clear the file input
        if (photoInput.value) {
            photoInput.value.value = '';
        }
    }
};

// Helper to get portrait data as Image object (handles snake_case from API)
// Laravel serializes 'portraitImage' relationship as 'portrait_image' (snake_case)
// So portrait_image can be either an Image object or a string
interface PortraitImageData {
    full_url?: string | null;
    status?: string;
    error?: string | null;
}

const getPortraitImageData = (): PortraitImageData | null => {
    const portraitData = props.character.portrait_image;
    if (portraitData && typeof portraitData === 'object' && 'full_url' in portraitData) {
        return portraitData as PortraitImageData;
    }
    return null;
};

// Watch for portrait image changes to clear regenerating state
watch(
    () => props.character.portrait_image,
    (newPortraitData) => {
        // Clear regenerating state when image is complete
        if (isRegeneratingPortrait.value) {
            if (newPortraitData && typeof newPortraitData === 'object' && 'status' in newPortraitData) {
                const imageData = newPortraitData as PortraitImageData;
                if (imageData.status === 'complete' && imageData.full_url) {
                    isRegeneratingPortrait.value = false;
                }
            } else if (typeof newPortraitData === 'string' && newPortraitData) {
                // Fallback: legacy string field updated
                isRegeneratingPortrait.value = false;
            }
        }
    },
    { deep: true }
);

const openChat = () => {
    showChatModal.value = true;
};

const closeChat = () => {
    showChatModal.value = false;
};

const getAvatarGradient = (characterId: string): string => {
    const gradients = [
        'from-violet-500 to-purple-600',
        'from-blue-500 to-cyan-600',
        'from-emerald-500 to-teal-600',
        'from-amber-500 to-orange-600',
        'from-rose-500 to-pink-600',
        'from-indigo-500 to-blue-600',
        'from-fuchsia-500 to-pink-600',
        'from-sky-500 to-blue-600',
    ];
    
    const hash = characterId.split('').reduce((acc, char) => acc + char.charCodeAt(0), 0);
    return gradients[hash % gradients.length];
};

const getInitials = (name: string): string => {
    return name
        .split(' ')
        .map(word => word[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);
};

const formatGender = (gender: string | null): string => {
    if (!gender) return '';
    return gender.charAt(0).toUpperCase() + gender.slice(1).toLowerCase();
};

// Get the portrait image URL from Image model or legacy field
// Note: Laravel serializes 'portraitImage' relationship as 'portrait_image' (snake_case)
const portraitImageUrl = computed(() => {
    const imageData = getPortraitImageData();
    if (imageData?.full_url) {
        return imageData.full_url;
    }
    // Fallback to legacy string field
    const portraitData = props.character.portrait_image;
    if (typeof portraitData === 'string') {
        return portraitData;
    }
    return null;
});

// Check if portrait has an error
const portraitHasError = computed(() => {
    const imageData = getPortraitImageData();
    return imageData?.status === 'error';
});

// Get portrait error message
const portraitErrorMessage = computed(() => {
    const imageData = getPortraitImageData();
    return imageData?.error || 'Failed to generate portrait';
});

// Check if portrait is processing (pending or processing status)
const isPortraitProcessing = computed(() => {
    const imageData = getPortraitImageData();
    const status = imageData?.status;
    return status === 'pending' || status === 'processing';
});

// Computed for showing portrait loading state
const isPortraitLoading = computed(() => {
    return isRegeneratingPortrait.value || isPortraitProcessing.value || isUploadingPortrait.value;
});

// Display values that update when editing or from props
const displayName = computed(() => isEditing.value ? editForm.value.name : props.character.name);
</script>

<template>
    <div class="relative z-10 flex h-full flex-col overflow-hidden">
        <!-- Back Button -->
        <button
            @click="emit('back')"
            :class="[
                'absolute z-20 flex items-center gap-1.5 rounded-full bg-amber-100/80 dark:bg-amber-200/80 px-3 py-1.5 text-sm font-medium text-amber-800 shadow-sm backdrop-blur-sm transition-all hover:bg-amber-200 hover:scale-105 active:scale-95 left-6',
                isSinglePageMode ? 'top-16' : 'top-6'
            ]"
        >
            <ArrowLeft class="h-4 w-4" />
            <span>Back</span>
        </button>

        <!-- Portrait Image Section (Top Half) -->
        <div class="relative h-1/2 w-full overflow-hidden">
            <!-- Portrait Image - show when we have a URL and not loading -->
            <template v-if="portraitImageUrl && !isPortraitLoading && !portraitHasError">
                <img
                    :src="portraitImageUrl"
                    :alt="character.name"
                    class="h-full w-full object-cover"
                />
            </template>
            <!-- Loading/Regenerating/Uploading state -->
            <template v-else-if="isPortraitLoading">
                <div
                    :class="[
                        'h-full w-full flex flex-col items-center justify-center bg-linear-to-br',
                        getAvatarGradient(character.id)
                    ]"
                >
                    <Spinner class="h-12 w-12 text-white/80 mb-3" />
                    <span class="text-white/90 font-medium text-sm">{{ isUploadingPortrait ? 'Uploading photo...' : 'Creating portrait...' }}</span>
                </div>
            </template>
            <!-- Error state -->
            <template v-else-if="portraitHasError">
                <div
                    :class="[
                        'h-full w-full flex flex-col items-center justify-center bg-linear-to-br',
                        getAvatarGradient(character.id)
                    ]"
                >
                    <span 
                        v-if="character.name"
                        class="text-6xl md:text-7xl font-bold text-white drop-shadow-lg mb-3"
                    >
                        {{ getInitials(character.name) }}
                    </span>
                    <User v-else class="h-24 w-24 text-white/80 mb-3" />
                    <span class="text-white/80 text-sm text-center px-4">Portrait generation failed</span>
                </div>
            </template>
            <!-- No portrait state -->
            <template v-else>
                <div
                    :class="[
                        'h-full w-full flex items-center justify-center bg-linear-to-br',
                        getAvatarGradient(character.id)
                    ]"
                >
                    <span 
                        v-if="character.name"
                        class="text-6xl md:text-7xl font-bold text-white drop-shadow-lg"
                    >
                        {{ getInitials(character.name) }}
                    </span>
                    <User v-else class="h-24 w-24 text-white/80" />
                </div>
            </template>
            
            <!-- Hidden file input for portrait upload -->
            <input
                ref="photoInput"
                type="file"
                accept="image/jpeg,image/jpg,image/png,image/gif,image/webp"
                class="hidden"
                @change="handlePhotoSelected"
            />
            
            <!-- Action Buttons Row (Edit + Upload + New Portrait) -->
            <div class="absolute bottom-4 right-4 z-20 flex items-center gap-2">
                <!-- Edit/Cancel Button -->
                <button
                    v-if="!isEditing"
                    @click="startEditing"
                    class="flex items-center gap-1.5 rounded-full bg-white/90 dark:bg-gray-800/90 px-3 py-1.5 text-sm font-medium text-amber-800 dark:text-amber-200 shadow-lg backdrop-blur-sm transition-all hover:bg-white hover:scale-105 active:scale-95"
                >
                    <Pencil class="h-4 w-4" />
                    <span>Edit</span>
                </button>
                <button
                    v-else
                    @click="cancelEditing"
                    class="flex items-center gap-1.5 rounded-full bg-red-100/90 dark:bg-red-900/90 px-3 py-1.5 text-sm font-medium text-red-700 dark:text-red-200 shadow-lg backdrop-blur-sm transition-all hover:bg-red-200 hover:scale-105 active:scale-95"
                >
                    <X class="h-4 w-4" />
                    <span>Cancel</span>
                </button>
                
                <!-- Upload Portrait Button -->
                <button
                    v-if="!isPortraitLoading"
                    @click="triggerPhotoUpload"
                    class="flex items-center gap-1.5 rounded-full bg-white/90 dark:bg-gray-800/90 px-3 py-1.5 text-sm font-medium text-amber-800 dark:text-amber-200 shadow-lg backdrop-blur-sm transition-all hover:bg-white hover:scale-105 active:scale-95"
                >
                    <Upload class="h-4 w-4" />
                    <span>Upload</span>
                </button>
                
                <!-- Regenerate Portrait Button -->
                <button
                    v-if="!isPortraitLoading"
                    @click="regeneratePortrait"
                    class="flex items-center gap-1.5 rounded-full bg-white/90 dark:bg-gray-800/90 px-3 py-1.5 text-sm font-medium text-amber-800 dark:text-amber-200 shadow-lg backdrop-blur-sm transition-all hover:bg-white hover:scale-105 active:scale-95"
                >
                    <RefreshCw class="h-4 w-4" />
                    <span>{{ portraitImageUrl && !portraitHasError ? 'New Portrait' : portraitHasError ? 'Retry Portrait' : 'Generate Portrait' }}</span>
                </button>
            </div>
            
            <!-- Gradient overlay for better text contrast -->
            <div class="absolute inset-x-0 bottom-0 h-16 bg-linear-to-t from-amber-50 dark:from-amber-100 to-transparent pointer-events-none" />
            
            <!-- Portrait upload error message -->
            <div 
                v-if="portraitUploadError"
                class="absolute top-4 left-4 right-16 z-20 rounded-lg bg-red-100/95 dark:bg-red-900/95 px-3 py-2 text-sm text-red-700 dark:text-red-200 shadow-lg backdrop-blur-sm"
            >
                {{ portraitUploadError }}
            </div>
        </div>

        <!-- Character Details (Bottom Half) -->
        <div class="flex-1 overflow-y-auto px-6 py-4 scrollbar-thin scrollbar-thumb-amber-300 scrollbar-track-transparent">
            <!-- Edit Mode -->
            <template v-if="isEditing">
                <div class="space-y-4">
                    <!-- Name Field -->
                    <div class="space-y-1.5">
                        <label class="text-sm font-semibold text-amber-700 dark:text-amber-700 uppercase tracking-wide">
                            Name *
                        </label>
                        <input
                            v-model="editForm.name"
                            type="text"
                            placeholder="Character name"
                            class="w-full rounded-xl border-2 border-amber-200 bg-white px-4 py-2.5 text-amber-900 placeholder:text-amber-400 focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-400/20 dark:bg-amber-50"
                        />
                        <p v-if="editErrors.name" class="text-sm text-red-600">{{ editErrors.name }}</p>
                    </div>

                    <!-- Age and Gender Row -->
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <label class="text-sm font-semibold text-amber-700 dark:text-amber-700 uppercase tracking-wide">
                                Age
                            </label>
                            <input
                                v-model="editForm.age"
                                type="text"
                                placeholder="How old?"
                                class="w-full rounded-xl border-2 border-amber-200 bg-white px-4 py-2.5 text-amber-900 placeholder:text-amber-400 focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-400/20 dark:bg-amber-50"
                            />
                        </div>
                        <div class="space-y-1.5">
                            <label class="text-sm font-semibold text-amber-700 dark:text-amber-700 uppercase tracking-wide">
                                Nationality
                            </label>
                            <input
                                v-model="editForm.nationality"
                                type="text"
                                placeholder="Where from?"
                                class="w-full rounded-xl border-2 border-amber-200 bg-white px-4 py-2.5 text-amber-900 placeholder:text-amber-400 focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-400/20 dark:bg-amber-50"
                            />
                        </div>
                    </div>

                    <!-- Gender Selection -->
                    <div class="space-y-1.5">
                        <label class="text-sm font-semibold text-amber-700 dark:text-amber-700 uppercase tracking-wide">
                            Gender
                        </label>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="gender in genderOptions"
                                :key="gender.value"
                                type="button"
                                @click="editForm.gender = gender.value"
                                class="cursor-pointer rounded-xl border-2 px-3 py-1.5 text-sm font-medium transition-all hover:-translate-y-0.5 hover:shadow-md active:scale-95"
                                :class="
                                    editForm.gender === gender.value
                                        ? 'border-amber-500 bg-amber-500 text-white'
                                        : 'border-amber-200 bg-white text-amber-800 hover:border-amber-400/50 hover:bg-amber-50'
                                "
                            >
                                {{ gender.label }}
                            </button>
                        </div>
                    </div>

                    <!-- Description Field -->
                    <div class="space-y-1.5">
                        <label class="text-sm font-semibold text-amber-700 dark:text-amber-700 uppercase tracking-wide">
                            About
                        </label>
                        <textarea
                            v-model="editForm.description"
                            rows="3"
                            placeholder="Describe their personality, appearance, or special abilities..."
                            class="w-full resize-none rounded-xl border-2 border-amber-200 bg-white px-4 py-2.5 text-amber-900 placeholder:text-amber-400 focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-400/20 dark:bg-amber-50"
                        />
                    </div>

                    <!-- Backstory Field -->
                    <div class="space-y-1.5">
                        <label class="text-sm font-semibold text-amber-700 dark:text-amber-700 uppercase tracking-wide">
                            Backstory
                        </label>
                        <textarea
                            v-model="editForm.backstory"
                            rows="3"
                            placeholder="What's their background? Where do they come from?"
                            class="w-full resize-none rounded-xl border-2 border-amber-200 bg-white px-4 py-2.5 text-amber-900 placeholder:text-amber-400 focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-400/20 dark:bg-amber-50"
                        />
                    </div>

                    <!-- Error Message -->
                    <p v-if="editErrors.general" class="text-sm text-red-600">{{ editErrors.general }}</p>

                    <!-- Save Button -->
                    <button
                        @click="saveCharacter"
                        :disabled="isSaving"
                        class="w-full flex items-center justify-center gap-2 rounded-xl bg-linear-to-r from-amber-500 to-orange-500 px-4 py-3 text-base font-semibold text-white shadow-lg transition-all hover:from-amber-600 hover:to-orange-600 hover:shadow-xl active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <Spinner v-if="isSaving" class="h-5 w-5" />
                        <Check v-else class="h-5 w-5" />
                        {{ isSaving ? 'Saving...' : 'Save Changes' }}
                    </button>
                </div>
            </template>

            <!-- View Mode -->
            <template v-else>
                <!-- Name and Chat Button Row -->
                <div class="flex items-center justify-between gap-3 mb-3">
                    <h2 class="font-serif text-2xl md:text-3xl font-bold text-amber-950 dark:text-amber-900 tracking-tight">
                        {{ character.name }}
                    </h2>
                    <button
                        @click="openChat"
                        class="shrink-0 flex items-center gap-1.5 rounded-full bg-linear-to-r from-amber-500 to-orange-500 px-3 py-1.5 text-sm font-medium text-white shadow-md transition-all hover:from-amber-600 hover:to-orange-600 hover:shadow-lg active:scale-[0.98]"
                    >
                        <MessageCircle class="h-4 w-4" />
                        <span>Chat</span>
                    </button>
                </div>

                <!-- Quick Info Tags -->
                <div class="flex flex-wrap gap-2 mb-4">
                    <span 
                        v-if="character.age"
                        class="inline-flex items-center gap-1.5 rounded-full bg-amber-200/60 dark:bg-amber-300/50 px-3 py-1 text-sm font-medium text-amber-800"
                    >
                        <Calendar class="h-3.5 w-3.5" />
                        {{ character.age }} years old
                    </span>
                    <span 
                        v-if="character.gender"
                        class="inline-flex items-center gap-1.5 rounded-full bg-amber-200/60 dark:bg-amber-300/50 px-3 py-1 text-sm font-medium text-amber-800"
                    >
                        <User class="h-3.5 w-3.5" />
                        {{ formatGender(character.gender) }}
                    </span>
                    <span 
                        v-if="character.nationality"
                        class="inline-flex items-center gap-1.5 rounded-full bg-amber-200/60 dark:bg-amber-300/50 px-3 py-1 text-sm font-medium text-amber-800"
                    >
                        <MapPin class="h-3.5 w-3.5" />
                        {{ character.nationality }}
                    </span>
                </div>

                <!-- Description -->
                <div v-if="character.description" class="mb-4">
                    <h3 class="flex items-center gap-2 text-sm font-semibold text-amber-700 dark:text-amber-700 uppercase tracking-wide mb-2">
                        <Sparkles class="h-3.5 w-3.5" />
                        About
                    </h3>
                    <p class="font-serif text-amber-900 dark:text-amber-800 leading-relaxed">
                        {{ character.description }}
                    </p>
                </div>

                <!-- Backstory -->
                <div v-if="character.backstory">
                    <h3 class="flex items-center gap-2 text-sm font-semibold text-amber-700 dark:text-amber-700 uppercase tracking-wide mb-2">
                        <Sparkles class="h-3.5 w-3.5" />
                        Backstory
                    </h3>
                    <p class="font-serif text-amber-900 dark:text-amber-800 leading-relaxed">
                        {{ character.backstory }}
                    </p>
                </div>

                <!-- Fallback if no details -->
                <div 
                    v-if="!character.description && !character.backstory && !character.age && !character.gender"
                    class="text-center py-6"
                >
                    <p class="text-amber-600 dark:text-amber-700 italic">
                        This character's story is yet to be told...
                    </p>
                </div>
            </template>
        </div>
    </div>

    <!-- Chat Modal -->
    <CharacterChatModal
        :visible="showChatModal"
        :character="character"
        @close="closeChat"
    />
</template>
