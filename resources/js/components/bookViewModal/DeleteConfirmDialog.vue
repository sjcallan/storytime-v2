<script setup lang="ts">
import { Button } from '@/components/ui/button';

interface Props {
    visible: boolean;
}

defineProps<Props>();

const emit = defineEmits<{
    (e: 'cancel'): void;
    (e: 'confirm'): void;
}>();
</script>

<template>
    <Transition
        enter-active-class="transition-all duration-300 ease-out"
        enter-from-class="opacity-0 scale-75"
        enter-to-class="opacity-100 scale-100"
        leave-active-class="transition-all duration-200 ease-in"
        leave-from-class="opacity-100 scale-100"
        leave-to-class="opacity-0 scale-75"
    >
        <div 
            v-if="visible" 
            class="absolute inset-0 z-50 flex items-center justify-center rounded-2xl bg-black/50 backdrop-blur-sm"
        >
            <div class="mx-4 w-full max-w-sm animate-bounce-in rounded-2xl border-2 border-red-200 bg-white p-6 shadow-2xl dark:border-red-800 dark:bg-gray-900">
                <div class="mb-4 text-center">
                    <div class="mx-auto mb-3 flex h-16 w-16 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/30">
                        <span class="text-3xl">😱</span>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white">Delete this story?</h3>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                        This will permanently delete your story and all its chapters. This cannot be undone!
                    </p>
                </div>
                <div class="flex gap-3">
                    <Button
                        type="button"
                        variant="outline"
                        @click="emit('cancel')"
                        class="flex-1 cursor-pointer rounded-xl border-gray-200 text-gray-900 transition-all duration-200 hover:scale-[1.02] active:scale-[0.98] dark:border-gray-700 dark:text-white"
                    >
                        Keep It! 📚
                    </Button>
                    <Button
                        type="button"
                        @click="emit('confirm')"
                        class="flex-1 cursor-pointer rounded-xl bg-gradient-to-r from-red-500 to-rose-500 text-white transition-all duration-200 hover:scale-[1.02] hover:from-red-600 hover:to-rose-600 active:scale-[0.98]"
                    >
                        Delete Forever
                    </Button>
                </div>
            </div>
        </div>
    </Transition>
</template>

<style scoped>
@keyframes bounce-in {
    0% {
        opacity: 0;
        transform: scale(0.3) translateY(-20px);
    }
    50% {
        opacity: 1;
        transform: scale(1.05) translateY(0);
    }
    70% {
        transform: scale(0.95);
    }
    100% {
        transform: scale(1);
    }
}

.animate-bounce-in {
    animation: bounce-in 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
}
</style>

