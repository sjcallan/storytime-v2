<script setup lang="ts">
import { provide, computed, ref } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import CreateStoryModal from '@/components/CreateStoryModal.vue';
import ProfileSwitcher from '@/components/ProfileSwitcher.vue';
import ThemeCustomizer from '@/components/ThemeCustomizer.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import {
    Sheet,
    SheetClose,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { useCreateStoryModal } from '@/composables/useCreateStoryModal';
import { getInitials } from '@/composables/useInitials';
import { useTheme } from '@/composables/useTheme';
import { dashboard } from '@/routes';
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    ChevronRight,
    LayoutGrid,
    LogOut,
    Menu,
    Palette,
    Settings,
    Sparkles,
    UserCircle,
    Wand2,
} from 'lucide-vue-next';
import { logout } from '@/routes';
import { edit as editProfile } from '@/routes/profile';
import { select as selectProfile } from '@/routes/profiles';

// Initialize theme system and get active theme
const { activeTheme } = useTheme();

const page = usePage();
const profiles = computed(() => page.props.auth.profiles || []);
const currentProfile = computed(() => page.props.auth.currentProfile);
const auth = computed(() => page.props.auth);
const mobileMenuOpen = ref(false);

// Use shared composable for modal state
const { isOpen: isCreateModalOpen, open: openCreateStoryModal } = useCreateStoryModal();

provide('currentProfile', currentProfile);

const openCreateStoryFromMobile = () => {
    mobileMenuOpen.value = false;
    // Small delay to let the sheet close animation start
    setTimeout(() => {
        isCreateModalOpen.value = true;
    }, 150);
};

const handleLogout = () => {
    mobileMenuOpen.value = false;
    router.flushAll();
};
</script>

