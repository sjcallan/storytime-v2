<script setup lang="ts">
import { provide, computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import CreateStoryModal from '@/components/CreateStoryModal.vue';
import ProfileSwitcher from '@/components/ProfileSwitcher.vue';
import { useCreateStoryModal } from '@/composables/useCreateStoryModal';
import { dashboard } from '@/routes';
import { Link, usePage } from '@inertiajs/vue3';
import { BookOpen, Wand2, Sparkles } from 'lucide-vue-next';

const page = usePage();
const profiles = computed(() => page.props.auth.profiles || []);
const currentProfile = computed(() => page.props.auth.currentProfile);

// Use shared composable for modal state
const { isOpen: isCreateModalOpen, open: openCreateStoryModal } = useCreateStoryModal();

provide('currentProfile', currentProfile);
</script>

<template>
    <div class="flex min-h-screen w-full flex-col bg-background">
        <!-- Top Navigation Header -->
        <header
            class="sticky top-0 z-50 w-full border-b border-border/40 bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60"
        >
            <div class="flex h-16 w-full items-center justify-between px-4 sm:px-6">
                <!-- Left: Logo & Navigation -->
                <div class="flex items-center gap-8">
                    <!-- Logo -->
                    <Link :href="dashboard()" class="flex items-center">
                        <AppLogo />
                    </Link>

                    <!-- Navigation -->
                    <nav class="flex items-center gap-1">
                        <Link
                            :href="dashboard()"
                            class="flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium text-foreground/80 transition-colors hover:bg-accent hover:text-foreground"
                        >
                            <BookOpen class="h-4 w-4" />
                            My Stories
                        </Link>
                    </nav>
                </div>

                <!-- Right: Create Story Button & Profile Menu -->
                <div class="flex items-center gap-3">
                    <!-- Start a New Story Button -->
                    <button
                        @click="isCreateModalOpen = true"
                        class="magic-button group relative cursor-pointer overflow-hidden rounded-full bg-gradient-to-r from-violet-600 via-fuchsia-500 to-amber-500 px-5 py-2.5 text-sm font-semibold text-white shadow-lg transition-all duration-300 hover:scale-105 hover:shadow-[0_0_40px_rgba(167,139,250,0.5)] active:scale-95 sm:px-6 sm:text-base"
                    >
                        <!-- Animated gradient overlay -->
                        <span class="absolute inset-0 bg-gradient-to-r from-amber-500 via-fuchsia-500 to-violet-600 opacity-0 transition-opacity duration-500 group-hover:opacity-100" />
                        
                        <!-- Shimmer effect -->
                        <span class="absolute inset-0 -translate-x-full bg-gradient-to-r from-transparent via-white/30 to-transparent transition-transform duration-1000 group-hover:translate-x-full" />
                        
                        <!-- Sparkle particles -->
                        <span class="sparkle sparkle-1" />
                        <span class="sparkle sparkle-2" />
                        <span class="sparkle sparkle-3" />
                        <span class="sparkle sparkle-4" />
                        
                        <!-- Button content -->
                        <span class="relative flex items-center gap-2">
                            <Wand2 class="h-4 w-4 transition-transform duration-300 group-hover:rotate-12 group-hover:scale-110 sm:h-5 sm:w-5" />
                            <span class="hidden sm:inline">Start a New Story</span>
                            <span class="sm:hidden">New Story</span>
                            <Sparkles class="h-3 w-3 opacity-0 transition-all duration-300 group-hover:opacity-100 group-hover:animate-pulse sm:h-4 sm:w-4" />
                        </span>
                    </button>

                    <!-- Profile Switcher (includes settings & logout) -->
                    <ProfileSwitcher 
                        v-if="profiles.length > 0"
                        :profiles="profiles" 
                        :current-profile="currentProfile" 
                    />
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="flex h-full w-full flex-1 flex-col gap-4 px-4 py-6 sm:px-6">
            <slot />
        </main>

        <!-- Create Story Modal -->
        <CreateStoryModal
            v-model:is-open="isCreateModalOpen"
            :default-genre="null"
        />
    </div>
</template>

<style scoped>
/* Sparkle base styles */
.sparkle {
    position: absolute;
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: radial-gradient(circle, white 0%, transparent 70%);
    opacity: 0;
    pointer-events: none;
    filter: blur(0.5px);
}

/* Sparkle positions and animations */
.sparkle-1 {
    top: 15%;
    left: 15%;
}
.sparkle-2 {
    top: 25%;
    right: 20%;
}
.sparkle-3 {
    bottom: 25%;
    left: 30%;
}
.sparkle-4 {
    bottom: 20%;
    right: 15%;
}

/* Trigger sparkles on hover */
.magic-button:hover .sparkle-1 {
    animation: sparkle-float 1.2s ease-in-out infinite;
    animation-delay: 0s;
}
.magic-button:hover .sparkle-2 {
    animation: sparkle-float 1.4s ease-in-out infinite;
    animation-delay: 0.2s;
}
.magic-button:hover .sparkle-3 {
    animation: sparkle-float 1.1s ease-in-out infinite;
    animation-delay: 0.4s;
}
.magic-button:hover .sparkle-4 {
    animation: sparkle-float 1.3s ease-in-out infinite;
    animation-delay: 0.1s;
}

@keyframes sparkle-float {
    0%, 100% {
        opacity: 0;
        transform: scale(0) translateY(0);
    }
    20% {
        opacity: 1;
        transform: scale(1) translateY(-3px);
    }
    40% {
        opacity: 0.8;
        transform: scale(1.2) translateY(-6px);
    }
    60% {
        opacity: 0.6;
        transform: scale(0.8) translateY(-9px);
    }
    80% {
        opacity: 0.3;
        transform: scale(0.5) translateY(-12px);
    }
}

/* Ring pulse effect on hover */
.magic-button::before {
    content: '';
    position: absolute;
    inset: -3px;
    border-radius: 9999px;
    background: linear-gradient(45deg, #a78bfa, #f472b6, #fbbf24, #a78bfa);
    background-size: 300% 300%;
    opacity: 0;
    z-index: -1;
    transition: opacity 0.3s ease;
    animation: gradient-rotate 3s ease infinite;
}

.magic-button:hover::before {
    opacity: 1;
}

@keyframes gradient-rotate {
    0% {
        background-position: 0% 50%;
    }
    50% {
        background-position: 100% 50%;
    }
    100% {
        background-position: 0% 50%;
    }
}
</style>
