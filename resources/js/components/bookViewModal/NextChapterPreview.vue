<script setup lang="ts">
import { computed } from 'vue';
import { Sparkles } from 'lucide-vue-next';
import type { Chapter, PageContentItem, BookType } from './types';
import { getChapterLabel, isSceneBasedBook, formatScriptDialogue } from './types';

interface Props {
    chapter: Chapter;
    firstPageContent: PageContentItem[];
    bookType?: BookType;
}

const props = defineProps<Props>();

const chapterLabel = computed(() => getChapterLabel(props.bookType));
const isScript = computed(() => isSceneBasedBook(props.bookType));

const isFirstParagraph = (items: PageContentItem[], idx: number): boolean => {
    let paragraphCount = 0;
    for (let i = 0; i <= idx; i++) {
        if (items[i]?.type === 'paragraph') {
            paragraphCount++;
        }
    }
    return paragraphCount === 1 && items[idx]?.type === 'paragraph';
};

// Format content - applies script formatting for theatre/screenplay
const formatContent = (content: string): string => {
    return formatScriptDialogue(content, isScript.value);
};
</script>

<template>
    <div class="relative z-10 h-full overflow-hidden">
        <div class="flex h-full flex-col px-12 pt-24 pb-6">
            <!-- 40% top margin space with chapter title -->
            <div class="h-[40%] flex items-end justify-center pb-4">
                <div class="text-center">
                    <div class="text-xs uppercase tracking-widest text-amber-700 dark:text-amber-600 mb-2 font-medium">
                        {{ chapterLabel }} {{ chapter.sort }}
                    </div>
                    <h2 class="font-serif text-2xl md:text-3xl font-bold text-amber-950 dark:text-amber-900">
                        {{ chapter.title || `${chapterLabel} ${chapter.sort}` }}
                    </h2>
                    
                    <!-- Decorative -->
                    <div class="mt-3 flex items-center justify-center gap-3">
                        <div class="h-px w-12 bg-linear-to-r from-transparent via-amber-700 to-transparent dark:via-amber-600"></div>
                        <Sparkles class="h-3 w-3 text-amber-700 dark:text-amber-500" />
                        <div class="h-px w-12 bg-linear-to-r from-transparent via-amber-700 to-transparent dark:via-amber-600"></div>
                    </div>
                </div>
            </div>
            
            <!-- Remaining ~60% for content -->
            <div class="flex-1 overflow-hidden">
                <div class="prose prose-amber prose-lg max-w-none text-amber-950 dark:text-amber-900">
                    <template v-for="(item, idx) in firstPageContent" :key="idx">
                        <p 
                            v-if="item.type === 'paragraph'"
                            :class="[
                                'mb-5 font-serif text-lg leading-relaxed',
                                isFirstParagraph(firstPageContent, idx) && !isScript ? 'drop-cap' : ''
                            ]"
                            v-html="formatContent(item.content)"
                        />
                        <figure v-else-if="item.type === 'image'" class="my-6">
                            <img
                                :src="item.imageUrl"
                                :alt="item.content"
                                class="w-full h-auto rounded-lg shadow-md object-cover aspect-video"
                                loading="lazy"
                            />
                        </figure>
                    </template>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.drop-cap::first-letter {
    font-size: 3.5rem;
    font-weight: 700;
    float: left;
    margin-right: 0.5rem;
    line-height: 0.8;
    color: inherit;
}
</style>


