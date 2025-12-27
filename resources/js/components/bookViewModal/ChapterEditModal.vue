<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Textarea } from '@/components/ui/textarea';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { X, Sparkles, PenLine, RefreshCw } from 'lucide-vue-next';
import type { BookType, Chapter } from './types';
import { getChapterLabel, isSceneBasedBook } from './types';

interface Props {
    visible: boolean;
    chapter: Chapter | null;
    bookType?: BookType;
    isProcessing?: boolean;
    error?: string | null;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'submitSmallChanges', instructions: string): void;
    (e: 'submitRewrite', newPrompt: string): void;
    (e: 'textareaFocused', value: boolean): void;
}>();

// Toggle state: 'small' or 'rewrite'
const editMode = ref<'small' | 'rewrite'>('small');

// Form values
const smallChangesInstructions = ref('');
const rewritePrompt = ref('');

// Initialize rewrite prompt from chapter's user_prompt when chapter changes
watch(() => props.chapter, (newChapter) => {
    if (newChapter) {
        rewritePrompt.value = newChapter.user_prompt || '';
    }
}, { immediate: true });

// Reset form when modal closes
watch(() => props.visible, (visible) => {
    if (!visible) {
        smallChangesInstructions.value = '';
        editMode.value = 'small';
    }
});

const chapterLabel = computed(() => getChapterLabel(props.bookType));
const isScript = computed(() => isSceneBasedBook(props.bookType));

const canSubmit = computed(() => {
    if (props.isProcessing) {
        return false;
    }
    if (editMode.value === 'small') {
        // Backend requires at least 5 characters
        return smallChangesInstructions.value.trim().length >= 5;
    }
    return true; // Rewrite can be submitted even with empty prompt (AI will decide)
});

const instructionsCharCount = computed(() => smallChangesInstructions.value.trim().length);
const showCharCountWarning = computed(() => {
    return editMode.value === 'small' && 
           instructionsCharCount.value > 0 && 
           instructionsCharCount.value < 5;
});

const handleSubmit = () => {
    if (!canSubmit.value) {
        return;
    }
    
    if (editMode.value === 'small') {
        emit('submitSmallChanges', smallChangesInstructions.value.trim());
    } else {
        emit('submitRewrite', rewritePrompt.value.trim());
    }
};

const handleBackdropClick = () => {
    if (!props.isProcessing) {
        emit('close');
    }
};
</script>

