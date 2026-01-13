<script setup lang="ts">
import { computed } from 'vue';
import { ArrowLeft, ExternalLink, ImageIcon, Sparkles, BookOpen, User } from 'lucide-vue-next';
import type { Image } from './types';

interface Props {
    image: Image;
    isSinglePageMode?: boolean;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'back'): void;
}>();

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
        default:
            return ImageIcon;
    }
});

// Format the prompt for display (truncate if very long)
const displayPrompt = computed(() => {
    if (!props.image.prompt) {
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
            <!-- Image -->
            <img
                v-if="image.full_url"
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
            
            <!-- Open in new window button -->
            <button
                v-if="image.full_url"
                @click="openInNewWindow"
                class="absolute bottom-4 right-4 z-20 flex items-center gap-1.5 rounded-full bg-black/60 px-3 py-1.5 text-sm font-medium text-white/90 shadow-lg backdrop-blur-sm transition-all hover:bg-black/75 hover:scale-105 active:scale-95 cursor-pointer"
            >
                <ExternalLink class="h-4 w-4" />
                <span>Open Full Size</span>
            </button>
            
            <!-- Gradient overlay for better contrast -->
            <div class="absolute inset-x-0 bottom-0 h-12 bg-linear-to-t from-amber-50 dark:from-amber-100 to-transparent pointer-events-none" />
        </div>

        <!-- Image Details (Bottom ~40%) -->
        <div class="flex-1 overflow-y-auto px-6 py-4 scrollbar-thin scrollbar-thumb-amber-300 scrollbar-track-transparent">
            <!-- Type Badge -->
            <div class="flex items-center gap-2 mb-3">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-200/60 dark:bg-amber-300/50 px-3 py-1 text-sm font-medium text-amber-800">
                    <component :is="imageTypeIcon" class="h-3.5 w-3.5" />
                    {{ imageTypeLabel }}
                </span>
                <span 
                    v-if="formattedDate"
                    class="text-xs text-amber-600 dark:text-amber-700"
                >
                    {{ formattedDate }}
                </span>
            </div>

            <!-- Prompt Section -->
            <div v-if="displayPrompt" class="mb-4">
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
            <div class="mt-4 pt-4 border-t border-amber-200/60 dark:border-amber-300/40">
                <p class="text-xs text-amber-600 dark:text-amber-600 text-center">
                    💡 Click the image or "Open Full Size" button to view in a new window
                </p>
            </div>
        </div>
    </div>
</template>
