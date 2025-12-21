<script setup lang="ts">
import { ref, watch, nextTick, onBeforeUnmount, computed } from 'vue';
import { apiFetch } from '@/composables/ApiFetch';
import { echo } from '@laravel/echo-vue';
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
} from '@/components/bookViewModal';
import type { 
    Book, 
    CardPosition, 
    BookEditFormData, 
    ApiFetchFn, 
    Character, 
    ChapterSummary,
    ChapterCreatedPayload,
    ChapterUpdatedPayload,
    ReadingHistory,
} from '@/components/bookViewModal';

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
    (e: 'readingHistoryUpdated', history: ReadingHistory): void;
    (e: 'favoriteToggled', data: { bookId: string; isFavorite: boolean }): void;
}>();

const requestApiFetch = apiFetch as ApiFetchFn;

// Animation composable
const animation = useBookAnimation();

// Handle reading history updates by emitting to parent
const handleReadingHistoryUpdate = (history: ReadingHistory) => {
    emit('readingHistoryUpdated', history);
};

// Chapter pagination composable with reading history callback
const chapters = useChapterPagination(handleReadingHistoryUpdate);

// Book data state
const book = ref<Book | null>(null);
const loading = ref(false);
const modalElement = ref<HTMLElement | null>(null);
const savedChapterNumber = ref<number | null>(null);

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

// Textarea focus state (for disabling keyboard navigation)
const isTextareaFocused = ref(false);

// Favorite state
const isFavorite = ref(false);
const isTogglingFavorite = ref(false);

// Echo channel for real-time book updates
// eslint-disable-next-line @typescript-eslint/no-explicit-any
const bookChannel = ref<any>(null);

type BookUpdatedPayload = {
    id: string;
    title: string | null;
    genre: string;
    author: string | null;
    age_level: number | null;
    status: string;
    cover_image: string | null;
    plot: string | null;
    is_published: boolean;
    updated_at: string;
};

type ChapterInlineImagesPayload = {
    chapter_id: string;
    chapter_sort: number;
    inline_images: Array<{
        paragraph_index: number;
        url: string;
        prompt: string;
    }>;
};

// Handle real-time chapter inline images created events
const handleChapterInlineImagesEvent = (payload: ChapterInlineImagesPayload) => {
    if (!payload.chapter_id || !payload.inline_images) {
        return;
    }
    
    // Update the chapter's inline images via the composable
    chapters.updateChapterInlineImages(payload.chapter_id, payload.inline_images);
};

// Handle real-time chapter created events
const handleChapterCreatedEvent = (payload: ChapterCreatedPayload) => {
    if (!props.bookId || payload.book_id !== props.bookId) {
        return;
    }
    
    // Delegate to the composable
    chapters.handleChapterCreated(payload);
};

// Handle real-time chapter updated events
const handleChapterUpdatedEvent = (payload: ChapterUpdatedPayload) => {
    if (!props.bookId || payload.book_id !== props.bookId) {
        return;
    }
    
    // Delegate to the composable
    chapters.handleChapterUpdated(payload, props.bookId);
    
    // If a chapter is now complete, update book's chapter list for TOC
    if (payload.status === 'complete' && book.value) {
        const existingChapter = book.value.chapters?.find(ch => ch.id === payload.id);
        if (existingChapter) {
            // Update existing chapter
            existingChapter.title = payload.title;
            existingChapter.sort = payload.sort;
        } else {
            // Add new chapter to book's chapter list
            if (!book.value.chapters) {
                book.value.chapters = [];
            }
            book.value.chapters.push({
                id: payload.id,
                title: payload.title,
                sort: payload.sort,
                final_chapter: payload.final_chapter,
            });
        }
    }
};

// Handle real-time book update events
const handleBookUpdatedEvent = (payload: BookUpdatedPayload) => {
    if (!book.value || book.value.id !== payload.id) {
        return;
    }
    
    // Update the local book state with the new data
    book.value = {
        ...book.value,
        title: payload.title ?? book.value.title,
        genre: payload.genre,
        author: payload.author,
        age_level: payload.age_level,
        status: payload.status,
        cover_image: payload.cover_image,
        plot: payload.plot,
    };
    
    // Emit the update to the parent (Dashboard)
    emit('updated', book.value);
};

