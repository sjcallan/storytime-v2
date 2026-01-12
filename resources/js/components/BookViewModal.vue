<script setup lang="ts">
import { ref, watch, nextTick, onBeforeUnmount, computed } from 'vue';
import { apiFetch } from '@/composables/ApiFetch';
import { echo } from '@laravel/echo-vue';
import { MagicalSparklesLoader } from '@/components/ui/magical-book-loader';

// Import from bookViewModal module
import {
    useBookAnimation,
    useChapterPagination,
    useResponsiveBook,
    BookCover,
    BookSpine,
    BookLeftPage,
    BookRightPage,
    BookHeaderControls,
    BookLoadingOverlay,
    DeleteConfirmDialog,
    ChapterEditModal,
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
    ImageGeneratedPayload,
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

// Responsive book composable (single-page mode for smaller screens)
const responsive = useResponsiveBook();

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
    type: '',
});

// Character selection state
const selectedCharacter = ref<Character | null>(null);
const isViewingCharacters = ref(false);

// Textarea focus state (for disabling keyboard navigation)
const isTextareaFocused = ref(false);

// Favorite state
const isFavorite = ref(false);
const isTogglingFavorite = ref(false);

// Chapter edit modal state
const showChapterEditModal = ref(false);
const isChapterEditing = ref(false);
const chapterEditError = ref<string | null>(null);
const pendingEditChapterId = ref<string | null>(null);

// Navigation hint state (flash arrow when user scrolled to bottom)
const isFlashingNextArrow = ref(false);
let flashTimeout: ReturnType<typeof setTimeout> | null = null;

const handleScrolledToBottom = () => {
    // Only flash if we can navigate forward
    if (!canNavigateForward.value) {
        return;
    }
    
    // Start flashing the arrow
    isFlashingNextArrow.value = true;
    
    // Clear any existing timeout
    if (flashTimeout) {
        clearTimeout(flashTimeout);
    }
    
    // Stop flashing after 3 seconds
    flashTimeout = setTimeout(() => {
        isFlashingNextArrow.value = false;
    }, 3000);
};

// Computed: is the current chapter the last chapter?
const isLastChapter = computed(() => {
    return chapters.totalChapters.value > 0 && 
           chapters.currentChapterNumber.value === chapters.totalChapters.value;
});

// Echo channel for real-time book updates
 
const bookChannel = ref<any>(null);

