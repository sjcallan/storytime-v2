<script setup lang="ts">
import { computed, ref } from 'vue';
import { ArrowLeft, ExternalLink, ImageIcon, Sparkles, BookOpen, User, Loader2, Camera, Palette, Sun, Heart, Mountain, Layout, Trash2, FileText, Pencil } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { apiFetch } from '@/composables/ApiFetch';
import EditImageModal from './EditImageModal.vue';
import type { Image, Character } from './types';

interface Props {
    image: Image;
    bookId: string;
    characters: Character[];
    isSinglePageMode?: boolean;
}

interface ParsedPrompt {
    scene?: string;
    subjects?: Array<{
        description: string;
        position?: string;
        action?: string;
    }>;
    style?: string;
    color_palette?: string[];
    lighting?: string;
    mood?: string;
    background?: string;
    composition?: string;
    camera?: {
        angle?: string;
        lens?: string;
        depth_of_field?: string;
    };
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'back'): void;
    (e: 'deleted', imageId: string): void;
    (e: 'imageUpdated', image: Image): void;
    (e: 'goToChapter', chapterId: string): void;
}>();

// Delete state
const showDeleteConfirm = ref(false);
const isDeleting = ref(false);
const isDeleted = ref(false);
const deleteError = ref<string | null>(null);

// Edit modal state
const showEditModal = ref(false);

// Handle image updated from edit modal
const handleImageUpdated = (newImage: Image) => {
    showEditModal.value = false;
    emit('imageUpdated', newImage);
};

// Check if the image can be edited (any image with a prompt or completed status)
const canEditImage = computed(() => {
    // Allow editing if there's a prompt or if the image is complete
    return !!props.image.prompt || props.image.status === 'complete';
});

// Delete image
const confirmDelete = () => {
    showDeleteConfirm.value = true;
};

const cancelDelete = () => {
    showDeleteConfirm.value = false;
};

const executeDelete = async () => {
    isDeleting.value = true;
    deleteError.value = null;
    
    const { error } = await apiFetch(`/api/images/${props.image.id}`, 'DELETE');
    
    isDeleting.value = false;
    showDeleteConfirm.value = false;
    
    if (error) {
        deleteError.value = (error as Error).message || 'Failed to delete image';
    } else {
        isDeleted.value = true;
        emit('deleted', props.image.id);
    }
};

// Check if image is generating
const isGenerating = computed(() => {
    return props.image.status === 'pending' || props.image.status === 'processing';
});

// Check if image is from a chapter (header or inline)
const isChapterImage = computed(() => {
    return (props.image.type === 'chapter_header' || props.image.type === 'chapter_inline') && 
           props.image.chapter_id !== null;
});

// Navigate to the chapter this image belongs to
const goToChapter = () => {
    if (props.image.chapter_id) {
        emit('goToChapter', props.image.chapter_id);
    }
};

// Open image in new window
const openInNewWindow = () => {
    if (props.image.full_url) {
        window.open(props.image.full_url, '_blank', 'noopener,noreferrer');
    }
};

// Get display label for image type
const imageTypeLabel = computed((): string => {
    switch (props.image.type) {
        case 'book_cover':
            return 'Book Cover';
        case 'character_portrait':
            return 'Character Portrait';
        case 'chapter_header':
            return 'Chapter Header';
        case 'chapter_inline':
            return 'Story Illustration';
        case 'manual':
            return 'Custom Image';
        default:
            return 'Image';
    }
});

// Get icon for image type
const imageTypeIcon = computed(() => {
    switch (props.image.type) {
        case 'book_cover':
            return BookOpen;
        case 'character_portrait':
            return User;
        case 'chapter_header':
        case 'chapter_inline':
        case 'manual':
        default:
            return ImageIcon;
    }
});

// Try to parse JSON prompt
const parsedPrompt = computed((): ParsedPrompt | null => {
    if (!props.image.prompt) {
        return null;
    }

    const trimmed = props.image.prompt.trim();
    if (!trimmed.startsWith('{')) {
        return null;
    }

    try {
        const parsed = JSON.parse(trimmed);
        return parsed as ParsedPrompt;
    } catch {
        return null;
    }
});

// Check if prompt is JSON
const isJsonPrompt = computed(() => parsedPrompt.value !== null);

