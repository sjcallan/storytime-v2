<script setup lang="ts">
import { Sparkles } from 'lucide-vue-next';
import type { Chapter, PageSpread } from './types';

interface Props {
    chapter: Chapter;
    spread: PageSpread;
}

defineProps<Props>();
</script>

<template>
    <div class="relative z-10 h-full overflow-hidden">
        <!-- First spread: Title with 40% top margin + beginning of content -->
        <template v-if="spread.isFirstSpread">
            <div class="flex h-full flex-col px-12 pt-8 pb-6">
                <!-- 40% top margin space -->
                <div class="h-[40%] flex items-end justify-center pb-4">
                    <div class="text-center">
                        <h2 class="font-serif text-2xl md:text-3xl font-bold text-amber-950 dark:text-amber-900">
                            {{ chapter.title || `Chapter ${chapter.sort}` }}
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
                    <div v-if="spread.rightContent" class="prose prose-amber prose-lg max-w-none text-amber-950 dark:text-amber-900">
                        <p 
                            v-for="(paragraph, idx) in spread.rightContent"
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
                <div v-if="spread.rightContent" class="prose prose-amber prose-lg max-w-none text-amber-950 dark:text-amber-900">
                    <p 
                        v-for="(paragraph, idx) in spread.rightContent"
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

