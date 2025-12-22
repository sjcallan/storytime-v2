<script setup lang="ts">
import BookViewModal from '@/components/BookViewModal.vue';
import { Button } from '@/components/ui/button';
import { useCreateStoryModal } from '@/composables/useCreateStoryModal';
import AppLayout from '@/layouts/AppLayout.vue';
import type { User } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import { echo } from '@laravel/echo-vue';
import { BookOpen, ChevronLeft, ChevronRight, Clock, Heart, Plus } from 'lucide-vue-next';
import {
    computed,
    nextTick,
    onMounted,
    onUnmounted,
    ref,
    toRefs,
    watch,
} from 'vue';

type RecentlyReadBook = {
    id: string;
    title: string;
    genre: string;
    author: string | null;
    age_level: number | null;
    status: string;
    cover_image?: string | null;
    current_chapter_number: number;
    last_read_at: string;
};

type FavoriteBook = {
    id: string;
    title: string;
    genre: string;
    author: string | null;
    age_level: number | null;
    status: string;
    cover_image?: string | null;
    current_chapter_number: number;
    last_read_at: string;
};

const props = defineProps<{
    booksByGenre: Record<
        string,
        Array<{
            id: string;
            title: string;
            genre: string;
            author: string | null;
            age_level: number | null;
            status: string;
            cover_image?: string;
            created_at?: string;
        }>
    >;
    recentlyRead: RecentlyReadBook[];
    favorites: FavoriteBook[];
    userName: string;
}>();

const { booksByGenre, recentlyRead, favorites } = toRefs(props);

// Get current user for channel subscription
const page = usePage();
const currentUser = computed(() => page.props.auth?.user as User | null);

type BookSummary = {
    id: string;
    title: string;
    genre: string;
    author: string | null;
    age_level: number | null;
    status: string;
    cover_image?: string | null;
    created_at?: string;
};

type BookDetails = BookSummary & {
    plot: string | null;
    created_at: string;
};

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

type BookCreatedPayload = {
    id: string;
    title: string | null;
    genre: string;
    author: string | null;
    age_level: number | null;
    status: string;
    cover_image: string | null;
    plot: string | null;
    user_id: string;
    created_at: string;
};

const cloneBooksByGenre = (
    source: Record<string, BookSummary[]>,
): Record<string, BookSummary[]> => {
    return Object.entries(source).reduce(
        (acc, [genre, books]) => {
            // Clone and sort by created_at desc
            acc[genre] = books
                .map((book) => ({ ...book }))
                .sort((a, b) => {
                    const dateA = a.created_at
                        ? new Date(a.created_at).getTime()
                        : 0;
                    const dateB = b.created_at
                        ? new Date(b.created_at).getTime()
                        : 0;
                    return dateB - dateA;
                });
            return acc;
        },
        {} as Record<string, BookSummary[]>,
    );
};

const booksByGenreState = ref<Record<string, BookSummary[]>>(
    cloneBooksByGenre(booksByGenre.value),
);

const recentlyReadState = ref<RecentlyReadBook[]>([...recentlyRead.value]);

const favoritesState = ref<FavoriteBook[]>([...favorites.value]);

watch(booksByGenre, (newValue) => {
    booksByGenreState.value = cloneBooksByGenre(newValue);
    nextTick(() => updateAllScrollStates());
});

watch(recentlyRead, (newValue) => {
    recentlyReadState.value = [...newValue];
    nextTick(() => updateAllScrollStates());
});

watch(favorites, (newValue) => {
    favoritesState.value = [...newValue];
    nextTick(() => updateAllScrollStates());
});

const hasBooks = computed(() =>
    Object.values(booksByGenreState.value).some((list) => list.length > 0),
);

// Sort genres alphabetically
const sortedBooksByGenre = computed(() => {
    return Object.entries(booksByGenreState.value).sort(([a], [b]) =>
        a.localeCompare(b),
    );
});

// Carousel functionality - use a non-reactive Map to avoid recursive updates
const carouselRefs = new Map<string, HTMLElement>();
const jumpBackInCarouselRef = ref<HTMLElement | null>(null);
const favoritesCarouselRef = ref<HTMLElement | null>(null);
const scrollStates = ref<
    Record<string, { canScrollLeft: boolean; canScrollRight: boolean }>
>({});
const jumpBackInScrollState = ref<{
    canScrollLeft: boolean;
    canScrollRight: boolean;
}>({ canScrollLeft: false, canScrollRight: false });
const favoritesScrollState = ref<{
    canScrollLeft: boolean;
    canScrollRight: boolean;
}>({ canScrollLeft: false, canScrollRight: false });

const setCarouselRef = (genre: string, el: HTMLElement | null) => {
    if (el) {
        if (carouselRefs.get(genre) !== el) {
            carouselRefs.set(genre, el);
            nextTick(() => updateScrollState(genre));
        }
    } else {
        carouselRefs.delete(genre);
    }
};

const updateScrollState = (genre: string) => {
    const container = carouselRefs.get(genre);
    if (!container) {
        return;
    }

    const { scrollLeft, scrollWidth, clientWidth } = container;
    const newState = {
        canScrollLeft: scrollLeft > 1,
        canScrollRight: scrollLeft < scrollWidth - clientWidth - 1,
    };

    // Only update if values actually changed to prevent unnecessary re-renders
    const current = scrollStates.value[genre];
    if (
        !current ||
        current.canScrollLeft !== newState.canScrollLeft ||
        current.canScrollRight !== newState.canScrollRight
    ) {
        scrollStates.value[genre] = newState;
    }
};

const updateAllScrollStates = () => {
    carouselRefs.forEach((_, genre) => {
        updateScrollState(genre);
    });
    updateJumpBackInScrollState();
    updateFavoritesScrollState();
};

