<script setup lang="ts">
import { ref, watch, nextTick, onBeforeUnmount, computed } from 'vue';
import { apiFetch } from '@/composables/ApiFetch';
import { MagicalSparklesLoader } from '@/components/ui/magical-book-loader';

// Import from bookViewModal module
import {
    useBookAnimation,
    useChapterPagination,
    BookCover,
    BookSpine,
    BookLeftPage,
    BookRightPage,
    BookHeaderControls,
    BookLoadingOverlay,
    DeleteConfirmDialog,
    TableOfContents,
} from '@/components/bookViewModal';
import type { Book, CardPosition, BookEditFormData, ApiFetchFn, Character, ChapterSummary } from '@/components/bookViewModal';

interface Props {
    bookId: string | null;
    cardPosition: CardPosition | null;
    coverImage: string | null;
    bookTitle?: string | null;
    bookAuthor?: string | null;
}

const props = defineProps<Props>();
const isOpen = defineModel<boolean>('isOpen');

const emit = defineEmits<{
    (e: 'updated', book: Book): void;
    (e: 'deleted', bookId: string): void;
}>();

const requestApiFetch = apiFetch as ApiFetchFn;

// Animation composable
const animation = useBookAnimation();

// Chapter pagination composable
const chapters = useChapterPagination();

// Book data state
const book = ref<Book | null>(null);
const loading = ref(false);
const modalElement = ref<HTMLElement | null>(null);

// Edit state
const isEditing = ref(false);
const isSaving = ref(false);
const isDeleting = ref(false);
const showDeleteConfirm = ref(false);
const actionError = ref<string | null>(null);
const editErrors = ref<Record<string, string>>({});
const editForm = ref<BookEditFormData>({
    title: '',
    genre: '',
    age_level: '',
    author: '',
    plot: '',
});

// Character selection state
const selectedCharacter = ref<Character | null>(null);

// Computed display values
const displayTitle = computed(() => {
    const fetchedTitle = book.value?.title?.trim();
    if (fetchedTitle) {
        return fetchedTitle;
    }
    const propTitle = props.bookTitle?.trim();
    if (propTitle) {
        return propTitle;
    }
    return 'Untitled Story';
});

const displayAuthor = computed(() => {
    const profileName = book.value?.profile?.name?.trim();
    if (profileName) {
        return profileName;
    }
    const fetchedAuthor = book.value?.author?.trim();
    if (fetchedAuthor) {
        return fetchedAuthor;
    }
    const propAuthor = props.bookAuthor?.trim() ?? null;
    return propAuthor && propAuthor.length > 0 ? propAuthor : null;
});

const displayCoverImage = computed(() => {
    return props.coverImage || book.value?.cover_image || null;
});

const displayCreatedAt = computed(() => {
    const createdAt = book.value?.created_at;
    if (!createdAt) {
        return null;
    }
    return new Date(createdAt).toLocaleDateString('en-US', { 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
    });
});

// Helper functions
const extractErrorMessage = (value: unknown): string | null => {
    if (value instanceof Error) {
        return value.message;
    }
    if (typeof value === 'string') {
        return value;
    }
    if (value && typeof value === 'object' && 'message' in value) {
        const message = (value as { message?: unknown }).message;
        return typeof message === 'string' ? message : null;
    }
    return null;
};

const toNullableString = (value: string): string | null => {
    const trimmed = value.trim();
    return trimmed.length > 0 ? trimmed : null;
};

// Book data functions
const initializeEditForm = (bookData: Book) => {
    editForm.value = {
        title: bookData.title ?? '',
        genre: bookData.genre ?? '',
        age_level: bookData.age_level ? String(bookData.age_level) : '',
        author: bookData.author ?? '',
        plot: bookData.plot ?? '',
    };
};

const resetEditFeedback = () => {
    editErrors.value = {};
    actionError.value = null;
};

const loadBook = async () => {
    if (!props.bookId) {
        return;
    }
    
    loading.value = true;
    const { data, error } = await requestApiFetch(`/api/books/${props.bookId}`, 'GET');
    
    if (error) {
        actionError.value = extractErrorMessage(error) ?? 'We could not load this story. Please try again.';
    } else if (data) {
        const fetchedBook = data as Book;
        book.value = fetchedBook;
        if (!isEditing.value) {
            initializeEditForm(fetchedBook);
        }
    }
    
    loading.value = false;
};

// Edit handlers
const startEditing = () => {
    if (!book.value || isDeleting.value || isSaving.value) {
        return;
    }
    initializeEditForm(book.value);
    resetEditFeedback();
    isEditing.value = true;
};

const cancelEditing = () => {
    if (isSaving.value || isDeleting.value) {
        return;
    }
    resetEditFeedback();
    if (book.value) {
        initializeEditForm(book.value);
    }
    isEditing.value = false;
};

