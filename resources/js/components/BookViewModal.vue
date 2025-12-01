<script setup lang="ts">
import { ref, watch, nextTick, onBeforeUnmount, computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { MagicalSparklesLoader } from '@/components/ui/magical-book-loader';
import { Textarea } from '@/components/ui/textarea';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuPortal,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { apiFetch } from '@/composables/ApiFetch';
import { BookOpen, ChevronLeft, ChevronRight, MoreVertical, Sparkles, Wand2, X } from 'lucide-vue-next';

interface Profile {
    id: string;
    name: string;
}

interface Book {
    id: string;
    title: string;
    author: string | null;
    genre: string;
    age_level: number | null;
    plot: string | null;
    cover_image: string | null;
    status: string;
    created_at: string;
    profile?: Profile | null;
}

interface Chapter {
    id: string;
    title: string | null;
    body: string | null;
    image: string | null;
    sort: number;
    summary: string | null;
    final_chapter: boolean;
}

interface ChapterResponse {
    chapter: Chapter | null;
    total_chapters: number;
    has_next: boolean;
    has_previous?: boolean;
}

interface CardPosition {
    top: number;
    left: number;
    width: number;
    height: number;
}

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

type ApiFetchFn = (
    request: string,
    method?: string,
    data?: Record<string, unknown> | FormData | null,
    isFormData?: boolean | null,
) => Promise<{ data: unknown; error: unknown }>;

const requestApiFetch = apiFetch as ApiFetchFn;

const book = ref<Book | null>(null);
const loading = ref(false);
const showContent = ref(false);
const animationPhase = ref<'initial' | 'flipping' | 'complete'>('initial');
const modalElement = ref<HTMLElement | null>(null);
const cardStyle = ref<Record<string, string>>({});
const isRendered = ref(false);
const isClosing = ref(false);
const frontVisible = ref(true);
const backVisible = ref(false);
const expandedRect = ref<{ top: number; left: number; width: number; height: number } | null>(null);

const isBookOpened = ref(false);
const isPageTurning = ref(false);
const isTitlePageFading = ref(false);

// Chapter reading state
const currentChapterNumber = ref(0); // 0 = title page
const currentChapter = ref<Chapter | null>(null);
const totalChapters = ref(0);
const isLoadingChapter = ref(false);
const isGeneratingChapter = ref(false);
const chapterError = ref<string | null>(null);
const nextChapterPrompt = ref('');
const isFinalChapter = ref(false);

// Reading view state: 'title' | 'chapter-image' | 'chapter-content' | 'create-chapter'
const readingView = ref<'title' | 'chapter-image' | 'chapter-content' | 'create-chapter'>('title');
const isPageFlipping = ref(false);

// Pagination state for chapter content across spreads
const currentSpreadIndex = ref(0);

// Page content calculation - estimates characters that fit per page
// Based on typical book page dimensions and font sizing (text-lg = 18px)
const CHARS_PER_LINE = 32;
const LINES_PER_FULL_PAGE = 18;
const FIRST_PAGE_LINES = 7; // ~40% of page used for title/margin, leaves ~60% for text
const CHARS_PER_FULL_PAGE = CHARS_PER_LINE * LINES_PER_FULL_PAGE;
const CHARS_FIRST_PAGE = CHARS_PER_LINE * FIRST_PAGE_LINES;

// Split chapter body into pages
const chapterPages = computed(() => {
    if (!currentChapter.value?.body) return [];
    
    const body = currentChapter.value.body;
    const paragraphs = body.split('\n\n').filter(p => p.trim());
    
    if (paragraphs.length === 0) return [];
    
    const pages: string[][] = [];
    let currentPage: string[] = [];
    let currentPageChars = 0;
    let pageIndex = 0;
    
    for (const paragraph of paragraphs) {
        // First page (index 0) has less space due to title + 40% top margin
        const maxChars = pageIndex === 0 ? CHARS_FIRST_PAGE : CHARS_PER_FULL_PAGE;
        const paragraphChars = paragraph.length + 20; // Add padding for paragraph spacing
        
        if (currentPageChars + paragraphChars > maxChars && currentPage.length > 0) {
            // Current page is full, start new page
            pages.push(currentPage);
            currentPage = [paragraph];
            currentPageChars = paragraphChars;
            pageIndex++;
        } else {
            currentPage.push(paragraph);
            currentPageChars += paragraphChars;
        }
    }
    
    // Don't forget the last page
    if (currentPage.length > 0) {
        pages.push(currentPage);
    }
    
    return pages;
});

// Group pages into spreads (pairs of left/right pages)
// Spread 0: left = chapter image, right = first page (title + content start)
// Spread 1+: left = continuation, right = continuation
interface PageSpread {
    leftContent: string[] | null;
    rightContent: string[] | null;
    isFirstSpread: boolean;
    showImage: boolean;
}

const chapterSpreads = computed((): PageSpread[] => {
    const pages = chapterPages.value;
    if (pages.length === 0) return [];
    
    const spreads: PageSpread[] = [];
    
    // First spread: left = image/decorative, right = first page of content
    spreads.push({
        leftContent: null, // Will show image or decorative
        rightContent: pages[0] || null,
        isFirstSpread: true,
        showImage: true
    });
    
    // Subsequent spreads pair remaining pages (left, right)
    for (let i = 1; i < pages.length; i += 2) {
        spreads.push({
            leftContent: pages[i] || null,
            rightContent: pages[i + 1] || null,
            isFirstSpread: false,
            showImage: false
        });
    }
    
    return spreads;
});

const currentSpread = computed(() => {
    return chapterSpreads.value[currentSpreadIndex.value] || null;
});

const hasNextSpread = computed(() => {
    return currentSpreadIndex.value < chapterSpreads.value.length - 1;
});

const hasPrevSpread = computed(() => {
    return currentSpreadIndex.value > 0;
});

const totalSpreads = computed(() => {
    return chapterSpreads.value.length;
});

const loadChapter = async (chapterNumber: number) => {
    if (!props.bookId || isLoadingChapter.value) {
        return;
    }

    isLoadingChapter.value = true;
    chapterError.value = null;

    try {
        const { data, error } = await requestApiFetch(
            `/api/books/${props.bookId}/chapters/${chapterNumber}`,
            'GET'
        );

        if (error) {
            chapterError.value = extractErrorMessage(error) ?? 'Could not load chapter.';
            return;
        }

        const response = data as ChapterResponse;
        totalChapters.value = response.total_chapters;

        if (response.chapter) {
            currentChapter.value = response.chapter;
            currentChapterNumber.value = chapterNumber;
            currentSpreadIndex.value = 0; // Reset to first spread
            readingView.value = 'chapter-image';
        } else {
            // No chapter exists - show create form
            currentChapter.value = null;
            currentChapterNumber.value = chapterNumber;
            readingView.value = 'create-chapter';
        }
    } catch (err) {
        chapterError.value = extractErrorMessage(err) ?? 'An error occurred loading the chapter.';
    } finally {
        isLoadingChapter.value = false;
    }
};

const generateNextChapter = async () => {
    if (!props.bookId || isGeneratingChapter.value) {
        return;
    }

    isGeneratingChapter.value = true;
    chapterError.value = null;

    try {
        const { data, error } = await requestApiFetch(
            `/api/books/${props.bookId}/chapters/generate`,
            'POST',
            {
                user_prompt: nextChapterPrompt.value.trim() || null,
                final_chapter: isFinalChapter.value,
            }
        );

        if (error) {
            chapterError.value = extractErrorMessage(error) ?? 'Could not generate chapter.';
            return;
        }

        const response = data as ChapterResponse;
        
        if (response.chapter) {
            currentChapter.value = response.chapter;
            currentChapterNumber.value = response.chapter.sort;
            totalChapters.value = response.total_chapters;
            nextChapterPrompt.value = '';
            isFinalChapter.value = false;
            currentSpreadIndex.value = 0; // Reset to first spread
            readingView.value = 'chapter-image';
        }
    } catch (err) {
        chapterError.value = extractErrorMessage(err) ?? 'An error occurred generating the chapter.';
    } finally {
        isGeneratingChapter.value = false;
    }
};

const goToChapter1 = () => {
    if (isTitlePageFading.value || isLoadingChapter.value) {
        return;
    }
    isTitlePageFading.value = true;
    
    // After fade, load chapter 1
    scheduleTimeout(() => {
        loadChapter(1);
        isTitlePageFading.value = false;
    }, 500);
};

// Navigate to next spread within chapter, or next chapter if at end
const goToNextChapter = () => {
    if (isLoadingChapter.value || isPageFlipping.value) {
        return;
    }
    
    if (hasNextSpread.value) {
        // More spreads in current chapter
        currentSpreadIndex.value++;
    } else {
        // At last spread - go to next chapter or create form
        const nextNumber = currentChapterNumber.value + 1;
        
        if (nextNumber <= totalChapters.value) {
            loadChapter(nextNumber);
        } else {
            // No more chapters - show create form
            currentChapter.value = null;
            currentChapterNumber.value = nextNumber;
            currentSpreadIndex.value = 0;
            readingView.value = 'create-chapter';
        }
    }
};

// Navigate to previous spread within chapter, or previous chapter/title if at start
const goToPreviousChapter = () => {
    if (isLoadingChapter.value || isPageFlipping.value) {
        return;
    }
    
    if (hasPrevSpread.value) {
        // More spreads in current chapter going back
        currentSpreadIndex.value--;
    } else if (currentChapterNumber.value > 1) {
        // At first spread - go to previous chapter
        loadChapter(currentChapterNumber.value - 1);
    } else if (currentChapterNumber.value === 1) {
        // At first spread of first chapter - go back to title page
        currentChapter.value = null;
        currentChapterNumber.value = 0;
        currentSpreadIndex.value = 0;
        readingView.value = 'title';
    }
};

const goBackToTitlePage = () => {
    currentChapter.value = null;
    currentChapterNumber.value = 0;
    readingView.value = 'title';
};

const isEditing = ref(false);
const isSaving = ref(false);
const isDeleting = ref(false);
const showDeleteConfirm = ref(false);
const actionError = ref<string | null>(null);
const editErrors = ref<Record<string, string>>({});
const editForm = ref({
    title: '',
    genre: '',
    age_level: '',
    author: '',
    plot: '',
});

const genres = [
    { value: 'fantasy', label: 'Fantasy' },
    { value: 'adventure', label: 'Adventure' },
    { value: 'mystery', label: 'Mystery' },
    { value: 'science_fiction', label: 'Science Fiction' },
    { value: 'fairy_tale', label: 'Fairy Tale' },
    { value: 'historical', label: 'Historical' },
    { value: 'comedy', label: 'Comedy' },
    { value: 'animal_stories', label: 'Animal Stories' },
];

const ageLevels = Array.from({ length: 15 }, (_, index) => ({
    value: String(index + 4),
    label: `Age ${index + 4}+`,
}));

const FLIP_DURATION = 1200;
const HALF_FLIP = FLIP_DURATION / 2;
const TRANSITION_EASING = 'cubic-bezier(0.4, 0, 0.2, 1)';

const scheduledTimeouts: number[] = [];

const scheduleTimeout = (callback: () => void, delay: number) => {
    const id = window.setTimeout(() => {
        callback();
        const index = scheduledTimeouts.indexOf(id);
        if (index !== -1) {
            scheduledTimeouts.splice(index, 1);
        }
    }, delay);

    scheduledTimeouts.push(id);
};

const clearScheduledTimeouts = () => {
    scheduledTimeouts.forEach(timeoutId => window.clearTimeout(timeoutId));
    scheduledTimeouts.length = 0;
};

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

// Generate gradient based on book ID
const getGradientColors = (bookId: string) => {
    const colors = [
        'bg-gradient-to-br from-violet-600 to-violet-300',
        'bg-gradient-to-br from-blue-600 to-blue-300',
        'bg-gradient-to-br from-emerald-600 to-emerald-300',
        'bg-gradient-to-br from-amber-600 to-amber-300',
        'bg-gradient-to-br from-rose-600 to-rose-300',
        'bg-gradient-to-br from-pink-600 to-pink-300',
        'bg-gradient-to-br from-indigo-600 to-indigo-300',
        'bg-gradient-to-br from-cyan-600 to-cyan-300',
        'bg-gradient-to-br from-orange-600 to-orange-300',
        'bg-gradient-to-br from-teal-600 to-teal-300',
    ];
    
    const hash = bookId.split('').reduce((acc, char) => acc + char.charCodeAt(0), 0);
    return colors[hash % colors.length];
};

const setCardStyle = (style: Partial<CSSStyleDeclaration>) => {
    const nextStyle: Record<string, string> = {};
    Object.entries(style).forEach(([key, value]) => {
        if (typeof value === 'number') {
            nextStyle[key] = `${value}px`;
        } else if (typeof value === 'string') {
            nextStyle[key] = value;
        }
    });

    cardStyle.value = nextStyle;
};

const coverRect = ref<{ width: number; left: number } | null>(null);
const isCoverFading = ref(false);

const openBook = () => {
    if (isPageTurning.value || isBookOpened.value || !expandedRect.value) {
        return;
    }
    
    isPageTurning.value = true;
    
    // Store cover dimensions before expanding
    const currentWidth = expandedRect.value.width;
    const currentLeft = expandedRect.value.left;
    coverRect.value = { width: currentWidth, left: currentLeft };
    
    // Calculate full width dimensions (maintain same height, double width for two pages)
    const fullWidth = Math.min(window.innerWidth * 0.96, expandedRect.value.height * 1.4);
    const fullLeft = (window.innerWidth - fullWidth) / 2;
    
    // Update expanded rect
    expandedRect.value = {
        ...expandedRect.value,
        width: fullWidth,
        left: fullLeft,
    };
    
    // Step 1: Expand the modal to full width AND fade out the cover simultaneously
    setCardStyle({
        position: 'fixed',
        top: `${expandedRect.value.top}px`,
        left: `${fullLeft}px`,
        width: `${fullWidth}px`,
        height: `${expandedRect.value.height}px`,
        transform: 'none',
        zIndex: '9999',
        transition: 'all 600ms cubic-bezier(0.4, 0, 0.2, 1)',
    });
    
    // Start fading the cover immediately (small delay to allow page turn state to render)
    scheduleTimeout(() => {
        isCoverFading.value = true;
    }, 50);
    
    // Step 3: After fade completes, show the opened book view
    scheduleTimeout(() => {
        isBookOpened.value = true;
        isPageTurning.value = false;
        isCoverFading.value = false;
    }, 1100);
};

const startAnimation = async () => {
    clearScheduledTimeouts();
    book.value = null;
    loading.value = false;
    showContent.value = false;
    animationPhase.value = 'initial';
    frontVisible.value = true;
    backVisible.value = true;
    isClosing.value = false;
    isBookOpened.value = false;
    isPageTurning.value = false;
    isCoverFading.value = false;
    isTitlePageFading.value = false;
    coverRect.value = null;
    isEditing.value = false;
    isSaving.value = false;
    isDeleting.value = false;
    showDeleteConfirm.value = false;
    // Reset chapter state
    currentChapterNumber.value = 0;
    currentChapter.value = null;
    totalChapters.value = 0;
    isLoadingChapter.value = false;
    isGeneratingChapter.value = false;
    chapterError.value = null;
    nextChapterPrompt.value = '';
    isFinalChapter.value = false;
    currentSpreadIndex.value = 0;
    readingView.value = 'title';
    isPageFlipping.value = false;
    resetEditFeedback();

    // Calculate cover dimensions (book cover aspect ratio ~2:3, centered)
    // Use 96% of viewport height for near-full-screen effect with small margins
    const coverHeight = window.innerHeight * 0.96;
    const coverWidth = coverHeight * 0.67; // Book cover aspect ratio
    const coverTop = (window.innerHeight - coverHeight) / 2;
    const coverLeft = (window.innerWidth - coverWidth) / 2;

    // Store the centered cover position
    expandedRect.value = {
        top: coverTop,
        left: coverLeft,
        width: coverWidth,
        height: coverHeight,
    };

    if (!props.cardPosition) {
        loadBook();
        animationPhase.value = 'complete';
        showContent.value = true;
        setCardStyle({
            position: 'fixed',
            top: `${coverTop}px`,
            left: `${coverLeft}px`,
            width: `${coverWidth}px`,
            height: `${coverHeight}px`,
            transform: 'none',
            zIndex: '9999',
        });
        return;
    }

    // Start at the card position
    setCardStyle({
        position: 'fixed',
        top: `${props.cardPosition.top}px`,
        left: `${props.cardPosition.left}px`,
        width: `${props.cardPosition.width}px`,
        height: `${props.cardPosition.height}px`,
        transform: 'none',
        zIndex: '9999',
        transition: 'none',
    });

    await nextTick();
    loadBook();

    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            animationPhase.value = 'flipping';

            // Simple grow animation to centered cover position (no flip)
            setCardStyle({
                position: 'fixed',
                top: `${coverTop}px`,
                left: `${coverLeft}px`,
                width: `${coverWidth}px`,
                height: `${coverHeight}px`,
                transform: 'none',
                zIndex: '9999',
                transition: `all 600ms ${TRANSITION_EASING}`,
            });

            scheduleTimeout(() => {
                showContent.value = true;
                animationPhase.value = 'complete';
            }, 600);
        });
    });
};

