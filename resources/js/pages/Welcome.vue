<script setup lang="ts">
import StorytimeSaplingIcon from '@/components/StorytimeSaplingIcon.vue';
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
import { dashboard, login, privacy, register, terms } from '@/routes';
import { select as profileSelect } from '@/routes/profiles';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { LogIn, Menu, Sparkles, UserPlus } from 'lucide-vue-next';
import { ref, onMounted } from 'vue';

withDefaults(
    defineProps<{
        canRegister: boolean;
    }>(),
    {
        canRegister: true,
    },
);

const page = usePage();
const isVisible = ref(false);
const mobileMenuOpen = ref(false);

onMounted(() => {
    // Redirect logged-in users to profile selection
    if (page.props.auth.user) {
        router.visit(profileSelect());
        return;
    }
    
    setTimeout(() => {
        isVisible.value = true;
    }, 100);
});

const closeMobileMenu = () => {
    mobileMenuOpen.value = false;
};
</script>

<template>
    <Head title="Storytime - Bring your stories to life">
        <link rel="preconnect" href="https://rsms.me/" />
        <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
    </Head>
    <div
        class="min-h-screen bg-gradient-to-br from-[#fff2f2] via-[#FDFDFC] to-[#fff9e6] dark:from-[#1D0002] dark:via-[#0a0a0a] dark:to-[#1a0f00]"
    >
        <!-- Header -->
        <header
            class="fixed top-0 z-50 w-full border-b border-[#19140010] bg-white/80 backdrop-blur-md dark:border-[#3E3E3A20] dark:bg-[#161615]/80"
        >
            <nav
                class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-[#f53003] to-[#F8B803]"
                    >
                        <StorytimeSaplingIcon class="h-6 w-6 text-white" />
                    </div>
                    <span
                        class="text-2xl font-bold bg-gradient-to-r from-[#f53003] to-[#F8B803] bg-clip-text text-transparent"
                    >
                        Storytime
                    </span>
                </div>

                <!-- Desktop Navigation -->
                <div class="hidden items-center gap-3 sm:flex">
                    <Link
                        v-if="$page.props.auth.user"
                        :href="dashboard()"
                        class="rounded-lg border border-[#19140020] px-6 py-2 font-medium text-[#1b1b18] transition-all hover:border-[#19140040] hover:bg-[#FDFDFC] dark:border-[#3E3E3A] dark:text-[#EDEDEC] dark:hover:border-[#62605b] dark:hover:bg-[#161615]"
                    >
                        Dashboard
                    </Link>
                    <template v-else>
                        <Link
                            :href="login()"
                            class="rounded-lg px-6 py-2 font-medium text-[#1b1b18] transition-all hover:bg-[#19140010] dark:text-[#EDEDEC] dark:hover:bg-[#3E3E3A20]"
                        >
                            Log in
                        </Link>
                        <Link
                            v-if="canRegister"
                            :href="register()"
                            class="rounded-lg bg-gradient-to-r from-[#f53003] to-[#F8B803] px-6 py-2 font-medium text-white shadow-lg transition-all hover:shadow-xl hover:scale-105"
                        >
                            Get Started
                        </Link>
                    </template>
                </div>

                <!-- Mobile Hamburger Menu -->
                <div class="sm:hidden">
                    <Sheet v-model:open="mobileMenuOpen">
                        <SheetTrigger :as-child="true">
                            <Button
                                variant="ghost"
                                size="icon"
                                class="h-10 w-10 text-[#1b1b18] hover:bg-[#19140010] dark:text-[#EDEDEC] dark:hover:bg-[#3E3E3A20]"
                            >
                                <Menu class="h-6 w-6" />
                            </Button>
                        </SheetTrigger>
                        <SheetContent
                            side="right"
                            class="flex w-[300px] flex-col border-l border-[#19140020] bg-white p-0 dark:border-[#3E3E3A] dark:bg-[#161615]"
                        >
                            <SheetTitle class="sr-only">Menu</SheetTitle>

                            <!-- Header with branding -->
                            <SheetHeader
                                class="border-b border-[#19140010] bg-gradient-to-br from-[#fff2f2] to-[#fff9e6] p-5 dark:border-[#3E3E3A20] dark:from-[#1D0002] dark:to-[#1a0f00]"
                            >
                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-[#f53003] to-[#F8B803] shadow-lg"
                                    >
                                        <StorytimeSaplingIcon class="h-7 w-7 text-white" />
                                    </div>
                                    <div>
                                        <p
                                            class="text-lg font-bold bg-gradient-to-r from-[#f53003] to-[#F8B803] bg-clip-text text-transparent"
                                        >
                                            Storytime
                                        </p>
                                        <p class="text-xs text-[#706f6c] dark:text-[#A1A09A]">
                                            Bring your stories to life
                                        </p>
                                    </div>
                                </div>
                            </SheetHeader>

                            <!-- Menu Content -->
                            <div class="flex flex-1 flex-col p-4">
                                <template v-if="$page.props.auth.user">
                                    <SheetClose :as-child="true">
                                        <Link
                                            :href="dashboard()"
                                            class="flex items-center gap-3 rounded-xl bg-gradient-to-r from-[#f53003] to-[#F8B803] px-4 py-3.5 font-semibold text-white shadow-lg transition-all hover:shadow-xl"
                                            @click="closeMobileMenu"
                                        >
                                            <Sparkles class="h-5 w-5" />
                                            Go to Dashboard
                                        </Link>
                                    </SheetClose>
                                </template>
                                <template v-else>
                                    <div class="space-y-3">
                                        <SheetClose :as-child="true">
                                            <Link
                                                :href="login()"
                                                class="flex items-center gap-3 rounded-xl border border-[#19140020] bg-white px-4 py-3.5 font-medium text-[#1b1b18] transition-all hover:border-[#19140040] hover:bg-[#FDFDFC] dark:border-[#3E3E3A] dark:bg-[#161615] dark:text-[#EDEDEC] dark:hover:border-[#62605b]"
                                                @click="closeMobileMenu"
                                            >
                                                <LogIn class="h-5 w-5" />
                                                Log in
                                            </Link>
                                        </SheetClose>
                                        <SheetClose v-if="canRegister" :as-child="true">
                                            <Link
                                                :href="register()"
                                                class="flex items-center gap-3 rounded-xl bg-gradient-to-r from-[#f53003] to-[#F8B803] px-4 py-3.5 font-semibold text-white shadow-lg transition-all hover:shadow-xl"
                                                @click="closeMobileMenu"
                                            >
                                                <UserPlus class="h-5 w-5" />
                                                Get Started
                                            </Link>
                                        </SheetClose>
                                    </div>

                                    <Separator class="my-5 bg-[#19140010] dark:bg-[#3E3E3A20]" />

                                    <!-- Feature highlights -->
                                    <div class="space-y-4">
                                        <p class="text-xs font-medium uppercase tracking-wider text-[#706f6c] dark:text-[#A1A09A]">
                                            Why Storytime?
                                        </p>
                                        <div class="space-y-3">
                                            <div class="flex items-start gap-3">
                                                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-[#F0ACB8]/20">
                                                    <span class="text-sm">📚</span>
                                                </div>
                                                <div>
                                                    <p class="text-sm font-medium text-[#1b1b18] dark:text-[#EDEDEC]">Multiple Formats</p>
                                                    <p class="text-xs text-[#706f6c] dark:text-[#A1A09A]">Storybooks, plays & more</p>
                                                </div>
                                            </div>
                                            <div class="flex items-start gap-3">
                                                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-[#F8B803]/20">
                                                    <span class="text-sm">💬</span>
                                                </div>
                                                <div>
                                                    <p class="text-sm font-medium text-[#1b1b18] dark:text-[#EDEDEC]">Chat with Characters</p>
                                                    <p class="text-xs text-[#706f6c] dark:text-[#A1A09A]">Bring stories to life</p>
                                                </div>
                                            </div>
                                            <div class="flex items-start gap-3">
                                                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-[#f53003]/20">
                                                    <span class="text-sm">🛡️</span>
                                                </div>
                                                <div>
                                                    <p class="text-sm font-medium text-[#1b1b18] dark:text-[#EDEDEC]">Safe & Secure</p>
                                                    <p class="text-xs text-[#706f6c] dark:text-[#A1A09A]">Made for families</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>

                            <!-- Footer -->
                            <div class="border-t border-[#19140010] p-4 dark:border-[#3E3E3A20]">
                                <p class="text-center text-xs text-[#706f6c] dark:text-[#A1A09A]">
                                    Made with <span class="text-[#f53003]">♥</span> for storytellers
                                </p>
                            </div>
                        </SheetContent>
                    </Sheet>
                </div>
            </nav>
        </header>

        <!-- Hero Section -->
        <section class="relative isolate overflow-hidden px-6 pt-32 pb-20 lg:px-8 ">
            <!-- Parallax Background Image -->
            <div
                class="absolute inset-0 bg-cover bg-center sm:bg-fixed"
                :style="{
                    backgroundImage: 'url(https://d3lz6w5lgn41k.cloudfront.net/forrest-1.webp)',
                }"
            ></div>
            <div class="relative z-10 mx-auto max-w-7xl">
                <div
                    class="grid gap-12 lg:grid-cols-2 lg:gap-16 items-center"
                >
                    <div
                        :class="[
                            'transition-all duration-1000',
                            isVisible
                                ? 'translate-y-0 opacity-100'
                                : 'translate-y-8 opacity-0',
                        ]"
                    >
                        <h1
                            class="mb-6 text-5xl font-bold leading-tight text-white lg:text-7xl drop-shadow-lg"
                        >
                            Watch their imagination
                            <span
                                class="bg-gradient-to-r from-[#f53003] via-[#F8B803] to-[#F0ACB8] bg-clip-text text-transparent drop-shadow-none block"
                            >
                                come to life
                            </span>
                        </h1>
                        <p
                            class="mb-8 text-lg leading-relaxed text-white/90 lg:text-xl drop-shadow-md"
                        >
                            Create magical storybooks, exciting chapter books,
                            and captivating plays. Chat with your characters,
                            share your imagination, and watch your stories come
                            alive!
                        </p>
                        <div class="flex w-full flex-col gap-4 sm:w-auto sm:flex-row">
                            <Link
                                v-if="canRegister"
                                :href="register()"
                                class="group relative inline-flex w-full items-center justify-center gap-2 overflow-hidden rounded-2xl bg-gradient-to-r from-[#f53003] to-[#F8B803] px-8 py-4 font-semibold text-white shadow-2xl transition-all hover:shadow-[#f5300340] hover:scale-105 sm:w-auto"
                            >
                                <span>Start Creating</span>
                                <svg
                                    class="h-5 w-5 transition-transform group-hover:translate-x-1"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M13 7l5 5m0 0l-5 5m5-5H6"
                                    />
                                </svg>
                            </Link>
                            <a
                                href="#features"
                                class="inline-flex w-full items-center justify-center gap-2 rounded-2xl border-2 border-white/30 px-8 py-4 font-semibold text-white transition-all hover:border-white/60 hover:bg-white/10 backdrop-blur-sm sm:w-auto"
                            >
                                <span>Learn More</span>
                            </a>
                        </div>
                    </div>
                    <div
                        :class="[
                            'relative hidden transition-all duration-1000 delay-300 lg:block',
                            isVisible
                                ? 'translate-y-0 opacity-100'
                                : 'translate-y-8 opacity-0',
                        ]"
                    >
                        <!-- Book Covers Fan -->
                        <div class="relative flex items-center justify-center py-8">
                            <!-- Glow effects -->
                            <div
                                class="absolute -top-8 -left-8 h-80 w-80 animate-pulse rounded-full bg-[#F0ACB8]/40 blur-3xl"
                            ></div>
                            <div
                                class="absolute -bottom-8 -right-8 h-80 w-80 animate-pulse rounded-full bg-[#F8B803]/40 blur-3xl animation-delay-1000"
                            ></div>
                            
                            <!-- Fan of Book Covers -->
                            <div class="relative h-[450px] w-[400px]">
                                <!-- Book 1 (far left) -->
                                <div
                                    class="book-cover absolute h-[300px] w-[200px] cursor-pointer overflow-hidden rounded-lg shadow-xl transition-all duration-300 hover:z-50 hover:scale-110"
                                    style="left: 50%; top: 55%; transform: translate(-50%, -50%) translateX(-120px) translateY(20px) rotate(-18deg); z-index: 0; transform-origin: bottom center;"
                                >
                                    <img
                                        src="https://d3lz6w5lgn41k.cloudfront.net/01kd8m1j8jpyb2qgrf2ck6kq1a_dGqeSF7M.webp"
                                        alt="Storybook cover"
                                        class="h-full w-full object-cover"
                                    />
                                </div>
                                
                                <!-- Book 2 (left) -->
                                <div
                                    class="book-cover absolute h-[320px] w-[210px] cursor-pointer overflow-hidden rounded-lg shadow-xl transition-all duration-300 hover:z-50 hover:scale-110"
                                    style="left: 50%; top: 52%; transform: translate(-50%, -50%) translateX(-55px) translateY(10px) rotate(-8deg); z-index: 1; transform-origin: bottom center;"
                                >
                                    <img
                                        src="https://d3lz6w5lgn41k.cloudfront.net/01kdbehm7vx30g9t1z4a59m1jq_8V4JDjhY.webp"
                                        alt="Storybook cover"
                                        class="h-full w-full object-cover"
                                    />
                                </div>
                                
                                <!-- Book 3 (right) -->
                                <div
                                    class="book-cover absolute h-[320px] w-[210px] cursor-pointer overflow-hidden rounded-lg shadow-xl transition-all duration-300 hover:z-50 hover:scale-110"
                                    style="left: 50%; top: 52%; transform: translate(-50%, -50%) translateX(55px) translateY(10px) rotate(8deg); z-index: 2; transform-origin: bottom center;"
                                >
                                    <img
                                        src="https://d3lz6w5lgn41k.cloudfront.net/01kd8d7jxjkbgv9yj1xdq2ghtq_BRYNCSdv.webp"
                                        alt="Storybook cover"
                                        class="h-full w-full object-cover"
                                    />
                                </div>
                                
                                <!-- Book 4 (front - most visible) -->
                                <div
                                    class="book-cover absolute h-[360px] w-[240px] cursor-pointer overflow-hidden rounded-lg shadow-2xl transition-all duration-300 hover:scale-105"
                                    style="left: 50%; top: 50%; transform: translate(-50%, -50%) rotate(2deg); z-index: 3;"
                                >
                                    <img
                                        src="https://d3lz6w5lgn41k.cloudfront.net/01kcf866nybd69xftd89wmdt28_i7iVQtCN.webp"
                                        alt="Storybook cover"
                                        class="h-full w-full object-cover"
                                    />
                                    <!-- Subtle shine effect -->
                                    <div class="absolute inset-0 bg-gradient-to-tr from-transparent via-white/10 to-transparent"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Features Section -->
        <section id="features" class="scroll-mt-24 px-6 py-20 lg:px-8">
            <div class="mx-auto max-w-7xl">
                <div class="mb-16 text-center">
                    <h2
                        class="mb-4 text-4xl font-bold text-[#1b1b18] lg:text-5xl dark:text-[#EDEDEC]"
                    >
                        Everything They Need to
                        <span
                            class="bg-gradient-to-r from-[#f53003] to-[#F8B803] bg-clip-text text-transparent"
                        >
                            Create Magic
                        </span>
                    </h2>
                    <p
                        class="mx-auto max-w-2xl text-lg text-[#706f6c] dark:text-[#A1A09A]"
                    >
                        Designed specifically for young storytellers and their families
                    </p>
                </div>

                <div class="grid gap-8 md:grid-cols-2 lg:grid-cols-3">
                    <!-- Feature 1 -->
                    <div
                        class="group rounded-3xl border border-[#19140020] bg-white p-8 shadow-lg transition-all hover:shadow-2xl hover:scale-105 hover:border-[#F0ACB8] dark:border-[#3E3E3A] dark:bg-[#161615] dark:hover:border-[#F0ACB8]"
                    >
                        <div
                            class="mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-[#F0ACB8] to-[#F3BEC7]"
                        >
                            <svg
                                class="h-8 w-8 text-white"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"
                                />
                            </svg>
                        </div>
                        <h3
                            class="mb-3 text-2xl font-bold text-[#1b1b18] dark:text-[#EDEDEC]"
                        >
                            Multiple Formats
                        </h3>
                        <p class="text-[#706f6c] dark:text-[#A1A09A]">
                            Create storybooks, chapter books, plays, and
                            screenplays. Choose the format that brings your
                            imagination to life.
                        </p>
                    </div>

                    <!-- Feature 2 -->
                    <div
                        class="group rounded-3xl border border-[#19140020] bg-white p-8 shadow-lg transition-all hover:shadow-2xl hover:scale-105 hover:border-[#F8B803] dark:border-[#3E3E3A] dark:bg-[#161615] dark:hover:border-[#F8B803]"
                    >
                        <div
                            class="mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-[#F8B803] to-[#f53003]"
                        >
                            <svg
                                class="h-8 w-8 text-white"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"
                                />
                            </svg>
                        </div>
                        <h3
                            class="mb-3 text-2xl font-bold text-[#1b1b18] dark:text-[#EDEDEC]"
                        >
                            Chat with Characters
                        </h3>
                        <p class="text-[#706f6c] dark:text-[#A1A09A]">
                            Bring your characters to life! Have conversations,
                            ask questions, and develop deeper storylines.
                        </p>
                    </div>

                    <!-- Feature 3 -->
                    <div
                        class="group rounded-3xl border border-[#19140020] bg-white p-8 shadow-lg transition-all hover:shadow-2xl hover:scale-105 hover:border-[#f53003] dark:border-[#3E3E3A] dark:bg-[#161615] dark:hover:border-[#f53003]"
                    >
                        <div
                            class="mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-[#f53003] to-[#FF4433]"
                        >
                            <svg
                                class="h-8 w-8 text-white"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                                />
                            </svg>
                        </div>
                        <h3
                            class="mb-3 text-2xl font-bold text-[#1b1b18] dark:text-[#EDEDEC]"
                        >
                            Age & Genre Options
                        </h3>
                        <p class="text-[#706f6c] dark:text-[#A1A09A]">
                            Customize stories for different ages and explore
                            various genres from fantasy to adventure.
                        </p>
                    </div>

                    <!-- Feature 4 -->
                    <div
                        class="group rounded-3xl border border-[#19140020] bg-white p-8 shadow-lg transition-all hover:shadow-2xl hover:scale-105 hover:border-[#F0ACB8] dark:border-[#3E3E3A] dark:bg-[#161615] dark:hover:border-[#F0ACB8]"
                    >
                        <div
                            class="mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-[#F3BEC7] to-[#F0ACB8]"
                        >
                            <svg
                                class="h-8 w-8 text-white"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"
                                />
                            </svg>
                        </div>
                        <h3
                            class="mb-3 text-2xl font-bold text-[#1b1b18] dark:text-[#EDEDEC]"
                        >
                            Share & Collaborate
                        </h3>
                        <p class="text-[#706f6c] dark:text-[#A1A09A]">
                            Share your stories with family and friends. Let
                            others read and enjoy your creative works.
                        </p>
                    </div>

                    <!-- Feature 5 -->
                    <div
                        class="group rounded-3xl border border-[#19140020] bg-white p-8 shadow-lg transition-all hover:shadow-2xl hover:scale-105 hover:border-[#F8B803] dark:border-[#3E3E3A] dark:bg-[#161615] dark:hover:border-[#F8B803]"
                    >
                        <div
                            class="mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-[#F8B803] to-[#f5ad00]"
                        >
                            <svg
                                class="h-8 w-8 text-white"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"
                                />
                            </svg>
                        </div>
                        <h3
                            class="mb-3 text-2xl font-bold text-[#1b1b18] dark:text-[#EDEDEC]"
                        >
                            Safe & Secure
                        </h3>
                        <p class="text-[#706f6c] dark:text-[#A1A09A]">
                            Parents can feel confident with our safe, moderated
                            platform designed with kids in mind.
                        </p>
                    </div>

                    <!-- Feature 6 -->
                    <div
                        class="group rounded-3xl border border-[#19140020] bg-white p-8 shadow-lg transition-all hover:shadow-2xl hover:scale-105 hover:border-[#f53003] dark:border-[#3E3E3A] dark:bg-[#161615] dark:hover:border-[#f53003]"
                    >
                        <div
                            class="mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-[#FF4433] to-[#f53003]"
                        >
                            <svg
                                class="h-8 w-8 text-white"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M13 10V3L4 14h7v7l9-11h-7z"
                                />
                            </svg>
                        </div>
                        <h3
                            class="mb-3 text-2xl font-bold text-[#1b1b18] dark:text-[#EDEDEC]"
                        >
                            Instant Creativity
                        </h3>
                        <p class="text-[#706f6c] dark:text-[#A1A09A]">
                            Start writing immediately with intuitive tools that
                            make storytelling fun and easy.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA Section -->
        <section class="px-6 py-20 lg:px-8">
            <div class="mx-auto max-w-7xl">
                <div
                    class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#f53003] via-[#F8B803] to-[#F0ACB8] p-12 shadow-2xl lg:p-20"
                >
                    <div class="relative z-10 text-center">
                        <h2
                            class="mb-4 text-4xl font-bold text-white lg:text-5xl"
                        >
                            Ready to Start Your Adventure?
                        </h2>
                        <p class="mb-8 text-xl text-white/90">
                            Join thousands of young storytellers bringing their
                            imagination to life
                        </p>
                        <div class="flex flex-col gap-4 sm:flex-row justify-center">
                            <Link
                                v-if="canRegister"
                                :href="register()"
                                class="inline-flex items-center justify-center gap-2 rounded-2xl bg-white px-8 py-4 font-semibold text-[#f53003] shadow-xl transition-all hover:shadow-2xl hover:scale-105"
                            >
                                <span>Create Free Account</span>
                                <svg
                                    class="h-5 w-5"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M13 7l5 5m0 0l-5 5m5-5H6"
                                    />
                                </svg>
                            </Link>
                        </div>
                    </div>
                    <div
                        class="absolute -top-24 -right-24 h-96 w-96 rounded-full bg-white/10 blur-3xl"
                    ></div>
                    <div
                        class="absolute -bottom-24 -left-24 h-96 w-96 rounded-full bg-white/10 blur-3xl"
                    ></div>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer
            class="border-t border-[#19140020] px-6 py-12 dark:border-[#3E3E3A]"
        >
            <div class="mx-auto max-w-7xl">
                <div class="flex flex-col items-center gap-4">
                    <div class="flex items-center gap-6">
                        <Link
                            :href="terms()"
                            class="text-sm text-[#706f6c] transition-colors hover:text-[#f53003] dark:text-[#A1A09A] dark:hover:text-[#F8B803]"
                        >
                            Terms of Use
                        </Link>
                        <span class="text-[#19140020] dark:text-[#3E3E3A]">•</span>
                        <Link
                            :href="privacy()"
                            class="text-sm text-[#706f6c] transition-colors hover:text-[#f53003] dark:text-[#A1A09A] dark:hover:text-[#F8B803]"
                        >
                            Privacy Policy
                        </Link>
                    </div>
                    <p class="text-sm text-[#706f6c] dark:text-[#A1A09A]">
                        &copy; 2025 StorytimeBooks.ai by Prescott & West. Made with
                        <span class="text-[#f53003]">♥</span> for young
                        storytellers everywhere.
                    </p>
                </div>
            </div>
        </footer>
    </div>
</template>

<style scoped>
@keyframes pulse {
    0%,
    100% {
        opacity: 1;
    }
    50% {
        opacity: 0.5;
    }
}

.animate-pulse {
    animation: pulse 4s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

.animation-delay-1000 {
    animation-delay: 1s;
}
</style>