const scrollCarousel = (genre: string, direction: 'left' | 'right') => {
    const container = carouselRefs.get(genre);
    if (!container) {
        return;
    }

    const cardWidth = 240; // Approximate card width including gap
    const scrollAmount = cardWidth * 3; // Scroll 3 cards at a time
    const targetScroll =
        direction === 'left'
            ? container.scrollLeft - scrollAmount
            : container.scrollLeft + scrollAmount;

    container.scrollTo({
        left: targetScroll,
        behavior: 'smooth',
    });
};

const handleScroll = (genre: string) => {
    updateScrollState(genre);
};

const updateJumpBackInScrollState = () => {
    const container = jumpBackInCarouselRef.value;
    if (!container) {
        return;
    }

    const { scrollLeft, scrollWidth, clientWidth } = container;
    jumpBackInScrollState.value = {
        canScrollLeft: scrollLeft > 1,
        canScrollRight: scrollLeft < scrollWidth - clientWidth - 1,
    };
};

const scrollJumpBackInCarousel = (direction: 'left' | 'right') => {
    const container = jumpBackInCarouselRef.value;
    if (!container) {
        return;
    }

    const cardWidth = 280; // Approximate card width including gap
    const scrollAmount = cardWidth * 2; // Scroll 2 cards at a time
    const targetScroll =
        direction === 'left'
            ? container.scrollLeft - scrollAmount
            : container.scrollLeft + scrollAmount;

    container.scrollTo({
        left: targetScroll,
        behavior: 'smooth',
    });
};

const handleJumpBackInScroll = () => {
    updateJumpBackInScrollState();
};

const updateFavoritesScrollState = () => {
    const container = favoritesCarouselRef.value;
    if (!container) {
        return;
    }

    const { scrollLeft, scrollWidth, clientWidth } = container;
    favoritesScrollState.value = {
        canScrollLeft: scrollLeft > 1,
        canScrollRight: scrollLeft < scrollWidth - clientWidth - 1,
    };
};

const scrollFavoritesCarousel = (direction: 'left' | 'right') => {
    const container = favoritesCarouselRef.value;
    if (!container) {
        return;
    }

    const cardWidth = 240; // Approximate card width including gap
    const scrollAmount = cardWidth * 3; // Scroll 3 cards at a time
    const targetScroll =
        direction === 'left'
            ? container.scrollLeft - scrollAmount
            : container.scrollLeft + scrollAmount;

    container.scrollTo({
        left: targetScroll,
        behavior: 'smooth',
    });
};

const handleFavoritesScroll = () => {
    updateFavoritesScrollState();
};

const formatRelativeTime = (dateString: string) => {
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffMins = Math.floor(diffMs / (1000 * 60));
    const diffHours = Math.floor(diffMs / (1000 * 60 * 60));
    const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));

    if (diffMins < 1) {
        return 'Just now';
    }
    if (diffMins < 60) {
        return `${diffMins}m ago`;
    }
    if (diffHours < 24) {
        return `${diffHours}h ago`;
    }
    if (diffDays === 1) {
        return 'Yesterday';
    }
    if (diffDays < 7) {
        return `${diffDays}d ago`;
    }
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
};

onMounted(() => {
    window.addEventListener('resize', updateAllScrollStates);
    nextTick(() => updateAllScrollStates());
    subscribeToAllBooks();
    subscribeToUserBooksChannel();
});

onUnmounted(() => {
    window.removeEventListener('resize', updateAllScrollStates);
    cleanupAllSubscriptions();
});

// Use shared composable for modal state
const {
    open: openCreateStoryModal,
    createdBookId,
    setCreatedBook,
} = useCreateStoryModal();

// Echo channel subscriptions for real-time book updates
 
const bookChannels = ref<Map<string, any>>(new Map());
 
const userBooksChannel = ref<any>(null);

// Handle book created events from user channel
const handleBookCreatedEvent = (payload: BookCreatedPayload) => {
    // Check if book already exists in state (avoid duplicates)
    const existingLocation = findBookLocation(payload.id);
    if (existingLocation) {
        return;
    }

    // Add the new book to the appropriate genre
    const targetGenre = payload.genre;
    if (!booksByGenreState.value[targetGenre]) {
        booksByGenreState.value[targetGenre] = [];
    }

    booksByGenreState.value[targetGenre].unshift({
        id: payload.id,
        title: payload.title ?? 'Untitled Story',
        genre: payload.genre,
        author: payload.author,
        age_level: payload.age_level,
        status: payload.status,
        cover_image: payload.cover_image ?? undefined,
        created_at: payload.created_at,
    });

    // Subscribe to the new book's channel for future updates
    subscribeToBook(payload.id);

    nextTick(() => updateAllScrollStates());
};

// Handle book update events from Echo
const handleBookUpdatedEvent = (payload: BookUpdatedPayload) => {
    const location = findBookLocation(payload.id);

    if (location) {
        const { genre, index } = location;
        const existingBook = booksByGenreState.value[genre][index];

        // Check if genre changed
        if (payload.genre !== genre) {
            // Remove from old genre
            booksByGenreState.value[genre].splice(index, 1);
            if (booksByGenreState.value[genre].length === 0) {
                delete booksByGenreState.value[genre];
            }

            // Add to new genre
            if (!booksByGenreState.value[payload.genre]) {
                booksByGenreState.value[payload.genre] = [];
            }
            booksByGenreState.value[payload.genre].unshift({
                ...existingBook,
                title: payload.title ?? existingBook.title,
                genre: payload.genre,
                author: payload.author,
                age_level: payload.age_level,
                status: payload.status,
                cover_image: payload.cover_image ?? undefined,
            });
        } else {
            // Update in place
            booksByGenreState.value[genre][index] = {
                ...existingBook,
                title: payload.title ?? existingBook.title,
                genre: payload.genre,
                author: payload.author,
                age_level: payload.age_level,
                status: payload.status,
                cover_image: payload.cover_image ?? undefined,
            };
        }

        nextTick(() => updateAllScrollStates());
    }
};

