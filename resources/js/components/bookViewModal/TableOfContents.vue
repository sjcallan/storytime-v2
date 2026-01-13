<script setup lang="ts">
import { computed } from 'vue';
import { Sparkles, Crown, Users, ImageIcon } from 'lucide-vue-next';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import type { ChapterSummary, BookType } from './types';
import { getChapterLabel } from './types';

interface Props {
    chapters: ChapterSummary[];
    currentChapterNumber: number;
    bookType?: BookType;
    hasCharacters?: boolean;
    isViewingCharacters?: boolean;
    hasImages?: boolean;
    isViewingGallery?: boolean;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'selectChapter', chapterNumber: number): void;
    (e: 'goToTitle'): void;
    (e: 'goToCharacters'): void;
    (e: 'goToGallery'): void;
}>();

const chapterLabel = computed(() => getChapterLabel(props.bookType));

const getChapterDisplayTitle = (chapter: ChapterSummary): string => {
    if (chapter.title && chapter.title.trim()) {
        return chapter.title;
    }
    return `${chapterLabel.value} ${chapter.sort}`;
};
</script>

<template>
    <DropdownMenu>
        <slot name="trigger" />
        <DropdownMenuContent 
            align="start" 
            class="theme-reset z-10001 w-80 max-h-[500px] overflow-y-auto"
        >
            <DropdownMenuLabel class="font-serif text-base">
                Table of Contents
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            
            <!-- Title Page Entry -->
            <DropdownMenuItem
                @select="emit('goToTitle')"
                class="cursor-pointer font-serif"
                :class="currentChapterNumber === 0 && !isViewingCharacters ? 'bg-accent' : ''"
            >
                <div class="flex items-center gap-3 w-full">
                    <Sparkles class="h-4 w-4 shrink-0" />
                    <span class="flex-1">Title Page</span>
                </div>
            </DropdownMenuItem>
            
            <!-- Characters Entry -->
            <DropdownMenuItem
                v-if="hasCharacters"
                @select="emit('goToCharacters')"
                class="cursor-pointer font-serif"
                :class="isViewingCharacters ? 'bg-accent' : ''"
            >
                <div class="flex items-center gap-3 w-full">
                    <Users class="h-4 w-4 shrink-0" />
                    <span class="flex-1">Characters</span>
                </div>
            </DropdownMenuItem>
            
            <!-- Gallery Entry -->
            <DropdownMenuItem
                v-if="hasImages"
                @select="emit('goToGallery')"
                class="cursor-pointer font-serif"
                :class="isViewingGallery ? 'bg-accent' : ''"
            >
                <div class="flex items-center gap-3 w-full">
                    <ImageIcon class="h-4 w-4 shrink-0" />
                    <span class="flex-1">Gallery</span>
                </div>
            </DropdownMenuItem>
            
            <DropdownMenuSeparator v-if="chapters.length > 0" />
            
            <!-- Chapter Entries -->
            <DropdownMenuItem
                v-for="chapter in chapters"
                :key="chapter.id"
                @select="emit('selectChapter', chapter.sort)"
                class="cursor-pointer font-serif"
                :class="currentChapterNumber === chapter.sort ? 'bg-accent' : ''"
            >
                <div class="flex items-center gap-3 w-full">
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold">
                        {{ chapter.sort }}
                    </span>
                    <span class="flex-1 truncate">{{ getChapterDisplayTitle(chapter) }}</span>
                    <Crown 
                        v-if="chapter.final_chapter" 
                        class="h-4 w-4 shrink-0 text-amber-500 dark:text-amber-400" 
                        title="Final Chapter"
                    />
                </div>
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
