<script setup lang="ts">
import { computed } from 'vue';
import BookPageTexture from './BookPageTexture.vue';
import BookPageDecorative from './BookPageDecorative.vue';
import BookTitlePage from './BookTitlePage.vue';
import BookChapterContent from './BookChapterContent.vue';
import BookEditForm from './BookEditForm.vue';
import CreateChapterForm from './CreateChapterForm.vue';
import CharacterDetail from './CharacterDetail.vue';
import NextChapterPreview from './NextChapterPreview.vue';
import type { Book, Chapter, PageSpread, ReadingView, BookEditFormData, Character, PageContentItem } from './types';

interface Props {
    book: Book | null;
    readingView: ReadingView;
    chapter: Chapter | null;
    spread: PageSpread | null;
    spreadIndex?: number;
    coverImage: string | null;
    title: string;
    author: string | null;
    createdAt: string | null;
    isTitlePageFading: boolean;
    isLoadingChapter: boolean;
    isEditing: boolean;
    editForm: BookEditFormData;
    editErrors: Record<string, string>;
    isSaving: boolean;
    isDeleting: boolean;
    actionError: string | null;
    chapterError: string | null;
    currentChapterNumber: number;
    nextChapterPrompt: string;
    suggestedPlaceholder?: string | null;
    isLoadingPlaceholder?: boolean;
    isFinalChapter: boolean;
    isGeneratingChapter: boolean;
    selectedCharacter?: Character | null;
    hasNextChapter?: boolean;
    chapterEndsOnLeft?: boolean;
    isOnLastSpread?: boolean;
    shouldShowNextChapterOnRight?: boolean;
    nextChapterData?: Chapter | null;
    nextChapterFirstPage?: PageContentItem[] | null;
}

// Book type is derived from the book prop
const bookType = computed(() => props.book?.type);

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'continueToChapter1'): void;
    (e: 'update:editForm', value: BookEditFormData): void;
    (e: 'submitEdit'): void;
    (e: 'cancelEdit'): void;
    (e: 'update:nextChapterPrompt', value: string): void;
    (e: 'update:isFinalChapter', value: boolean): void;
    (e: 'generateChapter'): void;
    (e: 'goBack'): void;
    (e: 'clearSelectedCharacter'): void;
}>();

// Show create form on right when in create-chapter view and chapter ended on left
const showCreateFormOnRight = computed(() => {
    return props.readingView === 'create-chapter' && 
           props.chapterEndsOnLeft && 
           !props.hasNextChapter;
});

// Show decorative on right when in create-chapter view and chapter ended on right
const showDecorativeOnRight = computed(() => {
    return props.readingView === 'create-chapter' && 
           !props.chapterEndsOnLeft && 
           !props.hasNextChapter;
});

// Show inline create form when on last spread with no right content and no next chapter
const showInlineCreateForm = computed(() => {
    const isChapterView = props.readingView === 'chapter-image' || props.readingView === 'chapter-content';
    const hasNoRightContent = props.spread && (props.spread.rightContent === null || props.spread.rightContent === undefined);
    return isChapterView && 
           hasNoRightContent && 
           props.isOnLastSpread && 
           !props.hasNextChapter &&
           !props.shouldShowNextChapterOnRight;
});
</script>

