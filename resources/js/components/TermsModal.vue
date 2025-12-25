<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogScrollContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { privacy } from '@/routes';
import { Link } from '@inertiajs/vue3';
import { marked } from 'marked';
import { computed } from 'vue';

const props = defineProps<{
    content: string;
}>();

const open = defineModel<boolean>('open', { default: false });

const renderedContent = computed(() => {
    return marked.parse(props.content);
});
</script>

<template>
    <Dialog v-model:open="open">
        <DialogScrollContent
            class="max-h-[85vh] max-w-3xl overflow-y-auto border-[#19140020] bg-white dark:border-[#3E3E3A] dark:bg-[#161615]"
        >
            <DialogHeader class="border-b border-[#19140010] pb-4 dark:border-[#3E3E3A20]">
                <DialogTitle
                    class="text-2xl font-bold bg-gradient-to-r from-[#f53003] to-[#F8B803] bg-clip-text text-transparent"
                >
                    Terms of Use
                </DialogTitle>
                <DialogDescription class="text-[#706f6c] dark:text-[#A1A09A]">
                    Please review our terms before creating your account
                </DialogDescription>
            </DialogHeader>

            <div
                class="prose prose-sm prose-slate dark:prose-invert max-w-none py-4"
                v-html="renderedContent"
            />

            <DialogFooter
                class="flex-col gap-3 border-t border-[#19140010] pt-4 sm:flex-row sm:justify-between dark:border-[#3E3E3A20]"
            >
                <div class="text-sm text-[#706f6c] dark:text-[#A1A09A]">
                    Also see our
                    <Link
                        :href="privacy()"
                        class="text-[#f53003] hover:text-[#F8B803] transition-colors"
                        target="_blank"
                    >
                        Privacy Policy
                    </Link>
                </div>
                <DialogClose :as-child="true">
                    <Button
                        class="bg-gradient-to-r from-[#f53003] to-[#F8B803] text-white"
                    >
                        I Understand
                    </Button>
                </DialogClose>
            </DialogFooter>
        </DialogScrollContent>
    </Dialog>
</template>

<style scoped>
@reference "tailwindcss";

/* Custom prose styles for modal content */
:deep(.prose h1) {
    @apply mb-4 text-xl font-bold text-[#1b1b18] dark:text-[#EDEDEC];
}

:deep(.prose h2) {
    @apply mt-6 mb-3 text-lg font-semibold text-[#1b1b18] dark:text-[#EDEDEC];
}

:deep(.prose h3) {
    @apply mt-4 mb-2 text-base font-semibold text-[#1b1b18] dark:text-[#EDEDEC];
}

:deep(.prose p) {
    @apply text-sm text-[#706f6c] leading-relaxed dark:text-[#A1A09A];
}

:deep(.prose strong) {
    @apply text-[#1b1b18] dark:text-[#EDEDEC];
}

:deep(.prose ul),
:deep(.prose ol) {
    @apply text-sm text-[#706f6c] dark:text-[#A1A09A];
}

:deep(.prose li) {
    @apply my-0.5;
}

:deep(.prose hr) {
    @apply my-4 border-[#19140020] dark:border-[#3E3E3A];
}

:deep(.prose a) {
    @apply text-[#f53003] no-underline hover:text-[#F8B803] transition-colors;
}
</style>

