<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { cn } from '@/lib/utils'

interface Props {
    class?: HTMLAttributes['class']
    /**
     * Primary color for the sparkles. Supports Tailwind color classes or CSS color values.
     * @example "text-amber-500" or "#fbbf24"
     */
    color?: string
    /**
     * Secondary/accent color for the glow effect.
     * @example "text-orange-400" or "#fb923c"
     */
    accentColor?: string
    /**
     * Size preset: 'sm' | 'md' | 'lg' | 'xl'
     * @default 'md'
     */
    size?: 'sm' | 'md' | 'lg' | 'xl'
}

const props = withDefaults(defineProps<Props>(), {
    color: 'text-amber-500',
    accentColor: 'text-orange-400',
    size: 'md',
})

const sizeClasses = {
    sm: 'w-6 h-6',
    md: 'w-10 h-10',
    lg: 'w-16 h-16',
    xl: 'w-24 h-24',
}

const sparkleSize = {
    sm: { large: 8, medium: 6, small: 4 },
    md: { large: 12, medium: 8, small: 6 },
    lg: { large: 18, medium: 12, small: 8 },
    xl: { large: 24, medium: 16, small: 12 },
}
</script>

<template>
    <div
        role="status"
        aria-label="Loading"
        :class="cn('magical-sparkles-loader relative', sizeClasses[size], props.class)"
    >
        <!-- Central glowing orb -->
        <div class="absolute inset-0 flex items-center justify-center">
            <div :class="['central-orb rounded-full', color]" />
        </div>

        <!-- Orbiting sparkles container -->
        <div class="absolute inset-0 sparkle-orbit">
            <!-- Large sparkle 1 -->
            <svg
                class="sparkle sparkle-1"
                :width="sparkleSize[size].large"
                :height="sparkleSize[size].large"
                viewBox="0 0 24 24"
                fill="none"
            >
                <path
                    d="M12 0L14.59 9.41L24 12L14.59 14.59L12 24L9.41 14.59L0 12L9.41 9.41L12 0Z"
                    fill="currentColor"
                    :class="color"
                />
            </svg>

            <!-- Medium sparkle 2 -->
            <svg
                class="sparkle sparkle-2"
                :width="sparkleSize[size].medium"
                :height="sparkleSize[size].medium"
                viewBox="0 0 24 24"
                fill="none"
            >
                <path
                    d="M12 0L14.59 9.41L24 12L14.59 14.59L12 24L9.41 14.59L0 12L9.41 9.41L12 0Z"
                    fill="currentColor"
                    :class="accentColor"
                />
            </svg>

            <!-- Small sparkle 3 -->
            <svg
                class="sparkle sparkle-3"
                :width="sparkleSize[size].small"
                :height="sparkleSize[size].small"
                viewBox="0 0 24 24"
                fill="none"
            >
                <path
                    d="M12 0L14.59 9.41L24 12L14.59 14.59L12 24L9.41 14.59L0 12L9.41 9.41L12 0Z"
                    fill="currentColor"
                    :class="color"
                />
            </svg>

            <!-- Large sparkle 4 -->
            <svg
                class="sparkle sparkle-4"
                :width="sparkleSize[size].large"
                :height="sparkleSize[size].large"
                viewBox="0 0 24 24"
                fill="none"
            >
                <path
                    d="M12 0L14.59 9.41L24 12L14.59 14.59L12 24L9.41 14.59L0 12L9.41 9.41L12 0Z"
                    fill="currentColor"
                    :class="accentColor"
                />
            </svg>

            <!-- Medium sparkle 5 -->
            <svg
                class="sparkle sparkle-5"
                :width="sparkleSize[size].medium"
                :height="sparkleSize[size].medium"
                viewBox="0 0 24 24"
                fill="none"
            >
                <path
                    d="M12 0L14.59 9.41L24 12L14.59 14.59L12 24L9.41 14.59L0 12L9.41 9.41L12 0Z"
                    fill="currentColor"
                    :class="color"
                />
            </svg>

            <!-- Small sparkle 6 -->
            <svg
                class="sparkle sparkle-6"
                :width="sparkleSize[size].small"
                :height="sparkleSize[size].small"
                viewBox="0 0 24 24"
                fill="none"
            >
                <path
                    d="M12 0L14.59 9.41L24 12L14.59 14.59L12 24L9.41 14.59L0 12L9.41 9.41L12 0Z"
                    fill="currentColor"
                    :class="accentColor"
                />
            </svg>
        </div>

        <!-- Pulsing glow rings -->
        <div class="absolute inset-0 flex items-center justify-center">
            <div :class="['glow-ring glow-ring-1', color]" />
            <div :class="['glow-ring glow-ring-2', accentColor]" />
        </div>

        <span class="sr-only">Loading...</span>
    </div>
</template>

<style scoped>
.magical-sparkles-loader {
    --sparkle-duration: 2.5s;
}

/* Central orb */
.central-orb {
    width: 30%;
    height: 30%;
    animation: pulse-glow 1.5s ease-in-out infinite;
    box-shadow:
        0 0 10px currentColor,
        0 0 20px currentColor,
        0 0 30px currentColor;
}

@keyframes pulse-glow {
    0%,
    100% {
        opacity: 0.6;
        transform: scale(0.8);
    }
    50% {
        opacity: 1;
        transform: scale(1.2);
    }
}

/* Sparkle orbit container */
.sparkle-orbit {
    animation: orbit-rotate 4s linear infinite;
}

@keyframes orbit-rotate {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

/* Individual sparkles */
.sparkle {
    position: absolute;
    filter: drop-shadow(0 0 3px currentColor);
    animation: sparkle-twinkle var(--sparkle-duration) ease-in-out infinite;
}

.sparkle-1 {
    top: 0;
    left: 50%;
    transform: translateX(-50%);
    animation-delay: 0s;
}

.sparkle-2 {
    top: 15%;
    right: 5%;
    animation-delay: 0.4s;
}

.sparkle-3 {
    bottom: 15%;
    right: 10%;
    animation-delay: 0.8s;
}

.sparkle-4 {
    bottom: 0;
    left: 50%;
    transform: translateX(-50%);
    animation-delay: 1.2s;
}

.sparkle-5 {
    bottom: 15%;
    left: 5%;
    animation-delay: 1.6s;
}

.sparkle-6 {
    top: 15%;
    left: 10%;
    animation-delay: 2s;
}

@keyframes sparkle-twinkle {
    0%,
    100% {
        opacity: 0.3;
        transform: scale(0.6) rotate(0deg);
    }
    25% {
        opacity: 1;
        transform: scale(1.2) rotate(45deg);
    }
    50% {
        opacity: 0.5;
        transform: scale(0.8) rotate(90deg);
    }
    75% {
        opacity: 1;
        transform: scale(1) rotate(135deg);
    }
}

/* Glow rings */
.glow-ring {
    position: absolute;
    border-radius: 50%;
    border: 1px solid currentColor;
    opacity: 0;
}

.glow-ring-1 {
    width: 60%;
    height: 60%;
    animation: ring-expand 2s ease-out infinite;
}

.glow-ring-2 {
    width: 60%;
    height: 60%;
    animation: ring-expand 2s ease-out infinite 1s;
}

@keyframes ring-expand {
    0% {
        opacity: 0.8;
        transform: scale(0.5);
    }
    100% {
        opacity: 0;
        transform: scale(2);
    }
}
</style>