type BookUpdatedPayload = {
    id: string;
    title: string | null;
    genre: string;
    author: string | null;
    age_level: number | null;
    status: string;
    cover_image: string | null;
    cover_image_status: string | null;
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

type CharacterPortraitPayload = {
    id: string;
    book_id: string;
    portrait_image: string;
};

type CharacterCreatedPayload = {
    id: string;
    book_id: string;
    name: string;
    gender: string | null;
    description: string | null;
    type: string | null;
    age: string | null;
    nationality: string | null;
    backstory: string | null;
    portrait_image: string | null;
    created_at: string;
};

// Handle real-time character created events
const handleCharacterCreatedEvent = (payload: CharacterCreatedPayload) => {
    if (!book.value || payload.book_id !== book.value.id) {
        return;
    }
    
    // Check if character already exists (avoid duplicates)
    const existingCharacter = book.value.characters?.find(c => c.id === payload.id);
    if (existingCharacter) {
        return;
    }
    
    // Initialize characters array if it doesn't exist
    if (!book.value.characters) {
        book.value.characters = [];
    }
    
    // Add the new character to the book's characters array
    book.value.characters.push({
        id: payload.id,
        name: payload.name,
        gender: payload.gender,
        description: payload.description,
        type: payload.type,
        age: payload.age,
        nationality: payload.nationality,
        backstory: payload.backstory,
        portrait_image: payload.portrait_image,
    });
};

// Handle real-time character portrait updated events
const handleCharacterPortraitEvent = (payload: CharacterPortraitPayload) => {
    if (!book.value || !payload.id || !payload.portrait_image) {
        return;
    }
    
    // Update the character's portrait image in the book's characters array
    const character = book.value.characters?.find(c => c.id === payload.id);
    if (character) {
        character.portrait_image = payload.portrait_image;
    }
    
    // Also update selected character if it's the same one
    if (selectedCharacter.value?.id === payload.id) {
        selectedCharacter.value.portrait_image = payload.portrait_image;
    }
};

// Handle real-time chapter inline images created events
const handleChapterInlineImagesEvent = (payload: ChapterInlineImagesPayload) => {
    if (!payload.chapter_id || !payload.inline_images) {
        return;
    }
    
    // Update the chapter's inline images via the composable
    chapters.updateChapterInlineImages(payload.chapter_id, payload.inline_images);
};

// Handle real-time image generated events (new unified image system)
const handleImageGeneratedEvent = (payload: ImageGeneratedPayload) => {
    if (!book.value) {
        return;
    }
    
    // Handle based on image type
    switch (payload.type) {
        case 'book_cover':
            // Update book cover
            if (payload.book_id === book.value.id && payload.status === 'complete' && payload.full_url) {
                book.value.cover_image = payload.full_url;
                book.value.cover_image_status = payload.status;
                emit('updated', book.value);
            }
            break;
            
        case 'character_portrait':
            // Update character portrait
            if (payload.character_id) {
                const character = book.value.characters?.find(c => c.id === payload.character_id);
                if (character) {
                    // Update the portraitImage object with all payload data
                    character.portraitImage = {
                        id: payload.id,
                        book_id: payload.book_id,
                        chapter_id: payload.chapter_id,
                        character_id: payload.character_id,
                        type: payload.type,
                        image_url: payload.image_url,
                        full_url: payload.full_url,
                        prompt: payload.prompt,
                        error: payload.error,
                        status: payload.status,
                        paragraph_index: payload.paragraph_index,
                        aspect_ratio: payload.aspect_ratio,
                    };
                    // Also update legacy field for backwards compatibility
                    if (payload.status === 'complete' && payload.full_url) {
                        character.portrait_image = payload.full_url;
                    }
                }
                // Also update selected character if it's the same one
                if (selectedCharacter.value?.id === payload.character_id) {
                    selectedCharacter.value.portraitImage = {
                        id: payload.id,
                        book_id: payload.book_id,
                        chapter_id: payload.chapter_id,
                        character_id: payload.character_id,
                        type: payload.type,
                        image_url: payload.image_url,
                        full_url: payload.full_url,
                        prompt: payload.prompt,
                        error: payload.error,
                        status: payload.status,
                        paragraph_index: payload.paragraph_index,
                        aspect_ratio: payload.aspect_ratio,
                    };
                    if (payload.status === 'complete' && payload.full_url) {
                        selectedCharacter.value.portrait_image = payload.full_url;
                    }
                }
            }
            break;
            
        case 'chapter_header':
            // Update chapter header image
            if (payload.chapter_id) {
                chapters.updateChapterHeaderImage(payload.chapter_id, payload.full_url, payload.status);
            }
            break;
            
        case 'chapter_inline':
            // Update chapter inline image
            if (payload.chapter_id && payload.paragraph_index !== null) {
                chapters.updateChapterInlineImage(
                    payload.chapter_id,
                    payload.paragraph_index,
                    payload.full_url,
                    payload.status,
                    payload.error
                );
            }
            break;
    }
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
    
    // Check if this is the chapter we were editing
    if (pendingEditChapterId.value === payload.id && payload.status === 'complete') {
        isChapterEditing.value = false;
        pendingEditChapterId.value = null;
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
        cover_image_status: payload.cover_image_status,
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
        channel.listen('.character.created', handleCharacterCreatedEvent);
        channel.listen('.character.portrait.updated', handleCharacterPortraitEvent);
        channel.listen('.image.generated', handleImageGeneratedEvent);
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
        bookChannel.value.stopListening('.character.created');
        bookChannel.value.stopListening('.character.portrait.updated');
        bookChannel.value.stopListening('.image.generated');
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

// Navigation availability computeds (accounting for single-page mode)
const canNavigateBack = computed(() => {
    // Can navigate back if:
    // 1. We have a previous spread, or
    // 2. We're past the first chapter, or
    // 3. In single-page mode on right side of spread (can go to left)
    if (responsive.isSinglePageMode.value) {
        // In single-page mode, check if we can go back within the current spread
        const currentSpread = chapters.currentSpread.value;
        const isFirstSpread = chapters.currentSpreadIndex.value === 0;
        
        // If on right side and not first spread, can go to left
        if (responsive.singlePageSide.value === 'right' && !isFirstSpread && currentSpread?.leftContent) {
            return true;
        }
    }
    return chapters.hasPrevSpread.value || chapters.currentChapterNumber.value >= 1;
});

const canNavigateForward = computed(() => {
    // Cannot navigate forward from create-chapter view (end of book)
    if (chapters.readingView.value === 'create-chapter') {
        return false;
    }
    
    // Can always navigate forward from title page (either from characters to title, or title to chapter 1)
    if (chapters.readingView.value === 'title') {
        return true;
    }
    
    // In single-page mode, check if we can navigate within spread
    if (responsive.isSinglePageMode.value) {
        const currentSpread = chapters.currentSpread.value;
        // If on left side and there's right content, can go to right
        if (responsive.singlePageSide.value === 'left' && currentSpread?.rightContent) {
            return true;
        }
    }
    
    // Can navigate if there's a next spread or we're in chapter view
    return chapters.hasNextSpread.value || 
           chapters.readingView.value === 'chapter-image' || 
           chapters.readingView.value === 'chapter-content';
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
        type: bookData.type ?? '',
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
        
        // Use the smart right edge handler for all forward navigation
        if (canNavigateForward.value) {
            event.preventDefault();
            handleRightEdgeClick();
        }
    }
};

// Chapter navigation handlers
const handleContinueToChapter1 = () => {
    if (props.bookId) {
        // Reset to right side for title/start of chapter in single-page mode
        responsive.resetSinglePageSide();
        isViewingCharacters.value = false;
        selectedCharacter.value = null;
        chapters.goToChapter1(props.bookId, animation.scheduleTimeout);
    }
};

const handleGoToNextChapter = () => {
    if (!props.bookId) {
        return;
    }
    
    if (responsive.isSinglePageMode.value) {
        // In single-page mode, handle page-by-page navigation
        const currentSpread = chapters.currentSpread.value;
        
        // If we're on the left side of a spread
        if (responsive.singlePageSide.value === 'left') {
            // Check if there's content on the right side to show
            if (currentSpread?.rightContent) {
                responsive.setSinglePageToRight();
                return;
            }
            // No right content - advance to next spread
            responsive.setSinglePageToLeft();
            chapters.goToNextChapter(props.bookId);
        } else {
            // We're on the right side - go to next spread's left page
            responsive.setSinglePageToLeft();
            chapters.goToNextChapter(props.bookId);
        }
    } else {
        chapters.goToNextChapter(props.bookId);
    }
};

const handleGoToPreviousChapter = () => {
    if (!props.bookId) {
        return;
    }
    
    if (responsive.isSinglePageMode.value) {
        // In single-page mode, handle page-by-page navigation
        const currentSpread = chapters.currentSpread.value;
        
        // If we're on the right side of a spread
        if (responsive.singlePageSide.value === 'right') {
            // Check if there's content on the left side to show (skip first spread which has image on left)
            const isFirstSpread = chapters.currentSpreadIndex.value === 0;
            if (!isFirstSpread && currentSpread?.leftContent) {
                responsive.setSinglePageToLeft();
                return;
            }
            // First spread or no left content - go to previous spread's right page
            responsive.setSinglePageToRight();
            chapters.goToPreviousChapter(props.bookId);
        } else {
            // We're on the left side - go to previous spread's right page
            responsive.setSinglePageToRight();
            chapters.goToPreviousChapter(props.bookId);
        }
    } else {
        chapters.goToPreviousChapter(props.bookId);
    }
};

const handleGenerateChapter = () => {
    if (props.bookId) {
        chapters.generateNextChapter(props.bookId);
    }
};

const handleRequestIdea = () => {
    if (props.bookId) {
        chapters.requestIdea(props.bookId);
    }
};

// Chapter edit handlers
const handleOpenChapterEditModal = () => {
    showChapterEditModal.value = true;
    chapterEditError.value = null;
};

const handleCloseChapterEditModal = () => {
    if (!isChapterEditing.value) {
        showChapterEditModal.value = false;
        chapterEditError.value = null;
    }
};

const handleSubmitSmallChanges = async (instructions: string) => {
    if (!props.bookId || !chapters.currentChapter.value || isChapterEditing.value) {
        return;
    }

    const chapterId = chapters.currentChapter.value.id;
    isChapterEditing.value = true;
    chapterEditError.value = null;

    try {
        const { error } = await requestApiFetch(
            `/api/books/${props.bookId}/chapters/${chapterId}/edit`,
            'POST',
            { instructions }
        );

        if (error) {
            const message = extractErrorMessage(error) ?? 'Could not edit chapter.';
            chapterEditError.value = message;
            isChapterEditing.value = false;
        } else {
            // Close modal but keep editing state - wait for websocket update
            showChapterEditModal.value = false;
            chapterEditError.value = null;
            pendingEditChapterId.value = chapterId;
            // isChapterEditing stays true until websocket confirms update
        }
    } catch (err) {
        const message = extractErrorMessage(err) ?? 'An error occurred editing the chapter.';
        chapterEditError.value = message;
        isChapterEditing.value = false;
    }
};

const handleSubmitRewrite = async (newPrompt: string) => {
    if (!props.bookId || !chapters.currentChapter.value || isChapterEditing.value) {
        return;
    }

    const chapterId = chapters.currentChapter.value.id;
    isChapterEditing.value = true;
    chapterEditError.value = null;

    try {
        const { data, error } = await requestApiFetch(
            `/api/books/${props.bookId}/chapters/${chapterId}/rewrite`,
            'POST',
            { user_prompt: newPrompt || null }
        );

        if (error) {
            const message = extractErrorMessage(error) ?? 'Could not rewrite chapter.';
            chapterEditError.value = message;
            isChapterEditing.value = false;
        } else if (data) {
            // Close modal but keep editing state - wait for websocket update
            showChapterEditModal.value = false;
            chapterEditError.value = null;
            pendingEditChapterId.value = chapterId;
            // isChapterEditing stays true until websocket confirms update
        }
    } catch (err) {
        const message = extractErrorMessage(err) ?? 'An error occurred rewriting the chapter.';
        chapterEditError.value = message;
        isChapterEditing.value = false;
    }
};

// Handle right edge click - smarter navigation for title page in single-page mode
const handleRightEdgeClick = () => {
    // Stop flashing when user navigates
    isFlashingNextArrow.value = false;
    if (flashTimeout) {
        clearTimeout(flashTimeout);
        flashTimeout = null;
    }
    
    if (chapters.readingView.value === 'title') {
        // In single-page mode viewing characters (left page), first show title (right page)
        if (responsive.isSinglePageMode.value && responsive.singlePageSide.value === 'left') {
            responsive.setSinglePageToRight();
            isViewingCharacters.value = false;
            return;
        }
        // Otherwise, go to chapter 1
        handleContinueToChapter1();
    } else {
        handleGoToNextChapter();
    }
};

// Swipe gesture handlers for touch devices
const handleSwipeForward = () => {
    if (canNavigateForward.value && !chapters.isLoadingChapter.value && !chapters.isGeneratingChapter.value) {
        handleRightEdgeClick();
    }
};

const handleSwipeBack = () => {
    if (canNavigateBack.value && !chapters.isLoadingChapter.value && !chapters.isGeneratingChapter.value) {
        handleGoToPreviousChapter();
    }
};

// Character selection handlers
const handleSelectCharacter = (character: Character) => {
    selectedCharacter.value = character;
    // In single-page mode, switch to right page to show character details
    if (responsive.isSinglePageMode.value) {
        responsive.setSinglePageToRight();
    }
};

const handleClearSelectedCharacter = () => {
    selectedCharacter.value = null;
    // In single-page mode, switch back to left page to show character grid
    if (responsive.isSinglePageMode.value) {
        responsive.setSinglePageToLeft();
    }
};

// Cover regeneration handler
const handleRegenerateCover = async () => {
    if (!props.bookId || !book.value) {
        return;
    }
    
    // Optimistically update local state to show pending
    book.value.cover_image_status = 'pending';
    
    const { error } = await requestApiFetch(`/api/books/${props.bookId}/regenerate-cover`, 'POST');
    
    if (error) {
        // Reset status on error
        book.value.cover_image_status = 'error';
        actionError.value = 'Failed to start cover generation. Please try again.';
    }
    // On success, the websocket will update the cover_image when complete
};

// Inline image regeneration handler
const handleRegenerateImage = async (item: { imageIndex?: number }, chapterId: string) => {
    if (!props.bookId || !book.value || item.imageIndex === undefined) {
        return;
    }
    
    // Optimistically update the image status to pending via the composable
    chapters.updateImageStatus(chapterId, item.imageIndex, 'pending');
    
    const { error } = await requestApiFetch(
        `/api/books/${props.bookId}/chapters/${chapterId}/regenerate-image`, 
        'POST',
        { image_index: item.imageIndex }
    );
    
    if (error) {
        // Reset status on error
        chapters.updateImageStatus(chapterId, item.imageIndex, 'error');
        actionError.value = 'Failed to start image generation. Please try again.';
    }
    // On success, the websocket will update the image when complete
};

// Retry inline image handler (when image generation failed or timed out)
const handleRetryInlineImage = async (item: { imageIndex?: number }, chapterId: string) => {
    if (!props.bookId || !book.value || item.imageIndex === undefined) {
        return;
    }
    
    // Optimistically update the image status to pending via the composable
    chapters.updateImageStatus(chapterId, item.imageIndex, 'pending');
    
    const { error } = await requestApiFetch(
        `/api/books/${props.bookId}/chapters/${chapterId}/retry-inline-image`, 
        'POST',
        { image_index: item.imageIndex }
    );
    
    if (error) {
        // Reset status on error
        chapters.updateImageStatus(chapterId, item.imageIndex, 'error');
        actionError.value = 'Failed to retry image generation. Please try again.';
    }
    // On success, the websocket will update the image when complete
};

// Cancel inline image handler (remove the pending/error image from the chapter)
const handleCancelInlineImage = async (item: { imageIndex?: number }, chapterId: string) => {
    if (!props.bookId || !book.value || item.imageIndex === undefined) {
        return;
    }
    
    const { error } = await requestApiFetch(
        `/api/books/${props.bookId}/chapters/${chapterId}/cancel-inline-image`, 
        'POST',
        { image_index: item.imageIndex }
    );
    
    if (error) {
        actionError.value = 'Failed to cancel image. Please try again.';
    }
    // On success, the websocket will update the chapter's inline_images
};

// Chapter header image regeneration handler
const handleRegenerateHeaderImage = async (chapterId: string) => {
    if (!props.bookId || !book.value) {
        return;
    }
    
    // Optimistically clear the image to show pending state
    const chapter = chapters.currentChapter.value;
    if (chapter && chapter.id === chapterId) {
        chapter.image = null;
    }
    
    const { error } = await requestApiFetch(
        `/api/books/${props.bookId}/chapters/${chapterId}/regenerate-header-image`, 
        'POST'
    );
    
    if (error) {
        actionError.value = 'Failed to start header image generation. Please try again.';
    }
    // On success, the websocket will update the image when complete
};

// Retry chapter header image handler (when image generation failed or timed out)
const handleRetryHeaderImage = async (chapterId: string) => {
    if (!props.bookId || !book.value) {
        return;
    }
    
    // Optimistically clear the image to show pending state
    const chapter = chapters.currentChapter.value;
    if (chapter && chapter.id === chapterId) {
        chapter.image = null;
    }
    
    const { error } = await requestApiFetch(
        `/api/books/${props.bookId}/chapters/${chapterId}/retry-header-image`, 
        'POST'
    );
    
    if (error) {
        actionError.value = 'Failed to retry header image generation. Please try again.';
    }
    // On success, the websocket will update the image when complete
};

// Cancel chapter header image handler (remove the pending/error image prompt)
const handleCancelHeaderImage = async (chapterId: string) => {
    if (!props.bookId || !book.value) {
        return;
    }
    
    const { error } = await requestApiFetch(
        `/api/books/${props.bookId}/chapters/${chapterId}/cancel-header-image`, 
        'POST'
    );
    
    if (error) {
        actionError.value = 'Failed to cancel header image. Please try again.';
    }
    // On success, the websocket will update the chapter
};

// Generate a new header image for a chapter that doesn't have one
const handleGenerateHeaderImage = async (chapterId: string) => {
    if (!props.bookId || !book.value) {
        return;
    }
    
    // Optimistically set image_prompt to show pending state (image is already null)
    const chapter = chapters.currentChapter.value;
    if (chapter && chapter.id === chapterId) {
        chapter.image_prompt = 'generating...';
        chapter.image = null;
    }
    
    const { error } = await requestApiFetch(
        `/api/books/${props.bookId}/chapters/${chapterId}/generate-header-image`, 
        'POST'
    );
    
    if (error) {
        actionError.value = 'Failed to start header image generation. Please try again.';
        // Revert the optimistic update on error
        if (chapter && chapter.id === chapterId) {
            chapter.image_prompt = null;
        }
    }
    // On success, the websocket will update the image when complete
};

// Character update handler
const handleCharacterUpdated = (updatedCharacter: Character) => {
    if (!book.value) {
        return;
    }
    
    // Update the character in the book's characters array
    const characterIndex = book.value.characters?.findIndex(c => c.id === updatedCharacter.id);
    if (characterIndex !== undefined && characterIndex >= 0 && book.value.characters) {
        book.value.characters[characterIndex] = updatedCharacter;
    }
    
    // Update selected character if it's the same one
    if (selectedCharacter.value?.id === updatedCharacter.id) {
        selectedCharacter.value = updatedCharacter;
    }
    
    // Emit the update to the parent (Dashboard) so it can update its book list
    emit('updated', book.value);
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
        // Reset to right side (title/first content) when jumping to a chapter
        responsive.resetSinglePageSide();
        isViewingCharacters.value = false;
        selectedCharacter.value = null;
        chapters.jumpToChapter(props.bookId, chapterNumber);
    }
};

const handleTocGoToTitle = () => {
    responsive.resetSinglePageSide();
    isViewingCharacters.value = false;
    selectedCharacter.value = null;
    chapters.goBackToTitlePage();
};

const handleTocGoToCharacters = () => {
    // Go to characters view (which is on the left side of title page)
    chapters.goBackToTitlePage();
    isViewingCharacters.value = true;
    selectedCharacter.value = null;
    // In single-page mode, show the left page (characters)
    if (responsive.isSinglePageMode.value) {
        responsive.setSinglePageToLeft();
    }
};

// Computed for whether book has characters
const hasCharacters = computed(() => {
    return book.value?.characters && book.value.characters.length > 0;
});

// Reset all state
const resetAllState = () => {
    animation.resetState();
    chapters.resetChapterState();
    responsive.resetSinglePageSide();
    unsubscribeFromBookChannel();
    book.value = null;
    loading.value = false;
    isEditing.value = false;
    isSaving.value = false;
    isDeleting.value = false;
    showDeleteConfirm.value = false;
    selectedCharacter.value = null;
    isViewingCharacters.value = false;
    savedChapterNumber.value = null;
    isFavorite.value = false;
    isTogglingFavorite.value = false;
    showChapterEditModal.value = false;
    isChapterEditing.value = false;
    chapterEditError.value = null;
    pendingEditChapterId.value = null;
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
        // Reset responsive state to start on the right side (title page) in single-page mode
        responsive.resetSinglePageSide();
        isViewingCharacters.value = false;
        
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
    if (flashTimeout) {
        clearTimeout(flashTimeout);
    }
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
                        <p 
                            v-if="!animation.isClosing.value"
                            class="text-lg font-medium text-amber-900 dark:text-amber-800 animate-pulse"
                        >
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

                    <!-- Chapter Edit Modal -->
                    <ChapterEditModal
                        :visible="showChapterEditModal"
                        :chapter="chapters.currentChapter.value"
                        :book-type="book?.type"
                        :is-processing="isChapterEditing"
                        :error="chapterEditError"
                        @close="handleCloseChapterEditModal"
                        @submit-small-changes="handleSubmitSmallChanges"
                        @submit-rewrite="handleSubmitRewrite"
                        @textarea-focused="isTextareaFocused = $event"
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
                            <p class="mt-2 text-sm text-amber-700 animate-pulse">
                                The magic is happening 🪄
                            </p>
                        </template>
                    </BookLoadingOverlay>

                    <!-- Header Controls -->
                    <BookHeaderControls
                        v-if="animation.animationPhase.value === 'complete' && !animation.isClosing.value"
                        :has-book="!!book"
                        :has-chapters="completedChapters.length > 0"
                        :chapters="completedChapters"
                        :current-chapter-number="chapters.currentChapterNumber.value"
                        :is-editing="isEditing"
                        :is-saving="isSaving"
                        :is-deleting="isDeleting"
                        :is-page-turning="animation.isPageTurning.value"
                        :is-book-opened="animation.isBookOpened.value"
                        :book-type="book?.type"
                        :is-favorite="isFavorite"
                        :is-toggling-favorite="isTogglingFavorite"
                        :has-characters="hasCharacters"
                        :is-viewing-characters="isViewingCharacters"
                        @edit="startEditing"
                        @delete="requestDelete"
                        @close="closeModal"
                        @toc-select-chapter="handleTocSelectChapter"
                        @toc-go-to-title="handleTocGoToTitle"
                        @toc-go-to-characters="handleTocGoToCharacters"
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
                        :is-single-page-mode="responsive.isSinglePageMode.value"
                        @open="animation.openBook"
                    />

                    <!-- ==================== OPENED BOOK VIEW (Two Pages / Single Page Responsive) ==================== -->
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
                                <p v-if="chapters.isGeneratingChapter.value" class="mt-2 text-sm text-amber-700 animate-pulse">
                                    The magic is happening 🪄
                                </p>
                            </template>
                        </BookLoadingOverlay>

                        <!-- Editing Chapter Overlay -->
                        <BookLoadingOverlay 
                            v-if="isChapterEditing"
                            message="Updating your chapter..."
                        >
                            <template #subtitle>
                                <p class="mt-2 text-sm text-amber-700 animate-pulse">
                                    Making your changes ✨
                                </p>
                            </template>
                        </BookLoadingOverlay>

                        <!-- Left Edge Click Zone (Go Back) -->
                        <button
                            v-if="canNavigateBack && !chapters.isLoadingChapter.value && !chapters.isGeneratingChapter.value"
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

                        <!-- Left Page (hidden in single-page mode when showing right) -->
                        <BookLeftPage
                            v-show="responsive.showLeftPage.value"
                            :class="{ 'w-full': responsive.isSinglePageMode.value }"
                            :reading-view="chapters.readingView.value"
                            :chapter="chapters.currentChapter.value"
                            :spread="chapters.currentSpread.value"
                            :spread-index="chapters.currentSpreadIndex.value"
                            :characters="book?.characters"
                            :selected-character-id="selectedCharacter?.id ?? null"
                            :has-next-chapter="chapters.hasNextChapter.value"
                            :chapter-ends-on-left="chapters.chapterEndsOnLeft.value"
                            :is-on-last-spread="chapters.isOnLastSpread.value"
                            :is-last-chapter="isLastChapter"
                            :current-chapter-number="chapters.currentChapterNumber.value"
                            :next-chapter-prompt="chapters.nextChapterPrompt.value"
                            :suggested-idea="chapters.suggestedIdea.value"
                            :is-loading-idea="chapters.isLoadingIdea.value"
                            :is-final-chapter="chapters.isFinalChapter.value"
                            :is-generating-chapter="chapters.isGeneratingChapter.value || chapters.isAwaitingChapterGeneration.value"
                            :book-type="book?.type"
                            :is-single-page-mode="responsive.isSinglePageMode.value"
                            :book-title="displayTitle"
                            @select-character="handleSelectCharacter"
                            @update:next-chapter-prompt="chapters.nextChapterPrompt.value = $event"
                            @update:is-final-chapter="chapters.isFinalChapter.value = $event"
                            @generate-chapter="handleGenerateChapter"
                            @textarea-focused="isTextareaFocused = $event"
                            @request-idea="handleRequestIdea"
                            @regenerate-image="(item, chapterId) => handleRegenerateImage(item, chapterId)"
                            @retry-inline-image="(item, chapterId) => handleRetryInlineImage(item, chapterId)"
                            @cancel-inline-image="(item, chapterId) => handleCancelInlineImage(item, chapterId)"
                            @edit-chapter="handleOpenChapterEditModal"
                            @swipe-forward="handleSwipeForward"
                            @swipe-back="handleSwipeBack"
                        />

                        <!-- Book Spine (hidden in single-page mode) -->
                        <BookSpine v-show="!responsive.isSinglePageMode.value" />

                        <!-- Right Page (hidden in single-page mode when showing left) -->
                        <BookRightPage
                            v-show="responsive.showRightPage.value"
                            :class="{ 'w-full': responsive.isSinglePageMode.value }"
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
                            :total-chapters="chapters.totalChapters.value"
                            :next-chapter-prompt="chapters.nextChapterPrompt.value"
                            :suggested-idea="chapters.suggestedIdea.value"
                            :is-loading-idea="chapters.isLoadingIdea.value"
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
                            :is-single-page-mode="responsive.isSinglePageMode.value"
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
                            @regenerate-cover="handleRegenerateCover"
                            @regenerate-image="(item, chapterId) => handleRegenerateImage(item, chapterId)"
                            @regenerate-header-image="(chapterId) => handleRegenerateHeaderImage(chapterId)"
                            @generate-header-image="(chapterId) => handleGenerateHeaderImage(chapterId)"
                            @retry-header-image="(chapterId) => handleRetryHeaderImage(chapterId)"
                            @cancel-header-image="(chapterId) => handleCancelHeaderImage(chapterId)"
                            @retry-inline-image="(item, chapterId) => handleRetryInlineImage(item, chapterId)"
                            @cancel-inline-image="(item, chapterId) => handleCancelInlineImage(item, chapterId)"
                            @request-idea="handleRequestIdea"
                            @character-updated="handleCharacterUpdated"
                            @edit-chapter="handleOpenChapterEditModal"
                            @scrolled-to-bottom="handleScrolledToBottom"
                            @swipe-forward="handleSwipeForward"
                            @swipe-back="handleSwipeBack"
                        />

                        <!-- Right Edge Click Zone (Go Forward / Start Reading) -->
                        <!-- Hidden when viewing character details to prevent accidental navigation -->
                        <button
                            v-if="canNavigateForward && !chapters.isLoadingChapter.value && !chapters.isGeneratingChapter.value && !selectedCharacter"
                            :class="[
                                'edge-nav-zone edge-nav-right group absolute right-0 inset-y-0 w-14 z-30 cursor-pointer bg-transparent transition-all duration-200 hover:bg-amber-900/5 dark:hover:bg-amber-100/5 focus:outline-none',
                                { 'nav-arrow-flash': isFlashingNextArrow }
                            ]"
                            @click="handleRightEdgeClick"
                            :aria-label="chapters.readingView.value === 'title' ? 'Start reading' : 'Next page'"
                        >
                            <div :class="[
                                'absolute inset-y-0 right-0 w-full flex items-center justify-center transition-opacity duration-200',
                                isFlashingNextArrow ? 'opacity-100' : 'opacity-0 group-hover:opacity-100'
                            ]">
                                <svg :class="[
                                    'w-6 h-6 transition-all duration-200',
                                    isFlashingNextArrow 
                                        ? 'text-amber-600 dark:text-amber-400 scale-125 animate-bounce-gentle' 
                                        : 'text-amber-700/60 dark:text-amber-500/60'
                                ]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
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

/* Navigation arrow flash animation */
.nav-arrow-flash {
    animation: arrowPulse 0.6s ease-in-out infinite;
}

@keyframes arrowPulse {
    0%, 100% {
        background-color: transparent;
    }
    50% {
        background-color: rgb(217 119 6 / 0.1);
    }
}

:is(.dark .nav-arrow-flash) {
    animation: arrowPulseDark 0.6s ease-in-out infinite;
}

@keyframes arrowPulseDark {
    0%, 100% {
        background-color: transparent;
    }
    50% {
        background-color: rgb(251 191 36 / 0.1);
    }
}

.animate-bounce-gentle {
    animation: bounceGentle 0.6s ease-in-out infinite;
}

@keyframes bounceGentle {
    0%, 100% {
        transform: translateX(0) scale(1.25);
    }
    50% {
        transform: translateX(4px) scale(1.25);
    }
}
</style>

