<script setup lang="ts">
import { computed } from 'vue';
import BookPageTexture from './BookPageTexture.vue';
import BookPageDecorative from './BookPageDecorative.vue';
import CharacterGrid from './CharacterGrid.vue';
import CreateChapterForm from './CreateChapterForm.vue';
import { BookOpen } from 'lucide-vue-next';
import type { Chapter, PageSpread, ReadingView, Character, BookType } from './types';
import { getChapterLabel, isSceneBasedBook, formatScriptDialogue } from './types';

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
    currentChapterNumber?: number;
    nextChapterPrompt?: string;
    suggestedPlaceholder?: string | null;
    isLoadingPlaceholder?: boolean;
    isFinalChapter?: boolean;
    isGeneratingChapter?: boolean;
    bookType?: BookType;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'selectCharacter', character: Character): void;
    (e: 'update:nextChapterPrompt', value: string): void;
    (e: 'update:isFinalChapter', value: boolean): void;
    (e: 'generateChapter'): void;
}>();

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

// Show create form on left page when chapter ended on right and no next chapter
const showCreateFormOnLeft = computed(() => {
    return props.readingView === 'create-chapter' && 
           !props.chapterEndsOnLeft && 
           !props.hasNextChapter;
});

const chapterLabel = computed(() => getChapterLabel(props.bookType));
const isScript = computed(() => isSceneBasedBook(props.bookType));

// Format content - applies script formatting for theatre/screenplay
const formatContent = (content: string): string => {
    return formatScriptDialogue(content, isScript.value);
};
</script>

<template>
    <div class="relative w-1/2 h-full bg-amber-50 dark:bg-amber-100 overflow-hidden">
        <!-- Paper texture -->
        <BookPageTexture />
        
        <!-- Decorative page lines -->
        <div class="absolute inset-y-0 left-4 w-px bg-amber-300/60 dark:bg-amber-400/50" />
        <div class="absolute inset-y-0 left-8 w-px bg-amber-300/40 dark:bg-amber-400/30" />
        
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
                <!-- First spread: show chapter image or decorative -->
                <template v-if="spread.showImage">
                    <div 
                        v-if="chapter.image"
                        class="flex-1 px-6 pt-24 pb-6"
                    >
                        <img
                            :src="chapter.image"
                            :alt="chapter.title || `${chapterLabel} ${chapter.sort}`"
                            class="h-full w-full object-contain rounded-lg shadow-md"
                        />
                    </div>
                    <div v-else class="flex h-full items-center justify-center p-12">
                        <div class="text-center opacity-40">
                            <div class="mx-auto mb-4 h-px w-24 bg-linear-to-r from-transparent via-amber-600 to-transparent dark:via-amber-400" />
                            <BookOpen class="mx-auto h-12 w-12 text-amber-500 dark:text-amber-400" />
                            <div class="mx-auto mt-4 h-px w-24 bg-linear-to-r from-transparent via-amber-600 to-transparent dark:via-amber-400" />
                        </div>
                    </div>
                </template>
                <!-- Subsequent spreads: show content continuation on left page (full height) -->
                <template v-else-if="spread.leftContent">
                    <div class="relative h-full px-16 pt-24 pb-8">
                        <div class="prose prose-amber prose-lg max-w-none text-amber-950 dark:text-amber-900">
                            <template v-for="(item, idx) in spread.leftContent" :key="idx">
                                <p 
                                    v-if="item.type === 'paragraph'"
                                    class="mb-5 font-serif text-lg leading-relaxed"
                                    v-html="formatContent(item.content)"
                                />
                                <figure v-else-if="item.type === 'image'" class="my-6">
                                    <img
                                        :src="item.imageUrl"
                                        :alt="item.content"
                                        class="w-full h-auto rounded-lg shadow-md object-cover aspect-video"
                                        loading="lazy"
                                    />
                                </figure>
                            </template>
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
                :suggested-placeholder="suggestedPlaceholder"
                :is-loading-placeholder="isLoadingPlaceholder"
                :is-final-chapter="isFinalChapter ?? false"
                :is-generating="isGeneratingChapter ?? false"
                :book-type="bookType"
                @update:prompt="emit('update:nextChapterPrompt', $event)"
                @update:is-final-chapter="emit('update:isFinalChapter', $event)"
                @generate="emit('generateChapter')"
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
            <span class="text-sm font-medium text-amber-700 dark:text-amber-600 tabular-nums">
                {{ leftPageNumber }}
            </span>
            <div class="h-px flex-1 bg-linear-to-l from-transparent via-amber-600 to-amber-600 dark:via-amber-400 dark:to-amber-400" />
        </div>
    </div>
</template>