const submitEdit = async () => {
    if (!book.value || isSaving.value || isDeleting.value) {
        return;
    }

    resetEditFeedback();
    isSaving.value = true;

    const payload = {
        title: toNullableString(editForm.value.title),
        genre: editForm.value.genre || null,
        age_level: editForm.value.age_level ? parseInt(editForm.value.age_level, 10) : null,
        author: toNullableString(editForm.value.author),
        plot: toNullableString(editForm.value.plot),
    };

    try {
        const { data, error } = await requestApiFetch(`/api/books/${book.value.id}`, 'PUT', payload);

        if (error) {
            const message = extractErrorMessage(error) ?? 'We could not save your changes. Please try again.';
            editErrors.value = { general: message };
            return;
        }

        if (data) {
            const updatedBook = data as Book;
            book.value = updatedBook;
            initializeEditForm(updatedBook);
            isEditing.value = false;
            emit('updated', updatedBook);
        }
    } catch (err) {
        const message = extractErrorMessage(err) ?? 'An unexpected error occurred. Please try again.';
        editErrors.value = { general: message };
    } finally {
        isSaving.value = false;
    }
};

// Delete handlers
const requestDelete = () => {
    if (!book.value || isDeleting.value || isSaving.value) {
        return;
    }
    showDeleteConfirm.value = true;
};

const cancelDelete = () => {
    showDeleteConfirm.value = false;
};

const confirmDelete = async () => {
    if (!book.value || isDeleting.value || isSaving.value) {
        return;
    }

    showDeleteConfirm.value = false;
    resetEditFeedback();
    isDeleting.value = true;

    try {
        const { error } = await requestApiFetch(`/api/books/${book.value.id}`, 'DELETE');

        if (error) {
            const message = extractErrorMessage(error) ?? 'We could not delete this story. Please try again.';
            actionError.value = message;
            return;
        }

        emit('deleted', book.value.id);
        isOpen.value = false;
    } catch (err) {
        const message = extractErrorMessage(err) ?? 'An unexpected error occurred. Please try again.';
        actionError.value = message;
    } finally {
        isDeleting.value = false;
    }
};

// Modal control
const closeModal = () => {
    if (animation.isClosing.value || isSaving.value || isDeleting.value) {
        return;
    }
    isOpen.value = false;
};

const handleKeydown = (event: KeyboardEvent) => {
    if (event.key === 'Escape') {
        closeModal();
    }
};

// Chapter navigation handlers
const handleContinueToChapter1 = () => {
    if (props.bookId) {
        chapters.goToChapter1(props.bookId, animation.scheduleTimeout);
    }
};

const handleGoToNextChapter = () => {
    if (props.bookId) {
        chapters.goToNextChapter(props.bookId);
    }
};

const handleGoToPreviousChapter = () => {
    if (props.bookId) {
        chapters.goToPreviousChapter(props.bookId);
    }
};

const handleGenerateChapter = () => {
    if (props.bookId) {
        chapters.generateNextChapter(props.bookId);
    }
};

// Character selection handlers
const handleSelectCharacter = (character: Character) => {
    selectedCharacter.value = character;
};

const handleClearSelectedCharacter = () => {
    selectedCharacter.value = null;
};

// Table of Contents handlers
const completedChapters = computed((): ChapterSummary[] => {
    if (!book.value?.chapters) {
        return [];
    }
    return book.value.chapters
        .filter(ch => ch.title || ch.sort)
        .sort((a, b) => a.sort - b.sort);
});

const handleOpenToc = () => {
    chapters.goToTableOfContents();
};

const handleTocSelectChapter = (chapterNumber: number) => {
    if (props.bookId) {
        chapters.jumpToChapter(props.bookId, chapterNumber);
    }
};

const handleTocGoToTitle = () => {
    chapters.goBackToTitlePage();
};

// Reset all state
const resetAllState = () => {
    animation.resetState();
    chapters.resetChapterState();
    book.value = null;
    loading.value = false;
    isEditing.value = false;
    isSaving.value = false;
    isDeleting.value = false;
    showDeleteConfirm.value = false;
    selectedCharacter.value = null;
    resetEditFeedback();
};

// Watch for book updates
watch(book, newBook => {
    if (newBook && !isEditing.value) {
        initializeEditForm(newBook);
    }
});

// Watch for modal open/close
watch(isOpen, async (open) => {
    if (open) {
        animation.isRendered.value = true;
        await nextTick();
        await animation.startAnimation(props.cardPosition, loadBook);
        window.addEventListener('keydown', handleKeydown);
    } else if (animation.isRendered.value) {
        window.removeEventListener('keydown', handleKeydown);
        await animation.reverseAnimation(props.cardPosition, resetAllState);
    }
});

