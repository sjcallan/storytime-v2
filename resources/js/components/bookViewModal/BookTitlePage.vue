<script setup lang="ts">
import { Sparkles, ChevronRight } from 'lucide-vue-next';

interface Props {
    coverImage: string | null;
    title: string;
    author: string | null;
    createdAt: string | null;
    isFading: boolean;
    isLoading: boolean;
}

defineProps<Props>();

const emit = defineEmits<{
    (e: 'continue'): void;
}>();
</script>

<template>
    <div 
        :class="[
            'relative z-10 flex h-full flex-col items-center px-6 pb-6 pt-16 text-center transition-opacity duration-500',
            isFading ? 'opacity-0' : 'opacity-100'
        ]"
    >
        <!-- Cover Image (full width, natural height) -->
        <div 
            v-if="coverImage"
            class="mb-6 w-full overflow-hidden rounded-lg shadow-lg"
        >
            <img
                :src="coverImage"
                :alt="title"
                class="h-auto w-full object-contain"
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

