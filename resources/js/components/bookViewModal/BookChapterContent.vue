<script setup lang="ts">
import { computed } from 'vue';
import { Sparkles, ImageIcon, RefreshCw, ExternalLink } from 'lucide-vue-next';
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
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'regenerateImage', item: PageContentItem, chapterId: string): void;
}>();

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
        <!-- First spread: Title with 40% top margin + beginning of content -->
        <template v-if="spread.isFirstSpread">
            <div class="flex h-full flex-col px-16 pt-24 pb-6">
                <!-- 40% top margin space -->
                <div class="h-[40%] flex items-end justify-center pb-4">
                    <div class="text-center">
                        <div class="text-xs uppercase tracking-widest text-amber-700 dark:text-amber-600 mb-2 font-medium">
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
                <div class="flex-1 overflow-y-auto">
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
                            <!-- Pending image placeholder (when generating or no valid URL) -->
                            <div 
                                v-if="item.imageStatus === 'pending' || !item.imageUrl || !isValidImageUrl(item.imageUrl)"
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
                                        Creating illustration...
                                    </p>
                                    <p class="text-xs text-amber-700 dark:text-amber-400 mt-1 max-w-xs">
                                        The magic is happening ✨
                                    </p>
                                </div>
                                <!-- Animated loading bar -->
                                <div class="w-32 h-1.5 bg-amber-200 dark:bg-amber-800 rounded-full overflow-hidden">
                                    <div class="h-full bg-linear-to-r from-amber-600 to-orange-500 dark:from-amber-400 dark:to-orange-400 rounded-full animate-shimmer"></div>
                                </div>
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
                </div>
            </div>
        </template>
        
        <!-- Subsequent spreads: Content continuation on right page (full height) -->
        <template v-else>
            <div class="h-full px-16 pt-24 pb-12 overflow-y-auto">
                <div v-if="spread.rightContent" class="prose prose-amber prose-lg max-w-none text-amber-950 dark:text-amber-900">
                    <template v-for="(item, idx) in spread.rightContent" :key="idx">
                        <p 
                            v-if="item.type === 'paragraph'"
                            class="mb-5 font-serif text-lg leading-relaxed"
                            v-html="formatContent(item.content)"
                        />
                        <figure v-else-if="item.type === 'image'" class="group/image my-6 relative">
                            <!-- Pending image placeholder (when generating or no valid URL) -->
                            <div 
                                v-if="item.imageStatus === 'pending' || !item.imageUrl || !isValidImageUrl(item.imageUrl)"
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
                                        Creating illustration...
                                    </p>
                                    <p class="text-xs text-amber-700 dark:text-amber-400 mt-1 max-w-xs">
                                        The magic is happening ✨
                                    </p>
                                </div>
                                <!-- Animated loading bar -->
                                <div class="w-32 h-1.5 bg-amber-200 dark:bg-amber-800 rounded-full overflow-hidden">
                                    <div class="h-full bg-linear-to-r from-amber-600 to-orange-500 dark:from-amber-400 dark:to-orange-400 rounded-full animate-shimmer"></div>
                                </div>
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
            </div>
        </template>

        <!-- Page Number Footer -->
        <div 
            v-if="rightPageNumber !== null"
            class="absolute bottom-6 left-0 right-0 flex items-center justify-center gap-3 px-12 z-20"
        >
            <div class="h-px flex-1 bg-linear-to-r from-transparent via-amber-600 to-amber-600 dark:via-amber-400 dark:to-amber-400" />
            <span class="text-sm font-medium text-amber-700 dark:text-amber-600 tabular-nums">
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

