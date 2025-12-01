<script setup lang="ts">
import { BookOpen, ChevronRight } from 'lucide-vue-next';
import { computed } from 'vue';

interface Props {
    bookId: string | null;
    coverImage: string | null;
    title: string;
    author: string | null;
    isPageTurning: boolean;
    isCoverFading: boolean;
    loading: boolean;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'open'): void;
}>();

const gradientColors = computed(() => {
    if (!props.bookId) {
        return 'bg-gradient-to-br from-violet-600 to-violet-300';
    }
    
    const colors = [
        'bg-gradient-to-br from-violet-600 to-violet-300',
        'bg-gradient-to-br from-blue-600 to-blue-300',
        'bg-gradient-to-br from-emerald-600 to-emerald-300',
        'bg-gradient-to-br from-amber-600 to-amber-300',
        'bg-gradient-to-br from-rose-600 to-rose-300',
        'bg-gradient-to-br from-pink-600 to-pink-300',
        'bg-gradient-to-br from-indigo-600 to-indigo-300',
        'bg-gradient-to-br from-cyan-600 to-cyan-300',
        'bg-gradient-to-br from-orange-600 to-orange-300',
        'bg-gradient-to-br from-teal-600 to-teal-300',
    ];
    
    const hash = props.bookId.split('').reduce((acc, char) => acc + char.charCodeAt(0), 0);
    return colors[hash % colors.length];
});
</script>

<template>
    <div class="book-closed-view relative h-full w-full">
        <!-- During expansion: show two-page layout with cover fading on right -->
        <template v-if="isPageTurning">
            <div class="relative flex h-full w-full">
                <!-- Left Side: Blank decorative page -->
                <div class="relative w-1/2 h-full bg-amber-50 dark:bg-amber-100 overflow-hidden">
                    <div class="absolute inset-0 opacity-[0.08]" style="background-image: url('data:image/svg+xml,%3Csvg viewBox=\'0 0 200 200\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cfilter id=\'noise\'%3E%3CfeTurbulence type=\'fractalNoise\' baseFrequency=\'0.5\' numOctaves=\'2\' stitchTiles=\'stitch\'/%3E%3C/filter%3E%3Crect width=\'100%25\' height=\'100%25\' filter=\'url(%23noise)\'/%3E%3C/svg%3E');" />
                    <div class="absolute inset-y-8 left-4 w-px bg-amber-300/60 dark:bg-amber-400/50" />
                    <div class="absolute inset-y-8 left-8 w-px bg-amber-300/40 dark:bg-amber-400/30" />
                </div>

                <!-- Book Spine / Center Seam -->
                <div class="pointer-events-none absolute left-1/2 top-0 bottom-0 z-30 w-24 -translate-x-1/2">
                    <div class="h-full w-full bg-gradient-to-r from-transparent via-amber-950/20 to-transparent" />
                    <div class="absolute inset-y-0 left-1/2 w-10 -translate-x-1/2 bg-gradient-to-r from-transparent via-amber-950/25 to-transparent" />
                    <div class="absolute inset-y-0 left-1/2 w-4 -translate-x-1/2 bg-gradient-to-r from-transparent via-amber-950/30 to-transparent" />
                    <div class="absolute inset-y-0 left-1/2 w-px -translate-x-1/2 bg-gradient-to-b from-amber-200/5 via-amber-100/20 to-amber-200/5" />
                </div>

                <!-- Right Side: Cover fading out over blank page -->
                <div class="relative w-1/2 h-full overflow-hidden">
                    <!-- Blank page underneath -->
                    <div class="absolute inset-0 bg-amber-50 dark:bg-amber-100">
                        <div class="absolute inset-0 opacity-[0.08]" style="background-image: url('data:image/svg+xml,%3Csvg viewBox=\'0 0 200 200\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cfilter id=\'noise\'%3E%3CfeTurbulence type=\'fractalNoise\' baseFrequency=\'0.5\' numOctaves=\'2\' stitchTiles=\'stitch\'/%3E%3C/filter%3E%3Crect width=\'100%25\' height=\'100%25\' filter=\'url(%23noise)\'/%3E%3C/svg%3E');" />
                        <div class="absolute inset-y-8 right-4 w-px bg-amber-300/60 dark:bg-amber-400/50" />
                        <div class="absolute inset-y-8 right-8 w-px bg-amber-300/40 dark:bg-amber-400/30" />
                        <div class="absolute inset-y-0 left-0 w-6 bg-gradient-to-r from-amber-900/10 to-transparent" />
                    </div>
                    
                    <!-- Cover image that fades out -->
                    <div 
                        :class="[
                            'absolute inset-0 transition-opacity duration-600',
                            isCoverFading ? 'opacity-0' : 'opacity-100'
                        ]"
                        style="transition-duration: 550ms;"
                    >
                        <img
                            v-if="coverImage"
                            :src="coverImage"
                            class="h-full w-full object-cover"
                            alt="Book cover"
                        />
                        <div
                            v-else-if="bookId"
                            class="absolute inset-0 flex items-center justify-center p-8"
                            :class="gradientColors"
                        >
                            <h2 class="text-3xl md:text-4xl font-bold text-center text-white drop-shadow-lg"
                                style="text-shadow: 0 2px 8px rgba(0,0,0,0.3)">
                                {{ title }}
                            </h2>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <!-- Initial state: Full Cover with Overlay -->
        <template v-else>
            <div class="cover-page relative h-full w-full overflow-hidden">
                <!-- Cover Image -->
                <div class="absolute inset-0">
                    <img
                        v-if="coverImage"
                        :src="coverImage"
                        class="h-full w-full object-cover"
                        alt="Book cover"
                    />
                    <div
                        v-else-if="bookId"
                        class="absolute inset-0 flex items-center justify-center p-8"
                        :class="gradientColors"
                    >
                        <h2 class="text-3xl md:text-4xl font-bold text-center text-white drop-shadow-lg"
                            style="text-shadow: 0 2px 8px rgba(0,0,0,0.3)">
                            {{ title }}
                        </h2>
                    </div>
                </div>
                
                <!-- Gradient Overlay for Text Legibility -->
                <div class="absolute inset-x-0 bottom-0 h-2/5 bg-gradient-to-t from-black/90 via-black/60 to-transparent" />
                
                <!-- Title, Author & Begin Button Overlay -->
                <div class="absolute inset-x-0 bottom-0 p-6 md:p-8 text-white">
                    <h1 class="mb-2 font-serif text-2xl md:text-4xl font-bold tracking-tight drop-shadow-lg line-clamp-2">
                        {{ title }}
                    </h1>
                    <p v-if="author" class="mb-6 font-serif text-sm md:text-base italic text-white/80 drop-shadow">
                        by {{ author }}
                    </p>
                    <button 
                        @click="emit('open')"
                        :disabled="isPageTurning || loading"
                        class="group flex cursor-pointer items-center gap-2 rounded-full bg-white/20 px-4 py-2 md:px-6 md:py-3 text-sm md:text-base font-semibold text-white backdrop-blur-sm transition-all duration-300 hover:bg-white/30 hover:scale-105 active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <BookOpen class="h-4 w-4 md:h-5 md:w-5 transition-transform group-hover:scale-110" />
                        <span>Begin Reading</span>
                        <ChevronRight class="h-4 w-4 md:h-5 md:w-5 transition-transform group-hover:translate-x-1" />
                    </button>
                </div>
            </div>
        </template>
    </div>
</template>

<style scoped>
.cover-page {
    transform-style: preserve-3d;
    transition: transform 0.8s cubic-bezier(0.645, 0.045, 0.355, 1);
    box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
}
</style>

