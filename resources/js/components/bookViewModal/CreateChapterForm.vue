<script setup lang="ts">
import { computed } from 'vue';
import { Textarea } from '@/components/ui/textarea';
import { Wand2, Sparkles, BookOpen, Check } from 'lucide-vue-next';
import type { BookType } from './types';
import { getChapterLabel, isSceneBasedBook } from './types';

interface Props {
    chapterNumber: number;
    prompt: string;
    isFinalChapter: boolean;
    isGenerating: boolean;
    bookType?: BookType;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'update:prompt', value: string): void;
    (e: 'update:isFinalChapter', value: boolean): void;
    (e: 'generate'): void;
}>();

const chapterLabel = computed(() => getChapterLabel(props.bookType));
const isScript = computed(() => isSceneBasedBook(props.bookType));
</script>

<template>
    <div class="relative z-10 flex h-full flex-col items-center justify-center p-8">
        <!-- Decorative top flourish -->
        <div class="absolute top-8 left-1/2 -translate-x-1/2 flex items-center gap-3 opacity-40">
            <div class="h-px w-16 bg-linear-to-r from-transparent to-amber-700 dark:to-amber-600" />
            <Sparkles class="h-4 w-4 text-amber-700 dark:text-amber-600" />
            <div class="h-px w-16 bg-linear-to-l from-transparent to-amber-700 dark:to-amber-600" />
        </div>

        <!-- Centered content container -->
        <div class="w-full max-w-sm space-y-6">
            <!-- Header -->
            <div class="text-center">
                
                <h2 class="font-serif text-3xl font-bold text-stone-800 dark:text-stone-900">
                    {{ isScript ? 'Continue the Script...' : 'Continue the Story...' }}
                </h2>
                <p class="mt-2 text-base text-stone-600 dark:text-stone-700">
                    {{ isScript 
                        ? `What happens in the next scene? Share your ideas below.`
                        : `What adventure awaits in the next chapter? Share your ideas below.` 
                    }}
                </p>
            </div>
            
            <!-- Prompt textarea -->
            <div class="space-y-2">
                <Textarea
                    :model-value="prompt"
                    @update:model-value="emit('update:prompt', String($event))"
                    placeholder="The hero discovers a hidden door behind the waterfall..."
                    rows="4"
                    :disabled="isGenerating"
                    class="w-full resize-none border-stone-300 bg-white font-serif text-lg text-stone-800 placeholder:text-stone-400 focus:border-amber-600 focus:ring-amber-600/30 dark:border-stone-400 dark:bg-white/90 dark:text-stone-900 dark:placeholder:text-stone-500"
                />
                <p class="text-center text-sm text-stone-500 dark:text-stone-600">
                    Optional — leave empty for a surprise!
                </p>
            </div>
            
            <!-- Final chapter question -->
            <div class="rounded-xl border border-stone-200 bg-stone-50 p-5 dark:border-stone-300 dark:bg-stone-100">
                <p class="mb-4 text-center text-base font-medium text-stone-700 dark:text-stone-800">
                    Will this be the final {{ chapterLabel.toLowerCase() }}?
                </p>
                <div class="flex items-center justify-center gap-4">
                    <button 
                        type="button"
                        @click="emit('update:isFinalChapter', true)"
                        :disabled="isGenerating"
                        :class="[
                            'group relative flex items-center gap-2 rounded-full px-6 py-2.5 text-base font-medium transition-all duration-200',
                            isFinalChapter 
                                ? 'bg-amber-800 text-white shadow-md' 
                                : 'bg-white text-stone-700 hover:bg-stone-100 border border-stone-300 dark:bg-white dark:text-stone-800 dark:border-stone-400 dark:hover:bg-stone-50',
                            'disabled:opacity-50 disabled:cursor-not-allowed'
                        ]"
                    >
                        <Check v-if="isFinalChapter" class="h-4 w-4" />
                        <span>Yes</span>
                    </button>
                    <button 
                        type="button"
                        @click="emit('update:isFinalChapter', false)"
                        :disabled="isGenerating"
                        :class="[
                            'group relative flex items-center gap-2 rounded-full px-6 py-2.5 text-base font-medium transition-all duration-200',
                            !isFinalChapter 
                                ? 'bg-amber-800 text-white shadow-md' 
                                : 'bg-white text-stone-700 hover:bg-stone-100 border border-stone-300 dark:bg-white dark:text-stone-800 dark:border-stone-400 dark:hover:bg-stone-50',
                            'disabled:opacity-50 disabled:cursor-not-allowed'
                        ]"
                    >
                        <Check v-if="!isFinalChapter" class="h-4 w-4" />
                        <span>No</span>
                    </button>
                </div>
            </div>
            
            <!-- Generate button -->
            <button 
                @click="emit('generate')"
                :disabled="isGenerating"
                class="group w-full cursor-pointer rounded-full bg-amber-800 px-6 py-3.5 text-base font-semibold text-white shadow-lg transition-all duration-300 hover:bg-amber-900 hover:shadow-xl hover:scale-[1.02] active:scale-[0.98] disabled:opacity-60 disabled:cursor-not-allowed disabled:hover:scale-100"
            >
                <span class="flex items-center justify-center gap-2">
                    <Wand2 :class="['h-5 w-5 transition-transform duration-300', isGenerating ? 'animate-pulse' : 'group-hover:rotate-12']" />
                    <span>{{ isGenerating 
                        ? (isScript ? 'Writing your script...' : 'Crafting your story...') 
                        : `Create ${chapterLabel}` 
                    }}</span>
                </span>
            </button>
        </div>

        <!-- Decorative bottom flourish -->
        <div class="absolute bottom-8 left-1/2 -translate-x-1/2 flex items-center gap-3 opacity-30">
            <div class="h-px w-12 bg-linear-to-r from-transparent to-amber-700 dark:to-amber-600" />
            <div class="h-1.5 w-1.5 rounded-full bg-amber-700 dark:bg-amber-600" />
            <div class="h-px w-12 bg-linear-to-l from-transparent to-amber-700 dark:to-amber-600" />
        </div>
    </div>
</template>