<template>
    <div class="relative w-1/2 h-full bg-amber-50 dark:bg-amber-100 overflow-hidden">
        <!-- Paper texture -->
        <BookPageTexture />
        
        <!-- Decorative page lines -->
        <div class="absolute inset-y-0 right-4 w-px bg-amber-300/60 dark:bg-amber-400/50" />
        <div class="absolute inset-y-0 right-8 w-px bg-amber-300/40 dark:bg-amber-400/30" />
        
        <!-- Left shadow from spine -->
        <div class="absolute inset-y-0 left-0 w-6 bg-linear-to-r from-amber-900/10 to-transparent pointer-events-none" />

        <!-- Action/Chapter Error -->
        <div
            v-if="actionError || chapterError"
            class="mx-8 mt-16 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-left text-sm font-medium text-red-700 dark:border-red-900/40 dark:bg-red-900/20 dark:text-red-200"
        >
            {{ actionError || chapterError }}
        </div>

        <!-- Edit Form -->
        <BookEditForm
            v-if="isEditing"
            :form="editForm"
            :errors="editErrors"
            :is-saving="isSaving"
            :is-deleting="isDeleting"
            @update:form="emit('update:editForm', $event)"
            @submit="emit('submitEdit')"
            @cancel="emit('cancelEdit')"
        />

        <!-- Character Detail View (when character is selected from grid) -->
        <CharacterDetail
            v-else-if="readingView === 'title' && selectedCharacter"
            :character="selectedCharacter"
            @back="emit('clearSelectedCharacter')"
        />

        <!-- Title Page View -->
        <BookTitlePage
            v-else-if="readingView === 'title'"
            :cover-image="coverImage"
            :cover-image-status="book?.cover_image_status ?? null"
            :title="title"
            :author="author"
            :created-at="createdAt"
            :is-fading="isTitlePageFading"
            :is-loading="isLoadingChapter"
            @continue="emit('continueToChapter1')"
        />

        <!-- Chapter Content View -->
        <template v-else-if="(readingView === 'chapter-image' || readingView === 'chapter-content') && chapter && spread">
            <!-- Show next chapter preview when current chapter ends on left and there's a next chapter -->
            <NextChapterPreview
                v-if="shouldShowNextChapterOnRight && nextChapterData && nextChapterFirstPage"
                :chapter="nextChapterData"
                :first-page-content="nextChapterFirstPage"
                :book-type="bookType"
            />
            <!-- Show inline create form when on last spread with no right content and no next chapter -->
            <CreateChapterForm
                v-else-if="showInlineCreateForm"
                :chapter-number="currentChapterNumber + 1"
                :prompt="nextChapterPrompt"
                :suggested-placeholder="suggestedPlaceholder"
                :is-loading-placeholder="isLoadingPlaceholder"
                :is-final-chapter="isFinalChapter"
                :is-generating="isGeneratingChapter"
                :book-type="bookType"
                @update:prompt="emit('update:nextChapterPrompt', $event)"
                @update:is-final-chapter="emit('update:isFinalChapter', $event)"
                @generate="emit('generateChapter')"
            />
            <!-- Otherwise show chapter content -->
            <BookChapterContent
                v-else
                :chapter="chapter"
                :spread="spread"
                :spread-index="spreadIndex"
                :book-type="bookType"
            />
        </template>

        <!-- Create Chapter View -->
        <template v-else-if="readingView === 'create-chapter'">
            <!-- Show create form on right when chapter ended on left -->
            <CreateChapterForm
                v-if="showCreateFormOnRight"
                :chapter-number="currentChapterNumber"
                :prompt="nextChapterPrompt"
                :suggested-placeholder="suggestedPlaceholder"
                :is-loading-placeholder="isLoadingPlaceholder"
                :is-final-chapter="isFinalChapter"
                :is-generating="isGeneratingChapter"
                :book-type="bookType"
                @update:prompt="emit('update:nextChapterPrompt', $event)"
                @update:is-final-chapter="emit('update:isFinalChapter', $event)"
                @generate="emit('generateChapter')"
            />
            <!-- Show decorative on right when chapter ended on right (form is on left) -->
            <BookPageDecorative v-else-if="showDecorativeOnRight" variant="wand" />
            <!-- Fallback for edge cases -->
            <CreateChapterForm
                v-else
                :chapter-number="currentChapterNumber"
                :prompt="nextChapterPrompt"
                :suggested-placeholder="suggestedPlaceholder"
                :is-loading-placeholder="isLoadingPlaceholder"
                :is-final-chapter="isFinalChapter"
                :is-generating="isGeneratingChapter"
                :book-type="bookType"
                @update:prompt="emit('update:nextChapterPrompt', $event)"
                @update:is-final-chapter="emit('update:isFinalChapter', $event)"
                @generate="emit('generateChapter')"
            />
        </template>

        <!-- Table of Contents View - show decorative right page -->
        <BookPageDecorative
            v-else-if="readingView === 'toc'"
            variant="sparkles"
        />
    </div>
</template>

