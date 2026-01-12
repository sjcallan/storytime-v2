<script setup lang="ts">
import { computed, ref, onMounted, onUnmounted, watch } from 'vue';
import { Sparkles, ImageIcon, RefreshCw, ExternalLink, PenLine, AlertTriangle, XCircle, Clock } from 'lucide-vue-next';
import type { Chapter, PageSpread, PageContentItem, BookType } from './types';
import { getChapterLabel, isSceneBasedBook, formatScriptDialogue } from './types';

// Open image in new window
const openImageInNewWindow = (url: string | null | undefined) => {
    if (url && typeof url === 'string') {
        window.open(url, '_blank', 'noopener,noreferrer');
    }
};

interface Props {
    chapter: Chapter;
    spread: PageSpread;
    spreadIndex?: number;
    bookType?: BookType;
    isLastChapter?: boolean;
    isLastSpread?: boolean;
    chapterImageStatus?: 'pending' | 'complete' | 'error' | 'timeout' | null;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'regenerateImage', item: PageContentItem, chapterId: string): void;
    (e: 'regenerateHeaderImage', chapterId: string): void;
    (e: 'generateHeaderImage', chapterId: string): void;
    (e: 'retryHeaderImage', chapterId: string): void;
    (e: 'cancelHeaderImage', chapterId: string): void;
    (e: 'retryInlineImage', item: PageContentItem, chapterId: string): void;
    (e: 'cancelInlineImage', item: PageContentItem, chapterId: string): void;
    (e: 'editChapter'): void;
    (e: 'scrolledToBottom'): void;
}>();

// Check header image states
const isHeaderImagePending = computed(() => {
    // Explicitly pending from status prop
    if (props.chapterImageStatus === 'pending') {
        return true;
    }
    // Has header image ID but no URL yet (and not in error/timeout state)
    if (props.chapter.header_image_id && !headerImageUrl.value && props.chapterImageStatus !== 'error' && props.chapterImageStatus !== 'timeout') {
        return true;
    }
    // Legacy: has image_prompt but no image (and not in error/timeout state)
    if (props.chapter.image_prompt && !headerImageUrl.value && props.chapterImageStatus !== 'error' && props.chapterImageStatus !== 'timeout') {
        return true;
    }
    return false;
});

const isHeaderImageError = computed(() => {
    return props.chapterImageStatus === 'error';
});

const isHeaderImageTimeout = computed(() => {
    return props.chapterImageStatus === 'timeout';
});

// Scroll detection
const contentScrollArea = ref<HTMLElement | null>(null);
const hasEmittedScrollToBottom = ref(false);

const checkScrollPosition = () => {
    const el = contentScrollArea.value;
    if (!el) {
        return;
    }
    
    // Check if content is scrollable (has overflow)
    const isScrollable = el.scrollHeight > el.clientHeight;
    if (!isScrollable) {
        return;
    }
    
    // Check if scrolled to bottom (with small tolerance)
    const scrolledToBottom = el.scrollTop + el.clientHeight >= el.scrollHeight - 10;
    
    if (scrolledToBottom && !hasEmittedScrollToBottom.value) {
        hasEmittedScrollToBottom.value = true;
        emit('scrolledToBottom');
    }
};

// Reset when spread changes
watch(() => [props.spread, props.spreadIndex], () => {
    hasEmittedScrollToBottom.value = false;
});

onMounted(() => {
    if (contentScrollArea.value) {
        contentScrollArea.value.addEventListener('scroll', checkScrollPosition, { passive: true });
    }
});

onUnmounted(() => {
    if (contentScrollArea.value) {
        contentScrollArea.value.removeEventListener('scroll', checkScrollPosition);
    }
});

// Show edit button only on the last spread of the most recent chapter
const showEditButton = computed(() => {
    return props.isLastChapter && props.isLastSpread && !props.chapter.final_chapter;
});

const chapterLabel = computed(() => getChapterLabel(props.bookType));
const isScript = computed(() => isSceneBasedBook(props.bookType));

const isFirstParagraph = (items: PageContentItem[] | null, idx: number): boolean => {
    if (!items) {
        return false;
    }
    let paragraphCount = 0;
    for (let i = 0; i <= idx; i++) {
        if (items[i]?.type === 'paragraph') {
            paragraphCount++;
        }
    }
    return paragraphCount === 1 && items[idx]?.type === 'paragraph';
};

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

// Computed property for header image URL (supports both legacy 'image' and new 'header_image_url')
const headerImageUrl = computed(() => {
    return props.chapter.image || props.chapter.header_image_url || null;
});

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


// Calculate right page number
const rightPageNumber = computed(() => {
    if (props.spreadIndex === undefined) {
        return null;
    }
    // Title page is 0, first spread is pages 2-3, second spread is 4-5, etc.
    return 3 + (props.spreadIndex * 2);
});
</script>

