<script setup lang="ts">
import { computed, ref, toRefs, watch, inject } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import BookViewModal from '@/components/BookViewModal.vue';
import { Button } from '@/components/ui/button';
import { Head } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';

const props = defineProps<{
    booksByGenre: Record<string, Array<{
        id: string;
        title: string;
        genre: string;
        author: string | null;
        age_level: number | null;
        status: string;
        cover_image?: string;
    }>>;
    userName: string;
}>();

const { booksByGenre } = toRefs(props);

type BookSummary = {
    id: string;
    title: string;
    genre: string;
    author: string | null;
    age_level: number | null;
    status: string;
    cover_image?: string | null;
};

type BookDetails = BookSummary & {
    plot: string | null;
    created_at: string;
};

const cloneBooksByGenre = (source: Record<string, BookSummary[]>): Record<string, BookSummary[]> => {
    return Object.entries(source).reduce((acc, [genre, books]) => {
        acc[genre] = books.map(book => ({ ...book }));
        return acc;
    }, {} as Record<string, BookSummary[]>);
};

const booksByGenreState = ref<Record<string, BookSummary[]>>(cloneBooksByGenre(booksByGenre.value));

watch(booksByGenre, newValue => {
    booksByGenreState.value = cloneBooksByGenre(newValue);
});

const hasBooks = computed(() => Object.values(booksByGenreState.value).some(list => list.length > 0));

const openCreateStoryModal = inject<() => void>('openCreateStoryModal', () => {});

const isBookViewOpen = ref(false);
const selectedBookId = ref<string | null>(null);
const cardPosition = ref<{ top: number; left: number; width: number; height: number } | null>(null);
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
    { fromClass: 'from-violet-600', toClass: 'to-violet-300', fromHex: '#7c3aed', toHex: '#c4b5fd' },
    { fromClass: 'from-blue-600', toClass: 'to-blue-300', fromHex: '#2563eb', toHex: '#93c5fd' },
    { fromClass: 'from-emerald-600', toClass: 'to-emerald-300', fromHex: '#059669', toHex: '#6ee7b7' },
    { fromClass: 'from-amber-600', toClass: 'to-amber-300', fromHex: '#d97706', toHex: '#fcd34d' },
    { fromClass: 'from-rose-600', toClass: 'to-rose-300', fromHex: '#e11d48', toHex: '#fda4af' },
    { fromClass: 'from-pink-600', toClass: 'to-pink-300', fromHex: '#db2777', toHex: '#f9a8d4' },
    { fromClass: 'from-indigo-600', toClass: 'to-indigo-300', fromHex: '#4f46e5', toHex: '#a5b4fc' },
    { fromClass: 'from-cyan-600', toClass: 'to-cyan-300', fromHex: '#0891b2', toHex: '#67e8f9' },
    { fromClass: 'from-orange-600', toClass: 'to-orange-300', fromHex: '#ea580c', toHex: '#fdba74' },
    { fromClass: 'from-teal-600', toClass: 'to-teal-300', fromHex: '#0d9488', toHex: '#5eead4' },
];

const resolveGradientOption = (key: string) => {
    const hash = key.split('').reduce((acc, char) => acc + char.charCodeAt(0), 0);

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
        .map(component => component.toString(16).padStart(2, '0'))
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

const openBook = (bookId: string, coverImage: string | null, title: string, author: string | null, event: MouseEvent) => {
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
        const index = list.findIndex(book => book.id === bookId);
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

    selectedBookId.value = null;
    selectedCoverImage.value = null;
    selectedBookTitle.value = null;
    selectedBookAuthor.value = null;
    cardPosition.value = null;
    isBookViewOpen.value = false;
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
        .map(word => word.charAt(0).toUpperCase() + word.slice(1))
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
            <div v-if="!hasBooks" 
                class="flex flex-col items-center justify-center gap-4 rounded-xl border border-dashed border-sidebar-border/70 p-12 dark:border-sidebar-border">
                <div class="text-center text-muted-foreground">
                    <h3 class="mb-2 text-lg font-semibold">No Books Yet</h3>
                    <p class="mb-4">Start creating your first story to see it here!</p>
                    <Button @click="openCreateStoryModal()" variant="outline" class="cursor-pointer">
                        <Plus class="mr-2 h-4 w-4" />
                        Create Your First Story
                    </Button>
                </div>
            </div>

            <!-- Genre Sections -->
            <div
                v-for="(books, genre) in booksByGenreState"
                :key="genre"
                class="space-y-6"
            >
                <!-- Genre Header -->
                <div>
                    <h2 class="text-2xl font-medium tracking-tight">
                        {{ formatGenreName(genre) }}
                    </h2>
                </div>

                <!-- Books Grid -->
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-6">
                    <div
                        v-for="book in books"
                        :key="book.id"
                        :style="getCardVisualStyles(book.id)"
                        @click="openBook(book.id, book.cover_image || null, book.title, book.author || null, $event)"
                        class="group relative overflow-hidden rounded-2xl border border-sidebar-border/70 bg-card transition-all duration-300 hover:-translate-y-1 hover:[box-shadow:0_24px_48px_-18px_var(--card-shadow-color)] dark:border-sidebar-border cursor-pointer"
                    >
                        <!-- Book Cover -->
                        <div class="relative aspect-[3/4] overflow-hidden">
                            <!-- Cover Image (if exists) -->
                            <template v-if="book.cover_image">
                                <div class="absolute inset-0 bg-gradient-to-br from-primary/10 via-primary/5 to-background">
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
                                    class="absolute inset-0 flex items-center justify-center p-6"
                                    :class="getGradientColors(book.id)"
                                >
                                    <h3 class="text-2xl md:text-3xl font-bold text-center text-white drop-shadow-[0_2px_4px_rgba(0,0,0,0.3)] transition-all duration-500 group-hover:scale-105"
                                        style="text-shadow: 0 2px 8px rgba(0,0,0,0.2), 0 -1px 2px rgba(255,255,255,0.3)"
                                    >
                                        {{ book.title || 'Untitled Story' }}
                                    </h3>
                                </div>
                            </template>
                            
                            <!-- Magical Shine Effect on Hover -->
                            <div class="absolute inset-0 bg-gradient-to-tr from-transparent via-white/20 to-transparent opacity-0 -translate-x-full transition-all duration-1000 group-hover:opacity-100 group-hover:translate-x-full" />
                            
                            <!-- Overlay on Hover -->
                            <div
                                class="absolute inset-0 opacity-0 transition-opacity duration-300 group-hover:opacity-100"
                                :style="{
                                    background: 'linear-gradient(to top, var(--card-overlay-dark) 0%, var(--card-overlay-mid) 55%, transparent 100%)',
                                }"
                            />
                            
                            
                            
                            <!-- Status Badge -->
                            <div class="absolute right-3 top-3">
                                <span class="inline-flex items-center rounded-full bg-background/90 px-3 py-1 text-xs font-medium backdrop-blur-sm">
                                    {{ book.status }}
                                </span>
                            </div>
                        </div>

                        <!-- Book Info -->
                        <div class="space-y-2 p-4">
                            <h3 class="line-clamp-2 text-lg font-semibold tracking-tight">
                                {{ book.title || 'Untitled Story' }}
                            </h3>
                            
                            <div class="flex items-center gap-2 text-sm text-muted-foreground">
                                <span v-if="book.author">{{ book.author }}</span>
                                <span v-if="book.author && book.age_level">•</span>
                                <span v-if="book.age_level">Age {{ book.age_level }}+</span>
                            </div>
                        </div>
                    </div>

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
        />
    </AppLayout>
</template>