// Subscribe to a book's private channel for updates
const subscribeToBook = (bookId: string) => {
    if (bookChannels.value.has(bookId)) {
        return;
    }

    try {
        const channel = echo().private(`book.${bookId}`);
        channel.listen('.book.updated', handleBookUpdatedEvent);
        bookChannels.value.set(bookId, channel);
    } catch (err) {
        console.error(`[Echo] Failed to subscribe to book.${bookId}:`, err);
    }
};

// Unsubscribe from a book's channel
const unsubscribeFromBook = (bookId: string) => {
    const channel = bookChannels.value.get(bookId);
    if (channel) {
        try {
            channel.stopListening('.book.updated');
            echo().leave(`book.${bookId}`);
        } catch {
            // Ignore cleanup errors
        }
        bookChannels.value.delete(bookId);
    }
};

// Subscribe to all current books
const subscribeToAllBooks = () => {
    Object.values(booksByGenreState.value)
        .flat()
        .forEach((book) => subscribeToBook(book.id));
};

// Subscribe to user's books channel for new book notifications
const subscribeToUserBooksChannel = () => {
    const userId = currentUser.value?.id;
    if (!userId || userBooksChannel.value) {
        return;
    }

    try {
        const channel = echo().private(`user.${userId}.books`);

        // Listen for new books created
        channel.listen('.book.created', handleBookCreatedEvent);

        // Also listen for updates on the user channel (for books not yet subscribed individually)
        channel.listen('.book.updated', (payload: BookUpdatedPayload) => {
            // Check if book exists, if not add it, otherwise update it
            const location = findBookLocation(payload.id);
            if (!location) {
                // Book doesn't exist yet, treat as new book
                handleBookCreatedEvent({
                    id: payload.id,
                    title: payload.title,
                    genre: payload.genre,
                    author: payload.author,
                    age_level: payload.age_level,
                    status: payload.status,
                    cover_image: payload.cover_image,
                    plot: payload.plot,
                    user_id: '', // Not needed for adding
                    created_at: payload.updated_at,
                });
            } else {
                // Book exists, update it
                handleBookUpdatedEvent(payload);
            }
        });

        userBooksChannel.value = channel;
    } catch (err) {
        console.error('[Echo] Failed to subscribe to user books channel:', err);
    }
};

// Cleanup user books channel subscription
const cleanupUserBooksChannel = () => {
    if (userBooksChannel.value) {
        try {
            userBooksChannel.value.stopListening('.book.created');
            userBooksChannel.value.stopListening('.book.updated');
            const userId = currentUser.value?.id;
            if (userId) {
                echo().leave(`user.${userId}.books`);
            }
        } catch {
            // Ignore cleanup errors
        }
        userBooksChannel.value = null;
    }
};

// Cleanup all subscriptions
const cleanupAllSubscriptions = () => {
    bookChannels.value.forEach((_, bookId) => {
        unsubscribeFromBook(bookId);
    });
    cleanupUserBooksChannel();
};

// Watch for books changes to update subscriptions
watch(
    booksByGenreState,
    (newState) => {
        const currentBookIds = new Set(
            Object.values(newState)
                .flat()
                .map((book) => book.id),
        );

        // Subscribe to new books
        currentBookIds.forEach((bookId) => {
            if (!bookChannels.value.has(bookId)) {
                subscribeToBook(bookId);
            }
        });

        // Unsubscribe from removed books
        bookChannels.value.forEach((_, bookId) => {
            if (!currentBookIds.has(bookId)) {
                unsubscribeFromBook(bookId);
            }
        });
    },
    { deep: true },
);

// Watch for newly created books from CreateStoryModal and open the book view modal
watch(createdBookId, async (newBookId) => {
    if (newBookId) {
        // Wait a moment for Echo updates to propagate
        await nextTick();

        // Find the book in the current state
        let foundBook: BookSummary | null = null;

        // Try to find the book, with a few retries in case Echo hasn't updated yet
        for (let attempt = 0; attempt < 5; attempt++) {
            for (const [, books] of Object.entries(booksByGenreState.value)) {
                const book = books.find((b) => b.id === newBookId);
                if (book) {
                    foundBook = book;
                    break;
                }
            }

            if (foundBook) {
                break;
            }

            // Wait 200ms before next attempt
            await new Promise((resolve) => setTimeout(resolve, 200));
        }

        // Set up the book view modal state with whatever info we have
        selectedBookId.value = newBookId;
        selectedCoverImage.value = foundBook?.cover_image ?? null;
        selectedBookTitle.value = foundBook?.title ?? 'Your New Story';
        selectedBookAuthor.value = foundBook?.author ?? null;

        // For animation, we need a card position but since the book card
        // might not be visible yet (or might be in carousel), we'll use
        // center of screen as fallback
        const centerX = window.innerWidth / 2 - 100;
        const centerY = window.innerHeight / 2 - 133;

        cardPosition.value = {
            top: centerY,
            left: centerX,
            width: 200,
            height: 267, // 3:4 aspect ratio
        };

        // Open the modal
        isBookViewOpen.value = true;

        // Reset the created book ID
        setCreatedBook(null);
    }
});

const isBookViewOpen = ref(false);
const selectedBookId = ref<string | null>(null);
const cardPosition = ref<{
    top: number;
    left: number;
    width: number;
    height: number;
} | null>(null);
const selectedCoverImage = ref<string | null>(null);

const selectedBookTitle = ref<string | null>(null);
const selectedBookAuthor = ref<string | null>(null);

type GradientOption = {
    fromClass: string;
    toClass: string;
    fromHex: string;
    toHex: string;
};