// Format the prompt for display (for non-JSON prompts)
const displayPrompt = computed(() => {
    if (!props.image.prompt || isJsonPrompt.value) {
        return null;
    }
    return props.image.prompt;
});

// Format date
const formattedDate = computed(() => {
    if (!props.image.created_at) {
        return null;
    }
    return new Date(props.image.created_at).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
});
</script>

<template>
    <div class="relative z-10 flex h-full flex-col overflow-hidden">
        <!-- Back Button -->
        <button
            @click="emit('back')"
            :class="[
                'absolute z-20 flex items-center gap-1.5 rounded-full bg-amber-100/80 dark:bg-amber-200/80 px-3 py-1.5 text-sm font-medium text-amber-800 shadow-sm backdrop-blur-sm transition-all hover:bg-amber-200 hover:scale-105 active:scale-95 left-6 cursor-pointer',
                isSinglePageMode ? 'top-16' : 'top-6'
            ]"
        >
            <ArrowLeft class="h-4 w-4" />
            <span>Back</span>
        </button>

        <!-- Image Section (Top ~60%) -->
        <div class="relative h-[60%] w-full overflow-hidden bg-amber-100/50 dark:bg-amber-200/30">
            <!-- Generating State -->
            <div 
                v-if="isGenerating"
                class="h-full w-full flex flex-col items-center justify-center gap-4"
            >
                <div class="relative">
                    <div class="absolute inset-0 animate-ping rounded-full bg-amber-400/30" />
                    <Loader2 class="h-16 w-16 text-amber-500 animate-spin" />
                </div>
                <div class="text-center">
                    <p class="text-lg font-semibold text-amber-700 dark:text-amber-600">
                        Creating your image...
                    </p>
                    <p class="text-sm text-amber-600/80 dark:text-amber-500/80 mt-1">
                        This usually takes 30-60 seconds
                    </p>
                </div>
            </div>
            <!-- Image -->
            <img
                v-else-if="image.full_url"
                :src="image.full_url"
                :alt="image.prompt || 'Story image'"
                class="h-full w-full object-contain cursor-pointer transition-all hover:scale-[1.02]"
                @click="openInNewWindow"
                title="Click to view full size"
            />
            <!-- Placeholder if no image -->
            <div 
                v-else
                class="h-full w-full flex items-center justify-center"
            >
                <ImageIcon class="h-24 w-24 text-amber-400/60" />
            </div>
            
            <!-- Action buttons -->
            <div v-if="!isGenerating && !isDeleted" class="absolute bottom-4 right-4 z-20 flex items-center gap-2">
                <!-- Go to chapter button (for chapter images) -->
                <button
                    v-if="isChapterImage"
                    @click="goToChapter"
                    class="flex items-center gap-1.5 rounded-full bg-amber-500/90 px-3 py-1.5 text-sm font-medium text-white shadow-lg backdrop-blur-sm transition-all hover:bg-amber-600 hover:scale-105 active:scale-95 cursor-pointer"
                    title="View this chapter"
                >
                    <FileText class="h-4 w-4" />
                    <span>Go to Chapter</span>
                </button>
                
                <!-- Edit button -->
                <button
                    v-if="canEditImage"
                    @click="showEditModal = true"
                    class="flex items-center gap-1.5 rounded-full bg-purple-500/90 px-3 py-1.5 text-sm font-medium text-white shadow-lg backdrop-blur-sm transition-all hover:bg-purple-600 hover:scale-105 active:scale-95 cursor-pointer"
                    title="Edit and regenerate image"
                >
                    <Pencil class="h-4 w-4" />
                    <span>Edit</span>
                </button>
                
                <!-- Delete button -->
                <button
                    @click="confirmDelete"
                    class="flex items-center gap-1.5 rounded-full bg-red-500/80 px-3 py-1.5 text-sm font-medium text-white shadow-lg backdrop-blur-sm transition-all hover:bg-red-600 hover:scale-105 active:scale-95 cursor-pointer"
                    title="Delete image"
                >
                    <Trash2 class="h-4 w-4" />
                    <span>Delete</span>
                </button>
                
                <!-- Open in new window button -->
                <button
                    v-if="image.full_url"
                    @click="openInNewWindow"
                    class="flex items-center gap-1.5 rounded-full bg-black/60 px-3 py-1.5 text-sm font-medium text-white/90 shadow-lg backdrop-blur-sm transition-all hover:bg-black/75 hover:scale-105 active:scale-95 cursor-pointer"
                >
                    <ExternalLink class="h-4 w-4" />
                    <span>Open Full Size</span>
                </button>
            </div>
            
            <!-- Gradient overlay for better contrast -->
            <div class="absolute inset-x-0 bottom-0 h-12 bg-linear-to-t from-amber-50 dark:from-amber-100 to-transparent pointer-events-none" />
        </div>

        <!-- Delete Confirmation Dialog -->
        <Transition
            enter-active-class="transition-all duration-300 ease-out"
            enter-from-class="opacity-0 scale-75"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="transition-all duration-200 ease-in"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-75"
        >
            <div 
                v-if="showDeleteConfirm" 
                class="absolute inset-0 z-50 flex items-center justify-center rounded-2xl bg-black/50 backdrop-blur-sm"
            >
                <div class="mx-4 w-full max-w-sm animate-bounce-in rounded-2xl border-2 border-red-200 bg-white p-6 shadow-2xl dark:border-red-800 dark:bg-gray-900">
                    <div class="mb-4 text-center">
                        <div class="mx-auto mb-3 flex h-16 w-16 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/30">
                            <span class="text-3xl">🗑️</span>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white">Delete this image?</h3>
                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                            This image will be removed from your gallery. This action cannot be undone.
                        </p>
                    </div>
                    <div class="flex gap-3">
                        <Button
                            type="button"
                            variant="outline"
                            @click="cancelDelete"
                            :disabled="isDeleting"
                            class="flex-1 cursor-pointer rounded-xl border-gray-200 text-gray-900 transition-all duration-200 hover:scale-[1.02] active:scale-[0.98] dark:border-gray-700 dark:text-white"
                        >
                            Keep It! 🖼️
                        </Button>
                        <Button
                            type="button"
                            @click="executeDelete"
                            :disabled="isDeleting"
                            class="flex-1 cursor-pointer rounded-xl bg-linear-to-r from-red-500 to-rose-500 text-white transition-all duration-200 hover:scale-[1.02] hover:from-red-600 hover:to-rose-600 active:scale-[0.98]"
                        >
                            <Loader2 v-if="isDeleting" class="mr-2 h-4 w-4 animate-spin" />
                            {{ isDeleting ? 'Deleting...' : 'Delete' }}
                        </Button>
                    </div>
                </div>
            </div>
        </Transition>

        <!-- Deleted Success State -->
        <Transition
            enter-active-class="transition-all duration-300 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-all duration-200 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div 
                v-if="isDeleted" 
                class="absolute inset-0 z-50 flex items-center justify-center rounded-2xl bg-amber-50/95 dark:bg-amber-100/95 backdrop-blur-sm"
            >
                <div class="text-center px-8">
                    <div class="mx-auto mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-green-100 dark:bg-green-200">
                        <span class="text-4xl">✨</span>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-2">Image Removed!</h3>
                    <p class="text-gray-600 mb-6">
                        The image has been successfully deleted from your gallery.
                    </p>
                    <Button
                        type="button"
                        @click="emit('back')"
                        class="cursor-pointer rounded-xl bg-linear-to-r from-amber-500 to-orange-500 px-6 py-2 text-white transition-all duration-200 hover:scale-[1.02] hover:from-amber-600 hover:to-orange-600 active:scale-[0.98]"
                    >
                        Back to Gallery 📸
                    </Button>
                </div>
            </div>
        </Transition>

        <!-- Image Details (Bottom ~40%) -->
        <div class="flex-1 overflow-y-auto px-6 py-4 scrollbar-thin scrollbar-thumb-amber-300 scrollbar-track-transparent">
            <!-- Type Badge -->
            <div class="flex items-center flex-wrap gap-2 mb-3">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-200/60 dark:bg-amber-300/50 px-3 py-1 text-sm font-medium text-amber-800">
                    <component :is="imageTypeIcon" class="h-3.5 w-3.5" />
                    {{ imageTypeLabel }}
                </span>
                <span 
                    v-if="isGenerating"
                    class="inline-flex items-center gap-1.5 rounded-full bg-blue-100 dark:bg-blue-200/60 px-3 py-1 text-sm font-medium text-blue-700"
                >
                    <Loader2 class="h-3.5 w-3.5 animate-spin" />
                    Generating
                </span>
                <span 
                    v-if="formattedDate"
                    class="text-xs text-amber-600 dark:text-amber-700"
                >
                    {{ formattedDate }}
                </span>
            </div>

            <!-- Parsed JSON Prompt Section -->
            <div v-if="parsedPrompt" class="space-y-3">
                <!-- Scene -->
                <div v-if="parsedPrompt.scene" class="rounded-lg bg-amber-50/50 dark:bg-amber-100/30 p-3">
                    <h3 class="flex items-center gap-2 text-xs font-semibold text-amber-700 uppercase tracking-wide mb-1.5">
                        <Sparkles class="h-3 w-3" />
                        Scene
                    </h3>
                    <p class="font-serif text-amber-900 dark:text-amber-800 leading-relaxed text-sm">
                        {{ parsedPrompt.scene }}
                    </p>
                </div>

                <!-- Characters/Subjects -->
                <div v-if="parsedPrompt.subjects?.length" class="rounded-lg bg-amber-50/50 dark:bg-amber-100/30 p-3">
                    <h3 class="flex items-center gap-2 text-xs font-semibold text-amber-700 uppercase tracking-wide mb-1.5">
                        <User class="h-3 w-3" />
                        Characters
                    </h3>
                    <ul class="space-y-2">
                        <li 
                            v-for="(subject, index) in parsedPrompt.subjects" 
                            :key="index"
                            class="text-sm text-amber-800 dark:text-amber-700 pl-3 border-l-2 border-amber-300"
                        >
                            <p class="font-medium">{{ subject.description }}</p>
                            <p v-if="subject.position || subject.action" class="text-xs text-amber-600 mt-0.5">
                                <span v-if="subject.position">📍 {{ subject.position }}</span>
                                <span v-if="subject.position && subject.action"> · </span>
                                <span v-if="subject.action">🎬 {{ subject.action }}</span>
                            </p>
                        </li>
                    </ul>
                </div>

                <!-- Style & Mood Row -->
                <div class="grid grid-cols-2 gap-2">
                    <div v-if="parsedPrompt.style" class="rounded-lg bg-amber-50/50 dark:bg-amber-100/30 p-3">
                        <h3 class="flex items-center gap-1.5 text-xs font-semibold text-amber-700 uppercase tracking-wide mb-1">
                            <Palette class="h-3 w-3" />
                            Style
                        </h3>
                        <p class="text-xs text-amber-800 dark:text-amber-700 leading-relaxed">
                            {{ parsedPrompt.style }}
                        </p>
                    </div>
                    <div v-if="parsedPrompt.mood" class="rounded-lg bg-amber-50/50 dark:bg-amber-100/30 p-3">
                        <h3 class="flex items-center gap-1.5 text-xs font-semibold text-amber-700 uppercase tracking-wide mb-1">
                            <Heart class="h-3 w-3" />
                            Mood
                        </h3>
                        <p class="text-xs text-amber-800 dark:text-amber-700 leading-relaxed">
                            {{ parsedPrompt.mood }}
                        </p>
                    </div>
                </div>

                <!-- Lighting & Background Row -->
                <div class="grid grid-cols-2 gap-2">
                    <div v-if="parsedPrompt.lighting" class="rounded-lg bg-amber-50/50 dark:bg-amber-100/30 p-3">
                        <h3 class="flex items-center gap-1.5 text-xs font-semibold text-amber-700 uppercase tracking-wide mb-1">
                            <Sun class="h-3 w-3" />
                            Lighting
                        </h3>
                        <p class="text-xs text-amber-800 dark:text-amber-700 leading-relaxed">
                            {{ parsedPrompt.lighting }}
                        </p>
                    </div>
                    <div v-if="parsedPrompt.background" class="rounded-lg bg-amber-50/50 dark:bg-amber-100/30 p-3">
                        <h3 class="flex items-center gap-1.5 text-xs font-semibold text-amber-700 uppercase tracking-wide mb-1">
                            <Mountain class="h-3 w-3" />
                            Background
                        </h3>
                        <p class="text-xs text-amber-800 dark:text-amber-700 leading-relaxed">
                            {{ parsedPrompt.background }}
                        </p>
                    </div>
                </div>

                <!-- Color Palette -->
                <div v-if="parsedPrompt.color_palette?.length" class="rounded-lg bg-amber-50/50 dark:bg-amber-100/30 p-3">
                    <h3 class="flex items-center gap-1.5 text-xs font-semibold text-amber-700 uppercase tracking-wide mb-2">
                        <Palette class="h-3 w-3" />
                        Color Palette
                    </h3>
                    <div class="flex gap-1.5">
                        <div 
                            v-for="(color, index) in parsedPrompt.color_palette" 
                            :key="index"
                            class="h-6 w-6 rounded-md border border-amber-300/50 shadow-sm"
                            :style="{ backgroundColor: color }"
                            :title="color"
                        />
                    </div>
                </div>

                <!-- Camera Settings -->
                <div v-if="parsedPrompt.camera" class="rounded-lg bg-amber-50/50 dark:bg-amber-100/30 p-3">
                    <h3 class="flex items-center gap-1.5 text-xs font-semibold text-amber-700 uppercase tracking-wide mb-1.5">
                        <Camera class="h-3 w-3" />
                        Camera
                    </h3>
                    <div class="flex flex-wrap gap-x-3 gap-y-1 text-xs text-amber-800 dark:text-amber-700">
                        <span v-if="parsedPrompt.camera.angle">📐 {{ parsedPrompt.camera.angle }}</span>
                        <span v-if="parsedPrompt.camera.lens">🔭 {{ parsedPrompt.camera.lens }}</span>
                        <span v-if="parsedPrompt.camera.depth_of_field">🎯 {{ parsedPrompt.camera.depth_of_field }}</span>
                    </div>
                </div>

                <!-- Composition -->
                <div v-if="parsedPrompt.composition" class="rounded-lg bg-amber-50/50 dark:bg-amber-100/30 p-3">
                    <h3 class="flex items-center gap-1.5 text-xs font-semibold text-amber-700 uppercase tracking-wide mb-1">
                        <Layout class="h-3 w-3" />
                        Composition
                    </h3>
                    <p class="text-xs text-amber-800 dark:text-amber-700 leading-relaxed">
                        {{ parsedPrompt.composition }}
                    </p>
                </div>
            </div>

            <!-- Regular Prompt Section (non-JSON) -->
            <div v-else-if="displayPrompt" class="mb-4">
                <h3 class="flex items-center gap-2 text-sm font-semibold text-amber-700 dark:text-amber-700 uppercase tracking-wide mb-2">
                    <Sparkles class="h-3.5 w-3.5" />
                    Image Prompt
                </h3>
                <p class="font-serif text-amber-900 dark:text-amber-800 leading-relaxed text-sm">
                    {{ displayPrompt }}
                </p>
            </div>

            <!-- No Prompt Fallback -->
            <div 
                v-else
                class="text-center py-4"
            >
                <p class="text-amber-600 dark:text-amber-700 italic text-sm">
                    No prompt information available for this image
                </p>
            </div>

            <!-- Tap to view hint -->
            <div v-if="!isGenerating && !isDeleted" class="mt-4 pt-4 border-t border-amber-200/60 dark:border-amber-300/40">
                <p class="text-xs text-amber-600 dark:text-amber-600 text-center">
                    💡 Click the image or "Open Full Size" button to view in a new window
                </p>
            </div>

            <!-- Delete error message -->
            <div v-if="deleteError" class="mt-4 rounded-lg bg-red-50 dark:bg-red-900/20 p-3 text-center">
                <p class="text-sm text-red-600 dark:text-red-400">
                    {{ deleteError }}
                </p>
            </div>
        </div>

        <!-- Edit Image Modal -->
        <EditImageModal
            v-model:is-open="showEditModal"
            :book-id="bookId"
            :image="image"
            :characters="characters"
            @image-updated="handleImageUpdated"
        />
    </div>
</template>

<style scoped>
@keyframes bounce-in {
    0% {
        opacity: 0;
        transform: scale(0.3) translateY(-20px);
    }
    50% {
        opacity: 1;
        transform: scale(1.05) translateY(0);
    }
    70% {
        transform: scale(0.95);
    }
    100% {
        transform: scale(1);
    }
}

.animate-bounce-in {
    animation: bounce-in 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
}
</style>
