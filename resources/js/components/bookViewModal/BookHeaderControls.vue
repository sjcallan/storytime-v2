<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuPortal,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { MoreVertical, X, List, Heart } from 'lucide-vue-next';
import TableOfContents from './TableOfContents.vue';
import type { ChapterSummary, BookType } from './types';

interface Props {
    hasBook: boolean;
    isEditing: boolean;
    isSaving: boolean;
    isDeleting: boolean;
    isPageTurning: boolean;
    isBookOpened: boolean;
    hasChapters?: boolean;
    chapters?: ChapterSummary[];
    currentChapterNumber?: number;
    bookType?: BookType;
    isFavorite?: boolean;
    isTogglingFavorite?: boolean;
    hasCharacters?: boolean;
    isViewingCharacters?: boolean;
    hasImages?: boolean;
    isViewingGallery?: boolean;
}

defineProps<Props>();

const emit = defineEmits<{
    (e: 'edit'): void;
    (e: 'delete'): void;
    (e: 'close'): void;
    (e: 'tocSelectChapter', chapterNumber: number): void;
    (e: 'tocGoToTitle'): void;
    (e: 'tocGoToCharacters'): void;
    (e: 'tocGoToGallery'): void;
    (e: 'toggleFavorite'): void;
}>();
</script>

<template>
    <div class="pointer-events-none absolute inset-x-0 top-0 z-50 flex justify-between px-6 pt-6">
        <!-- Left side: Table of Contents and Actions (only when book is opened) -->
        <div class="pointer-events-auto flex items-center gap-2">
            <TableOfContents
                v-if="isBookOpened && hasBook && (hasChapters || hasCharacters || hasImages)"
                :chapters="chapters || []"
                :current-chapter-number="currentChapterNumber || 0"
                :book-type="bookType"
                :has-characters="hasCharacters"
                :is-viewing-characters="isViewingCharacters"
                :has-images="hasImages"
                :is-viewing-gallery="isViewingGallery"
                @select-chapter="emit('tocSelectChapter', $event)"
                @go-to-title="emit('tocGoToTitle')"
                @go-to-characters="emit('tocGoToCharacters')"
                @go-to-gallery="emit('tocGoToGallery')"
            >
                <template #trigger>
                    <DropdownMenuTrigger :as-child="true">
                        <Button
                            variant="ghost"
                            size="icon"
                            class="cursor-pointer rounded-full bg-white/70 p-2 text-amber-900 shadow-md backdrop-blur-sm transition-colors hover:bg-white/90 dark:bg-white/70 dark:text-amber-900 dark:hover:bg-white/90"
                            :disabled="isEditing || isSaving || isDeleting || isPageTurning"
                            title="Table of Contents"
                        >
                            <List class="h-5 w-5" />
                            <span class="sr-only">Table of Contents</span>
                        </Button>
                    </DropdownMenuTrigger>
                </template>
            </TableOfContents>
            <DropdownMenu v-if="isBookOpened && hasBook">
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
                    <DropdownMenuContent align="start" class="theme-reset z-10001 w-48">
                        <DropdownMenuItem
                            @select="emit('toggleFavorite')"
                            :disabled="isTogglingFavorite || isSaving || isDeleting"
                            class="cursor-pointer"
                        >
                            <Heart 
                                class="mr-2 h-4 w-4" 
                                :class="isFavorite ? 'fill-rose-500 text-rose-500' : ''" 
                            />
                            {{ isFavorite ? 'Remove from Favorites' : 'Add to Favorites' }}
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            @select="emit('edit')"
                            :disabled="isEditing || isSaving || isDeleting"
                        >
                            Edit Story Details
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            @select="emit('delete')"
                            :disabled="isSaving || isDeleting"
                            class="cursor-pointer text-red-600 focus:bg-red-50 focus:text-red-700 dark:text-red-400 dark:focus:bg-red-950/50 dark:focus:text-red-300"
                        >
                            Delete Story
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenuPortal>
            </DropdownMenu>
        </div>

        <!-- Right side: Close button -->
        <div class="pointer-events-auto">
            <Button
                variant="ghost"
                size="icon"
                class="cursor-pointer rounded-full bg-white/70 p-2 text-amber-900 shadow-md backdrop-blur-sm transition-colors hover:bg-white/90 dark:bg-white/70 dark:text-amber-900 dark:hover:bg-white/90"
                @click="emit('close')"
                :disabled="isSaving || isDeleting || isPageTurning"
            >
                <X class="h-5 w-5" />
                <span class="sr-only">Close</span>
            </Button>
        </div>
    </div>
</template>