const gradientOptions: GradientOption[] = [
    {
        fromClass: 'from-violet-600',
        toClass: 'to-violet-300',
        fromHex: '#7c3aed',
        toHex: '#c4b5fd',
    },
    {
        fromClass: 'from-blue-600',
        toClass: 'to-blue-300',
        fromHex: '#2563eb',
        toHex: '#93c5fd',
    },
    {
        fromClass: 'from-emerald-600',
        toClass: 'to-emerald-300',
        fromHex: '#059669',
        toHex: '#6ee7b7',
    },
    {
        fromClass: 'from-amber-600',
        toClass: 'to-amber-300',
        fromHex: '#d97706',
        toHex: '#fcd34d',
    },
    {
        fromClass: 'from-rose-600',
        toClass: 'to-rose-300',
        fromHex: '#e11d48',
        toHex: '#fda4af',
    },
    {
        fromClass: 'from-pink-600',
        toClass: 'to-pink-300',
        fromHex: '#db2777',
        toHex: '#f9a8d4',
    },
    {
        fromClass: 'from-indigo-600',
        toClass: 'to-indigo-300',
        fromHex: '#4f46e5',
        toHex: '#a5b4fc',
    },
    {
        fromClass: 'from-cyan-600',
        toClass: 'to-cyan-300',
        fromHex: '#0891b2',
        toHex: '#67e8f9',
    },
    {
        fromClass: 'from-orange-600',
        toClass: 'to-orange-300',
        fromHex: '#ea580c',
        toHex: '#fdba74',
    },
    {
        fromClass: 'from-teal-600',
        toClass: 'to-teal-300',
        fromHex: '#0d9488',
        toHex: '#5eead4',
    },
];

const resolveGradientOption = (key: string) => {
    const hash = key
        .split('')
        .reduce((acc, char) => acc + char.charCodeAt(0), 0);

    return gradientOptions[hash % gradientOptions.length];
};

const clampColorValue = (value: number) => Math.max(0, Math.min(255, value));

const hexToRgb = (hex: string) => {
    const sanitized = hex.replace('#', '');
    const bigint = parseInt(sanitized, 16);

    const r = (bigint >> 16) & 255;
    const g = (bigint >> 8) & 255;
    const b = bigint & 255;

    return { r, g, b };
};

const hexToRgba = (hex: string, alpha: number) => {
    const { r, g, b } = hexToRgb(hex);

    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
};

const adjustColor = (hex: string, amount: number) => {
    const { r, g, b } = hexToRgb(hex);

    const adjustedR = clampColorValue(r + amount);
    const adjustedG = clampColorValue(g + amount);
    const adjustedB = clampColorValue(b + amount);

    return `#${[adjustedR, adjustedG, adjustedB]
        .map((component) => component.toString(16).padStart(2, '0'))
        .join('')}`;
};

const getCardVisualStyles = (key: string) => {
    const option = resolveGradientOption(key);

    return {
        '--card-gradient-from': option.fromHex,
        '--card-gradient-to': option.toHex,
        '--card-overlay-dark': hexToRgba(adjustColor(option.fromHex, -30), 0.8),
        '--card-overlay-mid': hexToRgba(adjustColor(option.toHex, -10), 0.5),
        '--card-shadow-color': hexToRgba(option.fromHex, 0.35),
    } as Record<string, string>;
};

const openBook = (
    bookId: string,
    coverImage: string | null,
    title: string,
    author: string | null,
    event: MouseEvent,
) => {
    const target = event.currentTarget as HTMLElement;
    const rect = target.getBoundingClientRect();

    cardPosition.value = {
        top: rect.top,
        left: rect.left,
        width: rect.width,
        height: rect.height,
    };

    selectedBookId.value = bookId;
    selectedCoverImage.value = coverImage;
    selectedBookTitle.value = title;
    selectedBookAuthor.value = author;
    isBookViewOpen.value = true;
};

const findBookLocation = (bookId: string) => {
    for (const [genre, list] of Object.entries(booksByGenreState.value)) {
        const index = list.findIndex((book) => book.id === bookId);
        if (index !== -1) {
            return { genre, index };
        }
    }

    return null;
};

const upsertBookSummary = (updatedBook: BookDetails) => {
    const summary: BookSummary = {
        id: updatedBook.id,
        title: updatedBook.title,
        genre: updatedBook.genre,
        author: updatedBook.author,
        age_level: updatedBook.age_level,
        status: updatedBook.status,
        cover_image: updatedBook.cover_image ?? null,
    };

    const targetGenre = summary.genre;
    const location = findBookLocation(updatedBook.id);

    if (location) {
        const { genre, index } = location;
        if (genre === targetGenre) {
            booksByGenreState.value[genre][index] = {
                ...booksByGenreState.value[genre][index],
                ...summary,
            };
        } else {
            const [existing] = booksByGenreState.value[genre].splice(index, 1);
            if (booksByGenreState.value[genre].length === 0) {
                delete booksByGenreState.value[genre];
            }

            if (!booksByGenreState.value[targetGenre]) {
                booksByGenreState.value[targetGenre] = [];
            }

            booksByGenreState.value[targetGenre].unshift({
                ...existing,
                ...summary,
            });
        }
    } else {
        if (!booksByGenreState.value[targetGenre]) {
            booksByGenreState.value[targetGenre] = [];
        }

        booksByGenreState.value[targetGenre].unshift(summary);
    }
};

const handleBookUpdated = (updatedBook: BookDetails) => {
    upsertBookSummary(updatedBook);
    selectedBookTitle.value = updatedBook.title ?? 'Untitled Story';
    selectedBookAuthor.value = updatedBook.author;
    selectedCoverImage.value = updatedBook.cover_image ?? null;
};

const handleBookDeleted = (bookId: string) => {
    const location = findBookLocation(bookId);

    if (location) {
        const { genre, index } = location;
        booksByGenreState.value[genre].splice(index, 1);

        if (booksByGenreState.value[genre].length === 0) {
            delete booksByGenreState.value[genre];
        }
    }

    // Also remove from recently read
    const recentIndex = recentlyReadState.value.findIndex(
        (b) => b.id === bookId,
    );
    if (recentIndex !== -1) {
        recentlyReadState.value.splice(recentIndex, 1);
    }

    selectedBookId.value = null;
    selectedCoverImage.value = null;
    selectedBookTitle.value = null;
    selectedBookAuthor.value = null;
    cardPosition.value = null;
    isBookViewOpen.value = false;
};

