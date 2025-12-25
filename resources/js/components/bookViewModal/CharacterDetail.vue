<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { ArrowLeft, User, Calendar, MapPin, Sparkles, MessageCircle, Pencil, RefreshCw, X, Check } from 'lucide-vue-next';
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
const editErrors = ref<Record<string, string>>({});

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

// Watch for portrait_image changes to clear regenerating state
watch(
    () => props.character.portrait_image,
    (newImage) => {
        if (newImage && isRegeneratingPortrait.value) {
            isRegeneratingPortrait.value = false;
        }
    }
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

// Computed for showing portrait loading state
const isPortraitLoading = computed(() => {
    return isRegeneratingPortrait.value || !props.character.portrait_image;
});

// Display values that update when editing or from props
const displayName = computed(() => isEditing.value ? editForm.value.name : props.character.name);
</script>

<template>
    <div class="relative z-10 flex h-full flex-col overflow-hidden">
        <!-- Top Action Bar - Back and Edit buttons in a row -->
        <div 
            :class="[
                'absolute left-0 right-0 z-20 flex items-center justify-between px-6',
                isSinglePageMode ? 'top-16' : 'top-6'
            ]"
        >
            <!-- Back Button -->
            <button
                @click="emit('back')"
                class="flex items-center gap-1.5 rounded-full bg-amber-100/80 dark:bg-amber-200/80 px-3 py-1.5 text-sm font-medium text-amber-800 shadow-sm backdrop-blur-sm transition-all hover:bg-amber-200 hover:scale-105 active:scale-95"
            >
                <ArrowLeft class="h-4 w-4" />
                <span>Back</span>
            </button>

            <!-- Edit/Cancel Button -->
            <button
                v-if="!isEditing"
                @click="startEditing"
                class="flex items-center gap-1.5 rounded-full bg-amber-100/80 dark:bg-amber-200/80 px-3 py-1.5 text-sm font-medium text-amber-800 shadow-sm backdrop-blur-sm transition-all hover:bg-amber-200 hover:scale-105 active:scale-95"
            >
                <Pencil class="h-4 w-4" />
                <span>Edit</span>
            </button>
            <button
                v-else
                @click="cancelEditing"
                class="flex items-center gap-1.5 rounded-full bg-red-100/80 dark:bg-red-200/80 px-3 py-1.5 text-sm font-medium text-red-700 shadow-sm backdrop-blur-sm transition-all hover:bg-red-200 hover:scale-105 active:scale-95"
            >
                <X class="h-4 w-4" />
                <span>Cancel</span>
            </button>
        </div>

        <!-- Portrait Image Section (Top Half) -->
        <div class="relative h-1/2 w-full overflow-hidden">
            <!-- Portrait Image or Loading State -->
            <template v-if="!isPortraitLoading">
                <img
                    :src="character.portrait_image!"
                    :alt="character.name"
                    class="h-full w-full object-cover"
                />
            </template>
            <template v-else-if="isRegeneratingPortrait">
                <!-- Regenerating state -->
                <div
                    :class="[
                        'h-full w-full flex flex-col items-center justify-center bg-linear-to-br',
                        getAvatarGradient(character.id)
                    ]"
                >
                    <Spinner class="h-12 w-12 text-white/80 mb-3" />
                    <span class="text-white/90 font-medium text-sm">Creating new portrait...</span>
                </div>
            </template>
            <template v-else>
                <!-- No portrait state -->
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
            
            <!-- Regenerate Portrait Button -->
            <button
                v-if="character.portrait_image && !isRegeneratingPortrait"
                @click="regeneratePortrait"
                class="absolute bottom-4 right-4 z-20 flex items-center gap-1.5 rounded-full bg-white/90 dark:bg-gray-800/90 px-3 py-1.5 text-sm font-medium text-amber-800 dark:text-amber-200 shadow-lg backdrop-blur-sm transition-all hover:bg-white hover:scale-105 active:scale-95"
            >
                <RefreshCw class="h-4 w-4" />
                <span>New Portrait</span>
            </button>
            
            <!-- Gradient overlay for better text contrast -->
            <div class="absolute inset-x-0 bottom-0 h-16 bg-linear-to-t from-amber-50 dark:from-amber-100 to-transparent" />
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
