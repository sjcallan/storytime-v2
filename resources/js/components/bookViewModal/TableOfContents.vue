<script setup lang="ts">
import { BookOpen, ChevronRight, Sparkles, Crown } from 'lucide-vue-next';
import type { ChapterSummary } from './types';

interface Props {
    chapters: ChapterSummary[];
    currentChapterNumber: number;
    bookTitle: string;
}

defineProps<Props>();

const emit = defineEmits<{
    (e: 'selectChapter', chapterNumber: number): void;
    (e: 'goToTitle'): void;
}>();

const getChapterDisplayTitle = (chapter: ChapterSummary): string => {
    if (chapter.title && chapter.title.trim()) {
        return chapter.title;
    }
    return `Chapter ${chapter.sort}`;
};
</script>

<template>
    <div class="relative z-10 h-full overflow-hidden">
        <div class="flex h-full flex-col px-8 pt-12 pb-6">
            <!-- Header -->
            <div class="mb-8 text-center">
                <div class="mb-4 flex items-center justify-center gap-3">
                    <div class="h-px w-16 bg-gradient-to-r from-transparent via-amber-700 to-transparent dark:via-amber-600"></div>
                    <BookOpen class="h-6 w-6 text-amber-700 dark:text-amber-600" />
                    <div class="h-px w-16 bg-gradient-to-r from-transparent via-amber-700 to-transparent dark:via-amber-600"></div>
                </div>
                <h2 class="font-serif text-2xl font-bold text-amber-950 dark:text-amber-900">
                    Table of Contents
                </h2>
                <p class="mt-2 font-serif text-sm italic text-amber-700 dark:text-amber-600">
                    {{ bookTitle }}
                </p>
            </div>
            
            <!-- Chapter List -->
            <div class="flex-1 overflow-y-auto scrollbar-thin scrollbar-thumb-amber-300 scrollbar-track-transparent">
                <nav class="space-y-1">
                    <!-- Title Page Entry -->
                    <button
                        @click="emit('goToTitle')"
                        class="group flex w-full items-center gap-3 rounded-lg px-4 py-3 text-left transition-all duration-200 hover:bg-amber-200/50 dark:hover:bg-amber-300/30"
                        :class="currentChapterNumber === 0 ? 'bg-amber-200/70 dark:bg-amber-300/40' : ''"
                    >
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-amber-700/10 text-amber-700 dark:bg-amber-600/20 dark:text-amber-600">
                            <Sparkles class="h-4 w-4" />
                        </span>
                        <span class="flex-1 font-serif text-base text-amber-950 dark:text-amber-900">
                            Title Page
                        </span>
                        <ChevronRight 
                            class="h-4 w-4 text-amber-500 opacity-0 transition-opacity group-hover:opacity-100" 
                        />
                    </button>

                    <!-- Chapter Entries -->
                    <button
                        v-for="chapter in chapters"
                        :key="chapter.id"
                        @click="emit('selectChapter', chapter.sort)"
                        class="group flex w-full items-center gap-3 rounded-lg px-4 py-3 text-left transition-all duration-200 hover:bg-amber-200/50 dark:hover:bg-amber-300/30"
                        :class="currentChapterNumber === chapter.sort ? 'bg-amber-200/70 dark:bg-amber-300/40' : ''"
                    >
                        <span 
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-semibold"
                            :class="currentChapterNumber === chapter.sort 
                                ? 'bg-amber-700 text-white dark:bg-amber-600' 
                                : 'bg-amber-700/10 text-amber-700 dark:bg-amber-600/20 dark:text-amber-600'"
                        >
                            {{ chapter.sort }}
                        </span>
                        <span class="flex-1 font-serif text-base text-amber-950 dark:text-amber-900">
                            {{ getChapterDisplayTitle(chapter) }}
                        </span>
                        <Crown 
                            v-if="chapter.final_chapter" 
                            class="h-4 w-4 text-amber-500 dark:text-amber-400" 
                            title="Final Chapter"
                        />
                        <ChevronRight 
                            class="h-4 w-4 text-amber-500 opacity-0 transition-opacity group-hover:opacity-100" 
                        />
                    </button>
                </nav>
            </div>

            <!-- Empty State -->
            <div 
                v-if="chapters.length === 0" 
                class="flex flex-1 flex-col items-center justify-center text-center"
            >
                <div class="mb-4 rounded-full bg-amber-200/50 p-4 dark:bg-amber-300/30">
                    <BookOpen class="h-8 w-8 text-amber-600 dark:text-amber-500" />
                </div>
                <p class="font-serif text-lg text-amber-800 dark:text-amber-700">
                    No chapters yet
                </p>
                <p class="mt-1 text-sm text-amber-600 dark:text-amber-500">
                    Start reading to create your first chapter
                </p>
            </div>

            <!-- Decorative Footer -->
            <div class="mt-6 flex items-center justify-center gap-3">
                <div class="h-px w-24 bg-gradient-to-r from-transparent via-amber-400 to-transparent dark:via-amber-500"></div>
                <Sparkles class="h-3 w-3 text-amber-400 dark:text-amber-500" />
                <div class="h-px w-24 bg-gradient-to-r from-transparent via-amber-400 to-transparent dark:via-amber-500"></div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.scrollbar-thin::-webkit-scrollbar {
    width: 6px;
}

.scrollbar-thin::-webkit-scrollbar-track {
    background: transparent;
}

.scrollbar-thin::-webkit-scrollbar-thumb {
    background-color: rgb(217 119 6 / 0.3);
    border-radius: 3px;
}

.scrollbar-thin::-webkit-scrollbar-thumb:hover {
    background-color: rgb(217 119 6 / 0.5);
}
</style>

