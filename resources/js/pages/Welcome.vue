<script setup lang="ts">
import { dashboard, login, register } from '@/routes';
import { Head, Link } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';

withDefaults(
    defineProps<{
        canRegister: boolean;
    }>(),
    {
        canRegister: true,
    },
);

const isVisible = ref(false);

onMounted(() => {
    setTimeout(() => {
        isVisible.value = true;
    }, 100);
});
</script>

<template>
    <Head title="Welcome to Storytime">
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
                class="mx-auto flex max-w-7xl items-center justify-between px-6 py-4 lg:px-8"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-[#f53003] to-[#F8B803]"
                    >
                        <svg
                            class="h-6 w-6 text-white"
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
                    <span
                        class="text-2xl font-bold bg-gradient-to-r from-[#f53003] to-[#F8B803] bg-clip-text text-transparent"
                    >
                        Storytime
                    </span>
                </div>
                <div class="flex items-center gap-3">
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
            </nav>
        </header>

        <!-- Hero Section -->
        <section class="relative overflow-hidden px-6 pt-32 pb-20 lg:px-8">
            <div class="mx-auto max-w-7xl">
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
                            class="mb-6 text-5xl font-bold leading-tight text-[#1b1b18] lg:text-7xl dark:text-[#EDEDEC]"
                        >
                            Bring Your
                            <span
                                class="bg-gradient-to-r from-[#f53003] via-[#F8B803] to-[#F0ACB8] bg-clip-text text-transparent"
                            >
                                Stories to Life
                            </span>
                        </h1>
                        <p
                            class="mb-8 text-lg leading-relaxed text-[#706f6c] lg:text-xl dark:text-[#A1A09A]"
                        >
                            Create magical storybooks, exciting chapter books,
                            and captivating plays. Chat with your characters,
                            share your imagination, and watch your stories come
                            alive!
                        </p>
                        <div class="flex flex-col gap-4 sm:flex-row">
                            <Link
                                v-if="canRegister"
                                :href="register()"
                                class="group relative inline-flex items-center justify-center gap-2 overflow-hidden rounded-2xl bg-gradient-to-r from-[#f53003] to-[#F8B803] px-8 py-4 font-semibold text-white shadow-2xl transition-all hover:shadow-[#f5300340] hover:scale-105"
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
                            <Link
                                :href="login()"
                                class="inline-flex items-center justify-center gap-2 rounded-2xl border-2 border-[#19140020] px-8 py-4 font-semibold text-[#1b1b18] transition-all hover:border-[#19140040] hover:bg-white dark:border-[#3E3E3A] dark:text-[#EDEDEC] dark:hover:border-[#62605b] dark:hover:bg-[#161615]"
                            >
                                <span>Learn More</span>
                            </Link>
                        </div>
                    </div>
                    <div
                        :class="[
                            'relative transition-all duration-1000 delay-300',
                            isVisible
                                ? 'translate-y-0 opacity-100'
                                : 'translate-y-8 opacity-0',
                        ]"
                    >
                        <!-- Floating Book Illustration -->
                        <div class="relative">
                            <div
                                class="absolute -top-4 -left-4 h-72 w-72 animate-pulse rounded-full bg-[#F0ACB8]/30 blur-3xl"
                            ></div>
                            <div
                                class="absolute -bottom-4 -right-4 h-72 w-72 animate-pulse rounded-full bg-[#F8B803]/30 blur-3xl animation-delay-1000"
                            ></div>
                            <div
                                class="relative rounded-3xl bg-gradient-to-br from-white to-[#fff9e6] p-8 shadow-2xl dark:from-[#161615] dark:to-[#1a0f00]"
                            >
                                <div
                                    class="flex items-center justify-center"
                                >
                                    <div
                                        class="relative h-96 w-80 transition-transform hover:scale-105"
                                    >
                                        <!-- Book -->
                                        <div
                                            class="absolute inset-0 rounded-2xl bg-gradient-to-br from-[#f53003] to-[#F8B803] shadow-2xl transform rotate-6"
                                        ></div>
                                        <div
                                            class="absolute inset-0 rounded-2xl bg-gradient-to-br from-white to-[#FDFDFC] shadow-xl dark:from-[#161615] dark:to-[#1b1b18]"
                                        >
                                            <div
                                                class="flex h-full flex-col items-center justify-center p-8"
                                            >
                                                <svg
                                                    class="mb-4 h-24 w-24 text-[#f53003]"
                                                    fill="none"
                                                    viewBox="0 0 24 24"
                                                    stroke="currentColor"
                                                >
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        stroke-width="1.5"
                                                        d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"
                                                    />
                                                </svg>
                                                <h3
                                                    class="mb-2 text-center text-2xl font-bold text-[#1b1b18] dark:text-[#EDEDEC]"
                                                >
                                                    Your Story
                                                </h3>
                                                <p
                                                    class="text-center text-sm text-[#706f6c] dark:text-[#A1A09A]"
                                                >
                                                    Imagination has no limits
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Features Section -->
        <section class="px-6 py-20 lg:px-8">
            <div class="mx-auto max-w-7xl">
                <div class="mb-16 text-center">
                    <h2
                        class="mb-4 text-4xl font-bold text-[#1b1b18] lg:text-5xl dark:text-[#EDEDEC]"
                    >
                        Everything You Need to
                        <span
                            class="bg-gradient-to-r from-[#f53003] to-[#F8B803] bg-clip-text text-transparent"
                        >
                            Create Magic
                        </span>
                    </h2>
                    <p
                        class="mx-auto max-w-2xl text-lg text-[#706f6c] dark:text-[#A1A09A]"
                    >
                        Powerful tools designed for young storytellers and
                        their families
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
            <div
                class="mx-auto max-w-7xl text-center text-sm text-[#706f6c] dark:text-[#A1A09A]"
            >
                <p>
                    &copy; 2025 Storytime. Made with
                    <span class="text-[#f53003]">♥</span> for young
                    storytellers everywhere.
                </p>
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
