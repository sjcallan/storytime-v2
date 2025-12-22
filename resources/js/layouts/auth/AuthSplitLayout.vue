<script setup lang="ts">
import StorytimeIcon from '@/components/StorytimeIcon.vue';
import { home } from '@/routes';
import { Link } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';

defineProps<{
    title?: string;
    description?: string;
    illustration?: 'book' | 'stars' | 'magic' | 'adventure';
}>();

const isVisible = ref(false);

onMounted(() => {
    setTimeout(() => {
        isVisible.value = true;
    }, 100);
});
</script>

<template>
    <div class="min-h-dvh flex">
        <!-- Left Side - Form -->
        <div
            class="flex w-full flex-col justify-center px-6 py-12 lg:w-1/2 lg:px-12 xl:px-24 bg-gradient-to-br from-[#fff9f9] via-white to-[#fffbf0] dark:from-[#0d0a09] dark:via-[#0a0a0a] dark:to-[#0d0b05]"
        >
            <div class="mx-auto w-full max-w-md">
                <!-- Logo -->
                <Link
                    :href="home()"
                    :class="[
                        'mb-8 flex items-center gap-3 transition-all duration-700',
                        isVisible
                            ? 'translate-y-0 opacity-100'
                            : '-translate-y-4 opacity-0',
                    ]"
                >
                    <div
                        class="flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-[#f53003] to-[#F8B803] shadow-lg shadow-[#f5300320]"
                    >
                        <StorytimeIcon class="h-8 w-8 text-white" />
                    </div>
                    <span
                        class="text-2xl font-bold bg-gradient-to-r from-[#f53003] to-[#F8B803] bg-clip-text text-transparent"
                    >
                        Storytime
                    </span>
                </Link>

                <!-- Title & Description -->
                <div
                    :class="[
                        'mb-8 transition-all duration-700 delay-100',
                        isVisible
                            ? 'translate-y-0 opacity-100'
                            : 'translate-y-4 opacity-0',
                    ]"
                >
                    <h1
                        class="text-3xl font-bold text-[#1b1b18] dark:text-[#EDEDEC] mb-2"
                    >
                        {{ title }}
                    </h1>
                    <p class="text-[#706f6c] dark:text-[#A1A09A]">
                        {{ description }}
                    </p>
                </div>

                <!-- Form Slot -->
                <div
                    :class="[
                        'transition-all duration-700 delay-200',
                        isVisible
                            ? 'translate-y-0 opacity-100'
                            : 'translate-y-4 opacity-0',
                    ]"
                >
                    <slot />
                </div>
            </div>
        </div>

        <!-- Right Side - Illustration (hidden on mobile) -->
        <div
            class="hidden lg:flex lg:w-1/2 relative overflow-hidden bg-gradient-to-br from-[#f53003] via-[#F8B803] to-[#F0ACB8]"
        >
            <!-- Animated Background Elements -->
            <div class="absolute inset-0">
                <!-- Floating circles -->
                <div
                    class="absolute top-20 left-20 h-40 w-40 rounded-full bg-white/10 animate-float"
                ></div>
                <div
                    class="absolute bottom-32 right-16 h-56 w-56 rounded-full bg-white/10 animate-float-delayed"
                ></div>
                <div
                    class="absolute top-1/2 left-1/3 h-32 w-32 rounded-full bg-white/5 animate-float-slow"
                ></div>
                <div
                    class="absolute top-1/4 right-1/4 h-24 w-24 rounded-full bg-white/10 animate-float"
                ></div>

                <!-- Stars -->
                <svg
                    class="absolute top-16 right-24 h-8 w-8 text-white/60 animate-twinkle"
                    viewBox="0 0 24 24"
                    fill="currentColor"
                >
                    <path
                        d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"
                    />
                </svg>
                <svg
                    class="absolute top-1/3 left-16 h-6 w-6 text-white/40 animate-twinkle-delayed"
                    viewBox="0 0 24 24"
                    fill="currentColor"
                >
                    <path
                        d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"
                    />
                </svg>
                <svg
                    class="absolute bottom-1/4 right-1/3 h-5 w-5 text-white/50 animate-twinkle"
                    viewBox="0 0 24 24"
                    fill="currentColor"
                >
                    <path
                        d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"
                    />
                </svg>
            </div>

            <!-- Main Illustration -->
            <div
                class="relative z-10 flex flex-col items-center justify-center w-full p-12"
            >
                <div
                    :class="[
                        'transition-all duration-1000 delay-300',
                        isVisible
                            ? 'translate-y-0 opacity-100 scale-100'
                            : 'translate-y-8 opacity-0 scale-95',
                    ]"
                >
                    <!-- Book Illustration -->
                    <div class="relative">
                        <!-- Glow effect -->
                        <div
                            class="absolute inset-0 blur-3xl bg-white/20 rounded-full scale-150"
                        ></div>

                        <!-- Stacked books -->
                        <div class="relative">
                            <!-- Back book -->
                            <div
                                class="absolute -left-4 top-4 w-64 h-80 bg-white/20 rounded-2xl transform rotate-[-8deg] shadow-2xl"
                            ></div>
                            <!-- Middle book -->
                            <div
                                class="absolute -left-2 top-2 w-64 h-80 bg-white/30 rounded-2xl transform rotate-[-4deg] shadow-2xl"
                            ></div>
                            <!-- Front book -->
                            <div
                                class="relative w-64 h-80 bg-white rounded-2xl shadow-2xl overflow-hidden"
                            >
                                <!-- Book spine decoration -->
                                <div
                                    class="absolute left-0 top-0 bottom-0 w-4 bg-gradient-to-r from-[#f53003]/20 to-transparent"
                                ></div>

                                <!-- Book content -->
                                <div
                                    class="flex flex-col items-center justify-center h-full p-8"
                                >
                                    <div
                                        class="w-20 h-20 mb-4 rounded-full bg-gradient-to-br from-[#f53003] to-[#F8B803] flex items-center justify-center"
                                    >
                                        <svg
                                            class="w-10 h-10 text-white"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke="currentColor"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"
                                            />
                                        </svg>
                                    </div>
                                    <h3
                                        class="text-xl font-bold text-[#1b1b18] text-center mb-2"
                                    >
                                        Your Story
                                    </h3>
                                    <p
                                        class="text-sm text-[#706f6c] text-center"
                                    >
                                        Awaits...
                                    </p>

                                    <!-- Decorative lines representing text -->
                                    <div class="mt-6 space-y-2 w-full">
                                        <div
                                            class="h-2 bg-[#f53003]/10 rounded-full w-full"
                                        ></div>
                                        <div
                                            class="h-2 bg-[#F8B803]/10 rounded-full w-4/5"
                                        ></div>
                                        <div
                                            class="h-2 bg-[#F0ACB8]/10 rounded-full w-3/5"
                                        ></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Floating elements around the book -->
                        <div
                            class="absolute -top-8 -right-8 animate-bounce-slow"
                        >
                            <svg
                                class="w-12 h-12 text-white/80"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="1.5"
                                    d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"
                                />
                            </svg>
                        </div>
                        <div
                            class="absolute -bottom-4 -left-12 animate-bounce-slow-delayed"
                        >
                            <svg
                                class="w-10 h-10 text-white/70"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="1.5"
                                    d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"
                                />
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Tagline -->
                <p
                    :class="[
                        'mt-12 text-xl font-semibold text-white text-center max-w-xs transition-all duration-1000 delay-500',
                        isVisible
                            ? 'translate-y-0 opacity-100'
                            : 'translate-y-4 opacity-0',
                    ]"
                >
                    Where imagination becomes reality
                </p>
                <p
                    :class="[
                        'mt-3 text-white/80 text-center max-w-sm transition-all duration-1000 delay-600',
                        isVisible
                            ? 'translate-y-0 opacity-100'
                            : 'translate-y-4 opacity-0',
                    ]"
                >
                    Create magical stories, bring characters to life, and share
                    your adventures with the world.
                </p>
            </div>

            <!-- Bottom wave decoration -->
            <div class="absolute bottom-0 left-0 right-0">
                <svg
                    viewBox="0 0 1440 120"
                    class="w-full h-auto fill-white/10"
                    preserveAspectRatio="none"
                >
                    <path
                        d="M0,64L48,69.3C96,75,192,85,288,80C384,75,480,53,576,48C672,43,768,53,864,64C960,75,1056,85,1152,80C1248,75,1344,53,1392,42.7L1440,32L1440,120L1392,120C1344,120,1248,120,1152,120C1056,120,960,120,864,120C768,120,672,120,576,120C480,120,384,120,288,120C192,120,96,120,48,120L0,120Z"
                    ></path>
                </svg>
            </div>
        </div>
    </div>