<template>
    <div class="relative z-10 h-full overflow-hidden">
        <!-- First spread: Header image + Title + beginning of content -->
        <template v-if="spread.isFirstSpread">
            <div class="flex h-full flex-col px-16 pt-6 pb-6">
                <!-- Chapter Header Image Section -->
                <div class="shrink-0 mb-6 mt-10">
                    <!-- Error state header image placeholder -->
                    <div 
                        v-if="isHeaderImageError"
                        class="relative w-full aspect-16/7 rounded-lg bg-red-50 dark:bg-red-950/50 flex flex-col items-center justify-center gap-2 border-2 border-dashed border-red-400 dark:border-red-600"
                    >
                        <div class="relative">
                            <AlertTriangle class="w-10 h-10 text-red-600 dark:text-red-400" />
                        </div>
                        <div class="text-center px-4">
                            <p class="text-xs font-semibold text-red-800 dark:text-red-200">
                                Chapter illustration failed
                            </p>
                            <p class="text-xs text-red-600 dark:text-red-400 mt-0.5">
                                Something went wrong while creating this image
                            </p>
                        </div>
                        <div class="flex gap-2 mt-1">
                            <button
                                @click.stop="emit('retryHeaderImage', chapter.id)"
                                class="flex items-center gap-1.5 rounded-full bg-red-600 px-3 py-1.5 text-xs font-medium text-white transition-all hover:bg-red-700 active:scale-95 cursor-pointer"
                            >
                                <RefreshCw class="h-3 w-3" />
                                <span>Retry</span>
                            </button>
                            <button
                                @click.stop="emit('cancelHeaderImage', chapter.id)"
                                class="flex items-center gap-1.5 rounded-full bg-red-100 dark:bg-red-900/50 px-3 py-1.5 text-xs font-medium text-red-700 dark:text-red-300 transition-all hover:bg-red-200 dark:hover:bg-red-800/50 active:scale-95 cursor-pointer"
                            >
                                <XCircle class="h-3 w-3" />
                                <span>Remove</span>
                            </button>
                        </div>
                    </div>
                    <!-- Timeout state header image placeholder -->
                    <div 
                        v-else-if="isHeaderImageTimeout"
                        class="relative w-full aspect-16/7 rounded-lg bg-orange-50 dark:bg-orange-950/50 flex flex-col items-center justify-center gap-2 border-2 border-dashed border-orange-400 dark:border-orange-600"
                    >
                        <div class="relative">
                            <Clock class="w-10 h-10 text-orange-600 dark:text-orange-400" />
                        </div>
                        <div class="text-center px-4">
                            <p class="text-xs font-semibold text-orange-800 dark:text-orange-200">
                                Taking longer than expected
                            </p>
                            <p class="text-xs text-orange-600 dark:text-orange-400 mt-0.5">
                                The image service is busy. You can retry or skip.
                            </p>
                        </div>
                        <div class="flex gap-2 mt-1">
                            <button
                                @click.stop="emit('retryHeaderImage', chapter.id)"
                                class="flex items-center gap-1.5 rounded-full bg-orange-600 px-3 py-1.5 text-xs font-medium text-white transition-all hover:bg-orange-700 active:scale-95 cursor-pointer"
                            >
                                <RefreshCw class="h-3 w-3" />
                                <span>Retry</span>
                            </button>
                            <button
                                @click.stop="emit('cancelHeaderImage', chapter.id)"
                                class="flex items-center gap-1.5 rounded-full bg-orange-100 dark:bg-orange-900/50 px-3 py-1.5 text-xs font-medium text-orange-700 dark:text-orange-300 transition-all hover:bg-orange-200 dark:hover:bg-orange-800/50 active:scale-95 cursor-pointer"
                            >
                                <XCircle class="h-3 w-3" />
                                <span>Skip</span>
                            </button>
                        </div>
                    </div>
                    <!-- Pending header image placeholder -->
                    <div 
                        v-else-if="isHeaderImagePending"
                        class="relative w-full aspect-16/7 rounded-lg bg-amber-50 dark:bg-amber-950/50 flex flex-col items-center justify-center gap-2 border-2 border-dashed border-amber-600 dark:border-amber-500 image-placeholder"
                    >
                        <div class="relative">
                            <ImageIcon class="w-10 h-10 text-amber-800 dark:text-amber-300" />
                            <div class="absolute -top-1 -right-1">
                                <Sparkles class="w-4 h-4 text-orange-600 dark:text-orange-400 animate-pulse" />
                            </div>
                        </div>
                        <div class="text-center px-4">
                            <p class="text-xs font-semibold text-amber-900 dark:text-amber-200">
                                Creating chapter illustration...
                            </p>
                        </div>
                        <!-- Animated loading bar -->
                        <div class="w-24 h-1 bg-amber-200 dark:bg-amber-800 rounded-full overflow-hidden">
                            <div class="h-full bg-linear-to-r from-amber-600 to-orange-500 dark:from-amber-400 dark:to-orange-400 rounded-full animate-shimmer"></div>
                        </div>
                        <!-- Cancel button -->
                        <button
                            @click.stop="emit('cancelHeaderImage', chapter.id)"
                            class="mt-1 flex items-center gap-1 text-xs text-amber-600 dark:text-amber-400 hover:text-amber-800 dark:hover:text-amber-200 transition-colors cursor-pointer"
                        >
                            <XCircle class="h-3 w-3" />
                            <span>Cancel</span>
                        </button>
                    </div>
                    <!-- Loaded header image -->
                    <figure 
                        v-else-if="headerImageUrl && isValidImageUrl(headerImageUrl)"
                        class="group/header-image relative w-full"
                    >
                        <!-- Regenerate Header Image Button -->
                        <button
                            @click.stop="emit('regenerateHeaderImage', chapter.id)"
                            class="absolute bottom-3 right-3 z-10 flex items-center gap-1.5 rounded-full bg-black/60 px-3 py-1.5 text-xs font-medium text-white/90 opacity-0 backdrop-blur-sm transition-all duration-300 hover:bg-black/75 group-hover/header-image:opacity-100 cursor-pointer"
                            title="Generate new chapter illustration"
                        >
                            <RefreshCw class="h-3.5 w-3.5" />
                            <span>New image</span>
                        </button>
                        <!-- Open in new window button -->
                        <button
                            @click.stop="openImageInNewWindow(headerImageUrl)"
                            class="absolute top-3 right-3 z-10 flex items-center gap-1.5 rounded-full bg-black/60 px-3 py-1.5 text-xs font-medium text-white/90 opacity-0 backdrop-blur-sm transition-all duration-300 hover:bg-black/75 group-hover/header-image:opacity-100 cursor-pointer"
                            title="Open full image in new window"
                        >
                            <ExternalLink class="h-3.5 w-3.5" />
                            <span>View full</span>
                        </button>
                        <img
                            :src="headerImageUrl"
                            :alt="`${chapterLabel} ${chapter.sort} illustration`"
                            class="w-full h-auto rounded-lg shadow-md object-cover aspect-16/7 cursor-pointer transition-all hover:shadow-lg"
                            loading="lazy"
                            @click.stop="openImageInNewWindow(headerImageUrl)"
                            title="Click to view full image"
                        />
                    </figure>
                    <!-- No header image - option to generate one -->
                    <div 
                        v-else
                        class="group/header-image relative w-full aspect-16/7 rounded-lg bg-amber-100/60 dark:bg-amber-900/30 flex flex-col items-center justify-center gap-2 border-2 border-dashed border-amber-300 dark:border-amber-700 hover:border-amber-500 dark:hover:border-amber-500 hover:bg-amber-100 dark:hover:bg-amber-900/50 transition-colors cursor-pointer"
                        @click.stop="emit('generateHeaderImage', chapter.id)"
                        title="Generate chapter illustration"
                    >
                        <div class="flex items-center gap-2 text-amber-700 group-hover/header-image:text-amber-800 dark:group-hover/header-image:text-amber-300 transition-colors">
                            <ImageIcon class="w-6 h-6" />
                            <span class="text-sm font-medium">Add chapter illustration</span>
                        </div>
                    </div>
                </div>
                
                <!-- Chapter Title Section -->
                <div class="shrink-0 flex items-center justify-center py-3">
                    <div class="text-center">
                        <div class="text-xs uppercase tracking-widest text-amber-700 mb-2 font-medium">
                            {{ chapterLabel }} {{ chapter.sort }}
                        </div>
                        <h2 class="font-serif text-2xl md:text-3xl font-bold text-amber-950 dark:text-amber-900">
                            {{ chapter.title || `${chapterLabel} ${chapter.sort}` }}
                        </h2>
                        
                        <!-- Decorative -->
                        <div class="mt-3 flex items-center justify-center gap-3">
                            <div class="h-px w-12 bg-linear-to-r from-transparent via-amber-700 to-transparent dark:via-amber-600"></div>
                            <Sparkles class="h-3 w-3 text-amber-700 dark:text-amber-500" />
                            <div class="h-px w-12 bg-linear-to-r from-transparent via-amber-700 to-transparent dark:via-amber-600"></div>
                        </div>
                    </div>
                </div>
                
                <!-- Remaining ~60% for content -->
                <div ref="contentScrollArea" class="flex-1 overflow-y-auto pb-8">
                    <div v-if="spread.rightContent" class="prose prose-amber prose-lg max-w-none text-amber-950 dark:text-amber-900">
                        <template v-for="(item, idx) in spread.rightContent" :key="idx">
                            <p 
                                v-if="item.type === 'paragraph'"
                                :class="[
                                    'mb-5 font-serif text-lg leading-relaxed',
                                    isFirstParagraph(spread.rightContent, idx) && !isScript ? 'drop-cap' : ''
                                ]"
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
                                        @click.stop="emit('retryInlineImage', item, chapter.id)"
                                        class="flex items-center gap-1.5 rounded-full bg-red-600 px-4 py-2 text-xs font-medium text-white transition-all hover:bg-red-700 active:scale-95 cursor-pointer"
                                    >
                                        <RefreshCw class="h-3.5 w-3.5" />
                                        <span>Retry</span>
                                    </button>
                                    <button
                                        @click.stop="emit('cancelInlineImage', item, chapter.id)"
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
                                        @click.stop="emit('retryInlineImage', item, chapter.id)"
                                        class="flex items-center gap-1.5 rounded-full bg-orange-600 px-4 py-2 text-xs font-medium text-white transition-all hover:bg-orange-700 active:scale-95 cursor-pointer"
                                    >
                                        <RefreshCw class="h-3.5 w-3.5" />
                                        <span>Retry</span>
                                    </button>
                                    <button
                                        @click.stop="emit('cancelInlineImage', item, chapter.id)"
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
                                @click.stop="emit('retryInlineImage', item, chapter.id)"
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
                                    @click.stop="emit('cancelInlineImage', item, chapter.id)"
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
                                    @click.stop="emit('regenerateImage', item, chapter.id)"
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
                    
                    <!-- Edit Chapter Button (shown on last spread of most recent chapter) - First spread -->
                    <div 
                        v-if="showEditButton && spread.isFirstSpread" 
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
            </div>
        </template>
        
        <!-- Subsequent spreads: Content continuation on right page (full height) -->
        <template v-else>
            <div ref="contentScrollArea" class="h-full px-16 pt-24 pb-12 overflow-y-auto">
                <div v-if="spread.rightContent" class="prose prose-amber prose-lg max-w-none text-amber-950 dark:text-amber-900">
                    <template v-for="(item, idx) in spread.rightContent" :key="idx">
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
                                        @click.stop="emit('retryInlineImage', item, chapter.id)"
                                        class="flex items-center gap-1.5 rounded-full bg-red-600 px-4 py-2 text-xs font-medium text-white transition-all hover:bg-red-700 active:scale-95 cursor-pointer"
                                    >
                                        <RefreshCw class="h-3.5 w-3.5" />
                                        <span>Retry</span>
                                    </button>
                                    <button
                                        @click.stop="emit('cancelInlineImage', item, chapter.id)"
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
                                        @click.stop="emit('retryInlineImage', item, chapter.id)"
                                        class="flex items-center gap-1.5 rounded-full bg-orange-600 px-4 py-2 text-xs font-medium text-white transition-all hover:bg-orange-700 active:scale-95 cursor-pointer"
                                    >
                                        <RefreshCw class="h-3.5 w-3.5" />
                                        <span>Retry</span>
                                    </button>
                                    <button
                                        @click.stop="emit('cancelInlineImage', item, chapter.id)"
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
                                @click.stop="emit('retryInlineImage', item, chapter.id)"
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
                                    @click.stop="emit('cancelInlineImage', item, chapter.id)"
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
                                    @click.stop="emit('regenerateImage', item, chapter.id)"
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
                <!-- If no right content on this spread (odd number of continuation pages) -->
                <div v-else class="flex h-full items-center justify-center">
                    <div class="text-center opacity-40">
                        <Sparkles class="mx-auto h-8 w-8 text-amber-500 dark:text-amber-400" />
                    </div>
                </div>
                
                <!-- Edit Chapter Button (shown on last spread of most recent chapter) - Subsequent spreads -->
                <div 
                    v-if="showEditButton && !spread.isFirstSpread" 
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

        <!-- Page Number Footer -->
        <div 
            v-if="rightPageNumber !== null"
            class="absolute bottom-6 left-0 right-0 flex items-center justify-center gap-3 px-12 z-20"
        >
            <div class="h-px flex-1 bg-linear-to-r from-transparent via-amber-600 to-amber-600 dark:via-amber-400 dark:to-amber-400" />
            <span class="text-sm font-medium text-amber-700 tabular-nums">
                {{ rightPageNumber }}
            </span>
            <div class="h-px flex-1 bg-linear-to-l from-transparent via-amber-600 to-amber-600 dark:via-amber-400 dark:to-amber-400" />
        </div>
    </div>
</template>

<style scoped>
.drop-cap::first-letter {
    font-size: 3.5rem;
    font-weight: 700;
    float: left;
    margin-right: 0.5rem;
    line-height: 0.8;
    color: inherit;
}

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