// Subscribe to book channel for real-time updates
const subscribeToBookChannel = (bookId: string) => {
    if (bookChannel.value) {
        return;
    }
    
    try {
        const channel = echo().private(`book.${bookId}`);
        channel.listen('.book.updated', handleBookUpdatedEvent);
        channel.listen('.chapter.created', handleChapterCreatedEvent);
        channel.listen('.chapter.updated', handleChapterUpdatedEvent);
        channel.listen('.chapter.inline-images.created', handleChapterInlineImagesEvent);
        bookChannel.value = channel;
    } catch (err) {
        console.error(`[Echo] Failed to subscribe to book.${bookId}:`, err);
    }
};

// Unsubscribe from book channel
const unsubscribeFromBookChannel = () => {
    if (!bookChannel.value || !props.bookId) {
        return;
    }
    
    try {
        bookChannel.value.stopListening('.book.updated');
        bookChannel.value.stopListening('.chapter.created');
        bookChannel.value.stopListening('.chapter.updated');
        bookChannel.value.stopListening('.chapter.inline-images.created');
        echo().leave(`book.${props.bookId}`);
    } catch {
        // Ignore cleanup errors
    }
    bookChannel.value = null;
};

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
    
    // Record that the book was opened and get saved reading position
    const readingHistory = await chapters.recordBookOpened(props.bookId);
    savedChapterNumber.value = readingHistory?.current_chapter_number ?? null;
    
    // Check if this book is a favorite
    const { data: favoriteData } = await requestApiFetch(`/api/books/${props.bookId}/favorite`, 'GET');
    if (favoriteData && typeof favoriteData === 'object' && 'is_favorite' in favoriteData) {
        isFavorite.value = (favoriteData as { is_favorite: boolean }).is_favorite;
    }
    
    const { data, error } = await requestApiFetch(`/api/books/${props.bookId}`, 'GET');
    
    if (error) {
        actionError.value = extractErrorMessage(error) ?? 'We could not load this story. Please try again.';
    } else if (data) {
        const fetchedBook = data as Book;
        book.value = fetchedBook;
        if (!isEditing.value) {
            initializeEditForm(fetchedBook);
        }
        
        // Check if book is in_progress but has no completed chapters
        // This means the first chapter is being generated
        const completedChapters = fetchedBook.chapters?.filter(ch => ch.title || ch.sort) ?? [];
        if (fetchedBook.status === 'in_progress' && completedChapters.length === 0) {
            chapters.setAwaitingChapterGeneration(true);
        } else {
            chapters.setAwaitingChapterGeneration(false);
        }
    }
    
    loading.value = false;
};

// Toggle favorite handler
const handleToggleFavorite = async () => {
    if (!props.bookId || isTogglingFavorite.value) {
        return;
    }
    
    isTogglingFavorite.value = true;
    
    try {
        if (isFavorite.value) {
            // Remove from favorites
            const { error } = await requestApiFetch(`/api/books/${props.bookId}/favorite`, 'DELETE');
            if (!error) {
                isFavorite.value = false;
                emit('favoriteToggled', { bookId: props.bookId, isFavorite: false });
            }
        } else {
            // Add to favorites
            const { error } = await requestApiFetch(`/api/books/${props.bookId}/favorite`, 'POST');
            if (!error) {
                isFavorite.value = true;
                emit('favoriteToggled', { bookId: props.bookId, isFavorite: true });
            }
        }
    } finally {
        isTogglingFavorite.value = false;
    }
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
        return;
    }
    
    // Don't handle navigation keys if loading or generating
    if (chapters.isLoadingChapter.value || chapters.isGeneratingChapter.value) {
        return;
    }
    
    // Don't handle left/right navigation if textarea is focused
    if (isTextareaFocused.value && (event.key === 'ArrowLeft' || event.key === 'ArrowRight')) {
        return;
    }
    
    // Handle left arrow key - go back
    if (event.key === 'ArrowLeft') {
        // Only navigate back if we have a previous spread or we're past the title page
        if (chapters.hasPrevSpread.value || chapters.currentChapterNumber.value >= 1) {
            event.preventDefault();
            handleGoToPreviousChapter();
        }
        return;
    }
    
    // Handle right arrow key - go forward
    if (event.key === 'ArrowRight') {
        // Don't allow going forward in create-chapter view (end of book)
        if (chapters.readingView.value === 'create-chapter') {
            return;
        }
        
        // From title page, start reading
        if (chapters.readingView.value === 'title') {
            event.preventDefault();
            handleContinueToChapter1();
        }
        // Otherwise navigate forward if we have a next spread or we're in a chapter view
        else if (chapters.hasNextSpread.value || 
                 chapters.readingView.value === 'chapter-image' || 
                 chapters.readingView.value === 'chapter-content') {
            event.preventDefault();
            handleGoToNextChapter();
        }
    }
};