const finalizeClose = () => {
    clearScheduledTimeouts();
    isClosing.value = false;
    isRendered.value = false;
    animationPhase.value = 'initial';
    showContent.value = false;
    frontVisible.value = true;
    backVisible.value = true;
    expandedRect.value = null;
    coverRect.value = null;
    cardStyle.value = {};
    book.value = null;
    loading.value = false;
    isBookOpened.value = false;
    isPageTurning.value = false;
    isCoverFading.value = false;
    isTitlePageFading.value = false;
    isEditing.value = false;
    isSaving.value = false;
    isDeleting.value = false;
    showDeleteConfirm.value = false;
    // Reset chapter state
    currentChapterNumber.value = 0;
    currentChapter.value = null;
    totalChapters.value = 0;
    isLoadingChapter.value = false;
    isGeneratingChapter.value = false;
    chapterError.value = null;
    nextChapterPrompt.value = '';
    isFinalChapter.value = false;
    currentSpreadIndex.value = 0;
    readingView.value = 'title';
    isPageFlipping.value = false;
    resetEditFeedback();
};

const reverseAnimation = async () => {
    if (isClosing.value) {
        return;
    }

    const sourceCard = props.cardPosition;

    if (!sourceCard || !expandedRect.value) {
        finalizeClose();
        return;
    }

    clearScheduledTimeouts();
    isClosing.value = true;
    animationPhase.value = 'flipping';
    showContent.value = false;
    isBookOpened.value = false;

    // If we're in the expanded (opened book) state, first shrink back to cover size
    const coverHeight = window.innerHeight * 0.96;
    const coverWidth = coverHeight * 0.67;
    const coverTop = (window.innerHeight - coverHeight) / 2;
    const coverLeft = (window.innerWidth - coverWidth) / 2;

    setCardStyle({
        position: 'fixed',
        top: `${coverTop}px`,
        left: `${coverLeft}px`,
        width: `${coverWidth}px`,
        height: `${coverHeight}px`,
        transform: 'none',
        zIndex: '9999',
        transition: 'all 300ms ease-out',
    });

    await nextTick();

    scheduleTimeout(() => {
    requestAnimationFrame(() => {
            // Shrink back to card position
            setCardStyle({
                position: 'fixed',
                top: `${sourceCard.top}px`,
                left: `${sourceCard.left}px`,
                width: `${sourceCard.width}px`,
                height: `${sourceCard.height}px`,
                transform: 'none',
                zIndex: '9999',
                transition: 'all 500ms ease-in-out',
    });

    scheduleTimeout(() => {
        finalizeClose();
            }, 500);
        });
    }, 300);
};

