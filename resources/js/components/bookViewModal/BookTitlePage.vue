<script setup lang="ts">
import { computed, onMounted, onUnmounted } from 'vue';
import { Sparkles, ChevronRight, Loader2 } from 'lucide-vue-next';

interface Props {
    coverImage: string | null;
    coverImageStatus: string | null;
    title: string;
    author: string | null;
    createdAt: string | null;
    isFading: boolean;
    isLoading: boolean;
    isAwaitingChapterGeneration?: boolean;
    savedChapterNumber?: number | null;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'continue'): void;
}>();

const isButtonDisabled = computed(() => {
    return props.isFading || props.isLoading || props.isAwaitingChapterGeneration;
});

const buttonText = computed(() => {
    if (props.isAwaitingChapterGeneration) {
        return 'Creating Chapter...';
    }
    if (props.savedChapterNumber && props.savedChapterNumber > 1) {
        return `Continue Chapter ${props.savedChapterNumber}`;
    }
    return 'Start Reading';
});

const handleKeyPress = (event: KeyboardEvent): void => {
    if (event.key === 'ArrowRight' && !isButtonDisabled.value) {
        emit('continue');
    }
};

onMounted(() => {
    window.addEventListener('keydown', handleKeyPress);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeyPress);
});
</script>

<template>
    <div 
        :class="[
            'relative z-10 flex h-full flex-col items-center px-6 pb-6 pt-16 text-center transition-opacity duration-500',
            isFading ? 'opacity-0' : 'opacity-100'
        ]"
    >
        <!-- Cover Image Placeholder (when generating) -->
        <div 
            v-if="coverImageStatus === 'pending' && !coverImage"
            class="mb-6 w-full overflow-hidden rounded-lg shadow-lg bg-gradient-to-br from-violet-100 to-purple-100 dark:from-violet-900/20 dark:to-purple-900/20 aspect-[3/4] flex flex-col items-center justify-center gap-4 p-8"
        >
            <Loader2 class="h-12 w-12 text-violet-500 animate-spin" />
            <div class="text-center">
                <p class="text-lg font-semibold text-violet-700 dark:text-violet-400 mb-1">
                    Creating your cover...
                </p>
                <p class="text-sm text-violet-600 dark:text-violet-500">
                    This will just take a moment ✨
                </p>
            </div>
        </div>

        <!-- Cover Image (2/3 page height, center-cropped) -->
        <div 
            v-else-if="coverImage"
            class="mb-6 w-full h-[66vh] overflow-hidden rounded-lg shadow-lg"
        >
            <img
                :src="coverImage"
                :alt="title"
                class="h-full w-full object-cover object-center"
            />
        </div>

        <!-- Decorative Border -->
        <div class="mb-4 flex items-center gap-3">
            <div class="h-px w-12 bg-gradient-to-r from-transparent via-amber-700 to-transparent dark:via-amber-600"></div>
            <Sparkles class="h-4 w-4 text-amber-700 dark:text-amber-500 animate-spin-slow" />
            <div class="h-px w-12 bg-gradient-to-r from-transparent via-amber-700 to-transparent dark:via-amber-600"></div>
        </div>

        <!-- Title -->
        <h1 class="mb-3 font-serif text-2xl md:text-3xl lg:text-4xl font-bold text-amber-950 dark:text-amber-900 tracking-tight">
            {{ title }}
        </h1>

        <!-- Author -->
        <p v-if="author" class="mb-2 font-serif text-base md:text-lg italic text-amber-800 dark:text-amber-800">
            by {{ author }}
        </p>

        <!-- Created Date -->
        <p v-if="createdAt" class="font-serif text-xs text-amber-700 dark:text-amber-700">
            {{ createdAt }}
        </p>

        <!-- Decorative Bottom Border -->
        <div class="mt-4 flex items-center gap-3">
            <div class="h-px w-12 bg-gradient-to-r from-transparent via-amber-700 to-transparent dark:via-amber-600"></div>
            <Sparkles class="h-4 w-4 text-amber-700 dark:text-amber-500 animate-spin-slow" style="animation-delay: 1s" />
            <div class="h-px w-12 bg-gradient-to-r from-transparent via-amber-700 to-transparent dark:via-amber-600"></div>
        </div>

        <!-- Next Button -->
        <button 
            @click="emit('continue')"
            :disabled="isButtonDisabled"
            class="group mt-6 flex cursor-pointer items-center gap-2 rounded-full bg-gradient-to-r from-amber-700 to-orange-700 px-5 py-2.5 text-sm font-semibold text-white shadow-lg transition-all duration-300 hover:shadow-xl hover:scale-105 active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:scale-100"
        >
            <Loader2 v-if="isAwaitingChapterGeneration" class="h-5 w-5 animate-spin" />
            <span>{{ buttonText }}</span>
            <ChevronRight v-if="!isAwaitingChapterGeneration" class="h-5 w-5 transition-transform group-hover:translate-x-1" />
        </button>
    </div>
</template>

<style scoped>
@keyframes spinSlow {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

.animate-spin-slow {
    animation: spinSlow 8s linear infinite;
}
</style>

