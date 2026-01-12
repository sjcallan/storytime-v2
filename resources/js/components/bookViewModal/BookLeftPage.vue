<script setup lang="ts">
import { computed } from 'vue';
import BookPageTexture from './BookPageTexture.vue';
import BookPageDecorative from './BookPageDecorative.vue';
import CharacterGrid from './CharacterGrid.vue';
import CreateChapterForm from './CreateChapterForm.vue';
import { BookOpen, ImageIcon, Sparkles, RefreshCw, PenLine, ExternalLink, AlertTriangle, XCircle, Clock } from 'lucide-vue-next';
import type { Chapter, PageSpread, ReadingView, Character, BookType, PageContentItem } from './types';
import { getChapterLabel, isSceneBasedBook, formatScriptDialogue } from './types';
import { useSwipeGesture } from './composables/useSwipeGesture';

interface Props {
    readingView: ReadingView;
    chapter: Chapter | null;
    spread: PageSpread | null;
    spreadIndex?: number;
    characters?: Character[];
    selectedCharacterId?: string | null;
    hasNextChapter?: boolean;
    chapterEndsOnLeft?: boolean;
    isOnLastSpread?: boolean;
    isLastChapter?: boolean;
    currentChapterNumber?: number;
    nextChapterPrompt?: string;
    suggestedIdea?: string | null;
    isLoadingIdea?: boolean;
    isFinalChapter?: boolean;
    isGeneratingChapter?: boolean;
    bookType?: BookType;
    isSinglePageMode?: boolean;
    bookTitle?: string;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'selectCharacter', character: Character): void;
    (e: 'update:nextChapterPrompt', value: string): void;
    (e: 'update:isFinalChapter', value: boolean): void;
    (e: 'generateChapter'): void;
    (e: 'textareaFocused', value: boolean): void;
    (e: 'requestIdea'): void;
    (e: 'regenerateImage', item: PageContentItem, chapterId: string): void;
    (e: 'retryInlineImage', item: PageContentItem, chapterId: string): void;
    (e: 'cancelInlineImage', item: PageContentItem, chapterId: string): void;
    (e: 'editChapter'): void;
    (e: 'swipeForward'): void;
    (e: 'swipeBack'): void;
}>();

// Swipe gesture support for touch devices
const { onTouchStart, onTouchMove, onTouchEnd } = useSwipeGesture(
    () => emit('swipeForward'),  // Swipe left = go forward
    () => emit('swipeBack'),     // Swipe right = go back
    { threshold: 50 }
);

// Calculate left page number
const leftPageNumber = computed(() => {
    if (props.readingView !== 'chapter-image' && props.readingView !== 'chapter-content') {
        return null;
    }
    if (props.spreadIndex === undefined) {
        return null;
    }
    // Title page is 0, first spread is pages 2-3, second spread is 4-5, etc.
    return 2 + (props.spreadIndex * 2);
});

// Show header when viewing chapter content
const showHeader = computed(() => {
    return (props.readingView === 'chapter-image' || props.readingView === 'chapter-content') && 
           props.bookTitle;
});

// Show create form on left page when chapter ended on right and no next chapter
const showCreateFormOnLeft = computed(() => {
    if (props.readingView !== 'create-chapter' || props.hasNextChapter) {
        return false;
    }
    // In single page mode, always show the form when in create-chapter view
    if (props.isSinglePageMode) {
        return true;
    }
    // In dual page mode, show form on left when chapter ended on right
    return !props.chapterEndsOnLeft;
});

const chapterLabel = computed(() => getChapterLabel(props.bookType));
const isScript = computed(() => isSceneBasedBook(props.bookType));

// Show edit button when chapter ends on left page, is last chapter, and on last spread
const showEditButton = computed(() => {
    return props.isLastChapter && 
           props.chapterEndsOnLeft && 
           props.isOnLastSpread && 
           props.chapter && 
           !props.chapter.final_chapter;
});

// Format content - applies script formatting for theatre/screenplay
const formatContent = (content: string): string => {
    return formatScriptDialogue(content, isScript.value);
};

