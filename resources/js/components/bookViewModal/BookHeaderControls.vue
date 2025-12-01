<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuPortal,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { MoreVertical, X } from 'lucide-vue-next';

interface Props {
    hasBook: boolean;
    isEditing: boolean;
    isSaving: boolean;
    isDeleting: boolean;
    isPageTurning: boolean;
}

defineProps<Props>();

const emit = defineEmits<{
    (e: 'edit'): void;
    (e: 'delete'): void;
    (e: 'close'): void;
}>();
</script>

<template>
    <div class="pointer-events-none absolute inset-x-0 top-0 z-40 flex justify-end px-4 pt-4">
        <div class="pointer-events-auto flex items-center gap-2">
            <DropdownMenu v-if="hasBook">
                <DropdownMenuTrigger :as-child="true">
                    <Button
                        variant="ghost"
                        size="icon"
                        class="cursor-pointer rounded-full bg-white/70 p-2 text-amber-900 shadow-md backdrop-blur-sm transition-colors hover:bg-white/90 dark:bg-white/70 dark:text-amber-900 dark:hover:bg-white/90"
                        :disabled="isEditing || isSaving || isDeleting"
                    >
                        <MoreVertical class="h-5 w-5" />
                        <span class="sr-only">Story actions</span>
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuPortal>
                    <DropdownMenuContent align="end" class="z-[10001] w-48">
                        <DropdownMenuItem
                            @select="emit('edit')"
                            :disabled="isEditing || isSaving || isDeleting"
                        >
                            Edit Story
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            @select="emit('delete')"
                            :disabled="isSaving || isDeleting"
                            class="cursor-pointer text-red-600 focus:bg-red-50 focus:text-red-700 dark:text-red-400 dark:focus:bg-red-950/50 dark:focus:text-red-300"
                        >
                            Delete Story
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenuPortal>
            </DropdownMenu>
            <Button
                variant="ghost"
                size="icon"
                class="cursor-pointer rounded-full bg-white/70 p-2 text-amber-900 shadow-md backdrop-blur-sm transition-colors hover:bg-white/90 dark:bg-white/70 dark:text-amber-900 dark:hover:bg-white/90"
                @click="emit('close')"
                :disabled="isSaving || isDeleting || isPageTurning"
            >
                <X class="h-5 w-5" />
                <span class="sr-only">Close</span>
            </Button>
        </div>
    </div>
</template>

