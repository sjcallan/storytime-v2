<script setup lang="ts">
import { MagicalSparklesLoader } from '@/components/ui/magical-book-loader';

interface Props {
    message: string;
}

defineProps<Props>();
</script>

<template>
    <div class="absolute inset-0 z-40 flex items-center justify-center bg-amber-50/90 dark:bg-amber-100/90 backdrop-blur-sm">
        <div class="text-center space-y-6">
            <MagicalSparklesLoader 
                size="xl" 
                color="text-amber-500" 
                accent-color="text-orange-400"
                class="mx-auto"
            />
            <p class="text-xl font-serif font-semibold text-amber-900 dark:text-amber-800">
                {{ message }}
            </p>
            <slot name="subtitle" />
        </div>
    </div>
</template>