// Chapter navigation handlers
const handleContinueToChapter1 = () => {
    if (props.bookId) {
        // If we have a saved reading position greater than 1, jump to that chapter instead
        if (savedChapterNumber.value && savedChapterNumber.value > 1) {
            chapters.jumpToChapter(props.bookId, savedChapterNumber.value);
        } else {
            chapters.goToChapter1(props.bookId, animation.scheduleTimeout);
        }
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
    unsubscribeFromBookChannel();
    book.value = null;
    loading.value = false;
    isEditing.value = false;
    isSaving.value = false;
    isDeleting.value = false;
    showDeleteConfirm.value = false;
    selectedCharacter.value = null;
    savedChapterNumber.value = null;
    isFavorite.value = false;
    isTogglingFavorite.value = false;
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
        // Subscribe to real-time updates for this book
        if (props.bookId) {
            subscribeToBookChannel(props.bookId);
        }
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
    unsubscribeFromBookChannel();
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
                    'fixed inset-0 bg-black/80 backdrop-blur-sm z-9998',
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
            class="book-modal-container theme-reset rounded-2xl overflow-hidden shadow-2xl"
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
                    class="absolute inset-0 flex items-center justify-center p-6 bg-linear-to-br from-violet-600 to-violet-300"
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

                    <!-- Awaiting First Chapter Generation Overlay (shown on title page) -->
                    <BookLoadingOverlay 
                        v-if="chapters.isAwaitingChapterGeneration.value && chapters.readingView.value === 'title'"
                        message="Creating your first chapter..."
                    >
                        <template #subtitle>
                            <p class="mt-2 text-sm text-amber-700 dark:text-amber-600 animate-pulse">
                                The magic is happening 🪄
                            </p>
                        </template>
                    </BookLoadingOverlay>

                    <!-- Header Controls -->
                    <BookHeaderControls
                        v-if="animation.animationPhase.value === 'complete' && !animation.isClosing.value && animation.isBookOpened.value"
                        :has-book="!!book"
                        :has-chapters="completedChapters.length > 0"
                        :chapters="completedChapters"
                        :current-chapter-number="chapters.currentChapterNumber.value"
                        :is-editing="isEditing"
                        :is-saving="isSaving"
                        :is-deleting="isDeleting"
                        :is-page-turning="animation.isPageTurning.value"
                        :book-type="book?.type"
                        :is-favorite="isFavorite"
                        :is-toggling-favorite="isTogglingFavorite"
                        @edit="startEditing"
                        @delete="requestDelete"
                        @close="closeModal"
                        @toc-select-chapter="handleTocSelectChapter"
                        @toc-go-to-title="handleTocGoToTitle"
                        @toggle-favorite="handleToggleFavorite"
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
                                    The magic is happening 🪄
                                </p>
                            </template>
                        </BookLoadingOverlay>

                        <!-- Left Edge Click Zone (Go Back) -->
                        <button
                            v-if="(chapters.hasPrevSpread.value || chapters.currentChapterNumber.value >= 1) && !chapters.isLoadingChapter.value && !chapters.isGeneratingChapter.value"
                            class="edge-nav-zone edge-nav-left group absolute left-0 inset-y-0 w-14 z-30 cursor-pointer bg-transparent transition-all duration-200 hover:bg-amber-900/5 dark:hover:bg-amber-100/5 focus:outline-none"
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
                            :has-next-chapter="chapters.hasNextChapter.value"
                            :chapter-ends-on-left="chapters.chapterEndsOnLeft.value"
                            :is-on-last-spread="chapters.isOnLastSpread.value"
                            :current-chapter-number="chapters.currentChapterNumber.value"
                            :next-chapter-prompt="chapters.nextChapterPrompt.value"
                            :suggested-placeholder="chapters.suggestedPlaceholder.value"
                            :is-loading-placeholder="chapters.isLoadingPlaceholder.value"
                            :is-final-chapter="chapters.isFinalChapter.value"
                            :is-generating-chapter="chapters.isGeneratingChapter.value || chapters.isAwaitingChapterGeneration.value"
                            :book-type="book?.type"
                            @select-character="handleSelectCharacter"
                            @update:next-chapter-prompt="chapters.nextChapterPrompt.value = $event"
                            @update:is-final-chapter="chapters.isFinalChapter.value = $event"
                            @generate-chapter="handleGenerateChapter"
                            @textarea-focused="isTextareaFocused = $event"
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
                            :is-awaiting-chapter-generation="chapters.isAwaitingChapterGeneration.value"
                            :is-editing="isEditing"
                            :edit-form="editForm"
                            :edit-errors="editErrors"
                            :is-saving="isSaving"
                            :is-deleting="isDeleting"
                            :action-error="actionError"
                            :chapter-error="chapters.chapterError.value"
                            :current-chapter-number="chapters.currentChapterNumber.value"
                            :next-chapter-prompt="chapters.nextChapterPrompt.value"
                            :suggested-placeholder="chapters.suggestedPlaceholder.value"
                            :is-loading-placeholder="chapters.isLoadingPlaceholder.value"
                            :is-final-chapter="chapters.isFinalChapter.value"
                            :is-generating-chapter="chapters.isGeneratingChapter.value || chapters.isAwaitingChapterGeneration.value"
                            :selected-character="selectedCharacter"
                            :has-next-chapter="chapters.hasNextChapter.value"
                            :chapter-ends-on-left="chapters.chapterEndsOnLeft.value"
                            :is-on-last-spread="chapters.isOnLastSpread.value"
                            :should-show-next-chapter-on-right="chapters.shouldShowNextChapterOnRight.value"
                            :next-chapter-data="chapters.nextChapterData.value"
                            :next-chapter-first-page="chapters.nextChapterFirstPage.value"
                            :saved-chapter-number="savedChapterNumber"
                            @continue-to-chapter1="handleContinueToChapter1"
                            @update:edit-form="editForm = $event"
                            @submit-edit="submitEdit"
                            @cancel-edit="cancelEditing"
                            @update:next-chapter-prompt="chapters.nextChapterPrompt.value = $event"
                            @update:is-final-chapter="chapters.isFinalChapter.value = $event"
                            @generate-chapter="handleGenerateChapter"
                            @go-back="handleGoToPreviousChapter"
                            @clear-selected-character="handleClearSelectedCharacter"
                            @textarea-focused="isTextareaFocused = $event"
                        />

                        <!-- Right Edge Click Zone (Go Forward / Start Reading) -->
                        <button
                            v-if="chapters.readingView.value !== 'create-chapter' && (chapters.readingView.value === 'title' || chapters.hasNextSpread.value || chapters.readingView.value === 'chapter-image' || chapters.readingView.value === 'chapter-content') && !chapters.isLoadingChapter.value && !chapters.isGeneratingChapter.value"
                            class="edge-nav-zone edge-nav-right group absolute right-0 inset-y-0 w-14 z-30 cursor-pointer bg-transparent transition-all duration-200 hover:bg-amber-900/5 dark:hover:bg-amber-100/5 focus:outline-none"
                            @click="chapters.readingView.value === 'title' ? handleContinueToChapter1() : handleGoToNextChapter()"
                            :aria-label="chapters.readingView.value === 'title' ? 'Start reading' : 'Next page'"
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