</template>

<style scoped>
@keyframes float {
    0%,
    100% {
        transform: translateY(0) rotate(0deg);
    }
    50% {
        transform: translateY(-20px) rotate(3deg);
    }
}

@keyframes float-delayed {
    0%,
    100% {
        transform: translateY(0) rotate(0deg);
    }
    50% {
        transform: translateY(-15px) rotate(-2deg);
    }
}

@keyframes float-slow {
    0%,
    100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-10px);
    }
}

@keyframes twinkle {
    0%,
    100% {
        opacity: 0.6;
        transform: scale(1);
    }
    50% {
        opacity: 1;
        transform: scale(1.2);
    }
}

@keyframes twinkle-delayed {
    0%,
    100% {
        opacity: 0.4;
        transform: scale(1);
    }
    50% {
        opacity: 0.8;
        transform: scale(1.15);
    }
}

@keyframes bounce-slow {
    0%,
    100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-8px);
    }
}

.animate-float {
    animation: float 6s ease-in-out infinite;
}

.animate-float-delayed {
    animation: float-delayed 8s ease-in-out infinite;
    animation-delay: 1s;
}

.animate-float-slow {
    animation: float-slow 10s ease-in-out infinite;
    animation-delay: 2s;
}

.animate-twinkle {
    animation: twinkle 3s ease-in-out infinite;
}

.animate-twinkle-delayed {
    animation: twinkle-delayed 4s ease-in-out infinite;
    animation-delay: 1.5s;
}

.animate-bounce-slow {
    animation: bounce-slow 4s ease-in-out infinite;
}

.animate-bounce-slow-delayed {
    animation: bounce-slow 5s ease-in-out infinite;
    animation-delay: 2s;
}
</style>
