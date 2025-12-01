<script setup lang="ts">
import { ArrowLeft, User, Calendar, MapPin, Sparkles } from 'lucide-vue-next';
import type { Character } from './types';

interface Props {
    character: Character;
}

defineProps<Props>();

const emit = defineEmits<{
    (e: 'back'): void;
}>();

const getAvatarGradient = (characterId: string): string => {
    const gradients = [
        'from-violet-500 to-purple-600',
        'from-blue-500 to-cyan-600',
        'from-emerald-500 to-teal-600',
        'from-amber-500 to-orange-600',
        'from-rose-500 to-pink-600',
        'from-indigo-500 to-blue-600',
        'from-fuchsia-500 to-pink-600',
        'from-sky-500 to-blue-600',
    ];
    
    const hash = characterId.split('').reduce((acc, char) => acc + char.charCodeAt(0), 0);
    return gradients[hash % gradients.length];
};

const getInitials = (name: string): string => {
    return name
        .split(' ')
        .map(word => word[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);
};

const formatGender = (gender: string | null): string => {
    if (!gender) return '';
    return gender.charAt(0).toUpperCase() + gender.slice(1).toLowerCase();
};
</script>

<template>
    <div class="relative z-10 flex h-full flex-col overflow-hidden">
        <!-- Back Button -->
        <button
            @click="emit('back')"
            class="absolute top-4 left-4 z-20 flex items-center gap-1.5 rounded-full bg-amber-100/80 dark:bg-amber-200/80 px-3 py-1.5 text-sm font-medium text-amber-800 shadow-sm backdrop-blur-sm transition-all hover:bg-amber-200 hover:scale-105 active:scale-95"
        >
            <ArrowLeft class="h-4 w-4" />
            <span>Back</span>
        </button>

        <!-- Portrait Image Section (Top Half) -->
        <div class="relative h-1/2 w-full overflow-hidden">
            <img
                v-if="character.portrait_image"
                :src="character.portrait_image"
                :alt="character.name"
                class="h-full w-full object-cover"
            />
            <div
                v-else
                :class="[
                    'h-full w-full flex items-center justify-center bg-gradient-to-br',
                    getAvatarGradient(character.id)
                ]"
            >
                <span 
                    v-if="character.name"
                    class="text-6xl md:text-7xl font-bold text-white drop-shadow-lg"
                >
                    {{ getInitials(character.name) }}
                </span>
                <User v-else class="h-24 w-24 text-white/80" />
            </div>
            
            <!-- Gradient overlay for better text contrast -->
            <div class="absolute inset-x-0 bottom-0 h-16 bg-gradient-to-t from-amber-50 dark:from-amber-100 to-transparent" />
        </div>

        <!-- Character Details (Bottom Half) -->
        <div class="flex-1 overflow-y-auto px-6 py-4 scrollbar-thin scrollbar-thumb-amber-300 scrollbar-track-transparent">
            <!-- Name -->
            <h2 class="font-serif text-2xl md:text-3xl font-bold text-amber-950 dark:text-amber-900 tracking-tight mb-3">
                {{ character.name }}
            </h2>

            <!-- Quick Info Tags -->
            <div class="flex flex-wrap gap-2 mb-4">
                <span 
                    v-if="character.age"
                    class="inline-flex items-center gap-1.5 rounded-full bg-amber-200/60 dark:bg-amber-300/50 px-3 py-1 text-sm font-medium text-amber-800"
                >
                    <Calendar class="h-3.5 w-3.5" />
                    {{ character.age }} years old
                </span>
                <span 
                    v-if="character.gender"
                    class="inline-flex items-center gap-1.5 rounded-full bg-amber-200/60 dark:bg-amber-300/50 px-3 py-1 text-sm font-medium text-amber-800"
                >
                    <User class="h-3.5 w-3.5" />
                    {{ formatGender(character.gender) }}
                </span>
                <span 
                    v-if="character.nationality"
                    class="inline-flex items-center gap-1.5 rounded-full bg-amber-200/60 dark:bg-amber-300/50 px-3 py-1 text-sm font-medium text-amber-800"
                >
                    <MapPin class="h-3.5 w-3.5" />
                    {{ character.nationality }}
                </span>
            </div>

            <!-- Description -->
            <div v-if="character.description" class="mb-4">
                <h3 class="flex items-center gap-2 text-sm font-semibold text-amber-700 dark:text-amber-700 uppercase tracking-wide mb-2">
                    <Sparkles class="h-3.5 w-3.5" />
                    About
                </h3>
                <p class="font-serif text-amber-900 dark:text-amber-800 leading-relaxed">
                    {{ character.description }}
                </p>
            </div>

            <!-- Backstory -->
            <div v-if="character.backstory">
                <h3 class="flex items-center gap-2 text-sm font-semibold text-amber-700 dark:text-amber-700 uppercase tracking-wide mb-2">
                    <Sparkles class="h-3.5 w-3.5" />
                    Backstory
                </h3>
                <p class="font-serif text-amber-900 dark:text-amber-800 leading-relaxed">
                    {{ character.backstory }}
                </p>
            </div>

            <!-- Fallback if no details -->
            <div 
                v-if="!character.description && !character.backstory && !character.age && !character.gender"
                class="text-center py-6"
            >
                <p class="text-amber-600 dark:text-amber-700 italic">
                    This character's story is yet to be told...
                </p>
            </div>
        </div>
    </div>
</template>