<template>
    <div class="flex min-h-screen w-full flex-col bg-background">
        <!-- Top Navigation Header -->
        <header
            class="sticky top-0 z-50 w-full border-b border-border/40 bg-background/95 backdrop-blur supports-backdrop-filter:bg-background/60"
        >
            <div class="flex h-16 w-full items-center justify-between px-4 sm:px-6">
                <!-- Left: Logo & Navigation -->
                <div class="flex items-center gap-8">
                    <!-- Logo -->
                    <Link :href="dashboard()" class="flex items-center">
                        <AppLogo />
                    </Link>

                    <!-- Navigation (Desktop only) -->
                    <nav class="hidden items-center gap-1 md:flex">
                        <Link
                            :href="dashboard()"
                            class="flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium text-foreground/80 transition-colors hover:bg-accent hover:text-foreground"
                        >
                            Home
                        </Link>
                    </nav>
                </div>

                <!-- Right: Desktop items -->
                <div class="hidden items-center gap-3 md:flex">
                    <!-- Theme Customizer -->
                    <ThemeCustomizer />

                    <!-- Start a New Story Button -->
                    <button
                        @click="isCreateModalOpen = true"
                        class="magic-button group relative cursor-pointer overflow-hidden rounded-full bg-linear-to-r from-violet-600 via-fuchsia-500 to-amber-500 px-5 py-2.5 text-sm font-semibold text-white shadow-lg transition-all duration-300 hover:scale-105 hover:shadow-[0_0_40px_rgba(167,139,250,0.5)] active:scale-95 sm:px-6 sm:text-base"
                    >
                        <!-- Animated gradient overlay -->
                        <span class="absolute inset-0 bg-linear-to-r from-amber-500 via-fuchsia-500 to-violet-600 opacity-0 transition-opacity duration-500 group-hover:opacity-100" />
                        
                        <!-- Shimmer effect -->
                        <span class="absolute inset-0 -translate-x-full bg-linear-to-r from-transparent via-white/30 to-transparent transition-transform duration-1000 group-hover:translate-x-full" />
                        
                        <!-- Sparkle particles -->
                        <span class="sparkle sparkle-1" />
                        <span class="sparkle sparkle-2" />
                        <span class="sparkle sparkle-3" />
                        <span class="sparkle sparkle-4" />
                        
                        <!-- Button content -->
                        <span class="relative flex items-center gap-2">
                            <Wand2 class="h-4 w-4 transition-transform duration-300 group-hover:rotate-12 group-hover:scale-110 sm:h-5 sm:w-5" />
                            <span>New Story</span>
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

                <!-- Mobile: Hamburger Menu -->
                <div class="flex items-center md:hidden">
                    <Sheet v-model:open="mobileMenuOpen">
                        <SheetTrigger :as-child="true">
                            <Button variant="ghost" size="icon" class="h-10 w-10">
                                <Menu class="h-5 w-5" />
                            </Button>
                        </SheetTrigger>
                        <SheetContent side="right" class="w-[300px] overflow-hidden p-0">
                            <SheetTitle class="sr-only">Menu</SheetTitle>

                            <!-- Profile Section -->
                            <SheetHeader class="border-b border-border p-4">
                                <div class="flex items-center gap-3">
                                    <Avatar class="size-10 ring-2 ring-border">
                                        <AvatarImage
                                            v-if="currentProfile?.avatar"
                                            :src="currentProfile.avatar"
                                            :alt="currentProfile?.name"
                                            class="object-cover"
                                        />
                                        <AvatarFallback class="bg-linear-to-br from-violet-500 to-purple-600 text-sm font-medium text-white">
                                            {{ getInitials(currentProfile?.name || 'P') }}
                                        </AvatarFallback>
                                    </Avatar>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-semibold">
                                            {{ currentProfile?.name || 'No Profile' }}
                                        </p>
                                        <p class="text-xs text-muted-foreground">
                                            {{ currentProfile?.age_group_label || 'Adult' }}
                                        </p>
                                    </div>
                                </div>
                            </SheetHeader>

                            <!-- Menu Content -->
                            <div class="flex flex-col p-4">
                                <!-- Create Story CTA -->
                                <button
                                    @click="openCreateStoryFromMobile"
                                    class="magic-button group relative w-full cursor-pointer overflow-hidden rounded-xl bg-linear-to-r from-violet-600 via-fuchsia-500 to-amber-500 px-5 py-3 text-base font-semibold text-white shadow-lg transition-all duration-300 hover:shadow-[0_0_40px_rgba(167,139,250,0.5)] active:scale-[0.98]"
                                >
                                    <!-- Animated gradient overlay -->
                                    <span class="absolute inset-0 bg-linear-to-r from-amber-500 via-fuchsia-500 to-violet-600 opacity-0 transition-opacity duration-500 group-hover:opacity-100" />
                                    
                                    <!-- Shimmer effect -->
                                    <span class="absolute inset-0 -translate-x-full bg-linear-to-r from-transparent via-white/30 to-transparent transition-transform duration-1000 group-hover:translate-x-full" />
                                    
                                    <!-- Button content -->
                                    <span class="relative flex items-center justify-center gap-2">
                                        <Wand2 class="h-5 w-5" />
                                        <span>Start a New Story</span>
                                        <Sparkles class="h-4 w-4 animate-pulse" />
                                    </span>
                                </button>

                                <Separator class="my-4" />

                                <!-- Navigation Section -->
                                <p class="mb-2 text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                    Navigation
                                </p>
                                <nav class="space-y-1">
                                    <SheetClose :as-child="true">
                                        <Link
                                            :href="dashboard()"
                                            class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors hover:bg-accent"
                                        >
                                            <LayoutGrid class="h-5 w-5" />
                                            Home
                                        </Link>
                                    </SheetClose>
                                </nav>

                                <Separator class="my-4" />

                                <!-- Appearance Section -->
                                <p class="mb-2 text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                    Appearance
                                </p>
                                <div class="flex items-center gap-3 rounded-lg border border-border p-3">
                                    <!-- Theme preview card -->
                                    <div
                                        v-if="activeTheme"
                                        class="relative flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-md border border-border shadow-sm"
                                        :style="{
                                            backgroundColor: activeTheme.background_color,
                                            color: activeTheme.text_color,
                                        }"
                                    >
                                        <img
                                            v-if="activeTheme.background_image"
                                            :src="activeTheme.background_image"
                                            alt=""
                                            class="absolute inset-0 h-full w-full object-cover"
                                        />
                                        <span v-else class="text-xs font-bold">Aa</span>
                                    </div>
                                    <!-- Default theme icon -->
                                    <div
                                        v-else
                                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-md border border-border bg-muted"
                                    >
                                        <Palette class="h-5 w-5 text-muted-foreground" />
                                    </div>
                                    <!-- Theme name -->
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-medium">
                                            {{ activeTheme?.name || 'Default Theme' }}
                                        </p>
                                        <p v-if="activeTheme" class="text-xs text-muted-foreground">
                                            Custom theme
                                        </p>
                                        <p v-else class="text-xs text-muted-foreground">
                                            System colors
                                        </p>
                                    </div>
                                    <!-- Theme customizer trigger -->
                                    <ThemeCustomizer />
                                </div>

                                <Separator class="my-4" />

                                <!-- Account Section -->
                                <p class="mb-2 text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                    Account
                                </p>
                                <nav class="space-y-1">
                                    <SheetClose :as-child="true">
                                        <Link
                                            :href="selectProfile()"
                                            class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors hover:bg-accent"
                                        >
                                            <UserCircle class="h-5 w-5" />
                                            Switch Profile
                                        </Link>
                                    </SheetClose>
                                    <SheetClose :as-child="true">
                                        <Link
                                            :href="editProfile()"
                                            class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors hover:bg-accent"
                                        >
                                            <Settings class="h-5 w-5" />
                                            Settings
                                        </Link>
                                    </SheetClose>
                                    <SheetClose :as-child="true">
                                        <Link
                                            :href="logout()"
                                            as="button"
                                            class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-destructive transition-colors hover:bg-destructive/10"
                                            @click="handleLogout"
                                        >
                                            <LogOut class="h-5 w-5" />
                                            Log out
                                        </Link>
                                    </SheetClose>
                                </nav>
                            </div>
                        </SheetContent>
                    </Sheet>
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
