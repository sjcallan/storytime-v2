import { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from 'lucide-vue-next';

export interface Auth {
    user: User;
    profiles: Profile[];
    currentProfile: Profile | null;
    hasPin: boolean;
    isAdmin: boolean;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon;
    isActive?: boolean;
}

export interface AiDebugConfig {
    environment: string;
    default_provider: string;
    active_provider: {
        driver: string;
        model: string;
        max_tokens: number;
        temperature: number;
        base_url?: string;
    };
    moderation: {
        enabled: boolean;
        model: string;
        min_threshold: number;
    };
    image_generation: {
        provider: string;
        models: string[];
        use_custom_model: boolean;
        custom_model_version: string | null;
        custom_model_lora: string | null;
        custom_model_lora_scale: number | null;
    };
}

export type AppPageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    sidebarOpen: boolean;
    aiDebug?: AiDebugConfig;
};

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    profile_photo_path?: string | null;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
}

export interface ProfileTheme {
    id: string;
    name: string;
    background_color: string;
    text_color: string;
    background_image?: string | null;
    background_description?: string | null;
}

export interface Profile {
    id: string;
    user_id: string;
    name: string;
    avatar: string | null;
    profile_image_path: string | null;
    age_group: string;
    age_group_label: string;
    is_default: boolean;
    themes: ProfileTheme[] | null;
    active_theme_id: string | null;
    active_theme: ProfileTheme | null;
    background_image: string | null;
    moderation_thresholds: Record<string, number> | null;
    effective_moderation_thresholds: Record<string, number>;
    created_at: string;
    updated_at: string;
}

export type BreadcrumbItemType = BreadcrumbItem;
