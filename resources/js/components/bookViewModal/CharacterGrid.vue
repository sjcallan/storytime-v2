<script setup lang="ts">
import { User } from 'lucide-vue-next';
import type { Character } from './types';

interface Props {
    characters: Character[];
    selectedCharacterId: string | null;
}

defineProps<Props>();

const emit = defineEmits<{
    (e: 'selectCharacter', character: Character): void;
}>();

const getInitials = (name: string): string => {
    return name
        .split(' ')
        .map(word => word[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);
};

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
</script>

<template>
    <div class="relative z-10 flex h-full flex-col p-6 pt-16">
        <!-- Header -->
        <div class="mb-6 text-center">
            <h2 class="font-serif text-xl md:text-2xl font-bold text-amber-950 dark:text-amber-900 tracking-tight">
                Characters
            </h2>
            <p class="mt-1 text-sm text-amber-700 dark:text-amber-700">
                Tap to learn more
            </p>
        </div>

        <!-- Character Grid -->
        <div 
            v-if="characters.length > 0"
            class="flex-1 overflow-y-auto scrollbar-thin scrollbar-thumb-amber-300 scrollbar-track-transparent p-2"
        >
            <div class="grid grid-cols-3 gap-3 pb-4">
                <button
                    v-for="character in characters"
                    :key="character.id"
                    @click="emit('selectCharacter', character)"
                    :class="[
                        'group flex flex-col items-center p-2 rounded-xl transition-all duration-200 cursor-pointer',
                        'hover:bg-amber-200/50 dark:hover:bg-amber-200/30',
                        'focus:outline-none focus:ring-2 focus:ring-amber-500/50',
                        selectedCharacterId === character.id 
                            ? 'bg-amber-200/60 dark:bg-amber-200/40 ring-2 ring-amber-500/40' 
                            : ''
                    ]"
                >
                    <!-- Avatar Circle - Full width of grid block -->
                    <div 
                        :class="[
                            'relative w-full aspect-square rounded-full overflow-hidden',
                            'ring-2 ring-amber-300 dark:ring-amber-400',
                            'shadow-md transition-transform duration-200 group-hover:scale-[1.02]',
                        ]"
                    >
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
                                class="text-xl md:text-2xl font-bold text-white drop-shadow"
                            >
                                {{ getInitials(character.name) }}
                            </span>
                            <User v-else class="h-8 w-8 text-white/80" />
                        </div>
                    </div>
                    
                    <!-- Character Name -->
                    <span 
                        class="mt-2 text-sm md:text-base font-medium text-amber-900 dark:text-amber-800 text-center line-clamp-2"
                    >
                        {{ character.name }}
                    </span>
                </button>
            </div>
        </div>

        <!-- Empty State -->
        <div 
            v-else
            class="flex-1 flex flex-col items-center justify-center text-center px-4"
        >
            <div class="h-16 w-16 rounded-full bg-amber-200/50 flex items-center justify-center mb-4">
                <User class="h-8 w-8 text-amber-600/60" />
            </div>
            <p class="text-amber-700 dark:text-amber-700 text-sm">
                No characters yet
            </p>
        </div>
    </div>
</template>