type ReadingHistoryPayload = {
    id: string;
    book_id: string;
    current_chapter_number: number;
    last_read_at: string;
    book?: {
        id: string;
        title: string;
        genre: string;
        author: string | null;
        age_level: number | null;
        status: string;
        cover_image: string | null;
    } | null;
};

const handleReadingHistoryUpdated = (history: ReadingHistoryPayload) => {
    const bookId = history.book_id;

    // Find the book data - either from the history payload or from our existing state
    let bookData = history.book;

    if (!bookData) {
        // Try to find book in our existing state
        const location = findBookLocation(bookId);
        if (location) {
            const existingBook = booksByGenreState.value[location.genre][location.index];
            bookData = {
                id: existingBook.id,
                title: existingBook.title,
                genre: existingBook.genre,
                author: existingBook.author,
                age_level: existingBook.age_level,
                status: existingBook.status,
                cover_image: existingBook.cover_image ?? null,
            };
        }
    }

    if (!bookData) {
        // Can't update without book data
        return;
    }

    // Create the recently read entry
    const recentEntry: RecentlyReadBook = {
        id: bookData.id,
        title: bookData.title,
        genre: bookData.genre,
        author: bookData.author,
        age_level: bookData.age_level,
        status: bookData.status,
        cover_image: bookData.cover_image,
        current_chapter_number: history.current_chapter_number,
        last_read_at: history.last_read_at,
    };

    // Find existing entry in recently read
    const existingIndex = recentlyReadState.value.findIndex(
        (b) => b.id === bookId,
    );

    if (existingIndex !== -1) {
        // Update and move to front
        recentlyReadState.value.splice(existingIndex, 1);
    }

    // Add to front of list
    recentlyReadState.value.unshift(recentEntry);

    // Keep only the 10 most recent
    if (recentlyReadState.value.length > 10) {
        recentlyReadState.value = recentlyReadState.value.slice(0, 10);
    }

    nextTick(() => updateJumpBackInScrollState());
};

type FavoriteToggledPayload = {
    bookId: string;
    isFavorite: boolean;
};

const handleFavoriteToggled = (payload: FavoriteToggledPayload) => {
    const { bookId, isFavorite: isNowFavorite } = payload;

    if (isNowFavorite) {
        // Book was added to favorites
        // Find book data from existing state
        let bookData = null;

        // Try to find from books by genre
        const location = findBookLocation(bookId);
        if (location) {
            const existingBook = booksByGenreState.value[location.genre][location.index];
            bookData = {
                id: existingBook.id,
                title: existingBook.title,
                genre: existingBook.genre,
                author: existingBook.author,
                age_level: existingBook.age_level,
                status: existingBook.status,
                cover_image: existingBook.cover_image ?? null,
            };
        }

        // Try to find from recently read if not found
        if (!bookData) {
            const recentBook = recentlyReadState.value.find((b) => b.id === bookId);
            if (recentBook) {
                bookData = {
                    id: recentBook.id,
                    title: recentBook.title,
                    genre: recentBook.genre,
                    author: recentBook.author,
                    age_level: recentBook.age_level,
                    status: recentBook.status,
                    cover_image: recentBook.cover_image ?? null,
                };
            }
        }

        if (bookData) {
            // Get reading history info if available
            const recentEntry = recentlyReadState.value.find((b) => b.id === bookId);

            const favoriteEntry: FavoriteBook = {
                id: bookData.id,
                title: bookData.title,
                genre: bookData.genre,
                author: bookData.author,
                age_level: bookData.age_level,
                status: bookData.status,
                cover_image: bookData.cover_image,
                current_chapter_number: recentEntry?.current_chapter_number ?? 1,
                last_read_at: recentEntry?.last_read_at ?? new Date().toISOString(),
            };

            // Check if already in favorites
            const existingIndex = favoritesState.value.findIndex((b) => b.id === bookId);
            if (existingIndex === -1) {
                // Add to front of favorites
                favoritesState.value.unshift(favoriteEntry);
            }
        }
    } else {
        // Book was removed from favorites
        const existingIndex = favoritesState.value.findIndex((b) => b.id === bookId);
        if (existingIndex !== -1) {
            favoritesState.value.splice(existingIndex, 1);
        }
    }

    nextTick(() => updateFavoritesScrollState());
};

// Generate a random color gradient for books without cover images
const getGradientColors = (bookId: string) => {
    // Use book ID to consistently pick the same gradient
    const colorSet = resolveGradientOption(bookId);

    return `bg-gradient-to-br ${colorSet.fromClass} ${colorSet.toClass}`;
};

