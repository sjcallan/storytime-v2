<script setup lang="ts">
import StorytimeSaplingIcon from '@/components/StorytimeSaplingIcon.vue';
import { Button } from '@/components/ui/button';
import { home, login, register } from '@/routes';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft, LogIn, UserPlus } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    title: string;
}>();

const page = usePage();
const isAuthenticated = computed(() => !!page.props.auth.user);
</script>

<template>
    <div
        class="min-h-screen bg-gradient-to-br from-[#fff2f2] via-[#FDFDFC] to-[#fff9e6] dark:from-[#1D0002] dark:via-[#0a0a0a] dark:to-[#1a0f00]"
    >
        <Head :title="title" />

        <!-- Header -->
        <header
            class="sticky top-0 z-50 border-b border-[#19140010] bg-white/80 backdrop-blur-md dark:border-[#3E3E3A20] dark:bg-[#161615]/80"
        >
            <nav
                class="mx-auto flex max-w-4xl items-center justify-between px-4 py-4 sm:px-6"
            >
                <Link
                    :href="home()"
                    class="flex items-center gap-3 transition-opacity hover:opacity-80"
                >
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-[#f53003] to-[#F8B803]"
                    >
                        <StorytimeSaplingIcon class="h-6 w-6 text-white" />
                    </div>
                    <span
                        class="text-xl font-bold bg-gradient-to-r from-[#f53003] to-[#F8B803] bg-clip-text text-transparent"
                    >
                        Storytime
                    </span>
                </Link>

                <div class="flex items-center gap-2">
                    <Link
                        :href="home()"
                        class="flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium text-[#706f6c] transition-colors hover:bg-[#19140010] hover:text-[#1b1b18] dark:text-[#A1A09A] dark:hover:bg-[#3E3E3A20] dark:hover:text-[#EDEDEC]"
                    >
                        <ArrowLeft class="h-4 w-4" />
                        Back to Home
                    </Link>
                    <template v-if="!isAuthenticated">
                        <Link :href="login()">
                            <Button
                                variant="ghost"
                                size="sm"
                                class="hidden sm:flex gap-2"
                            >
                                <LogIn class="h-4 w-4" />
                                Log in
                            </Button>
                        </Link>
                        <Link :href="register()">
                            <Button
                                size="sm"
                                class="hidden sm:flex gap-2 bg-gradient-to-r from-[#f53003] to-[#F8B803] text-white"
                            >
                                <UserPlus class="h-4 w-4" />
                                Sign up
                            </Button>
                        </Link>
                    </template>
                </div>
            </nav>
        </header>

        <!-- Main Content -->
        <main class="mx-auto max-w-4xl px-4 py-12 sm:px-6">
            <article
                class="prose prose-lg prose-slate dark:prose-invert max-w-none rounded-2xl border border-[#19140010] bg-white/80 p-8 shadow-lg backdrop-blur-sm dark:border-[#3E3E3A20] dark:bg-[#161615]/80 sm:p-12"
            >
                <slot />
            </article>
        </main>

        <!-- Footer -->
        <footer
            class="border-t border-[#19140020] px-6 py-8 dark:border-[#3E3E3A]"
        >
            <div
                class="mx-auto max-w-4xl text-center text-sm text-[#706f6c] dark:text-[#A1A09A]"
            >
                <p>
                    &copy; 2025 Storytime. Made with
                    <span class="text-[#f53003]">♥</span> for young storytellers
                    everywhere.
                </p>
            </div>
        </footer>
    </div>
</template>

<style>
@reference "tailwindcss";

/* Custom prose styles for legal documents - Light mode */
.prose h1 {
    @apply mb-8 bg-gradient-to-r from-[#f53003] to-[#F8B803] bg-clip-text text-transparent font-bold;
}

.prose h2 {
    @apply mt-10 mb-4 font-bold;
    color: #2d2d2a;
}

.prose h3 {
    @apply mt-6 mb-3 font-semibold;
    color: #3d3d38;
}

.prose p {
    @apply leading-relaxed;
    color: #4a4a45;
}

.prose strong {
    @apply font-semibold;
    color: #2d2d2a;
}

.prose ul,
.prose ol {
    color: #4a4a45;
}

.prose li {
    @apply my-1.5;
}

.prose li::marker {
    color: #f53003;
}

.prose hr {
    @apply my-8;
    border-color: #d0d0c8;
}

.prose a {
    @apply font-medium no-underline transition-colors;
    color: #f53003;
}

.prose a:hover {
    color: #F8B803;
}

.prose blockquote {
    @apply border-l-4 pl-6 italic;
    border-color: #f53003;
    background-color: rgba(245, 48, 3, 0.03);
    color: #4a4a45;
}

.prose code {
    @apply rounded px-1.5 py-0.5 text-sm;
    background-color: rgba(25, 20, 0, 0.08);
    color: #3d3d38;
}

/* Dark mode overrides */
:root.dark .prose h2,
.dark .prose h2 {
    color: #EDEDEC;
}

:root.dark .prose h3,
.dark .prose h3 {
    color: #EDEDEC;
}

:root.dark .prose p,
.dark .prose p {
    color: #B8B8B0;
}

:root.dark .prose strong,
.dark .prose strong {
    color: #EDEDEC;
}

:root.dark .prose ul,
:root.dark .prose ol,
.dark .prose ul,
.dark .prose ol {
    color: #B8B8B0;
}

:root.dark .prose hr,
.dark .prose hr {
    border-color: #3E3E3A;
}

:root.dark .prose blockquote,
.dark .prose blockquote {
    color: #B8B8B0;
}

:root.dark .prose code,
.dark .prose code {
    background-color: rgba(62, 62, 58, 0.3);
    color: #EDEDEC;
}
</style>

