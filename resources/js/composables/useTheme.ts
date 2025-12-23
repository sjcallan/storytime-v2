import type { ProfileTheme } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed, watch, onMounted } from 'vue';

/**
 * Convert hex color to RGB components.
 */
function hexToRgb(hex: string): { r: number; g: number; b: number } {
    const sanitized = hex.replace('#', '');
    const bigint = parseInt(sanitized, 16);
    return {
        r: (bigint >> 16) & 255,
        g: (bigint >> 8) & 255,
        b: bigint & 255,
    };
}

/**
 * Convert RGB to HSL.
 */
function rgbToHsl(r: number, g: number, b: number): { h: number; s: number; l: number } {
    r /= 255;
    g /= 255;
    b /= 255;

    const max = Math.max(r, g, b);
    const min = Math.min(r, g, b);
    let h = 0;
    let s = 0;
    const l = (max + min) / 2;

    if (max !== min) {
        const d = max - min;
        s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
        switch (max) {
            case r:
                h = ((g - b) / d + (g < b ? 6 : 0)) / 6;
                break;
            case g:
                h = ((b - r) / d + 2) / 6;
                break;
            case b:
                h = ((r - g) / d + 4) / 6;
                break;
        }
    }

    return { h: h * 360, s: s * 100, l: l * 100 };
}

/**
 * Calculate luminance from RGB.
 */
function getLuminance(r: number, g: number, b: number): number {
    const [rs, gs, bs] = [r, g, b].map(c => {
        c /= 255;
        return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
    });
    return 0.2126 * rs + 0.7152 * gs + 0.0722 * bs;
}

/**
 * Determine if a color is dark.
 */
function isDarkColor(hex: string): boolean {
    const { r, g, b } = hexToRgb(hex);
    return getLuminance(r, g, b) < 0.5;
}

/**
 * Lighten or darken a hex color.
 */
function adjustBrightness(hex: string, percent: number): string {
    const { r, g, b } = hexToRgb(hex);
    const adjust = (c: number) => Math.max(0, Math.min(255, Math.round(c + (255 * percent) / 100)));
    const toHex = (c: number) => c.toString(16).padStart(2, '0');
    return `#${toHex(adjust(r))}${toHex(adjust(g))}${toHex(adjust(b))}`;
}

/**
 * Blend two colors together.
 */
function blendColors(hex1: string, hex2: string, ratio: number): string {
    const c1 = hexToRgb(hex1);
    const c2 = hexToRgb(hex2);
    const blend = (a: number, b: number) => Math.round(a + (b - a) * ratio);
    const toHex = (c: number) => c.toString(16).padStart(2, '0');
    return `#${toHex(blend(c1.r, c2.r))}${toHex(blend(c1.g, c2.g))}${toHex(blend(c1.b, c2.b))}`;
}

/**
 * Generate complementary theme colors from background and text colors.
 */
function generateThemeColors(backgroundColor: string, textColor: string) {
    const isDark = isDarkColor(backgroundColor);
    
    // Card: slightly lighter/darker than background
    const card = isDark 
        ? adjustBrightness(backgroundColor, 5) 
        : adjustBrightness(backgroundColor, -3);
    
    // Accent: blend between background and text with slight shift
    const accent = isDark
        ? adjustBrightness(backgroundColor, 12)
        : adjustBrightness(backgroundColor, -8);
    
    // Muted: significantly shifted from background
    const muted = isDark
        ? adjustBrightness(backgroundColor, 10)
        : adjustBrightness(backgroundColor, -6);
    
    // Muted foreground: dimmed text color
    const mutedForeground = blendColors(textColor, backgroundColor, 0.4);
    
    // Border: subtle contrast from background
    const border = isDark
        ? adjustBrightness(backgroundColor, 15)
        : adjustBrightness(backgroundColor, -12);
    
    // Sidebar: slightly different from main background
    const sidebar = isDark
        ? adjustBrightness(backgroundColor, 3)
        : adjustBrightness(backgroundColor, -2);

    return {
        background: backgroundColor,
        foreground: textColor,
        card,
        accent,
        muted,
        mutedForeground,
        border,
        sidebar,
    };
}

/**
 * Composable to manage and apply custom themes from the current profile.
 */
