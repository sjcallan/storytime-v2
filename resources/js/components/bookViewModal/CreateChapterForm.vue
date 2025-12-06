<script setup lang="ts">
import { Textarea } from '@/components/ui/textarea';
import { ChevronLeft, Wand2 } from 'lucide-vue-next';

interface Props {
    chapterNumber: number;
    prompt: string;
    isFinalChapter: boolean;
    isGenerating: boolean;
}

defineProps<Props>();

const emit = defineEmits<{
    (e: 'update:prompt', value: string): void;
    (e: 'update:isFinalChapter', value: boolean): void;
    (e: 'generate'): void;
    (e: 'back'): void;
}>();
</script>

<template>
    <div class="relative z-10 flex h-full flex-col p-8 pt-16 pb-6">
        <div class="flex-1">
            <!-- Header -->
            <div class="mb-6 text-center">
                <div class="mb-3">
                    <span class="inline-block rounded-full bg-amber-200/60 px-4 py-1 text-xs font-semibold uppercase tracking-widest text-amber-800 dark:bg-amber-300/60 dark:text-amber-900">
                        Chapter {{ chapterNumber }}
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
                    :model-value="prompt"
                    @update:model-value="emit('update:prompt', String($event))"
                    placeholder="The hero discovers a hidden door behind the waterfall..."
                    rows="5"
                    :disabled="isGenerating"
                    class="w-full resize-none bg-white/70 dark:bg-white/10 font-serif text-amber-950 dark:text-amber-900 placeholder:text-amber-500"
                />
                
                <div class="flex items-center gap-2">
                    <input
                        type="checkbox"
                        id="final-chapter"
                        :checked="isFinalChapter"
                        @change="emit('update:isFinalChapter', ($event.target as HTMLInputElement).checked)"
                        :disabled="isGenerating"
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
                @click="emit('back')"
                :disabled="isGenerating"
                class="flex cursor-pointer items-center gap-1 text-sm text-amber-700 hover:text-amber-900 transition-colors dark:text-amber-600 dark:hover:text-amber-800 disabled:opacity-50"
            >
                <ChevronLeft class="h-4 w-4" />
                <span>Back</span>
            </button>
            
            <button 
                @click="emit('generate')"
                :disabled="isGenerating"
                class="group flex cursor-pointer items-center gap-2 rounded-full bg-gradient-to-r from-amber-700 to-orange-700 px-5 py-2.5 text-sm font-semibold text-white shadow-lg transition-all duration-300 hover:shadow-xl hover:scale-105 active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed"
            >
                <Wand2 class="h-4 w-4" />
                <span>{{ isGenerating ? 'Creating...' : 'Create Chapter' }}</span>
            </button>
        </div>
    </div>
</template>

