<script setup lang="ts">
import { Sparkles, BookOpen, Wand2 } from 'lucide-vue-next';

interface Props {
    variant: 'sparkles' | 'book' | 'wand';
}

defineProps<Props>();
</script>

<template>
    <div class="flex h-full items-center justify-center p-12">
        <div class="text-center opacity-40">
            <div class="mx-auto mb-4 h-px w-24 bg-gradient-to-r from-transparent via-amber-600 to-transparent dark:via-amber-400" />
            <Sparkles v-if="variant === 'sparkles'" class="mx-auto h-10 w-10 text-amber-500 dark:text-amber-400" />
            <BookOpen v-else-if="variant === 'book'" class="mx-auto h-12 w-12 text-amber-500 dark:text-amber-400" />
            <Wand2 v-else-if="variant === 'wand'" class="mx-auto h-12 w-12 text-amber-500 dark:text-amber-400" />
            <div class="mx-auto mt-4 h-px w-24 bg-gradient-to-r from-transparent via-amber-600 to-transparent dark:via-amber-400" />
        </div>
    </div>
</template>

