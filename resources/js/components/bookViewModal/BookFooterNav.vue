<script setup lang="ts">
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';

interface Props {
    currentSpreadIndex: number;
    totalSpreads: number;
    hasPrevSpread: boolean;
    hasNextSpread: boolean;
    currentChapterNumber: number;
    totalChapters: number;
    isFinalChapter: boolean;
    isLoadingChapter: boolean;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'previous'): void;
    (e: 'next'): void;
}>();

const getPreviousLabel = () => {
    if (props.hasPrevSpread) {
        return 'Previous';
    }
    if (props.currentChapterNumber > 1) {
        return 'Prev Chapter';
    }
    return 'Title Page';
};

const getNextLabel = () => {
    if (props.hasNextSpread) {
        return 'Next';
    }
    if (props.isFinalChapter) {
        return 'The End';
    }
    if (props.currentChapterNumber < props.totalChapters) {
        return 'Next Chapter';
    }
    return 'Continue Story';
};
</script>

<template>
    <div 
        class="absolute bottom-0 left-0 right-0 z-40 flex items-center justify-between border-t border-amber-300/50 bg-amber-50/95 px-8 py-4 backdrop-blur-sm dark:border-amber-400/30 dark:bg-amber-100/95"
    >
        <button 
            @click.stop="emit('previous')"
            class="flex cursor-pointer items-center gap-2 text-base text-amber-700 hover:text-amber-900 transition-colors dark:text-amber-600 dark:hover:text-amber-800"
        >
            <ChevronLeft class="h-5 w-5" />
            <span>{{ getPreviousLabel() }}</span>
        </button>
        
        <!-- Page indicator -->
        <span class="text-sm text-amber-600 dark:text-amber-500">
            {{ currentSpreadIndex + 1 }} / {{ totalSpreads }}
        </span>
        
        <button 
            @click="emit('next')"
            :disabled="isLoadingChapter"
            class="group flex cursor-pointer items-center gap-2 rounded-full bg-gradient-to-r from-amber-700 to-orange-700 px-5 py-2.5 text-sm font-semibold text-white shadow-md transition-all duration-300 hover:shadow-lg hover:scale-105 active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed"
        >
            <span>{{ getNextLabel() }}</span>
            <ChevronRight 
                v-if="hasNextSpread || !isFinalChapter" 
                class="h-5 w-5 transition-transform group-hover:translate-x-1" 
            />
        </button>
    </div>
</template>