export function useTheme() {
    const page = usePage();

    const currentProfile = computed(() => page.props.auth?.currentProfile);
    const activeTheme = computed(() => currentProfile.value?.active_theme ?? null);
    const themes = computed(() => currentProfile.value?.themes ?? []);
    // Background image is now part of each theme, not a global profile setting
    const backgroundImage = computed(() => activeTheme.value?.background_image ?? null);

    /**
     * Apply a theme by setting CSS custom properties on the document root.
     */
    const applyTheme = (theme: ProfileTheme | null, bgImage: string | null = null) => {
        const root = document.documentElement;

        if (!theme) {
            // Remove custom theme styles
            root.style.removeProperty('--custom-background');
            root.style.removeProperty('--custom-background-rgb');
            root.style.removeProperty('--custom-foreground');
            root.style.removeProperty('--custom-card');
            root.style.removeProperty('--custom-accent');
            root.style.removeProperty('--custom-muted');
            root.style.removeProperty('--custom-muted-foreground');
            root.style.removeProperty('--custom-border');
            root.style.removeProperty('--custom-sidebar');
            root.classList.remove('custom-theme-active');
        } else {
            const colors = generateThemeColors(theme.background_color, theme.text_color);
            const bgRgb = hexToRgb(theme.background_color);

            // Set custom properties for the theme
            root.style.setProperty('--custom-background', colors.background);
            root.style.setProperty('--custom-background-rgb', `${bgRgb.r}, ${bgRgb.g}, ${bgRgb.b}`);
            root.style.setProperty('--custom-foreground', colors.foreground);
            root.style.setProperty('--custom-card', colors.card);
            root.style.setProperty('--custom-accent', colors.accent);
            root.style.setProperty('--custom-muted', colors.muted);
            root.style.setProperty('--custom-muted-foreground', colors.mutedForeground);
            root.style.setProperty('--custom-border', colors.border);
            root.style.setProperty('--custom-sidebar', colors.sidebar);
            root.classList.add('custom-theme-active');
        }

        // Handle background image separately (can be used with or without color theme)
        applyBackgroundImage(bgImage);
    };

    /**
     * Apply a background image to the dashboard.
     */
    const applyBackgroundImage = (imageUrl: string | null) => {
        const root = document.documentElement;
        
        if (imageUrl) {
            root.style.setProperty('--custom-background-image', `url(${imageUrl})`);
            root.classList.add('custom-background-image-active');
        } else {
            root.style.removeProperty('--custom-background-image');
            root.classList.remove('custom-background-image-active');
        }
    };

    /**
     * Preview a theme without persisting it.
     */
    const previewTheme = (backgroundColor: string, textColor: string) => {
        const root = document.documentElement;
        const colors = generateThemeColors(backgroundColor, textColor);

        root.style.setProperty('--custom-background', colors.background);
        root.style.setProperty('--custom-foreground', colors.foreground);
        root.style.setProperty('--custom-card', colors.card);
        root.style.setProperty('--custom-accent', colors.accent);
        root.style.setProperty('--custom-muted', colors.muted);
        root.style.setProperty('--custom-muted-foreground', colors.mutedForeground);
        root.style.setProperty('--custom-border', colors.border);
        root.style.setProperty('--custom-sidebar', colors.sidebar);
        root.classList.add('custom-theme-active');
    };

    /**
     * Preview a background image without persisting it.
     */
    const previewBackgroundImage = (imageUrl: string | null) => {
        applyBackgroundImage(imageUrl);
    };

    /**
     * Clear the preview and restore the active theme.
     */
    const clearPreview = () => {
        applyTheme(activeTheme.value, backgroundImage.value);
    };

    // Watch for changes to the active theme and background image, apply them
    watch([activeTheme, backgroundImage], ([newTheme, newBgImage]) => {
        applyTheme(newTheme, newBgImage);
    }, { immediate: true });

    // Apply theme on mount
    onMounted(() => {
        applyTheme(activeTheme.value, backgroundImage.value);
    });

    return {
        activeTheme,
        themes,
        backgroundImage,
        currentProfile,
        applyTheme,
        applyBackgroundImage,
        previewTheme,
        previewBackgroundImage,
        clearPreview,
    };
}