// Format genre name for display
const formatGenreName = (genre: string) => {
    return genre
        .split('_')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
};
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout>
        <div class="flex h-full flex-1 flex-col gap-12 overflow-x-auto p-6">
            <!-- Header -->
            <div>
                <h1 class="text-3xl font-bold tracking-tight">My Stories</h1>
            </div>
            <!-- Empty State -->
            <div
                v-if="!hasBooks"
                class="flex flex-col items-center justify-center gap-4 rounded-xl border border-dashed border-sidebar-border/70 p-12 dark:border-sidebar-border"
            >
                <div class="text-center text-muted-foreground">
                    <h3 class="mb-2 text-lg font-semibold">No Stories Yet</h3>
                    <p class="mb-4">
                        Start creating your first story to see it here!
                    </p>
                    <Button
                        @click="openCreateStoryModal()"
                        variant="outline"
                        class="cursor-pointer"
                    >
                        <Plus class="mr-2 h-4 w-4" />
                        Create Your First Story
                    </Button>
                </div>
            </div>

            <!-- Jump Back In Section -->
            <div v-if="recentlyReadState.length > 0" class="space-y-4">
                <!-- Section Header -->
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-linear-to-br from-amber-500 to-orange-600 shadow-lg shadow-amber-500/20">
                        <BookOpen class="h-5 w-5 text-white" />
                    </div>
                    <div>
                        <h2 class="text-2xl font-medium tracking-tight">Jump Back In</h2>
                        <p class="text-sm text-muted-foreground">Continue where you left off</p>
                    </div>
                </div>

                <!-- Recently Read Carousel -->
                <div class="group/carousel relative">
                    <!-- Left Navigation Arrow -->
                    <button
                        v-show="jumpBackInScrollState.canScrollLeft"
                        @click="scrollJumpBackInCarousel('left')"
                        class="absolute top-1/2 -left-2 z-10 flex h-10 w-10 -translate-y-1/2 cursor-pointer items-center justify-center rounded-full border border-sidebar-border/70 bg-background/95 shadow-lg backdrop-blur-sm transition-all duration-200 hover:scale-110 hover:bg-background dark:border-sidebar-border"
                        aria-label="Scroll left"
                    >
                        <ChevronLeft class="h-5 w-5 text-foreground" />
                    </button>

                    <!-- Carousel Container -->
                    <div
                        ref="jumpBackInCarouselRef"
                        @scroll="handleJumpBackInScroll"
                        class="scrollbar-none flex gap-4 overflow-x-auto scroll-smooth pb-4"
                        style="scrollbar-width: none; -ms-overflow-style: none"
                    >
                        <div
                            v-for="book in recentlyReadState"
                            :key="`recent-${book.id}`"
                            :style="getCardVisualStyles(book.id)"
                            @click="
                                openBook(
                                    book.id,
                                    book.cover_image || null,
                                    book.title,
                                    book.author || null,
                                    $event,
                                )
                            "
                            class="group relative flex w-[280px] shrink-0 cursor-pointer gap-4 overflow-hidden rounded-2xl border border-sidebar-border/70 bg-card p-3 transition-all duration-300 hover:-translate-y-1 hover:[box-shadow:0_24px_48px_-18px_var(--card-shadow-color)] dark:border-sidebar-border"
                        >
                            <!-- Book Cover Thumbnail -->
                            <div class="relative h-24 w-18 shrink-0 overflow-hidden rounded-lg">
                                <template v-if="book.cover_image">
                                    <img
                                        :src="book.cover_image"
                                        :alt="book.title"
                                        class="h-full w-full object-cover transition-all duration-300 group-hover:scale-110"
                                    />
                                </template>
                                <template v-else>
                                    <div
                                        class="flex h-full w-full items-center justify-center p-2"
                                        :class="getGradientColors(book.id)"
                                    >
                                        <span class="text-center text-xs font-bold text-white leading-tight line-clamp-3">
                                            {{ book.title || 'Untitled' }}
                                        </span>
                                    </div>
                                </template>
                            </div>

                            <!-- Book Info -->
                            <div class="flex min-w-0 flex-1 flex-col justify-between py-0.5">
                                <div>
                                    <h3 class="line-clamp-2 text-sm font-semibold tracking-tight leading-snug">
                                        {{ book.title || 'Untitled Story' }}
                                    </h3>
                                    <p v-if="book.author" class="mt-0.5 truncate text-xs text-muted-foreground">
                                        {{ book.author }}
                                    </p>
                                </div>

                                <div class="flex items-center justify-between gap-2">
                                    <!-- Chapter Progress -->
                                    <div class="flex items-center gap-1.5 rounded-full bg-amber-100 px-2 py-0.5 dark:bg-amber-900/30">
                                        <BookOpen class="h-3 w-3 text-amber-700 dark:text-amber-400" />
                                        <span class="text-xs font-medium text-amber-700 dark:text-amber-400">
                                            Ch. {{ book.current_chapter_number }}
                                        </span>
                                    </div>

                                    <!-- Last Read Time -->
                                    <div class="flex items-center gap-1 text-xs text-muted-foreground">
                                        <Clock class="h-3 w-3" />
                                        <span>{{ formatRelativeTime(book.last_read_at) }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Hover Shine Effect -->
                            <div
                                class="absolute inset-0 -translate-x-full bg-linear-to-tr from-transparent via-white/10 to-transparent opacity-0 transition-all duration-700 group-hover:translate-x-full group-hover:opacity-100"
                            />
                        </div>
                    </div>

                    <!-- Right Navigation Arrow -->
                    <button
                        v-show="jumpBackInScrollState.canScrollRight"
                        @click="scrollJumpBackInCarousel('right')"
                        class="absolute top-1/2 -right-2 z-10 flex h-10 w-10 -translate-y-1/2 cursor-pointer items-center justify-center rounded-full border border-sidebar-border/70 bg-background/95 shadow-lg backdrop-blur-sm transition-all duration-200 hover:scale-110 hover:bg-background dark:border-sidebar-border"
                        aria-label="Scroll right"
                    >
                        <ChevronRight class="h-5 w-5 text-foreground" />
                    </button>

                    <!-- Edge fade gradients -->
                    <div
                        v-show="jumpBackInScrollState.canScrollLeft"
                        class="pointer-events-none absolute top-0 bottom-4 left-0 w-12 bg-linear-to-r from-background to-transparent"
                    />
                    <div
                        v-show="jumpBackInScrollState.canScrollRight"
                        class="pointer-events-none absolute top-0 right-0 bottom-4 w-12 bg-linear-to-l from-background to-transparent"
                    />
                </div>
            </div>

            <!-- Favorites Section -->
            <div v-if="favoritesState.length > 0" class="space-y-4">
                <!-- Section Header -->
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-linear-to-br from-rose-500 to-pink-600 shadow-lg shadow-rose-500/20">
                        <Heart class="h-5 w-5 fill-white text-white" />
                    </div>
                    <div>
                        <h2 class="text-2xl font-medium tracking-tight">Favorites</h2>
                        <p class="text-sm text-muted-foreground">Your saved stories</p>
                    </div>
                </div>

                <!-- Favorites Carousel -->
                <div class="group/carousel relative">
                    <!-- Left Navigation Arrow -->
                    <button
                        v-show="favoritesScrollState.canScrollLeft"
                        @click="scrollFavoritesCarousel('left')"
                        class="absolute top-1/2 -left-2 z-10 flex h-10 w-10 -translate-y-1/2 cursor-pointer items-center justify-center rounded-full border border-sidebar-border/70 bg-background/95 shadow-lg backdrop-blur-sm transition-all duration-200 hover:scale-110 hover:bg-background dark:border-sidebar-border"
                        aria-label="Scroll left"
                    >
                        <ChevronLeft class="h-5 w-5 text-foreground" />
                    </button>

                    <!-- Carousel Container -->
                    <div
                        ref="favoritesCarouselRef"
                        @scroll="handleFavoritesScroll"
                        class="scrollbar-none flex gap-6 overflow-x-auto scroll-smooth pb-4"
                        style="scrollbar-width: none; -ms-overflow-style: none"
                    >
                        <div
                            v-for="book in favoritesState"
                            :key="`favorite-${book.id}`"
                            :style="getCardVisualStyles(book.id)"
                            @click="
                                openBook(
                                    book.id,
                                    book.cover_image || null,
                                    book.title,
                                    book.author || null,
                                    $event,
                                )
                            "
                            class="group relative w-[200px] shrink-0 cursor-pointer overflow-hidden rounded-2xl border border-sidebar-border/70 bg-card transition-all duration-300 hover:-translate-y-1 hover:[box-shadow:0_24px_48px_-18px_var(--card-shadow-color)] dark:border-sidebar-border"
                        >
                            <!-- Book Cover -->
                            <div class="relative aspect-3/4 overflow-hidden">
                                <!-- Cover Image (if exists) -->
                                <template v-if="book.cover_image">
                                    <div
                                        class="absolute inset-0 bg-linear-to-br from-primary/10 via-primary/5 to-background"
                                    >
                                        <img
                                            :src="book.cover_image"
                                            :alt="book.title"
                                            class="h-full w-full object-cover transition-all duration-500 group-hover:scale-110 group-hover:rotate-1"
                                        />
                                    </div>
                                </template>

                                <!-- Gradient Background with Title (no cover image) -->
                                <template v-else>
                                    <div
                                        class="absolute inset-0 flex items-center justify-center p-4"
                                        :class="getGradientColors(book.id)"
                                    >
                                        <h3
                                            class="text-center text-xl font-bold text-white drop-shadow-[0_2px_4px_rgba(0,0,0,0.3)] transition-all duration-500 group-hover:scale-105"
                                            style="
                                                text-shadow:
                                                    0 2px 8px rgba(0, 0, 0, 0.2),
                                                    0 -1px 2px
                                                        rgba(255, 255, 255, 0.3);
                                            "
                                        >
                                            {{ book.title || 'Untitled Story' }}
                                        </h3>
                                    </div>
                                </template>

                                <!-- Favorite Heart Badge -->
                                <div class="absolute top-2 right-2 flex h-7 w-7 items-center justify-center rounded-full bg-rose-500 shadow-lg">
                                    <Heart class="h-4 w-4 fill-white text-white" />
                                </div>

                                <!-- Magical Shine Effect on Hover -->
                                <div
                                    class="absolute inset-0 -translate-x-full bg-linear-to-tr from-transparent via-white/20 to-transparent opacity-0 transition-all duration-1000 group-hover:translate-x-full group-hover:opacity-100"
                                />

                                <!-- Overlay on Hover -->
                                <div
                                    class="absolute inset-0 opacity-0 transition-opacity duration-300 group-hover:opacity-100"
                                    :style="{
                                        background:
                                            'linear-gradient(to top, var(--card-overlay-dark) 0%, var(--card-overlay-mid) 55%, transparent 100%)',
                                    }"
                                />
                            </div>

                            <!-- Book Info -->
                            <div class="space-y-1.5 p-3">
                                <h3
                                    class="line-clamp-2 text-sm font-semibold tracking-tight"
                                >
                                    {{ book.title || 'Untitled Story' }}
                                </h3>

                                <div
                                    class="flex items-center gap-2 text-xs text-muted-foreground"
                                >
                                    <span v-if="book.author" class="truncate">{{
                                        book.author
                                    }}</span>
                                    <span v-if="book.author && book.age_level"
                                        >•</span
                                    >
                                    <span v-if="book.age_level" class="shrink-0"
                                        >Age {{ book.age_level }}+</span
                                    >
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Navigation Arrow -->
                    <button
                        v-show="favoritesScrollState.canScrollRight"
                        @click="scrollFavoritesCarousel('right')"
                        class="absolute top-1/2 -right-2 z-10 flex h-10 w-10 -translate-y-1/2 cursor-pointer items-center justify-center rounded-full border border-sidebar-border/70 bg-background/95 shadow-lg backdrop-blur-sm transition-all duration-200 hover:scale-110 hover:bg-background dark:border-sidebar-border"
                        aria-label="Scroll right"
                    >
                        <ChevronRight class="h-5 w-5 text-foreground" />
                    </button>

                    <!-- Edge fade gradients -->
                    <div
                        v-show="favoritesScrollState.canScrollLeft"
                        class="pointer-events-none absolute top-0 bottom-4 left-0 w-12 bg-linear-to-r from-background to-transparent"
                    />
                    <div
                        v-show="favoritesScrollState.canScrollRight"
                        class="pointer-events-none absolute top-0 right-0 bottom-4 w-12 bg-linear-to-l from-background to-transparent"
                    />
                </div>
            </div>

            <!-- Genre Sections -->
            <div
                v-for="([genre, books], index) in sortedBooksByGenre"
                :key="genre"
                class="space-y-4"
                :class="{
                    'border-t border-border pt-10 dark:border-border/70':
                        index > 0,
                }"
            >
                <!-- Genre Header -->
                <div class="flex items-center justify-between">
                    <h2 class="text-2xl font-medium tracking-tight">
                        {{ formatGenreName(genre) }}
                    </h2>
                </div>

                <!-- Books Carousel -->
                <div class="group/carousel relative">
                    <!-- Left Navigation Arrow -->
                    <button
                        v-show="scrollStates[genre]?.canScrollLeft"
                        @click="scrollCarousel(genre as string, 'left')"
                        class="absolute top-1/2 -left-2 z-10 flex h-10 w-10 -translate-y-1/2 cursor-pointer items-center justify-center rounded-full border border-sidebar-border/70 bg-background/95 shadow-lg backdrop-blur-sm transition-all duration-200 hover:scale-110 hover:bg-background dark:border-sidebar-border"
                        aria-label="Scroll left"
                    >
                        <ChevronLeft class="h-5 w-5 text-foreground" />
                    </button>

                    <!-- Carousel Container -->
                    <div
                        :ref="
                            (el) =>
                                setCarouselRef(
                                    genre as string,
                                    el as HTMLElement | null,
                                )
                        "
                        @scroll="handleScroll(genre as string)"
                        class="scrollbar-none flex gap-6 overflow-x-auto scroll-smooth pb-4"
                        style="scrollbar-width: none; -ms-overflow-style: none"
                    >
                        <div
                            v-for="book in books"
                            :key="book.id"
                            :style="getCardVisualStyles(book.id)"
                            @click="
                                openBook(
                                    book.id,
                                    book.cover_image || null,
                                    book.title,
                                    book.author || null,
                                    $event,
                                )
                            "
                            class="group relative w-[200px] shrink-0 cursor-pointer overflow-hidden rounded-2xl border border-sidebar-border/70 bg-card transition-all duration-300 hover:-translate-y-1 hover:[box-shadow:0_24px_48px_-18px_var(--card-shadow-color)] dark:border-sidebar-border"
                        >
                            <!-- Book Cover -->
                            <div class="relative aspect-[3/4] overflow-hidden">
                                <!-- Cover Image (if exists) -->
                                <template v-if="book.cover_image">
                                    <div
                                        class="absolute inset-0 bg-gradient-to-br from-primary/10 via-primary/5 to-background"
                                    >
                                        <img
                                            :src="book.cover_image"
                                            :alt="book.title"
                                            class="h-full w-full object-cover transition-all duration-500 group-hover:scale-110 group-hover:rotate-1"
                                        />
                                    </div>
                                </template>

                                <!-- Gradient Background with Title (no cover image) -->
                                <template v-else>
                                    <div
                                        class="absolute inset-0 flex items-center justify-center p-4"
                                        :class="getGradientColors(book.id)"
                                    >
                                        <h3
                                            class="text-center text-xl font-bold text-white drop-shadow-[0_2px_4px_rgba(0,0,0,0.3)] transition-all duration-500 group-hover:scale-105"
                                            style="
                                                text-shadow:
                                                    0 2px 8px rgba(0, 0, 0, 0.2),
                                                    0 -1px 2px
                                                        rgba(255, 255, 255, 0.3);
                                            "
                                        >
                                            {{ book.title || 'Untitled Story' }}
                                        </h3>
                                    </div>
                                </template>

                                <!-- Magical Shine Effect on Hover -->
                                <div
                                    class="absolute inset-0 -translate-x-full bg-gradient-to-tr from-transparent via-white/20 to-transparent opacity-0 transition-all duration-1000 group-hover:translate-x-full group-hover:opacity-100"
                                />

                                <!-- Overlay on Hover -->
                                <div
                                    class="absolute inset-0 opacity-0 transition-opacity duration-300 group-hover:opacity-100"
                                    :style="{
                                        background:
                                            'linear-gradient(to top, var(--card-overlay-dark) 0%, var(--card-overlay-mid) 55%, transparent 100%)',
                                    }"
                                />
                            </div>

                            <!-- Book Info -->
                            <div class="space-y-1.5 p-3">
                                <h3
                                    class="line-clamp-2 text-sm font-semibold tracking-tight"
                                >
                                    {{ book.title || 'Untitled Story' }}
                                </h3>

                                <div
                                    class="flex items-center gap-2 text-xs text-muted-foreground"
                                >
                                    <span v-if="book.author" class="truncate">{{
                                        book.author
                                    }}</span>
                                    <span v-if="book.author && book.age_level"
                                        >•</span
                                    >
                                    <span v-if="book.age_level" class="shrink-0"
                                        >Age {{ book.age_level }}+</span
                                    >
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Navigation Arrow -->
                    <button
                        v-show="scrollStates[genre]?.canScrollRight"
                        @click="scrollCarousel(genre as string, 'right')"
                        class="absolute top-1/2 -right-2 z-10 flex h-10 w-10 -translate-y-1/2 cursor-pointer items-center justify-center rounded-full border border-sidebar-border/70 bg-background/95 shadow-lg backdrop-blur-sm transition-all duration-200 hover:scale-110 hover:bg-background dark:border-sidebar-border"
                        aria-label="Scroll right"
                    >
                        <ChevronRight class="h-5 w-5 text-foreground" />
                    </button>

                    <!-- Edge fade gradients -->
                    <div
                        v-show="scrollStates[genre]?.canScrollLeft"
                        class="pointer-events-none absolute top-0 bottom-4 left-0 w-12 bg-gradient-to-r from-background to-transparent"
                    />
                    <div
                        v-show="scrollStates[genre]?.canScrollRight"
                        class="pointer-events-none absolute top-0 right-0 bottom-4 w-12 bg-gradient-to-l from-background to-transparent"
                    />
                </div>
            </div>
        </div>

        <!-- Book View Modal -->
        <BookViewModal
            v-model:is-open="isBookViewOpen"
            :book-id="selectedBookId"
            :card-position="cardPosition"
            :cover-image="selectedCoverImage"
            :book-title="selectedBookTitle"
            :book-author="selectedBookAuthor"
            @updated="handleBookUpdated"
            @deleted="handleBookDeleted"
            @reading-history-updated="handleReadingHistoryUpdated"
            @favorite-toggled="handleFavoriteToggled"
        />
    </AppLayout>
</template>
