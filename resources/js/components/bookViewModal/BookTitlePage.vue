<script setup lang="ts">
import { Sparkles, ChevronRight, Loader2, RefreshCw, Image, Pencil } from 'lucide-vue-next';

interface Props {
    coverImage: string | null;
    coverImageStatus: string | null;
    title: string;
    author: string | null;
    createdAt: string | null;
    isFading: boolean;
    isLoading: boolean;
}

defineProps<Props>();

const emit = defineEmits<{
    (e: 'continue'): void;
    (e: 'regenerateCover'): void;
    (e: 'editCoverImage'): void;
}>();
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

        <!-- No Cover Placeholder (failed or missing) -->
        <div 
            v-else-if="!coverImage"
            class="group/cover mb-6 w-full overflow-hidden rounded-lg shadow-lg bg-gradient-to-br from-amber-50 to-orange-50 dark:from-amber-900/20 dark:to-orange-900/20 h-48 flex flex-col items-center justify-center gap-4 p-8"
        >
            <Image class="h-12 w-12 text-amber-500 dark:text-amber-400" />
            <div class="text-center">
                <p class="text-sm text-amber-600 dark:text-amber-500 mb-4">
                    No cover image yet
                </p>
                <button
                    @click.stop="emit('regenerateCover')"
                    class="mx-auto flex items-center gap-1.5 rounded-full bg-amber-600 px-4 py-2 text-sm font-medium text-white transition-all duration-300 hover:bg-amber-700 hover:scale-105 active:scale-95"
                >
                    <RefreshCw class="h-4 w-4" />
                    <span>Generate cover</span>
                </button>
            </div>
        </div>

        <!-- Cover Image (2/3 page height, center-cropped) -->
        <div 
            v-else
            class="group/cover relative mb-6 w-full h-[66vh] overflow-hidden rounded-lg shadow-lg"
        >
            <img
                :src="coverImage"
                :alt="title"
                class="h-full w-full object-cover object-center"
            />
            <!-- Edit Cover Button -->
            <button
                @click.stop="emit('editCoverImage')"
                :disabled="coverImageStatus === 'pending'"
                class="absolute bottom-3 left-3 flex h-8 w-8 items-center justify-center rounded-full bg-black/50 text-white/90 backdrop-blur-sm transition-all duration-200 hover:bg-black/70 hover:scale-110 active:scale-95 disabled:cursor-not-allowed disabled:opacity-50 cursor-pointer"
                title="Edit cover image"
            >
                <Pencil class="h-4 w-4" />
            </button>
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
            :disabled="isFading || isLoading"
            class="group mt-6 flex cursor-pointer items-center gap-2 rounded-full bg-gradient-to-r from-amber-700 to-orange-700 px-5 py-2.5 text-sm font-semibold text-white shadow-lg transition-all duration-300 hover:shadow-xl hover:scale-105 active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:scale-100"
        >
            <span>Start Reading</span>
            <ChevronRight class="h-5 w-5 transition-transform group-hover:translate-x-1" />
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