// Check if a URL looks like a valid image URL (starts with http, https, or /)
const isValidImageUrl = (url: string | null | undefined): boolean => {
    if (!url || typeof url !== 'string') {
        return false;
    }
    const trimmed = url.trim();
    return trimmed.startsWith('http://') || trimmed.startsWith('https://') || trimmed.startsWith('/');
};

// Check if an image is in an error, timeout, or cancelled state
const isImageError = (item: PageContentItem): boolean => {
    return item.imageStatus === 'error';
};

const isImageTimeout = (item: PageContentItem): boolean => {
    return item.imageStatus === 'timeout';
};

const isImageCancelled = (item: PageContentItem): boolean => {
    return item.imageStatus === 'cancelled';
};

// Check if image is pending (loading)
const isImagePending = (item: PageContentItem): boolean => {
    return item.imageStatus === 'pending' || (!item.imageUrl && !isImageError(item) && !isImageTimeout(item) && !isImageCancelled(item));
};

// Open image in new window
const openImageInNewWindow = (url: string | null | undefined) => {
    if (url && typeof url === 'string') {
        window.open(url, '_blank', 'noopener,noreferrer');
    }
};
</script>

<template>
    <div 
        :class="[
            'relative h-full bg-amber-50 dark:bg-amber-100 overflow-hidden',
            isSinglePageMode ? 'w-full' : 'w-1/2'
        ]"
        @touchstart.passive="onTouchStart"
        @touchmove="onTouchMove"
        @touchend.passive="onTouchEnd"
    >
        <!-- Paper texture -->
        <BookPageTexture />
        
        <!-- Decorative page lines -->
        <div class="absolute inset-y-0 left-4 w-px bg-amber-300/60 dark:bg-amber-400/50" />
        <div class="absolute inset-y-0 left-8 w-px bg-amber-300/40 dark:bg-amber-400/30" />
        
        <!-- Page Header - Book Title -->
        <div 
            v-if="showHeader"
            class="absolute top-6 left-0 right-0 z-10 flex items-center justify-center gap-3 px-12"
        >
            <div class="h-px flex-1 bg-linear-to-r from-amber-600 via-amber-600 to-transparent dark:from-amber-400 dark:via-amber-400" />
            <span class="text-xs font-medium text-amber-700 truncate max-w-[60%] text-center">
                {{ bookTitle }}
            </span>
            <div class="h-px flex-1 bg-linear-to-l from-amber-600 via-amber-600 to-transparent dark:from-amber-400 dark:via-amber-400" />
        </div>
        
        <!-- Left Page Content Based on View -->
        <template v-if="readingView === 'title'">
            <CharacterGrid
                v-if="characters && characters.length > 0"
                :characters="characters"
                :selected-character-id="selectedCharacterId ?? null"
                @select-character="emit('selectCharacter', $event)"
            />
            <BookPageDecorative v-else variant="sparkles" />
        </template>
        
        <template v-else-if="(readingView === 'chapter-image' || readingView === 'chapter-content') && chapter && spread">
            <div class="flex h-full flex-col">
                <!-- First spread: show decorative element (chapter image is now on right page) -->
                <template v-if="spread.showImage">
                    <div class="flex h-full items-center justify-center p-12">
                        <div class="text-center opacity-40">
                            <div class="mx-auto mb-4 h-px w-24 bg-linear-to-r from-transparent via-amber-600 to-transparent dark:via-amber-400" />
                            <BookOpen class="mx-auto h-12 w-12 text-amber-500 dark:text-amber-400" />
                            <div class="mx-auto mt-4 h-px w-24 bg-linear-to-r from-transparent via-amber-600 to-transparent dark:via-amber-400" />
                        </div>
                    </div>
                </template>
                <!-- Subsequent spreads: show content continuation on left page (full height) -->
                <template v-else-if="spread.leftContent">
                    <div class="h-full px-16 pt-24 pb-12 overflow-y-auto">
                        <div class="prose prose-amber prose-lg max-w-none text-amber-950 dark:text-amber-900">
                            <template v-for="(item, idx) in spread.leftContent" :key="idx">
                                <p 
                                    v-if="item.type === 'paragraph'"
                                    class="mb-5 font-serif text-lg leading-relaxed"
                                    v-html="formatContent(item.content)"
                                />
                                <figure v-else-if="item.type === 'image'" class="group/image my-6 relative">
                                    <!-- Error state placeholder -->
                                    <div 
                                        v-if="isImageError(item)"
                                        class="w-full aspect-video rounded-lg bg-red-50 dark:bg-red-950/50 flex flex-col items-center justify-center gap-3 border-2 border-dashed border-red-400 dark:border-red-600"
                                    >
                                        <div class="relative">
                                            <AlertTriangle class="w-12 h-12 text-red-600 dark:text-red-400" />
                                        </div>
                                        <div class="text-center px-4">
                                            <p class="text-sm font-semibold text-red-800 dark:text-red-200">
                                                Image generation failed
                                            </p>
                                            <p class="text-xs text-red-600 dark:text-red-400 mt-1 max-w-xs">
                                                Something went wrong while creating this illustration
                                            </p>
                                        </div>
                                        <div class="flex gap-2 mt-1">
                                            <button
                                                @click.stop="emit('retryInlineImage', item, chapter!.id)"
                                                class="flex items-center gap-1.5 rounded-full bg-red-600 px-4 py-2 text-xs font-medium text-white transition-all hover:bg-red-700 active:scale-95 cursor-pointer"
                                            >
                                                <RefreshCw class="h-3.5 w-3.5" />
                                                <span>Retry</span>
                                            </button>
                                            <button
                                                @click.stop="emit('cancelInlineImage', item, chapter!.id)"
                                                class="flex items-center gap-1.5 rounded-full bg-red-100 dark:bg-red-900/50 px-4 py-2 text-xs font-medium text-red-700 dark:text-red-300 transition-all hover:bg-red-200 dark:hover:bg-red-800/50 active:scale-95 cursor-pointer"
                                            >
                                                <XCircle class="h-3.5 w-3.5" />
                                                <span>Remove</span>
                                            </button>
                                        </div>
                                    </div>
                                    <!-- Timeout state placeholder -->
                                    <div 
                                        v-else-if="isImageTimeout(item)"
                                        class="w-full aspect-video rounded-lg bg-orange-50 dark:bg-orange-950/50 flex flex-col items-center justify-center gap-3 border-2 border-dashed border-orange-400 dark:border-orange-600"
                                    >
                                        <div class="relative">
                                            <Clock class="w-12 h-12 text-orange-600 dark:text-orange-400" />
                                        </div>
                                        <div class="text-center px-4">
                                            <p class="text-sm font-semibold text-orange-800 dark:text-orange-200">
                                                Taking longer than expected
                                            </p>
                                            <p class="text-xs text-orange-600 dark:text-orange-400 mt-1 max-w-xs">
                                                The image service is busy. You can retry or skip this image.
                                            </p>
                                        </div>
                                        <div class="flex gap-2 mt-1">
                                            <button
                                                @click.stop="emit('retryInlineImage', item, chapter!.id)"
                                                class="flex items-center gap-1.5 rounded-full bg-orange-600 px-4 py-2 text-xs font-medium text-white transition-all hover:bg-orange-700 active:scale-95 cursor-pointer"
                                            >
                                                <RefreshCw class="h-3.5 w-3.5" />
                                                <span>Retry</span>
                                            </button>
                                            <button
                                                @click.stop="emit('cancelInlineImage', item, chapter!.id)"
                                                class="flex items-center gap-1.5 rounded-full bg-orange-100 dark:bg-orange-900/50 px-4 py-2 text-xs font-medium text-orange-700 dark:text-orange-300 transition-all hover:bg-orange-200 dark:hover:bg-orange-800/50 active:scale-95 cursor-pointer"
                                            >
                                                <XCircle class="h-3.5 w-3.5" />
                                                <span>Skip</span>
                                            </button>
                                        </div>
                                    </div>
                                    <!-- Cancelled state placeholder - option to regenerate -->
                                    <div
                                        v-else-if="isImageCancelled(item)"
                                        class="w-full aspect-video rounded-lg bg-amber-100/60 dark:bg-amber-900/30 flex flex-col items-center justify-center gap-2 border-2 border-dashed border-amber-300 dark:border-amber-700 hover:border-amber-500 dark:hover:border-amber-500 hover:bg-amber-100 dark:hover:bg-amber-900/50 transition-colors cursor-pointer"
                                        @click.stop="emit('retryInlineImage', item, chapter!.id)"
                                        title="Generate illustration"
                                    >
                                        <div class="flex items-center gap-2 text-amber-700 dark:text-amber-400 hover:text-amber-800 dark:hover:text-amber-300 transition-colors">
                                            <ImageIcon class="w-6 h-6" />
                                            <span class="text-sm font-medium">Add illustration</span>
                                        </div>
                                    </div>
                                    <!-- Pending image placeholder (when generating or no valid URL) -->
                                    <div 
                                        v-else-if="isImagePending(item) || !isValidImageUrl(item.imageUrl)"
                                        class="image-placeholder w-full aspect-video rounded-lg bg-amber-50 dark:bg-amber-950/50 flex flex-col items-center justify-center gap-3 border-2 border-dashed border-amber-600 dark:border-amber-500"
                                    >
                                        <div class="relative">
                                            <ImageIcon class="w-12 h-12 text-amber-800 dark:text-amber-300" />
                                            <div class="absolute -top-1 -right-1">
                                                <Sparkles class="w-5 h-5 text-orange-600 dark:text-orange-400 animate-pulse" />
                                            </div>
                                        </div>
                                        <div class="text-center px-4">
                                            <p class="text-sm font-semibold text-amber-900 dark:text-amber-200">
                                                Creating image...
                                            </p>
                                            <p class="text-xs text-amber-700 dark:text-amber-400 mt-1 max-w-xs">
                                                The magic is happening ✨
                                            </p>
                                        </div>
                                        <!-- Animated loading bar -->
                                        <div class="w-32 h-1.5 bg-amber-200 dark:bg-amber-800 rounded-full overflow-hidden">
                                            <div class="h-full bg-linear-to-r from-amber-600 to-orange-500 dark:from-amber-400 dark:to-orange-400 rounded-full animate-shimmer"></div>
                                        </div>
                                        <!-- Cancel button for long-running generation -->
                                        <button
                                            @click.stop="emit('cancelInlineImage', item, chapter!.id)"
                                            class="mt-1 flex items-center gap-1 text-xs text-amber-600 dark:text-amber-400 hover:text-amber-800 dark:hover:text-amber-200 transition-colors cursor-pointer"
                                        >
                                            <XCircle class="h-3 w-3" />
                                            <span>Cancel</span>
                                        </button>
                                    </div>
                                    <!-- Loaded image (only when we have a valid URL) -->
                                    <template v-else>
                                        <!-- Regenerate Image Button -->
                                        <button
                                            @click.stop="emit('regenerateImage', item, chapter!.id)"
                                            class="absolute bottom-3 right-3 z-10 flex items-center gap-1.5 rounded-full bg-black/60 px-3 py-1.5 text-xs font-medium text-white/90 opacity-0 backdrop-blur-sm transition-all duration-300 hover:bg-black/75 group-hover/image:opacity-100 cursor-pointer"
                                            title="Generate new illustration"
                                        >
                                            <RefreshCw class="h-3.5 w-3.5" />
                                            <span>New image</span>
                                        </button>
                                        <!-- Open in new window button -->
                                        <button
                                            @click.stop="openImageInNewWindow(item.imageUrl)"
                                            class="absolute top-3 right-3 z-10 flex items-center gap-1.5 rounded-full bg-black/60 px-3 py-1.5 text-xs font-medium text-white/90 opacity-0 backdrop-blur-sm transition-all duration-300 hover:bg-black/75 group-hover/image:opacity-100 cursor-pointer"
                                            title="Open full image in new window"
                                        >
                                            <ExternalLink class="h-3.5 w-3.5" />
                                            <span>View full</span>
                                        </button>
                                        <img
                                            :src="item.imageUrl!"
                                            :alt="'Chapter illustration'"
                                            class="w-full h-auto rounded-lg shadow-md object-cover aspect-video cursor-pointer transition-all hover:shadow-lg"
                                            loading="lazy"
                                            @click.stop="openImageInNewWindow(item.imageUrl)"
                                            title="Click to view full image"
                                        />
                                    </template>
                                </figure>
                            </template>
                        </div>
                        
                        <!-- Edit Chapter Button (shown on last spread of most recent chapter when ending on left) -->
                        <div 
                            v-if="showEditButton" 
                            class="flex justify-center mt-8 pb-4"
                        >
                            <button
                                @click.stop="emit('editChapter')"
                                class="group flex items-center gap-2 rounded-full bg-amber-100/90 px-4 py-2 text-sm font-medium text-amber-800 shadow-sm backdrop-blur-sm transition-all duration-300 hover:bg-amber-200/90 hover:shadow-md hover:scale-[1.02] active:scale-[0.98] dark:bg-amber-900/80 dark:text-amber-200 dark:hover:bg-amber-800/90 cursor-pointer"
                            >
                                <PenLine class="h-4 w-4 transition-transform duration-300 group-hover:rotate-[-8deg]" />
                                <span>Need to make changes?</span>
                            </button>
                        </div>
                    </div>
                </template>
                <!-- Empty left page (e.g., last spread with only right content) -->
                <template v-else>
                    <BookPageDecorative variant="book" />
                </template>
            </div>
        </template>
        
        <template v-else-if="readingView === 'create-chapter'">
            <!-- Show create form on left when chapter ended on right -->
            <CreateChapterForm
                v-if="showCreateFormOnLeft"
                :chapter-number="currentChapterNumber ?? 1"
                :prompt="nextChapterPrompt ?? ''"
                :suggested-idea="suggestedIdea"
                :is-loading-idea="isLoadingIdea"
                :is-final-chapter="isFinalChapter ?? false"
                :is-generating="isGeneratingChapter ?? false"
                :book-type="bookType"
                @update:prompt="emit('update:nextChapterPrompt', $event)"
                @update:is-final-chapter="emit('update:isFinalChapter', $event)"
                @generate="emit('generateChapter')"
                @textarea-focused="emit('textareaFocused', $event)"
                @request-idea="emit('requestIdea')"
            />
            <!-- Otherwise show decorative -->
            <BookPageDecorative v-else variant="wand" />
        </template>

        <!-- Page Number Footer -->
        <div 
            v-if="leftPageNumber !== null"
            class="absolute bottom-6 left-0 right-0 flex items-center justify-center gap-3 px-12"
        >
            <div class="h-px flex-1 bg-linear-to-r from-transparent via-amber-600 to-amber-600 dark:via-amber-400 dark:to-amber-400" />
            <span class="text-sm font-medium text-amber-700 tabular-nums">
                {{ leftPageNumber }}
            </span>
            <div class="h-px flex-1 bg-linear-to-l from-transparent via-amber-600 to-amber-600 dark:via-amber-400 dark:to-amber-400" />
        </div>
    </div>
</template>

<style scoped>
.image-placeholder {
    animation: placeholder-pulse 2s ease-in-out infinite;
}

@keyframes placeholder-pulse {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.85;
    }
}

.animate-shimmer {
    animation: shimmer 1.5s ease-in-out infinite;
}

@keyframes shimmer {
    0% {
        transform: translateX(-100%);
        width: 30%;
    }
    50% {
        width: 60%;
    }
    100% {
        transform: translateX(400%);
        width: 30%;
    }
}
</style>