<template>
    <Transition
        enter-active-class="transition-all duration-300 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition-all duration-200 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div 
            v-if="visible" 
            class="absolute inset-0 z-50 flex items-center justify-center rounded-2xl bg-black/60 backdrop-blur-sm"
            @click.self="handleBackdropClick"
        >
            <Transition
                enter-active-class="transition-all duration-300 ease-out"
                enter-from-class="opacity-0 scale-90 translate-y-4"
                enter-to-class="opacity-100 scale-100 translate-y-0"
                leave-active-class="transition-all duration-200 ease-in"
                leave-from-class="opacity-100 scale-100 translate-y-0"
                leave-to-class="opacity-0 scale-90 translate-y-4"
            >
                <div 
                    v-if="visible"
                    class="mx-4 w-full max-w-md rounded-2xl border-2 border-amber-200 bg-amber-50 p-6 shadow-2xl dark:border-amber-700 dark:bg-amber-950/95"
                >
                    <!-- Close button -->
                    <button
                        v-if="!isProcessing"
                        @click="emit('close')"
                        class="absolute top-4 right-4 rounded-full p-1.5 text-amber-700 transition-colors hover:bg-amber-200/50 dark:text-amber-400 dark:hover:bg-amber-800/50"
                    >
                        <X class="h-5 w-5" />
                    </button>

                    <!-- Header -->
                    <div class="mb-6 text-center">
                        <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-900/50">
                            <PenLine class="h-7 w-7 text-amber-700 dark:text-amber-400" />
                        </div>
                        <h3 class="font-serif text-xl font-bold text-amber-900 dark:text-amber-100">
                            Edit {{ chapterLabel }} {{ chapter?.sort }}
                        </h3>
                        <p class="mt-1 text-sm text-amber-700 dark:text-amber-400">
                            {{ chapter?.title || `${chapterLabel} ${chapter?.sort}` }}
                        </p>
                    </div>

                    <!-- Toggle Switch -->
                    <div class="mb-5 flex rounded-xl bg-amber-100 p-1 dark:bg-amber-900/50">
                        <button
                            type="button"
                            @click="editMode = 'small'"
                            :disabled="isProcessing"
                            :class="[
                                'flex-1 rounded-lg px-4 py-2.5 text-sm font-medium transition-all duration-200',
                                editMode === 'small'
                                    ? 'bg-white text-amber-900 shadow-sm dark:bg-amber-800 dark:text-amber-100'
                                    : 'text-amber-700 hover:text-amber-900 dark:text-amber-400 dark:hover:text-amber-200',
                                isProcessing ? 'cursor-not-allowed opacity-50' : 'cursor-pointer'
                            ]"
                        >
                            <span class="flex items-center justify-center gap-1.5">
                                <PenLine class="h-4 w-4" />
                                Small changes
                            </span>
                        </button>
                        <button
                            type="button"
                            @click="editMode = 'rewrite'"
                            :disabled="isProcessing"
                            :class="[
                                'flex-1 rounded-lg px-4 py-2.5 text-sm font-medium transition-all duration-200',
                                editMode === 'rewrite'
                                    ? 'bg-white text-amber-900 shadow-sm dark:bg-amber-800 dark:text-amber-100'
                                    : 'text-amber-700 hover:text-amber-900 dark:text-amber-400 dark:hover:text-amber-200',
                                isProcessing ? 'cursor-not-allowed opacity-50' : 'cursor-pointer'
                            ]"
                        >
                            <span class="flex items-center justify-center gap-1.5">
                                <RefreshCw class="h-4 w-4" />
                                Completely rewrite
                            </span>
                        </button>
                    </div>

                    <!-- Small Changes Form -->
                    <div v-if="editMode === 'small'" class="space-y-4">
                        <div>
                            <label class="mb-2 block text-sm font-medium text-amber-800 dark:text-amber-300">
                                What would you like to change?
                            </label>
                            <Textarea
                                v-model="smallChangesInstructions"
                                :disabled="isProcessing"
                                placeholder="e.g., Make the dialogue more dramatic, add more description of the setting, change the ending to be more suspenseful..."
                                rows="4"
                                class="w-full resize-none border-amber-300 bg-white font-serif text-amber-900 placeholder:text-amber-500 focus:border-amber-500 focus:ring-amber-500/30 dark:border-amber-700 dark:bg-amber-900/50 dark:text-amber-100 dark:placeholder:text-amber-600"
                                @focus="emit('textareaFocused', true)"
                                @blur="emit('textareaFocused', false)"
                            />
                            <div class="mt-1.5 flex items-center justify-between gap-2">
                                <p class="text-xs text-amber-600 dark:text-amber-500">
                                    Describe the changes you'd like to make. The AI will modify the existing content while keeping the overall story intact.
                                </p>
                                <span 
                                    v-if="showCharCountWarning" 
                                    class="shrink-0 text-xs text-orange-600 dark:text-orange-400"
                                >
                                    {{ 5 - instructionsCharCount }} more chars needed
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Rewrite Form -->
                    <div v-else class="space-y-4">
                        <div>
                            <label class="mb-2 block text-sm font-medium text-amber-800 dark:text-amber-300">
                                Your {{ isScript ? 'scene' : 'story' }} prompt
                            </label>
                            <Textarea
                                v-model="rewritePrompt"
                                :disabled="isProcessing"
                                :placeholder="isScript 
                                    ? 'Describe what happens in this scene...' 
                                    : 'Describe what adventure awaits...'"
                                rows="4"
                                class="w-full resize-none border-amber-300 bg-white font-serif text-amber-900 placeholder:text-amber-500 focus:border-amber-500 focus:ring-amber-500/30 dark:border-amber-700 dark:bg-amber-900/50 dark:text-amber-100 dark:placeholder:text-amber-600"
                                @focus="emit('textareaFocused', true)"
                                @blur="emit('textareaFocused', false)"
                            />
                            <p class="mt-1.5 text-xs text-amber-600 dark:text-amber-500">
                                This will completely replace the current {{ chapterLabel.toLowerCase() }} with a new one based on your prompt. Leave empty for a surprise!
                            </p>
                        </div>
                    </div>

                    <!-- Error Message -->
                    <div 
                        v-if="error" 
                        class="mt-4 rounded-lg bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/30 dark:text-red-300"
                    >
                        {{ error }}
                    </div>

                    <!-- Action Buttons -->
                    <div class="mt-6 flex gap-3">
                        <Button
                            type="button"
                            variant="outline"
                            @click="emit('close')"
                            :disabled="isProcessing"
                            class="flex-1 cursor-pointer rounded-xl border-amber-300 text-amber-800 transition-all duration-200 hover:bg-amber-100 hover:scale-[1.02] active:scale-[0.98] dark:border-amber-700 dark:text-amber-200 dark:hover:bg-amber-900/50"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            @click="handleSubmit"
                            :disabled="!canSubmit || isProcessing"
                            class="flex-1 cursor-pointer rounded-xl bg-gradient-to-r from-amber-600 to-orange-500 text-white transition-all duration-200 hover:scale-[1.02] hover:from-amber-700 hover:to-orange-600 active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:scale-100"
                        >
                            <span class="flex items-center justify-center gap-2">
                                <Spinner v-if="isProcessing" class="h-4 w-4" />
                                <Sparkles v-else class="h-4 w-4" />
                                <span>{{ isProcessing 
                                    ? (editMode === 'small' ? 'Updating...' : 'Rewriting...') 
                                    : (editMode === 'small' ? 'Apply Changes' : 'Rewrite ' + chapterLabel) 
                                }}</span>
                            </span>
                        </Button>
                    </div>
                </div>
            </Transition>
        </div>
    </Transition>
</template>

