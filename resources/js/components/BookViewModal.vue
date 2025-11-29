<script setup lang="ts">
import { ref, watch, nextTick, onBeforeUnmount, computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
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
import { MoreVertical, Sparkles, X } from 'lucide-vue-next';

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

const startAnimation = async () => {
    clearScheduledTimeouts();
    book.value = null;
    loading.value = false;
    showContent.value = false;
    animationPhase.value = 'initial';
    frontVisible.value = true;
    backVisible.value = false;
    isClosing.value = false;
    isEditing.value = false;
    isSaving.value = false;
    isDeleting.value = false;
    showDeleteConfirm.value = false;
    resetEditFeedback();

    if (!props.cardPosition) {
        loadBook();
        animationPhase.value = 'complete';
        showContent.value = true;
        backVisible.value = true;
        frontVisible.value = false;
        setCardStyle({
            position: 'fixed',
            top: '50%',
            left: '50%',
            width: 'min(90vw, 960px)',
            height: 'min(90vh, 680px)',
            transform: 'translate(-50%, -50%)',
            zIndex: '9999',
        });
        return;
    }

    const targetWidth = window.innerWidth * 0.9;
    const targetHeight = window.innerHeight * 0.9;
    const targetTop = (window.innerHeight - targetHeight) / 2;
    const targetLeft = (window.innerWidth - targetWidth) / 2;

    expandedRect.value = {
        top: targetTop,
        left: targetLeft,
        width: targetWidth,
        height: targetHeight,
    };

    setCardStyle({
        position: 'fixed',
        top: `${props.cardPosition.top}px`,
        left: `${props.cardPosition.left}px`,
        width: `${props.cardPosition.width}px`,
        height: `${props.cardPosition.height}px`,
        transform: 'perspective(2000px) rotateY(0deg) scale(1)',
        zIndex: '9999',
        transition: 'none',
    });

    await nextTick();

    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            animationPhase.value = 'flipping';
            loadBook();

            setCardStyle({
                position: 'fixed',
                top: `${targetTop}px`,
                left: `${targetLeft}px`,
                width: `${targetWidth}px`,
                height: `${targetHeight}px`,
                transform: 'perspective(2000px) rotateY(180deg) scale(1)',
                zIndex: '9999',
                transition: `all ${FLIP_DURATION}ms ${TRANSITION_EASING}`,
            });

            scheduleTimeout(() => {
                frontVisible.value = false;
                backVisible.value = true;
                showContent.value = true;
            }, HALF_FLIP);

            scheduleTimeout(() => {
                animationPhase.value = 'complete';
            }, FLIP_DURATION);
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
    backVisible.value = false;
    expandedRect.value = null;
    cardStyle.value = {};
    book.value = null;
    loading.value = false;
    isEditing.value = false;
    isSaving.value = false;
    isDeleting.value = false;
    showDeleteConfirm.value = false;
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
    frontVisible.value = false;
    backVisible.value = true;

    const { top, left, width, height } = expandedRect.value;

    setCardStyle({
        position: 'fixed',
        top: `${top}px`,
        left: `${left}px`,
        width: `${width}px`,
        height: `${height}px`,
        transform: 'perspective(2000px) rotateY(180deg) scale(1)',
        zIndex: '9999',
        transition: 'none',
    });

    await nextTick();

    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            setCardStyle({
                position: 'fixed',
                top: `${sourceCard.top}px`,
                left: `${sourceCard.left}px`,
                width: `${sourceCard.width}px`,
                height: `${sourceCard.height}px`,
                transform: 'perspective(2000px) rotateY(0deg) scale(1)',
                zIndex: '9999',
                transition: `all ${FLIP_DURATION}ms ${TRANSITION_EASING}`,
            });
        });
    });

    scheduleTimeout(() => {
        frontVisible.value = true;
        backVisible.value = false;
    }, HALF_FLIP);

    scheduleTimeout(() => {
        finalizeClose();
    }, FLIP_DURATION);
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
    } else if (isRendered.value) {
        await reverseAnimation();
    }
});

