<script setup lang="ts">
import { computed } from 'vue';
import { ImageIcon, Plus, Sparkles } from 'lucide-vue-next';
import { Spinner } from '@/components/ui/spinner';
import type { Image } from './types';

interface Props {
    images: Image[];
    selectedImageId: string | null;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'selectImage', image: Image): void;
    (e: 'createImage'): void;
}>();

// Filter to only show complete images with valid URLs
const galleryImages = computed(() => {
    return props.images.filter(img => 
        img.status === 'complete' && 
        img.full_url && 
        img.full_url.trim() !== ''
    );
});

// Pending/processing images to show as generating
const generatingImages = computed(() => {
    return props.images.filter(img => 
        img.status === 'pending' || img.status === 'processing'
    );
});

// Get display label for image type
const getImageTypeLabel = (type: Image['type']): string => {
    switch (type) {
        case 'book_cover':
            return 'Cover';
        case 'character_portrait':
            return 'Portrait';
        case 'chapter_header':
            return 'Header';
        case 'chapter_inline':
            return 'Illustration';
        case 'manual':
            return 'Custom';
        default:
            return 'Image';
    }
};

// Generate a gradient background for loading/error states
const getPlaceholderGradient = (imageId: string): string => {
    const gradients = [
        'from-violet-400 to-purple-500',
        'from-blue-400 to-cyan-500',
        'from-emerald-400 to-teal-500',
        'from-amber-400 to-orange-500',
        'from-rose-400 to-pink-500',
        'from-indigo-400 to-blue-500',
        'from-fuchsia-400 to-pink-500',
        'from-sky-400 to-blue-500',
    ];
    
    const hash = imageId.split('').reduce((acc, char) => acc + char.charCodeAt(0), 0);
    return gradients[hash % gradients.length];
};
</script>

<template>
    <div class="relative z-10 flex h-full flex-col p-6 pt-16">
        <!-- Header -->
        <div class="mb-4 text-center">
            <h2 class="font-serif text-xl md:text-2xl font-bold text-amber-950 dark:text-amber-900 tracking-tight">
                Image Gallery
            </h2>
            <p class="mt-1 text-sm text-amber-700 dark:text-amber-700">
                {{ galleryImages.length }} {{ galleryImages.length === 1 ? 'image' : 'images' }} in this story
            </p>
        </div>

        <!-- Create New Image Button -->
        <button
            @click="emit('createImage')"
            class="mb-4 flex items-center justify-center gap-2 rounded-xl bg-linear-to-r from-purple-500 to-indigo-600 px-4 py-3 text-sm font-medium text-white shadow-md transition-all duration-200 hover:scale-[1.02] hover:shadow-lg active:scale-[0.98] cursor-pointer"
        >
            <Plus class="h-4 w-4" />
            <span>Create New Image</span>
            <Sparkles class="h-4 w-4" />
        </button>

        <!-- Image Grid -->
        <div 
            v-if="galleryImages.length > 0 || generatingImages.length > 0"
            class="flex-1 overflow-y-auto scrollbar-thin scrollbar-thumb-amber-300 scrollbar-track-transparent p-2"
        >
            <div class="grid grid-cols-2 gap-3 pb-4">
                <!-- Generating Images (show first as they're in progress) -->
                <button
                    v-for="image in generatingImages"
                    :key="'generating-' + image.id"
                    @click="emit('selectImage', image)"
                    :class="[
                        'group relative aspect-square overflow-hidden rounded-xl cursor-pointer transition-all duration-200',
                        'hover:ring-2 hover:ring-purple-400',
                        'focus:outline-none focus:ring-2 focus:ring-purple-400',
                        selectedImageId === image.id 
                            ? 'ring-2 ring-purple-500 shadow-lg' 
                            : 'ring-1 ring-purple-300 dark:ring-purple-500',
                        'bg-linear-to-br',
                        getPlaceholderGradient(image.id)
                    ]"
                >
                    <div class="h-full w-full flex flex-col items-center justify-center gap-2">
                        <Spinner class="h-8 w-8 text-white/80" />
                        <span class="text-xs font-medium text-white/80 px-2 text-center">
                            Generating...
                        </span>
                    </div>
                    
                    <!-- Image type badge -->
                    <div class="absolute bottom-1.5 left-1.5 rounded-full bg-purple-500/80 px-2 py-0.5 text-xs font-medium text-white/90 backdrop-blur-sm">
                        <Sparkles class="inline h-3 w-3 mr-1" />
                        Creating
                    </div>
                    
                    <!-- Selected indicator -->
                    <div 
                        v-if="selectedImageId === image.id"
                        class="absolute inset-0 border-4 border-purple-500 rounded-xl pointer-events-none"
                    />
                </button>

                <!-- Completed Images -->
                <button
                    v-for="image in galleryImages"
                    :key="image.id"
                    @click="emit('selectImage', image)"
                    :class="[
                        'group relative aspect-square overflow-hidden rounded-xl transition-all duration-200 cursor-pointer',
                        'hover:ring-2 hover:ring-amber-500/50',
                        'focus:outline-none focus:ring-2 focus:ring-amber-500/50',
                        selectedImageId === image.id 
                            ? 'ring-2 ring-amber-500 shadow-lg' 
                            : 'ring-1 ring-amber-200 dark:ring-amber-400'
                    ]"
                >
                    <!-- Image thumbnail -->
                    <img
                        v-if="image.full_url"
                        :src="image.full_url"
                        :alt="image.prompt || 'Story image'"
                        class="h-full w-full object-cover transition-transform duration-200 group-hover:scale-105"
                        loading="lazy"
                    />
                    <!-- Loading/Error state -->
                    <div
                        v-else
                        :class="[
                            'h-full w-full flex items-center justify-center bg-linear-to-br',
                            getPlaceholderGradient(image.id)
                        ]"
                    >
                        <Spinner v-if="image.status === 'pending' || image.status === 'processing'" class="h-8 w-8 text-white/80" />
                        <ImageIcon v-else class="h-8 w-8 text-white/80" />
                    </div>
                    
                    <!-- Image type badge -->
                    <div class="absolute bottom-1.5 left-1.5 rounded-full bg-black/60 px-2 py-0.5 text-xs font-medium text-white/90 backdrop-blur-sm">
                        {{ getImageTypeLabel(image.type) }}
                    </div>
                    
                    <!-- Selected indicator -->
                    <div 
                        v-if="selectedImageId === image.id"
                        class="absolute inset-0 border-4 border-amber-500 rounded-xl pointer-events-none"
                    />
                </button>
            </div>
        </div>

        <!-- Empty State -->
        <div 
            v-else
            class="flex-1 flex flex-col items-center justify-center text-center px-4"
        >
            <div class="h-16 w-16 rounded-full bg-amber-200/50 flex items-center justify-center mb-4">
                <ImageIcon class="h-8 w-8 text-amber-600/60" />
            </div>
            <p class="text-amber-700 dark:text-amber-700 text-sm">
                No images in this story yet
            </p>
            <p class="text-amber-600 dark:text-amber-600 text-xs mt-1">
                Click "Create New Image" to generate one!
            </p>
        </div>
    </div>
</template>