const loadBook = async () => {
    if (!props.bookId) return;
    
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

const closeModal = () => {
    if (isClosing.value || isSaving.value || isDeleting.value) {
        return;
    }

    isOpen.value = false;
};

const handleKeydown = (event: KeyboardEvent) => {
    if (event.key === 'Escape') {
        closeModal();
    }
};

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('en-US', { 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
    });
};

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
    // First try profile name from relationship
    const profileName = book.value?.profile?.name?.trim();
    if (profileName) {
        return profileName;
    }

    // Fall back to author field
    const fetchedAuthor = book.value?.author?.trim();
    if (fetchedAuthor) {
        return fetchedAuthor;
    }

    const propAuthor = props.bookAuthor?.trim() ?? null;
    return propAuthor && propAuthor.length > 0 ? propAuthor : null;
});

const displayPlot = computed(() => {
    const plot = book.value?.plot?.trim();
    return plot && plot.length > 0 ? plot : null;
});

const displayCreatedAt = computed(() => {
    const createdAt = book.value?.created_at;
    return createdAt ? formatDate(createdAt) : null;
});

watch(book, newBook => {
    if (newBook && !isEditing.value) {
        initializeEditForm(newBook);
    }
});

watch(isOpen, async (open) => {
    if (open) {
        isRendered.value = true;
        await nextTick();
        await startAnimation();
        window.addEventListener('keydown', handleKeydown);
    } else if (isRendered.value) {
        window.removeEventListener('keydown', handleKeydown);
        await reverseAnimation();
    }
});

