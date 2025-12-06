<script setup lang="ts">
import BookPageTexture from './BookPageTexture.vue';
import BookPageDecorative from './BookPageDecorative.vue';
import BookTitlePage from './BookTitlePage.vue';
import BookChapterContent from './BookChapterContent.vue';
import BookEditForm from './BookEditForm.vue';
import CreateChapterForm from './CreateChapterForm.vue';
import CharacterDetail from './CharacterDetail.vue';
import type { Book, Chapter, PageSpread, ReadingView, BookEditFormData, Character } from './types';

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
    isFinalChapter: boolean;
    isGeneratingChapter: boolean;
    selectedCharacter?: Character | null;
}

defineProps<Props>();

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
</script>

<template>
    <div class="relative w-1/2 h-full bg-amber-50 dark:bg-amber-100 overflow-hidden">
        <!-- Paper texture -->
        <BookPageTexture />
        
        <!-- Decorative page lines -->
        <div class="absolute inset-y-8 right-4 w-px bg-amber-300/60 dark:bg-amber-400/50" />
        <div class="absolute inset-y-8 right-8 w-px bg-amber-300/40 dark:bg-amber-400/30" />
        
        <!-- Left shadow from spine -->
        <div class="absolute inset-y-0 left-0 w-6 bg-gradient-to-r from-amber-900/10 to-transparent pointer-events-none" />

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
        <BookChapterContent
            v-else-if="(readingView === 'chapter-image' || readingView === 'chapter-content') && chapter && spread"
            :chapter="chapter"
            :spread="spread"
            :spread-index="spreadIndex"
        />

        <!-- Create Chapter View -->
        <CreateChapterForm
            v-else-if="readingView === 'create-chapter'"
            :chapter-number="currentChapterNumber"
            :prompt="nextChapterPrompt"
            :is-final-chapter="isFinalChapter"
            :is-generating="isGeneratingChapter"
            @update:prompt="emit('update:nextChapterPrompt', $event)"
            @update:is-final-chapter="emit('update:isFinalChapter', $event)"
            @generate="emit('generateChapter')"
            @back="emit('goBack')"
        />

        <!-- Table of Contents View - show decorative right page -->
        <BookPageDecorative
            v-else-if="readingView === 'toc'"
            variant="sparkles"
        />
    </div>
</template>