onBeforeUnmount(() => {
    clearScheduledTimeouts();
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
            class="book-modal-container rounded-2xl overflow-hidden shadow-2xl bg-white"
        >
            <!-- Card Front Face (cover image) - Visible from 0-90deg -->
            <div
                v-show="frontVisible"
                class="absolute inset-0 bg-card"
                style="backface-visibility: hidden; transform: rotateY(0deg);"
            >
                <!-- With Cover Image -->
                <img
                    v-if="props.coverImage"
                    :src="props.coverImage"
                    class="h-full w-full object-cover"
                    alt="Book cover"
                />
                
                <!-- Without Cover Image - Show Gradient with Title -->
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

            <!-- Card Back Face (title page) - Pre-rotated 180deg, visible from 90-180deg -->
            <div 
                v-show="backVisible"
                class="absolute inset-0 bg-gradient-to-br from-white via-gray-50 to-gray-100 dark:from-neutral-900 dark:via-neutral-900/90 dark:to-neutral-800 overflow-auto"
                style="transform: rotateY(180deg); backface-visibility: visible; transform-style: preserve-3d;"
            >
                <!-- Loading State (shows while flipping and loading) -->
                <div 
                    v-if="!showContent"
                    class="absolute inset-0 flex items-center justify-center"
                >
                    <div class="text-center space-y-4">
                        <Spinner class="mx-auto h-12 w-12 text-amber-600 dark:text-amber-400" />
                        <p class="text-lg font-medium text-amber-900 dark:text-amber-100">
                            Opening your story...
                        </p>
                    </div>
                </div>

                <!-- Book Title Page Content (shows when loaded) -->
                <div v-else class="book-page relative h-full w-full">
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

                    <div
                        v-if="animationPhase === 'complete' && !isClosing"
                        class="pointer-events-none absolute inset-x-0 top-0 z-40 flex justify-end px-8 pt-6"
                    >
                        <div class="pointer-events-auto flex items-center gap-2">
                            <DropdownMenu v-if="book">
                                <DropdownMenuTrigger :as-child="true">
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="cursor-pointer rounded-full bg-white/90 p-2 text-amber-900 shadow-lg backdrop-blur-sm transition-colors hover:bg-white dark:bg-black/80 dark:text-amber-100 dark:hover:bg-black"
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
                                class="cursor-pointer rounded-full bg-white/90 p-2 text-amber-900 shadow-lg backdrop-blur-sm transition-colors hover:bg-white dark:bg-black/80 dark:text-amber-100 dark:hover:bg-black"
                                @click="closeModal"
                                :disabled="isSaving || isDeleting"
                            >
                                <X class="h-5 w-5" />
                                <span class="sr-only">Close</span>
                            </Button>
                        </div>
                    </div>

                    <div 
                        v-if="loading || isSaving || isDeleting"
                        class="absolute inset-0 z-20 flex items-center justify-center bg-white/80 backdrop-blur-sm dark:bg-black/70"
                    >
                        <div class="text-center space-y-4">
                            <Spinner class="mx-auto h-12 w-12 text-amber-600 dark:text-amber-400" />
                            <p class="text-lg font-medium text-amber-900 dark:text-amber-100">
                                {{ loading ? 'Opening your story...' : isSaving ? 'Saving changes...' : 'Deleting story...' }}
                            </p>
                        </div>
                    </div>

                    <!-- Magical Background Effects -->
                    <div class="absolute inset-0 opacity-30">
                        <div class="absolute top-10 left-10 w-32 h-32 bg-amber-300 dark:bg-amber-600 rounded-full blur-3xl animate-pulse"></div>
                        <div class="absolute bottom-10 right-10 w-40 h-40 bg-rose-300 dark:bg-rose-600 rounded-full blur-3xl animate-pulse" style="animation-delay: 1s"></div>
                        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-48 h-48 bg-orange-300 dark:bg-orange-600 rounded-full blur-3xl animate-pulse" style="animation-delay: 2s"></div>
                    </div>

                    <!-- Sparkle Effects -->
                    <div v-if="showContent" class="absolute inset-0 pointer-events-none">
                        <div class="sparkle" style="top: 20%; left: 15%; animation-delay: 0s"></div>
                        <div class="sparkle" style="top: 40%; right: 20%; animation-delay: 0.5s"></div>
                        <div class="sparkle" style="bottom: 30%; left: 25%; animation-delay: 1s"></div>
                        <div class="sparkle" style="top: 60%; right: 30%; animation-delay: 1.5s"></div>
                    </div>

                    <!-- Content -->
                    <div 
                        class="relative z-10 flex flex-col items-center justify-center min-h-[600px] p-12 text-center"
                    >
                        <div
                            v-if="actionError"
                            class="mb-8 w-full max-w-2xl rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-left text-sm font-medium text-red-700 dark:border-red-900/40 dark:bg-red-900/20 dark:text-red-200"
                        >
                            {{ actionError }}
                        </div>

                        <template v-if="isEditing">
                            <div class="w-full max-w-3xl text-left">
                                <form @submit.prevent="submitEdit" class="space-y-6">
                                    <div class="grid gap-2">
                                        <Label for="edit-title" class="text-base font-semibold text-foreground">Story Title</Label>
                                        <Input
                                            id="edit-title"
                                            v-model="editForm.title"
                                            placeholder="Enter your story title"
                                            :disabled="isSaving || isDeleting"
                                            class="h-12 bg-white/70 text-lg dark:bg-white/5"
                                        />
                                        <InputError :message="editErrors.title" />
                                    </div>

                                    <div class="grid gap-2">
                                        <Label for="edit-genre" class="text-base font-semibold text-foreground">Genre</Label>
                                        <Select v-model="editForm.genre" :disabled="isSaving || isDeleting">
                                            <SelectTrigger id="edit-genre" class="h-12 text-left text-base">
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
                                        <Label for="edit-age-level" class="text-base font-semibold text-foreground">Age Level</Label>
                                        <Select v-model="editForm.age_level" :disabled="isSaving || isDeleting">
                                            <SelectTrigger id="edit-age-level" class="h-12 text-left text-base">
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
                                        <Label for="edit-author" class="text-base font-semibold text-foreground">Author</Label>
                                        <Input
                                            id="edit-author"
                                            v-model="editForm.author"
                                            placeholder="Author name"
                                            :disabled="isSaving || isDeleting"
                                            class="h-12 bg-white/70 text-lg dark:bg-white/5"
                                        />
                                        <InputError :message="editErrors.author" />
                                    </div>

                                    <div class="grid gap-2">
                                        <Label for="edit-plot" class="text-base font-semibold text-foreground">Plot Summary</Label>
                                        <Textarea
                                            id="edit-plot"
                                            v-model="editForm.plot"
                                            placeholder="Briefly describe your story's plot..."
                                            rows="6"
                                            :disabled="isSaving || isDeleting"
                                            class="min-h-[160px] text-base leading-relaxed"
                                        />
                                        <InputError :message="editErrors.plot" />
                                    </div>

                                    <InputError v-if="editErrors.general" :message="editErrors.general" />

                                    <div class="flex items-center justify-end gap-3">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            @click="cancelEditing"
                                            :disabled="isSaving || isDeleting"
                                        >
                                            Cancel
                                        </Button>
                                        <Button
                                            type="submit"
                                            :disabled="isSaving || isDeleting"
                                        >
                                            <Spinner v-if="isSaving" class="mr-2 h-4 w-4" />
                                            {{ isSaving ? 'Saving...' : 'Save Changes' }}
                                        </Button>
                                    </div>
                                </form>
                            </div>
                        </template>

                        <template v-else>
                            <!-- Decorative Top Border -->
                            <div class="mb-8 flex items-center gap-4">
                                <div class="h-px w-20 bg-gradient-to-r from-transparent via-amber-600 to-transparent dark:via-amber-400"></div>
                                <Sparkles class="h-6 w-6 text-amber-600 dark:text-amber-400 animate-spin-slow" />
                                <div class="h-px w-20 bg-gradient-to-r from-transparent via-amber-600 to-transparent dark:via-amber-400"></div>
                            </div>

                            <!-- Title -->
                            <h1 class="mb-6 text-6xl md:text-7xl font-serif font-bold text-amber-950 dark:text-amber-50 tracking-tight drop-shadow-lg">
                                {{ displayTitle }}
                            </h1>

                            <!-- Author -->
                            <div v-if="displayAuthor" class="mb-6 text-2xl font-serif italic text-amber-800 dark:text-amber-200">
                                by {{ displayAuthor }}
                            </div>

                            <!-- Created Date -->
                            <div v-if="displayCreatedAt" class="mb-8 text-xl font-serif text-amber-700 dark:text-amber-300">
                                {{ displayCreatedAt }}
                            </div>

                            <!-- Plot Summary -->
                            <div v-if="displayPlot" class="mb-8 max-w-2xl">
                                <p class="text-lg leading-relaxed text-amber-900 dark:text-amber-100 italic">
                                    "{{ displayPlot }}"
                                </p>
                            </div>

                            <!-- Decorative Bottom Border -->
                            <div class="mt-8 flex items-center gap-4">
                                <div class="h-px w-20 bg-gradient-to-r from-transparent via-amber-600 to-transparent dark:via-amber-400"></div>
                                <Sparkles class="h-6 w-6 text-amber-600 dark:text-amber-400 animate-spin-slow" style="animation-delay: 1s" />
                                <div class="h-px w-20 bg-gradient-to-r from-transparent via-amber-600 to-transparent dark:via-amber-400"></div>
                            </div>

                            <!-- Continue Button -->
                            <div class="mt-12">
                                <button 
                                    class="group relative px-8 py-4 text-lg font-semibold text-white bg-gradient-to-r from-amber-600 to-orange-600 dark:from-amber-500 dark:to-orange-500 rounded-full shadow-xl hover:shadow-2xl transition-all duration-300 hover:scale-105 overflow-hidden"
                                >
                                    <span class="relative z-10">Begin Reading</span>
                                    <div class="absolute inset-0 bg-gradient-to-r from-orange-600 to-rose-600 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                                </button>
                            </div>
                        </template>
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
</style>