onBeforeUnmount(() => {
    clearScheduledTimeouts();
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
                v-if="isRendered"
                :class="[
                    'fixed inset-0 bg-black/80 backdrop-blur-sm z-[9998]',
                    { 'pointer-events-none': isClosing }
                ]"
                @click="closeModal"
            />
        </Transition>

        <!-- Animated Modal -->
        <div
            v-if="isRendered"
            ref="modalElement"
            :style="cardStyle"
            class="book-modal-container rounded-2xl overflow-hidden shadow-2xl"
        >
            <!-- Card Front Face (shown during shrink-back close animation) -->
            <div
                v-show="frontVisible && isClosing"
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
                    class="absolute inset-0 flex items-center justify-center p-6"
                    :class="getGradientColors(props.bookId)"
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
                v-show="backVisible"
                class="absolute inset-0 overflow-hidden"
            >
                <!-- Loading State -->
                <div 
                    v-if="!showContent"
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
                    <Transition
                        enter-active-class="transition-all duration-300 ease-out"
                        enter-from-class="opacity-0 scale-75"
                        enter-to-class="opacity-100 scale-100"
                        leave-active-class="transition-all duration-200 ease-in"
                        leave-from-class="opacity-100 scale-100"
                        leave-to-class="opacity-0 scale-75"
                    >
                        <div 
                            v-if="showDeleteConfirm" 
                            class="absolute inset-0 z-50 flex items-center justify-center rounded-2xl bg-black/50 backdrop-blur-sm"
                        >
                            <div class="mx-4 w-full max-w-sm animate-bounce-in rounded-2xl border-2 border-red-200 bg-white p-6 shadow-2xl dark:border-red-800 dark:bg-gray-900">
                                <div class="mb-4 text-center">
                                    <div class="mx-auto mb-3 flex h-16 w-16 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/30">
                                        <span class="text-3xl">😱</span>
                                    </div>
                                    <h3 class="text-xl font-bold text-gray-900 dark:text-white">Delete this story?</h3>
                                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                                        This will permanently delete your story and all its chapters. This cannot be undone!
                                    </p>
                                </div>
                                <div class="flex gap-3">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        @click="cancelDelete"
                                        class="flex-1 cursor-pointer rounded-xl transition-all duration-200 hover:scale-[1.02] active:scale-[0.98]"
                                    >
                                        Keep It! 📚
                                    </Button>
                                    <Button
                                        type="button"
                                        @click="confirmDelete"
                                        class="flex-1 cursor-pointer rounded-xl bg-gradient-to-r from-red-500 to-rose-500 text-white transition-all duration-200 hover:scale-[1.02] hover:from-red-600 hover:to-rose-600 active:scale-[0.98]"
                                    >
                                        Delete Forever
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </Transition>

                    <!-- Loading/Saving Overlay -->
                    <div 
                        v-if="loading || isSaving || isDeleting"
                        class="absolute inset-0 z-20 flex items-center justify-center bg-amber-50/90 backdrop-blur-sm dark:bg-amber-100/90"
                    >
                        <div class="text-center space-y-6">
                            <MagicalSparklesLoader 
                                size="lg" 
                                color="text-amber-500" 
                                accent-color="text-orange-400"
                                class="mx-auto"
                            />
                            <p class="text-lg font-medium text-amber-900 dark:text-amber-800 animate-pulse">
                                {{ loading ? 'Opening your story...' : isSaving ? 'Saving changes...' : 'Deleting story...' }}
                            </p>
                        </div>
                    </div>

                    <!-- Header Controls -->
                    <div
                        v-if="animationPhase === 'complete' && !isClosing"
                        class="pointer-events-none absolute inset-x-0 top-0 z-40 flex justify-end px-4 pt-4"
                    >
                        <div class="pointer-events-auto flex items-center gap-2">
                            <DropdownMenu v-if="book">
                                <DropdownMenuTrigger :as-child="true">
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="cursor-pointer rounded-full bg-white/70 p-2 text-amber-900 shadow-md backdrop-blur-sm transition-colors hover:bg-white/90 dark:bg-white/70 dark:text-amber-900 dark:hover:bg-white/90"
                                        :disabled="isEditing || isSaving || isDeleting"
                                    >
                                        <MoreVertical class="h-5 w-5" />
                                        <span class="sr-only">Story actions</span>
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuPortal>
                                    <DropdownMenuContent align="end" class="z-[10001] w-48">
                                        <DropdownMenuItem
                                            @select="startEditing"
                                            :disabled="isEditing || isSaving || isDeleting"
                                        >
                                            Edit Story
                                        </DropdownMenuItem>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem
                                            @select="requestDelete"
                                            :disabled="isSaving || isDeleting"
                                            class="cursor-pointer text-red-600 focus:bg-red-50 focus:text-red-700 dark:text-red-400 dark:focus:bg-red-950/50 dark:focus:text-red-300"
                                        >
                                            Delete Story
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenuPortal>
                            </DropdownMenu>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="cursor-pointer rounded-full bg-white/70 p-2 text-amber-900 shadow-md backdrop-blur-sm transition-colors hover:bg-white/90 dark:bg-white/70 dark:text-amber-900 dark:hover:bg-white/90"
                                @click="closeModal"
                                :disabled="isSaving || isDeleting || isPageTurning"
                            >
                                <X class="h-5 w-5" />
                                <span class="sr-only">Close</span>
                            </Button>
                        </div>
                    </div>

                    <!-- ==================== CLOSED BOOK VIEW (Cover Centered) ==================== -->
                    <div 
                        v-if="!isBookOpened" 
                        class="book-closed-view relative h-full w-full"
                    >
                        <!-- During expansion: show two-page layout with cover fading on right -->
                        <template v-if="isPageTurning">
                            <div class="relative flex h-full w-full">
                                <!-- Left Side: Blank decorative page -->
                                <div class="relative w-1/2 h-full bg-amber-50 dark:bg-amber-100 overflow-hidden">
                                    <div class="absolute inset-0 opacity-[0.08]" style="background-image: url('data:image/svg+xml,%3Csvg viewBox=\'0 0 200 200\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cfilter id=\'noise\'%3E%3CfeTurbulence type=\'fractalNoise\' baseFrequency=\'0.5\' numOctaves=\'2\' stitchTiles=\'stitch\'/%3E%3C/filter%3E%3Crect width=\'100%25\' height=\'100%25\' filter=\'url(%23noise)\'/%3E%3C/svg%3E');" />
                                    <div class="absolute inset-y-8 left-4 w-px bg-amber-300/60 dark:bg-amber-400/50" />
                                    <div class="absolute inset-y-8 left-8 w-px bg-amber-300/40 dark:bg-amber-400/30" />
                                </div>

                                <!-- Book Spine / Center Seam - positioned above content for curved effect -->
                                <div class="pointer-events-none absolute left-1/2 top-0 bottom-0 z-30 w-24 -translate-x-1/2">
                                    <!-- Wide soft shadow for page curve effect -->
                                    <div class="h-full w-full bg-gradient-to-r from-transparent via-amber-950/20 to-transparent" />
                                    <!-- Inner darker shadow -->
                                    <div class="absolute inset-y-0 left-1/2 w-10 -translate-x-1/2 bg-gradient-to-r from-transparent via-amber-950/25 to-transparent" />
                                    <!-- Core shadow at binding -->
                                    <div class="absolute inset-y-0 left-1/2 w-4 -translate-x-1/2 bg-gradient-to-r from-transparent via-amber-950/30 to-transparent" />
                                    <!-- Highlight line in center -->
                                    <div class="absolute inset-y-0 left-1/2 w-px -translate-x-1/2 bg-gradient-to-b from-amber-200/5 via-amber-100/20 to-amber-200/5" />
                                </div>

                                <!-- Right Side: Cover fading out over blank page -->
                                <div class="relative w-1/2 h-full overflow-hidden">
                                    <!-- Blank page underneath -->
                                    <div class="absolute inset-0 bg-amber-50 dark:bg-amber-100">
                                        <div class="absolute inset-0 opacity-[0.08]" style="background-image: url('data:image/svg+xml,%3Csvg viewBox=\'0 0 200 200\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cfilter id=\'noise\'%3E%3CfeTurbulence type=\'fractalNoise\' baseFrequency=\'0.5\' numOctaves=\'2\' stitchTiles=\'stitch\'/%3E%3C/filter%3E%3Crect width=\'100%25\' height=\'100%25\' filter=\'url(%23noise)\'/%3E%3C/svg%3E');" />
                                        <div class="absolute inset-y-8 right-4 w-px bg-amber-300/60 dark:bg-amber-400/50" />
                                        <div class="absolute inset-y-8 right-8 w-px bg-amber-300/40 dark:bg-amber-400/30" />
                                        <div class="absolute inset-y-0 left-0 w-6 bg-gradient-to-r from-amber-900/10 to-transparent" />
                                    </div>
                                    
                                    <!-- Cover image that fades out (synced with modal expansion) -->
                                    <div 
                                        :class="[
                                            'absolute inset-0 transition-opacity duration-600',
                                            isCoverFading ? 'opacity-0' : 'opacity-100'
                                        ]"
                                        style="transition-duration: 550ms;"
                                    >
                                        <img
                                            v-if="props.coverImage || book?.cover_image"
                                            :src="props.coverImage || book?.cover_image || ''"
                                            class="h-full w-full object-cover"
                                            alt="Book cover"
                                        />
                                        <div
                                            v-else-if="props.bookId"
                                            class="absolute inset-0 flex items-center justify-center p-8"
                                            :class="getGradientColors(props.bookId)"
                                        >
                                            <h2 class="text-3xl md:text-4xl font-bold text-center text-white drop-shadow-lg"
                                                style="text-shadow: 0 2px 8px rgba(0,0,0,0.3)">
                                                {{ displayTitle }}
                                            </h2>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <!-- Initial state: Full Cover with Overlay -->
                        <template v-else>
                            <div class="cover-page relative h-full w-full overflow-hidden">
                                <!-- Cover Image -->
                                <div class="absolute inset-0">
                                    <img
                                        v-if="props.coverImage || book?.cover_image"
                                        :src="props.coverImage || book?.cover_image || ''"
                                        class="h-full w-full object-cover"
                                        alt="Book cover"
                                    />
                                    <div
                                        v-else-if="props.bookId"
                                        class="absolute inset-0 flex items-center justify-center p-8"
                                        :class="getGradientColors(props.bookId)"
                                    >
                                        <h2 class="text-3xl md:text-4xl font-bold text-center text-white drop-shadow-lg"
                                            style="text-shadow: 0 2px 8px rgba(0,0,0,0.3)">
                                            {{ displayTitle }}
                                        </h2>
                                    </div>
                                </div>
                                
                                <!-- Gradient Overlay for Text Legibility (bottom portion only) -->
                                <div class="absolute inset-x-0 bottom-0 h-2/5 bg-gradient-to-t from-black/90 via-black/60 to-transparent" />
                                
                                <!-- Title, Author & Begin Button Overlay -->
                                <div class="absolute inset-x-0 bottom-0 p-6 md:p-8 text-white">
                                    <h1 class="mb-2 font-serif text-2xl md:text-4xl font-bold tracking-tight drop-shadow-lg line-clamp-2">
                                        {{ displayTitle }}
                                    </h1>
                                    <p v-if="displayAuthor" class="mb-6 font-serif text-sm md:text-base italic text-white/80 drop-shadow">
                                        by {{ displayAuthor }}
                                    </p>
                                    <button 
                                        @click="openBook"
                                        :disabled="isPageTurning || loading"
                                        class="group flex cursor-pointer items-center gap-2 rounded-full bg-white/20 px-4 py-2 md:px-6 md:py-3 text-sm md:text-base font-semibold text-white backdrop-blur-sm transition-all duration-300 hover:bg-white/30 hover:scale-105 active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed"
                                    >
                                        <BookOpen class="h-4 w-4 md:h-5 md:w-5 transition-transform group-hover:scale-110" />
                                        <span>Begin Reading</span>
                                        <ChevronRight class="h-4 w-4 md:h-5 md:w-5 transition-transform group-hover:translate-x-1" />
                                    </button>
                        </div>
                            </div>
                        </template>
                    </div>

                    <!-- ==================== OPENED BOOK VIEW (Two Pages) ==================== -->
                    <div 
                        v-else 
                        class="book-opened-view relative flex h-full w-full"
                    >
                        <!-- Loading Chapter Overlay -->
                        <div 
                            v-if="isLoadingChapter || isGeneratingChapter"
                            class="absolute inset-0 z-40 flex items-center justify-center bg-amber-50/90 dark:bg-amber-100/90 backdrop-blur-sm"
                        >
                            <div class="text-center space-y-6">
                                <MagicalSparklesLoader 
                                    size="xl" 
                                    color="text-amber-500" 
                                    accent-color="text-orange-400"
                                    class="mx-auto"
                                />
                                <div>
                                    <p class="text-xl font-serif font-semibold text-amber-900 dark:text-amber-800">
                                        {{ isGeneratingChapter ? 'Crafting your story...' : 'Loading chapter...' }}
                                    </p>
                                    <p v-if="isGeneratingChapter" class="mt-2 text-sm text-amber-700 dark:text-amber-600 animate-pulse">
                                        The magic is happening ✨
                                    </p>
                                </div>
                            </div>
                    </div>

                        <!-- Left Page -->
                        <div class="relative w-1/2 h-full bg-amber-50 dark:bg-amber-100 overflow-hidden">
                            <!-- Paper texture -->
                            <div class="absolute inset-0 opacity-[0.08]" style="background-image: url('data:image/svg+xml,%3Csvg viewBox=\'0 0 200 200\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cfilter id=\'noise\'%3E%3CfeTurbulence type=\'fractalNoise\' baseFrequency=\'0.5\' numOctaves=\'2\' stitchTiles=\'stitch\'/%3E%3C/filter%3E%3Crect width=\'100%25\' height=\'100%25\' filter=\'url(%23noise)\'/%3E%3C/svg%3E');" />
                            
                            <!-- Decorative page lines -->
                            <div class="absolute inset-y-8 left-4 w-px bg-amber-300/60 dark:bg-amber-400/50" />
                            <div class="absolute inset-y-8 left-8 w-px bg-amber-300/40 dark:bg-amber-400/30" />
                            
                            <!-- Left Page Content Based on View -->
                            <template v-if="readingView === 'title'">
                                <!-- Decorative element for title page -->
                                <div class="flex h-full items-center justify-center p-12">
                                    <div class="text-center opacity-40">
                                        <div class="mx-auto mb-4 h-px w-24 bg-gradient-to-r from-transparent via-amber-600 to-transparent dark:via-amber-400" />
                                        <Sparkles class="mx-auto h-10 w-10 text-amber-500 dark:text-amber-400" />
                                        <div class="mx-auto mt-4 h-px w-24 bg-gradient-to-r from-transparent via-amber-600 to-transparent dark:via-amber-400" />
                    </div>
                                </div>
                            </template>
                            
                            <template v-else-if="(readingView === 'chapter-image' || readingView === 'chapter-content') && currentChapter && currentSpread">
                                <!-- Left page content based on spread -->
                                <div class="flex h-full flex-col">
                                    <!-- First spread: show chapter image or decorative -->
                                    <template v-if="currentSpread.showImage">
                                        <div 
                                            v-if="currentChapter.image"
                                            class="flex-1 p-6"
                                        >
                                            <img
                                                :src="currentChapter.image"
                                                :alt="currentChapter.title || `Chapter ${currentChapter.sort}`"
                                                class="h-full w-full object-contain rounded-lg shadow-md"
                                            />
                                        </div>
                                        <div v-else class="flex h-full items-center justify-center p-12">
                                            <div class="text-center opacity-40">
                                                <div class="mx-auto mb-4 h-px w-24 bg-gradient-to-r from-transparent via-amber-600 to-transparent dark:via-amber-400" />
                                                <BookOpen class="mx-auto h-12 w-12 text-amber-500 dark:text-amber-400" />
                                                <div class="mx-auto mt-4 h-px w-24 bg-gradient-to-r from-transparent via-amber-600 to-transparent dark:via-amber-400" />
                                            </div>
                                        </div>
                                    </template>
                                    <!-- Subsequent spreads: show content continuation on left page (full height) -->
                                    <template v-else-if="currentSpread.leftContent">
                                        <div class="relative h-full px-12 py-8">
                                            <div class="prose prose-amber prose-lg max-w-none text-amber-950 dark:text-amber-900">
                                                <p 
                                                    v-for="(paragraph, idx) in currentSpread.leftContent"
                                                    :key="idx"
                                                    class="mb-5 font-serif text-lg leading-relaxed"
                                                >
                                                    {{ paragraph }}
                                                </p>
                                            </div>
                                        </div>
                                    </template>
                                    <!-- Empty left page (e.g., last spread with only right content) -->
                                    <template v-else>
                                        <div class="flex h-full items-center justify-center p-12">
                                            <div class="text-center opacity-40">
                                                <div class="mx-auto mb-4 h-px w-24 bg-gradient-to-r from-transparent via-amber-600 to-transparent dark:via-amber-400" />
                                                <BookOpen class="mx-auto h-12 w-12 text-amber-500 dark:text-amber-400" />
                                                <div class="mx-auto mt-4 h-px w-24 bg-gradient-to-r from-transparent via-amber-600 to-transparent dark:via-amber-400" />
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </template>
                            
                            <template v-else-if="readingView === 'create-chapter'">
                                <!-- Decorative element for create chapter page -->
                                <div class="flex h-full items-center justify-center p-12">
                                    <div class="text-center opacity-40">
                                        <div class="mx-auto mb-4 h-px w-24 bg-gradient-to-r from-transparent via-amber-600 to-transparent dark:via-amber-400" />
                                        <Wand2 class="mx-auto h-12 w-12 text-amber-500 dark:text-amber-400" />
                                        <div class="mx-auto mt-4 h-px w-24 bg-gradient-to-r from-transparent via-amber-600 to-transparent dark:via-amber-400" />
                                    </div>
                                </div>
                            </template>
                        </div>

                        <!-- Book Spine / Center Seam - positioned above content for curved effect -->
                        <div class="pointer-events-none absolute left-1/2 top-0 bottom-0 z-30 w-24 -translate-x-1/2">
                            <!-- Wide soft shadow for page curve effect -->
                            <div class="h-full w-full bg-gradient-to-r from-transparent via-amber-950/20 to-transparent" />
                            <!-- Inner darker shadow -->
                            <div class="absolute inset-y-0 left-1/2 w-10 -translate-x-1/2 bg-gradient-to-r from-transparent via-amber-950/25 to-transparent" />
                            <!-- Core shadow at binding -->
                            <div class="absolute inset-y-0 left-1/2 w-4 -translate-x-1/2 bg-gradient-to-r from-transparent via-amber-950/30 to-transparent" />
                            <!-- Highlight line in center -->
                            <div class="absolute inset-y-0 left-1/2 w-px -translate-x-1/2 bg-gradient-to-b from-amber-200/5 via-amber-100/20 to-amber-200/5" />
                        </div>

                        <!-- Right Page (Content) -->
                        <div 
                            class="relative w-1/2 h-full bg-amber-50 dark:bg-amber-100 overflow-hidden"
                        >
                            <!-- Paper texture -->
                            <div class="absolute inset-0 opacity-[0.08] pointer-events-none" style="background-image: url('data:image/svg+xml,%3Csvg viewBox=\'0 0 200 200\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cfilter id=\'noise\'%3E%3CfeTurbulence type=\'fractalNoise\' baseFrequency=\'0.5\' numOctaves=\'2\' stitchTiles=\'stitch\'/%3E%3C/filter%3E%3Crect width=\'100%25\' height=\'100%25\' filter=\'url(%23noise)\'/%3E%3C/svg%3E');" />
                            
                            <!-- Decorative page lines -->
                            <div class="absolute inset-y-8 right-4 w-px bg-amber-300/60 dark:bg-amber-400/50" />
                            <div class="absolute inset-y-8 right-8 w-px bg-amber-300/40 dark:bg-amber-400/30" />
                            
                            <!-- Left shadow from spine -->
                            <div class="absolute inset-y-0 left-0 w-6 bg-gradient-to-r from-amber-900/10 to-transparent pointer-events-none" />

                            <!-- Action/Chapter Error -->
                            <div
                                v-if="actionError || chapterError"
                                class="mx-8 mt-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-left text-sm font-medium text-red-700 dark:border-red-900/40 dark:bg-red-900/20 dark:text-red-200"
                            >
                                {{ actionError || chapterError }}
                        </div>

                            <!-- Edit Form -->
                        <template v-if="isEditing">
                                <div class="relative z-10 p-8 pt-16">
                                    <form @submit.prevent="submitEdit" class="space-y-5">
                                    <div class="grid gap-2">
                                            <Label for="edit-title" class="text-sm font-semibold text-foreground">Story Title</Label>
                                        <Input
                                            id="edit-title"
                                            v-model="editForm.title"
                                            placeholder="Enter your story title"
                                            :disabled="isSaving || isDeleting"
                                                class="h-10 bg-white/70 dark:bg-white/5"
                                        />
                                        <InputError :message="editErrors.title" />
                                    </div>

                                    <div class="grid gap-2">
                                            <Label for="edit-genre" class="text-sm font-semibold text-foreground">Genre</Label>
                                        <Select v-model="editForm.genre" :disabled="isSaving || isDeleting">
                                                <SelectTrigger id="edit-genre" class="h-10 text-left">
                                                <SelectValue placeholder="Select a genre" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem
                                                    v-for="genre in genres"
                                                    :key="genre.value"
                                                    :value="genre.value"
                                                >
                                                    {{ genre.label }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <InputError :message="editErrors.genre" />
                                    </div>

                                    <div class="grid gap-2">
                                            <Label for="edit-age-level" class="text-sm font-semibold text-foreground">Age Level</Label>
                                        <Select v-model="editForm.age_level" :disabled="isSaving || isDeleting">
                                                <SelectTrigger id="edit-age-level" class="h-10 text-left">
                                                <SelectValue placeholder="Select age level" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem
                                                    v-for="age in ageLevels"
                                                    :key="age.value"
                                                    :value="age.value"
                                                >
                                                    {{ age.label }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <InputError :message="editErrors.age_level" />
                                    </div>

                                    <div class="grid gap-2">
                                            <Label for="edit-author" class="text-sm font-semibold text-foreground">Author</Label>
                                        <Input
                                            id="edit-author"
                                            v-model="editForm.author"
                                            placeholder="Author name"
                                            :disabled="isSaving || isDeleting"
                                                class="h-10 bg-white/70 dark:bg-white/5"
                                        />
                                        <InputError :message="editErrors.author" />
                                    </div>

                                    <div class="grid gap-2">
                                            <Label for="edit-plot" class="text-sm font-semibold text-foreground">Plot Summary</Label>
                                        <Textarea
                                            id="edit-plot"
                                            v-model="editForm.plot"
                                            placeholder="Briefly describe your story's plot..."
                                                rows="4"
                                            :disabled="isSaving || isDeleting"
                                                class="min-h-[100px] text-sm leading-relaxed"
                                        />
                                        <InputError :message="editErrors.plot" />
                                    </div>

                                    <InputError v-if="editErrors.general" :message="editErrors.general" />

                                        <div class="flex items-center justify-end gap-3 pt-2">
                                        <Button
                                            type="button"
                                            variant="outline"
                                                size="sm"
                                            @click="cancelEditing"
                                            :disabled="isSaving || isDeleting"
                                        >
                                            Cancel
                                        </Button>
                                        <Button
                                            type="submit"
                                                size="sm"
                                            :disabled="isSaving || isDeleting"
                                        >
                                            <Spinner v-if="isSaving" class="mr-2 h-4 w-4" />
                                            {{ isSaving ? 'Saving...' : 'Save Changes' }}
                                        </Button>
                                    </div>
                                </form>
                            </div>
                        </template>

                            <!-- ==================== TITLE PAGE VIEW ==================== -->
                            <template v-else-if="readingView === 'title'">
                                <div 
                                    :class="[
                                        'relative z-10 flex h-full flex-col items-center p-6 pt-8 text-center transition-opacity duration-500',
                                        isTitlePageFading ? 'opacity-0' : 'opacity-100'
                                    ]"
                                >
                                    <!-- Cover Image (full width, natural height) -->
                                    <div 
                                        v-if="props.coverImage || book?.cover_image"
                                        class="mb-6 w-full overflow-hidden rounded-lg shadow-lg"
                                    >
                                        <img
                                            :src="props.coverImage || book?.cover_image || ''"
                                            :alt="displayTitle"
                                            class="h-auto w-full object-contain"
                                        />
                                    </div>

                                    <!-- Decorative Border -->
                                    <div class="mb-4 flex items-center gap-3">
                                        <div class="h-px w-12 bg-gradient-to-r from-transparent via-amber-700 to-transparent dark:via-amber-600"></div>
                                        <Sparkles class="h-4 w-4 text-amber-700 dark:text-amber-500 animate-spin-slow" />
                                        <div class="h-px w-12 bg-gradient-to-r from-transparent via-amber-700 to-transparent dark:via-amber-600"></div>
                            </div>

                            <!-- Title -->
                                    <h1 class="mb-3 font-serif text-2xl md:text-3xl lg:text-4xl font-bold text-amber-950 dark:text-amber-900 tracking-tight">
                                {{ displayTitle }}
                            </h1>

                            <!-- Author -->
                                    <p v-if="displayAuthor" class="mb-2 font-serif text-base md:text-lg italic text-amber-800 dark:text-amber-800">
                                by {{ displayAuthor }}
                                    </p>

                            <!-- Created Date -->
                                    <p v-if="displayCreatedAt" class="font-serif text-xs text-amber-700 dark:text-amber-700">
                                {{ displayCreatedAt }}
                                    </p>

                                    <!-- Decorative Bottom Border -->
                                    <div class="mt-4 flex items-center gap-3">
                                        <div class="h-px w-12 bg-gradient-to-r from-transparent via-amber-700 to-transparent dark:via-amber-600"></div>
                                        <Sparkles class="h-4 w-4 text-amber-700 dark:text-amber-500 animate-spin-slow" style="animation-delay: 1s" />
                                        <div class="h-px w-12 bg-gradient-to-r from-transparent via-amber-700 to-transparent dark:via-amber-600"></div>
                            </div>

                                    <!-- Next Button -->
                                    <button 
                                        @click="goToChapter1"
                                        :disabled="isTitlePageFading || isLoadingChapter"
                                        class="group mt-6 flex cursor-pointer items-center gap-2 rounded-full bg-gradient-to-r from-amber-700 to-orange-700 px-5 py-2.5 text-sm font-semibold text-white shadow-lg transition-all duration-300 hover:shadow-xl hover:scale-105 active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:scale-100"
                                    >
                                        <span>Continue to Chapter 1</span>
                                        <ChevronRight class="h-5 w-5 transition-transform group-hover:translate-x-1" />
                                    </button>
                                </div>
                            </template>

                            <!-- ==================== CHAPTER VIEW (Paginated Content) ==================== -->
                            <template v-else-if="(readingView === 'chapter-image' || readingView === 'chapter-content') && currentChapter && currentSpread">
                                <div class="relative z-10 h-full overflow-hidden">
                                    <!-- First spread: Title with 40% top margin + beginning of content -->
                                    <template v-if="currentSpread.isFirstSpread">
                                        <div class="flex h-full flex-col px-12 pt-8 pb-6">
                                            <!-- 40% top margin space -->
                                            <div class="h-[40%] flex items-end justify-center pb-4">
                                                <div class="text-center">
                                                    <h2 class="font-serif text-2xl md:text-3xl font-bold text-amber-950 dark:text-amber-900">
                                                        {{ currentChapter.title || `Chapter ${currentChapter.sort}` }}
                                                    </h2>
                                                    
                                                    <!-- Decorative -->
                                                    <div class="mt-3 flex items-center justify-center gap-3">
                                                        <div class="h-px w-12 bg-gradient-to-r from-transparent via-amber-700 to-transparent dark:via-amber-600"></div>
                                                        <Sparkles class="h-3 w-3 text-amber-700 dark:text-amber-500" />
                                                        <div class="h-px w-12 bg-gradient-to-r from-transparent via-amber-700 to-transparent dark:via-amber-600"></div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Remaining ~60% for content -->
                                            <div class="flex-1 overflow-hidden">
                                                <div v-if="currentSpread.rightContent" class="prose prose-amber prose-lg max-w-none text-amber-950 dark:text-amber-900">
                                                    <p 
                                                        v-for="(paragraph, idx) in currentSpread.rightContent"
                                                        :key="idx"
                                                        :class="[
                                                            'mb-5 font-serif text-lg leading-relaxed',
                                                            idx === 0 ? 'drop-cap' : ''
                                                        ]"
                                                    >
                                                        {{ paragraph }}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                    
                                    <!-- Subsequent spreads: Content continuation on right page (full height) -->
                                    <template v-else>
                                        <div class="h-full px-12 py-8">
                                            <div v-if="currentSpread.rightContent" class="prose prose-amber prose-lg max-w-none text-amber-950 dark:text-amber-900">
                                                <p 
                                                    v-for="(paragraph, idx) in currentSpread.rightContent"
                                                    :key="idx"
                                                    class="mb-5 font-serif text-lg leading-relaxed"
                                                >
                                                    {{ paragraph }}
                                                </p>
                                            </div>
                                            <!-- If no right content on this spread (odd number of continuation pages) -->
                                            <div v-else class="flex h-full items-center justify-center">
                                                <div class="text-center opacity-40">
                                                    <Sparkles class="mx-auto h-8 w-8 text-amber-500 dark:text-amber-400" />
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </template>

                            <!-- ==================== CREATE CHAPTER VIEW ==================== -->
                            <template v-else-if="readingView === 'create-chapter'">
                                <div class="relative z-10 flex h-full flex-col p-8 pt-12 pb-6">
                                    <div class="flex-1">
                                        <!-- Header -->
                                        <div class="mb-6 text-center">
                                            <div class="mb-3">
                                                <span class="inline-block rounded-full bg-amber-200/60 px-4 py-1 text-xs font-semibold uppercase tracking-widest text-amber-800 dark:bg-amber-300/60 dark:text-amber-900">
                                                    Chapter {{ currentChapterNumber }}
                                                </span>
                    </div>
                                            <h2 class="font-serif text-2xl font-bold text-amber-950 dark:text-amber-900">
                                                What happens next?
                                            </h2>
                                            <p class="mt-2 text-sm text-amber-700 dark:text-amber-600">
                                                Describe what you'd like to happen in the next chapter (optional)
                                            </p>
                                        </div>
                                        
                                        <!-- Form -->
                                        <div class="space-y-4">
                                            <Textarea
                                                v-model="nextChapterPrompt"
                                                placeholder="The hero discovers a hidden door behind the waterfall..."
                                                rows="5"
                                                :disabled="isGeneratingChapter"
                                                class="w-full resize-none bg-white/70 dark:bg-white/10 font-serif text-amber-950 dark:text-amber-900 placeholder:text-amber-500"
                                            />
                                            
                                            <div class="flex items-center gap-2">
                                                <input
                                                    type="checkbox"
                                                    id="final-chapter"
                                                    v-model="isFinalChapter"
                                                    :disabled="isGeneratingChapter"
                                                    class="h-4 w-4 rounded border-amber-300 text-amber-700 focus:ring-amber-500"
                                                />
                                                <label for="final-chapter" class="text-sm text-amber-800 dark:text-amber-700">
                                                    This is the final chapter
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Actions -->
                                    <div class="flex items-center justify-between border-t border-amber-200 pt-6 dark:border-amber-300">
                                        <button 
                                            @click="goToPreviousChapter"
                                            :disabled="isGeneratingChapter"
                                            class="flex cursor-pointer items-center gap-1 text-sm text-amber-700 hover:text-amber-900 transition-colors dark:text-amber-600 dark:hover:text-amber-800 disabled:opacity-50"
                                        >
                                            <ChevronLeft class="h-4 w-4" />
                                            <span>Back</span>
                                        </button>
                                        
                                        <button 
                                            @click="generateNextChapter"
                                            :disabled="isGeneratingChapter"
                                            class="group flex cursor-pointer items-center gap-2 rounded-full bg-gradient-to-r from-amber-700 to-orange-700 px-5 py-2.5 text-sm font-semibold text-white shadow-lg transition-all duration-300 hover:shadow-xl hover:scale-105 active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed"
                                        >
                                            <Wand2 class="h-4 w-4" />
                                            <span>{{ isGeneratingChapter ? 'Creating...' : 'Create Chapter' }}</span>
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <!-- ==================== GLOBAL FOOTER NAVIGATION (Spans Both Pages) ==================== -->
                        <div 
                            v-if="(readingView === 'chapter-image' || readingView === 'chapter-content') && currentChapter && currentSpread"
                            class="absolute bottom-0 left-0 right-0 z-40 flex items-center justify-between border-t border-amber-300/50 bg-amber-50/95 px-8 py-4 backdrop-blur-sm dark:border-amber-400/30 dark:bg-amber-100/95"
                        >
                            <button 
                                @click.stop="goToPreviousChapter"
                                class="flex cursor-pointer items-center gap-2 text-base text-amber-700 hover:text-amber-900 transition-colors dark:text-amber-600 dark:hover:text-amber-800"
                            >
                                <ChevronLeft class="h-5 w-5" />
                                <span>{{ hasPrevSpread ? 'Previous' : (currentChapterNumber > 1 ? 'Prev Chapter' : 'Title Page') }}</span>
                            </button>
                            
                            <!-- Page indicator -->
                            <span class="text-sm text-amber-600 dark:text-amber-500">
                                {{ currentSpreadIndex + 1 }} / {{ totalSpreads }}
                            </span>
                            
                            <button 
                                @click="goToNextChapter"
                                :disabled="isLoadingChapter"
                                class="group flex cursor-pointer items-center gap-2 rounded-full bg-gradient-to-r from-amber-700 to-orange-700 px-5 py-2.5 text-sm font-semibold text-white shadow-md transition-all duration-300 hover:shadow-lg hover:scale-105 active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <span>{{ !hasNextSpread ? (currentChapter.final_chapter ? 'The End' : (currentChapterNumber < totalChapters ? 'Next Chapter' : 'Continue Story')) : 'Next' }}</span>
                                <ChevronRight v-if="hasNextSpread || !currentChapter.final_chapter" class="h-5 w-5 transition-transform group-hover:translate-x-1" />
                            </button>
                        </div>
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