onBeforeUnmount(() => {
    animation.clearScheduledTimeouts();
    window.removeEventListener('keydown', handleKeydown);
});
</script>

<template>
    <Teleport to="body">
        <!-- Backdrop -->
        <Transition
            enter-active-class="transition-opacity duration-300"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-300"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="animation.isRendered.value"
                :class="[
                    'fixed inset-0 bg-black/80 backdrop-blur-sm z-[9998]',
                    { 'pointer-events-none': animation.isClosing.value }
                ]"
                @click="closeModal"
            />
        </Transition>

        <!-- Animated Modal -->
        <div
            v-if="animation.isRendered.value"
            ref="modalElement"
            :style="animation.cardStyle.value"
            class="book-modal-container rounded-2xl overflow-hidden shadow-2xl"
        >
            <!-- Card Front Face (shown during shrink-back close animation) -->
            <div
                v-show="animation.frontVisible.value && animation.isClosing.value"
                class="absolute inset-0 bg-card rounded-2xl overflow-hidden"
            >
                <img
                    v-if="props.coverImage"
                    :src="props.coverImage"
                    class="h-full w-full object-cover"
                    alt="Book cover"
                />
                <div
                    v-else-if="props.bookId"
                    class="absolute inset-0 flex items-center justify-center p-6 bg-gradient-to-br from-violet-600 to-violet-300"
                >
                    <h3 
                        class="text-2xl md:text-3xl lg:text-4xl font-bold text-center text-white drop-shadow-[0_2px_4px_rgba(0,0,0,0.3)]"
                        style="text-shadow: 0 2px 8px rgba(0,0,0,0.2), 0 -1px 2px rgba(255,255,255,0.3)"
                    >
                        {{ displayTitle }} 
                    </h3>
                </div>
            </div>

            <!-- Book Content -->
            <div 
                v-show="animation.backVisible.value"
                class="absolute inset-0 overflow-hidden"
            >
                <!-- Loading State -->
                <div 
                    v-if="!animation.showContent.value"
                    class="absolute inset-0 flex items-center justify-center bg-amber-50 dark:bg-amber-100"
                >
                    <div class="text-center space-y-6">
                        <MagicalSparklesLoader 
                            size="xl" 
                            color="text-amber-500" 
                            accent-color="text-orange-400"
                            class="mx-auto"
                        />
                        <p class="text-lg font-medium text-amber-900 dark:text-amber-800 animate-pulse">
                            Opening your story...
                        </p>
                    </div>
                </div>

                <!-- Book Interior Content -->
                <div v-else class="relative h-full w-full">
                    <!-- Delete Confirmation Modal -->
                    <DeleteConfirmDialog 
                        :visible="showDeleteConfirm"
                        @cancel="cancelDelete"
                        @confirm="confirmDelete"
                    />

                    <!-- Loading/Saving Overlay -->
                    <BookLoadingOverlay 
                        v-if="loading || isSaving || isDeleting"
                        :message="loading ? 'Opening your story...' : isSaving ? 'Saving changes...' : 'Deleting story...'"
                    />

                    <!-- Header Controls -->
                    <BookHeaderControls
                        v-if="animation.animationPhase.value === 'complete' && !animation.isClosing.value && animation.isBookOpened.value"
                        :has-book="!!book"
                        :has-chapters="completedChapters.length > 0"
                        :is-editing="isEditing"
                        :is-saving="isSaving"
                        :is-deleting="isDeleting"
                        :is-page-turning="animation.isPageTurning.value"
                        @edit="startEditing"
                        @delete="requestDelete"
                        @close="closeModal"
                        @open-toc="handleOpenToc"
                    />

                    <!-- ==================== CLOSED BOOK VIEW (Cover Centered) ==================== -->
                    <BookCover
                        v-if="!animation.isBookOpened.value"
                        :book-id="props.bookId"
                        :cover-image="displayCoverImage"
                        :title="displayTitle"
                        :author="displayAuthor"
                        :is-page-turning="animation.isPageTurning.value"
                        :is-cover-fading="animation.isCoverFading.value"
                        :loading="loading"
                        @open="animation.openBook"
                    />

                    <!-- ==================== OPENED BOOK VIEW (Two Pages) ==================== -->
                    <div 
                        v-else 
                        class="book-opened-view relative flex h-full w-full"
                    >
                        <!-- Loading Chapter Overlay -->
                        <BookLoadingOverlay 
                            v-if="chapters.isLoadingChapter.value || chapters.isGeneratingChapter.value"
                            :message="chapters.isGeneratingChapter.value ? 'Crafting your story...' : 'Loading chapter...'"
                        >
                            <template #subtitle>
                                <p v-if="chapters.isGeneratingChapter.value" class="mt-2 text-sm text-amber-700 dark:text-amber-600 animate-pulse">
                                    The magic is happening ✨
                                </p>
                            </template>
                        </BookLoadingOverlay>

                        <!-- Left Edge Click Zone (Go Back) -->
                        <button
                            v-if="(chapters.hasPrevSpread.value || chapters.currentChapterNumber.value >= 1) && !chapters.isLoadingChapter.value && !chapters.isGeneratingChapter.value"
                            class="edge-nav-zone edge-nav-left group absolute left-0 top-12 bottom-4 w-14 z-30 cursor-pointer bg-transparent transition-all duration-200 hover:bg-amber-900/5 dark:hover:bg-amber-100/5 focus:outline-none"
                            @click="handleGoToPreviousChapter"
                            aria-label="Previous page"
                        >
                            <div class="absolute inset-y-0 left-0 w-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                                <svg class="w-6 h-6 text-amber-700/60 dark:text-amber-500/60" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                                </svg>
                            </div>
                        </button>

                        <!-- Left Page -->
                        <BookLeftPage
                            :reading-view="chapters.readingView.value"
                            :chapter="chapters.currentChapter.value"
                            :spread="chapters.currentSpread.value"
                            :spread-index="chapters.currentSpreadIndex.value"
                            :characters="book?.characters"
                            :selected-character-id="selectedCharacter?.id ?? null"
                            :chapters="completedChapters"
                            :current-chapter-number="chapters.currentChapterNumber.value"
                            :book-title="displayTitle"
                            @select-character="handleSelectCharacter"
                            @toc-select-chapter="handleTocSelectChapter"
                            @toc-go-to-title="handleTocGoToTitle"
                        />

                        <!-- Book Spine -->
                        <BookSpine />

                        <!-- Right Page -->
                        <BookRightPage
                            :book="book"
                            :reading-view="chapters.readingView.value"
                            :chapter="chapters.currentChapter.value"
                            :spread="chapters.currentSpread.value"
                            :spread-index="chapters.currentSpreadIndex.value"
                            :cover-image="displayCoverImage"
                            :title="displayTitle"
                            :author="displayAuthor"
                            :created-at="displayCreatedAt"
                            :is-title-page-fading="chapters.isTitlePageFading.value"
                            :is-loading-chapter="chapters.isLoadingChapter.value"
                            :is-editing="isEditing"
                            :edit-form="editForm"
                            :edit-errors="editErrors"
                            :is-saving="isSaving"
                            :is-deleting="isDeleting"
                            :action-error="actionError"
                            :chapter-error="chapters.chapterError.value"
                            :current-chapter-number="chapters.currentChapterNumber.value"
                            :next-chapter-prompt="chapters.nextChapterPrompt.value"
                            :is-final-chapter="chapters.isFinalChapter.value"
                            :is-generating-chapter="chapters.isGeneratingChapter.value"
                            :selected-character="selectedCharacter"
                            @continue-to-chapter1="handleContinueToChapter1"
                            @update:edit-form="editForm = $event"
                            @submit-edit="submitEdit"
                            @cancel-edit="cancelEditing"
                            @update:next-chapter-prompt="chapters.nextChapterPrompt.value = $event"
                            @update:is-final-chapter="chapters.isFinalChapter.value = $event"
                            @generate-chapter="handleGenerateChapter"
                            @go-back="handleGoToPreviousChapter"
                            @clear-selected-character="handleClearSelectedCharacter"
                        />

                        <!-- Right Edge Click Zone (Go Forward) -->
                        <button
                            v-if="(chapters.hasNextSpread.value || (chapters.readingView.value === 'chapter-image' || chapters.readingView.value === 'chapter-content')) && !chapters.isLoadingChapter.value && !chapters.isGeneratingChapter.value"
                            class="edge-nav-zone edge-nav-right group absolute right-0 top-12 bottom-4 w-14 z-30 cursor-pointer bg-transparent transition-all duration-200 hover:bg-amber-900/5 dark:hover:bg-amber-100/5 focus:outline-none"
                            @click="handleGoToNextChapter"
                            aria-label="Next page"
                        >
                            <div class="absolute inset-y-0 right-0 w-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                                <svg class="w-6 h-6 text-amber-700/60 dark:text-amber-500/60" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                </svg>
                            </div>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<style scoped>
.book-modal-container {
    transform-style: preserve-3d;
    backface-visibility: visible;
    background: linear-gradient(to right, #fef3c7, #fff7ed, #fef3c7);
}

:is(.dark .book-modal-container) {
    background: linear-gradient(to right, #262626, #1c1c1c, #262626);
}

.book-opened-view {
    animation: fadeIn 0.5s ease-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}
</style>