/* Cover page turning animation */
.cover-page {
    transform-style: preserve-3d;
    transition: transform 0.8s cubic-bezier(0.645, 0.045, 0.355, 1);
    box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
}

.cover-page.turning {
    transform: perspective(2000px) rotateY(-180deg);
}

/* Shadow that appears during page turn */
.page-shadow {
    opacity: 0;
    transition: opacity 0.3s ease;
}

.cover-page.turning .page-shadow {
    opacity: 1;
}

/* Book opened animation */
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

@keyframes sparkleFloat {
    0%, 100% {
        opacity: 0;
        transform: translateY(0) scale(0);
    }
    50% {
        opacity: 1;
        transform: translateY(-20px) scale(1);
    }
}

@keyframes spinSlow {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

.sparkle {
    position: absolute;
    width: 8px;
    height: 8px;
    background: radial-gradient(circle, #fbbf24, transparent);
    border-radius: 50%;
    animation: sparkleFloat 3s ease-in-out infinite;
    box-shadow: 0 0 10px #fbbf24;
}

.animate-spin-slow {
    animation: spinSlow 8s linear infinite;
}

@keyframes bounce-in {
    0% {
        opacity: 0;
        transform: scale(0.3) translateY(-20px);
    }
    50% {
        opacity: 1;
        transform: scale(1.05) translateY(0);
    }
    70% {
        transform: scale(0.95);
    }
    100% {
        transform: scale(1);
    }
}

.animate-bounce-in {
    animation: bounce-in 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
}

/* Dark mode adjustments */
:is(.dark .sparkle) {
    background: radial-gradient(circle, #fcd34d, transparent);
    box-shadow: 0 0 10px #fcd34d;
}

/* Page flip animation */
.page-flip-enter-active,
.page-flip-leave-active {
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

.page-flip-enter-from {
    opacity: 0;
    transform: translateX(20px);
}

.page-flip-leave-to {
    opacity: 0;
    transform: translateX(-20px);
}

/* Drop cap styling - only applied via specific class */
.drop-cap::first-letter {
    font-size: 3.5rem;
    font-weight: 700;
    float: left;
    margin-right: 0.5rem;
    line-height: 0.8;
    color: inherit;
}
</style>

