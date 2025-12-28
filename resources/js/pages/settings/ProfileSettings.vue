<script setup lang="ts">
import ProfileSettingsController from '@/actions/App/Http/Controllers/Settings/ProfileSettingsController';
import ProfilesController from '@/actions/App/Http/Controllers/Settings/ProfilesController';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import axios from 'axios';

import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useInitials } from '@/composables/useInitials';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import {
    ArrowLeft,
    BarChart3,
    BookOpen,
    Camera,
    ChevronDown,
    Coins,
    ExternalLink,
    FileText,
    Info,
    Loader2,
    Pencil,
    RotateCcw,
    Save,
    Shield,
    ShieldAlert,
    ShieldCheck,
    Sparkles,
    Star,
    Trash2,
    Upload,
    Wand2,
    X,
    Zap,
} from 'lucide-vue-next';

interface AgeGroup {
    label: string;
    range: string;
    emoji: string;
}

interface ModerationCategory {
    label: string;
    description: string;
    icon: string;
}

interface Stats {
    total_cost: number;
    total_tokens: number;
    prompt_tokens: number;
    completion_tokens: number;
    output_images: number;
    total_requests: number;
    total_books: number;
    total_chapters: number;
}

interface AllTimeStats {
    total_cost: number;
    total_tokens: number;
    total_requests: number;
    total_books: number;
    total_chapters: number;
}

interface ChartDataPoint {
    date: string;
    cost: number;
}

interface Filters {
    start_date: string;
    end_date: string;
}

interface Profile {
    id: string;
    name: string;
    avatar: string | null;
    profile_image_path: string | null;
    profile_image_prompt: string | null;
    age_group: string;
    age_group_label: string;
    is_default: boolean;
    moderation_thresholds: Record<string, number> | null;
    effective_moderation_thresholds: Record<string, number>;
    created_at: string;
}

interface Props {
    profile: Profile;
    ageGroups: Record<string, AgeGroup>;
    moderationCategories: Record<string, ModerationCategory>;
    defaultThresholds: Record<string, number>;
    stats: Stats;
    allTimeStats: AllTimeStats;
    chartData: ChartDataPoint[];
    filters: Filters;
}

const props = defineProps<Props>();

const { getInitials } = useInitials();

// Profile settings state
const profileName = ref(props.profile.name);
const profileAgeGroup = ref(props.profile.age_group);
const isSavingProfile = ref(false);
const profileErrors = ref<Record<string, string>>({});

// Profile image state
const profileAvatar = ref(props.profile.avatar);
const photoInput = ref<HTMLInputElement | null>(null);
const uploadingPhoto = ref(false);
const photoError = ref<string | null>(null);
const imageDescription = ref(props.profile.profile_image_prompt || '');
const isGeneratingImage = ref(false);
const generationError = ref<string | null>(null);
const isImageModalOpen = ref(false);

// Example prompts for AI profile images
const examplePrompts = [
    'A brave young adventurer with flowing hair and determined eyes',
    'A wise elder with kind eyes and silver hair',
    'A playful child with freckles and a big smile',
    'A mysterious figure with a hood and glowing eyes',
    'A nature-loving character surrounded by leaves and flowers',
    'A tech-savvy hero with futuristic gear',
];

// Moderation thresholds state
const thresholds = ref<Record<string, number>>({ ...props.profile.effective_moderation_thresholds });
const isSavingModeration = ref(false);
const isResettingModeration = ref(false);
const moderationErrors = ref<Record<string, string>>({});
const moderationSectionOpen = ref(false);

// Delete confirmation
const isDeleteDialogOpen = ref(false);
const isDeleting = ref(false);

// Date filters
const startDate = ref(props.filters.start_date);
const endDate = ref(props.filters.end_date);

const ageGroupOptions = computed(() => {
    return Object.entries(props.ageGroups).map(([value, data]) => ({
        value,
        label: `${data.emoji} ${data.label}`,
        range: data.range,
    }));
});

const hasCustomThresholds = computed(() => {
    return props.profile.moderation_thresholds !== null;
});

const categoriesByRisk = computed(() => {
    const categories = Object.entries(props.moderationCategories);
    
    // Group into critical (always strict) and adjustable
    const critical = categories.filter(([key]) => 
        key === 'sexual/minors'
    );
    
    const adjustable = categories.filter(([key]) => 
        key !== 'sexual/minors'
    );
    
    return { critical, adjustable };
});

function applyFilters(): void {
    router.get(
        ProfileSettingsController.show.url(props.profile.id),
        {
            start_date: startDate.value,
            end_date: endDate.value,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        }
    );
}

function setPreset(days: number): void {
    const end = new Date();
    const start = new Date();
    start.setDate(start.getDate() - days);
    
    startDate.value = start.toISOString().split('T')[0];
    endDate.value = end.toISOString().split('T')[0];
    applyFilters();
}

function formatCost(cost: number): string {
    return `$${cost.toFixed(3)}`;
}

function formatNumber(num: number): string {
    return num.toLocaleString();
}

function formatDate(dateString: string): string {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
    });
}

function saveProfile(): void {
    isSavingProfile.value = true;
    profileErrors.value = {};

    router.patch(
        ProfileSettingsController.update.url(props.profile.id),
        {
            name: profileName.value,
            age_group: profileAgeGroup.value,
        },
        {
            preserveScroll: true,
            onError: (errors) => {
                profileErrors.value = errors;
            },
            onFinish: () => {
                isSavingProfile.value = false;
            },
        }
    );
}

// Image handling functions
function selectPhoto(): void {
    photoInput.value?.click();
}

function handlePhotoChange(): void {
    const photo = photoInput.value?.files?.[0];
    if (!photo) return;

    photoError.value = null;
    generationError.value = null;

    const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    if (!validTypes.includes(photo.type)) {
        photoError.value = 'Please select a JPG, PNG, GIF, or WebP image.';
        return;
    }

    if (photo.size > 2 * 1024 * 1024) {
        photoError.value = 'The photo must not be larger than 2MB.';
        return;
    }

    uploadPhoto(photo);
}

function uploadPhoto(photo: File): void {
    uploadingPhoto.value = true;
    photoError.value = null;

    const formData = new FormData();
    formData.append('image', photo);

    router.post(ProfilesController.updateImage.url(props.profile.id), formData, {
        preserveScroll: true,
        onSuccess: (page: any) => {
            // Update from the profile prop if available
            const updatedProfile = page.props.profile;
            if (updatedProfile) {
                profileAvatar.value = updatedProfile.avatar;
            }
            // Also check the currentProfile in auth (for header/flyout updates)
            const currentProfile = page.props.auth?.currentProfile;
            if (currentProfile?.id === props.profile.id) {
                profileAvatar.value = currentProfile.avatar;
            }
        },
        onError: (errors) => {
            photoError.value = errors.image || 'Failed to upload photo.';
        },
        onFinish: () => {
            uploadingPhoto.value = false;
            if (photoInput.value) {
                photoInput.value.value = '';
            }
        },
    });
}

async function handleGenerateImage(): Promise<void> {
    if (imageDescription.value.length < 10) {
        generationError.value = 'Please provide a description of at least 10 characters.';
        return;
    }

    isGeneratingImage.value = true;
    generationError.value = null;

    try {
        const response = await axios.post(
            ProfilesController.generateImage.url(props.profile.id),
            { description: imageDescription.value }
        );

        if (response.data.success) {
            profileAvatar.value = response.data.avatar;
            // Reload to refresh the auth.currentProfile in header/flyout
            router.reload({ only: ['auth'] });
        } else {
            generationError.value = response.data.error || 'Failed to generate image.';
        }
    } catch (error: any) {
        console.error('Failed to generate profile image:', error);
        generationError.value = error.response?.data?.error || 'An error occurred while generating the image.';
    } finally {
        isGeneratingImage.value = false;
    }
}

function removePhoto(): void {
    router.delete(ProfilesController.destroyImage.url(props.profile.id), {
        preserveScroll: true,
        onSuccess: () => {
            profileAvatar.value = null;
            // Reload to refresh the auth.currentProfile in header/flyout
            router.reload({ only: ['auth'] });
        },
    });
}

function useExamplePrompt(prompt: string): void {
    imageDescription.value = prompt;
}

function getSafeKey(category: string): string {
    return category.replace(/\//g, '_').replace(/-/g, '_');
}

function saveModeration(): void {
    isSavingModeration.value = true;
    moderationErrors.value = {};

    const safeThresholds: Record<string, number> = {};
    for (const [key, value] of Object.entries(thresholds.value)) {
        safeThresholds[getSafeKey(key)] = value;
    }

    router.patch(
        ProfileSettingsController.updateModeration.url(props.profile.id),
        {
            thresholds: safeThresholds,
        },
        {
            preserveScroll: true,
            onError: (errors) => {
                moderationErrors.value = errors;
            },
            onFinish: () => {
                isSavingModeration.value = false;
            },
        }
    );
}

function resetModeration(): void {
    isResettingModeration.value = true;

    router.post(
        ProfileSettingsController.resetModeration.url(props.profile.id),
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                thresholds.value = { ...props.defaultThresholds };
            },
            onFinish: () => {
                isResettingModeration.value = false;
            },
        }
    );
}

function setDefault(): void {
    router.post(ProfileSettingsController.setDefault.url(props.profile.id), {}, {
        preserveScroll: true,
    });
}

function deleteProfile(): void {
    isDeleting.value = true;

    router.delete(ProfileSettingsController.destroy.url(props.profile.id), {
        onFinish: () => {
            isDeleting.value = false;
            isDeleteDialogOpen.value = false;
        },
    });
}

function getThresholdColor(value: number): string {
    if (value <= 0.2) return 'text-green-600 dark:text-green-400';
    if (value <= 0.5) return 'text-yellow-600 dark:text-yellow-400';
    if (value <= 0.7) return 'text-orange-600 dark:text-orange-400';
    return 'text-red-600 dark:text-red-400';
}

function getThresholdLabel(value: number): string {
    if (value <= 0.2) return 'Very Strict';
    if (value <= 0.4) return 'Strict';
    if (value <= 0.6) return 'Moderate';
    if (value <= 0.8) return 'Permissive';
    return 'Very Permissive';
}

const maxChartValue = computed(() => {
    return Math.max(...props.chartData.map((d) => d.cost), 0.001);
});

const chartBars = computed(() => {
    return props.chartData.map((point) => ({
        ...point,
        height: (point.cost / maxChartValue.value) * 100,
        formattedDate: formatDate(point.date),
    }));
});

// Update thresholds when age group changes (to show new defaults)
watch(profileAgeGroup, () => {
    if (!hasCustomThresholds.value) {
        // User hasn't customized yet, update to show new defaults for reference
    }
});

// Keep local avatar in sync with props (for when page reloads update the profile)
watch(() => props.profile.avatar, (newAvatar) => {
    profileAvatar.value = newAvatar;
});
</script>

<template>
    <AppLayout>
        <Head :title="`${profile.name} - Profile Settings`" />

        <SettingsLayout>
            <div class="space-y-8">
                <!-- Hidden file input for photo upload -->
                <input
                    ref="photoInput"
                    type="file"
                    class="hidden"
                    accept="image/jpeg,image/jpg,image/png,image/gif,image/webp"
                    @change="handlePhotoChange"
                />

                <!-- Header with Back Button -->
                <div class="flex items-start justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <Button variant="ghost" size="icon" as-child>
                            <Link href="/settings/profiles">
                                <ArrowLeft class="h-5 w-5" />
                            </Link>
                        </Button>
                        <div class="flex items-center gap-4">
                            <!-- Clickable Avatar with Pencil Overlay -->
                            <button
                                type="button"
                                class="relative group cursor-pointer rounded-full focus:outline-none focus:ring-2 focus:ring-violet-500 focus:ring-offset-2"
                                @click="isImageModalOpen = true"
                                title="Click to edit profile image"
                            >
                                <Avatar class="h-14 w-14 ring-2 ring-border group-hover:ring-violet-500 transition-all">
                                    <AvatarImage
                                        v-if="profileAvatar"
                                        :src="profileAvatar"
                                        :alt="profile.name"
                                        class="object-cover"
                                    />
                                    <AvatarFallback class="text-lg font-medium bg-gradient-to-br from-violet-500 to-purple-600 text-white">
                                        {{ getInitials(profile.name) }}
                                    </AvatarFallback>
                                </Avatar>
                                <!-- Pencil Overlay on Hover -->
                                <div class="absolute inset-0 flex items-center justify-center rounded-full bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <Pencil class="h-5 w-5 text-white" />
                                </div>
                            </button>
                            <div>
                                <h1 class="text-xl font-semibold flex items-center gap-2">
                                    {{ profile.name }}
                                    <Badge v-if="profile.is_default" variant="secondary" class="shrink-0">
                                        <Star class="mr-1 h-3 w-3" />
                                        Default
                                    </Badge>
                                </h1>
                                <p class="text-sm text-muted-foreground">
                                    {{ ageGroups[profile.age_group]?.emoji }} {{ profile.age_group_label }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <Separator />

                <!-- Profile Settings Section -->
                <div class="space-y-4">
                    <HeadingSmall
                        title="Profile Information"
                        description="Update the profile's name and age group"
                    />

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="profile-name">Profile Name</Label>
                            <Input
                                id="profile-name"
                                v-model="profileName"
                                placeholder="Profile name"
                            />
                            <InputError :message="profileErrors.name" />
                        </div>

                        <div class="space-y-2">
                            <Label for="profile-age-group">Age Group</Label>
                            <Select v-model="profileAgeGroup">
                                <SelectTrigger>
                                    <SelectValue placeholder="Select age group" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="option in ageGroupOptions"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }} ({{ option.range }})
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="profileErrors.age_group" />
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <Button @click="saveProfile" :disabled="isSavingProfile" size="sm">
                            <Loader2 v-if="isSavingProfile" class="mr-2 h-4 w-4 animate-spin" />
                            <Save v-else class="mr-2 h-4 w-4" />
                            Save Changes
                        </Button>

                        <Button
                            v-if="!profile.is_default"
                            variant="outline"
                            size="sm"
                            @click="setDefault"
                        >
                            <Star class="mr-2 h-4 w-4" />
                            Set as Default
                        </Button>
                    </div>
                </div>

                <Separator />

                <!-- Usage Statistics Section -->
                <div class="space-y-4">
                    <div class="flex items-start justify-between gap-4">
                        <HeadingSmall
                            title="Usage Statistics"
                            description="Monitor this profile's usage and costs"
                        />

                        <!-- Date Range Filter -->
                        <div class="flex flex-wrap items-end gap-2">
                            <div class="flex items-center gap-2">
                                <Input
                                    v-model="startDate"
                                    type="date"
                                    class="w-32 text-xs"
                                />
                                <span class="text-muted-foreground">to</span>
                                <Input
                                    v-model="endDate"
                                    type="date"
                                    class="w-32 text-xs"
                                />
                            </div>
                            <Button @click="applyFilters" size="sm" variant="outline">
                                Apply
                            </Button>
                            <div class="flex gap-1">
                                <Button variant="ghost" size="sm" @click="setPreset(7)">
                                    7d
                                </Button>
                                <Button variant="ghost" size="sm" @click="setPreset(30)">
                                    30d
                                </Button>
                                <Button variant="ghost" size="sm" @click="setPreset(90)">
                                    90d
                                </Button>
                            </div>
                        </div>
                    </div>

                    <!-- Stats Cards -->
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <!-- Books Card -->
                        <Card class="overflow-hidden pt-0">
                            <CardHeader class="border-b border-border/50 bg-gradient-to-br from-blue-500/10 to-transparent p-4">
                                <div class="flex items-center gap-2">
                                    <div class="rounded-lg bg-blue-500/20 p-2">
                                        <BookOpen class="h-4 w-4 text-blue-600 dark:text-blue-400" />
                                    </div>
                                    <CardTitle class="text-base font-semibold">Books</CardTitle>
                                </div>
                            </CardHeader>
                            <CardContent class="pt-4">
                                <p class="text-2xl font-bold tabular-nums">
                                    {{ formatNumber(allTimeStats.total_books) }}
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    Total books created
                                </p>
                            </CardContent>
                        </Card>

                        <!-- Chapters Card -->
                        <Card class="overflow-hidden pt-0">
                            <CardHeader class="border-b border-border/50 bg-gradient-to-br from-purple-500/10 to-transparent p-4">
                                <div class="flex items-center gap-2">
                                    <div class="rounded-lg bg-purple-500/20 p-2">
                                        <FileText class="h-4 w-4 text-purple-600 dark:text-purple-400" />
                                    </div>
                                    <CardTitle class="text-base font-semibold">Chapters</CardTitle>
                                </div>
                            </CardHeader>
                            <CardContent class="pt-4">
                                <p class="text-2xl font-bold tabular-nums">
                                    {{ formatNumber(allTimeStats.total_chapters) }}
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    Total chapters written
                                </p>
                            </CardContent>
                        </Card>

                        <!-- Requests Card -->
                        <Card class="overflow-hidden pt-0">
                            <CardHeader class="border-b border-border/50 bg-gradient-to-br from-amber-500/10 to-transparent p-4">
                                <div class="flex items-center gap-2">
                                    <div class="rounded-lg bg-amber-500/20 p-2">
                                        <Zap class="h-4 w-4 text-amber-600 dark:text-amber-400" />
                                    </div>
                                    <CardTitle class="text-base font-semibold">Requests (Period)</CardTitle>
                                </div>
                            </CardHeader>
                            <CardContent class="pt-4">
                                <p class="text-2xl font-bold tabular-nums">
                                    {{ formatNumber(stats.total_requests) }}
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    {{ formatNumber(stats.total_tokens) }} tokens used
                                </p>
                            </CardContent>
                        </Card>

                        <!-- Cost Card -->
                        <Card class="overflow-hidden pt-0">
                            <CardHeader class="border-b border-border/50 bg-gradient-to-br from-emerald-500/10 to-transparent p-4">
                                <div class="flex items-center gap-2">
                                    <div class="rounded-lg bg-emerald-500/20 p-2">
                                        <Coins class="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
                                    </div>
                                    <CardTitle class="text-base font-semibold">Cost (Period)</CardTitle>
                                </div>
                            </CardHeader>
                            <CardContent class="pt-4">
                                <p class="text-2xl font-bold tabular-nums text-emerald-600 dark:text-emerald-400">
                                    {{ formatCost(stats.total_cost) }}
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    {{ formatCost(allTimeStats.total_cost) }} all time
                                </p>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- Daily Cost Chart -->
                    <Card class="overflow-hidden pt-0" v-if="chartData.length > 0">
                        <CardHeader class="border-b border-border/50 p-4">
                            <div class="flex items-center gap-2">
                                <div class="rounded-lg bg-muted p-2">
                                    <BarChart3 class="h-4 w-4 text-muted-foreground" />
                                </div>
                                <CardTitle class="text-base font-semibold">Daily Cost</CardTitle>
                            </div>
                        </CardHeader>
                        <CardContent class="pt-4">
                            <div class="space-y-2">
                                <div class="relative h-32 ml-12">
                                    <div class="flex h-full items-end gap-px">
                                        <div
                                            v-for="(bar, index) in chartBars"
                                            :key="bar.date"
                                            class="group relative h-full flex-1 min-w-0 flex items-end"
                                        >
                                            <div
                                                class="w-full rounded-t bg-gradient-to-t from-emerald-500 to-emerald-400 transition-all hover:from-emerald-600 hover:to-emerald-500"
                                                :style="{ height: `${Math.max(bar.height, 1)}%` }"
                                            />
                                            <div class="pointer-events-none absolute bottom-full left-1/2 z-10 mb-2 -translate-x-1/2 whitespace-nowrap opacity-0 transition-opacity group-hover:opacity-100">
                                                <div class="rounded-md bg-popover border border-border px-2 py-1 text-xs shadow-md">
                                                    <p class="font-medium">{{ bar.formattedDate }}</p>
                                                    <p class="text-emerald-600 dark:text-emerald-400">{{ formatCost(bar.cost) }}</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="absolute left-0 top-0 h-full flex flex-col justify-between text-[10px] text-muted-foreground -translate-x-full pr-2">
                                        <span>{{ formatCost(maxChartValue) }}</span>
                                        <span>$0</span>
                                    </div>
                                </div>
                                <div class="flex justify-between text-[10px] text-muted-foreground ml-12 px-1">
                                    <span>{{ formatDate(chartData[0]?.date) }}</span>
                                    <span v-if="chartData.length > 1">{{ formatDate(chartData[chartData.length - 1]?.date) }}</span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <Separator />

                <!-- Content Safety Settings Section -->
                <div class="space-y-4">
                    <Collapsible v-model:open="moderationSectionOpen">
                        <CollapsibleTrigger class="flex w-full items-center justify-between rounded-lg border border-border/50 bg-card p-4 hover:bg-muted/50 transition-colors">
                            <div class="flex items-center gap-3">
                                <div class="rounded-lg bg-violet-500/20 p-2.5">
                                    <Shield class="h-5 w-5 text-violet-600 dark:text-violet-400" />
                                </div>
                                <div class="text-left">
                                    <h3 class="text-base font-semibold flex items-center gap-2">
                                        Content Safety Settings
                                        <Badge v-if="hasCustomThresholds" variant="outline" class="text-xs">
                                            Customized
                                        </Badge>
                                    </h3>
                                    <p class="text-sm text-muted-foreground">
                                        Control what content is allowed for this profile
                                    </p>
                                </div>
                            </div>
                            <ChevronDown class="h-5 w-5 text-muted-foreground transition-transform duration-200" :class="{ 'rotate-180': moderationSectionOpen }" />
                        </CollapsibleTrigger>

                        <CollapsibleContent class="pt-4 space-y-6">
                            <!-- Info Banner -->
                            <div class="flex items-start gap-3 rounded-lg border border-blue-200 dark:border-blue-800 bg-blue-50/50 dark:bg-blue-950/30 p-4">
                                <Info class="h-5 w-5 text-blue-600 dark:text-blue-400 shrink-0 mt-0.5" />
                                <div class="text-sm text-blue-800 dark:text-blue-200">
                                    <p class="font-medium">How this works</p>
                                    <p class="mt-1 text-blue-700 dark:text-blue-300">
                                        Lower thresholds are more restrictive and will block content more easily. 
                                        Higher thresholds are more permissive. Each category has age-appropriate 
                                        defaults that you can customize. Content flagged by OpenAI's safety system 
                                        is always blocked regardless of these settings.
                                    </p>
                                </div>
                            </div>

                            <!-- Adjustable Categories -->
                            <div class="space-y-3">
                                <h4 class="text-sm font-medium text-muted-foreground flex items-center gap-2">
                                    <ShieldCheck class="h-4 w-4 text-green-500" />
                                    Adjustable Filters
                                </h4>

                                <div class="grid gap-4 md:grid-cols-2">
                                    <TooltipProvider :delay-duration="300">
                                        <div
                                            v-for="[category, meta] in categoriesByRisk.adjustable"
                                            :key="category"
                                            class="rounded-lg border border-border bg-card p-4 space-y-3"
                                        >
                                            <div class="flex items-start justify-between gap-2">
                                                <div class="flex items-center gap-2">
                                                    <Tooltip>
                                                        <TooltipTrigger>
                                                            <Info class="h-3.5 w-3.5 text-muted-foreground" />
                                                        </TooltipTrigger>
                                                        <TooltipContent>
                                                            <p class="max-w-xs">{{ meta.description }}</p>
                                                        </TooltipContent>
                                                    </Tooltip>
                                                    <span class="text-sm font-medium">{{ meta.label }}</span>
                                                </div>
                                                <span :class="['text-xs font-medium', getThresholdColor(thresholds[category] ?? 0.5)]">
                                                    {{ getThresholdLabel(thresholds[category] ?? 0.5) }}
                                                </span>
                                            </div>

                                            <div class="space-y-2">
                                                <input
                                                    type="range"
                                                    v-model.number="thresholds[category]"
                                                    min="0.01"
                                                    max="1"
                                                    step="0.01"
                                                    class="w-full h-2 bg-muted rounded-lg appearance-none cursor-pointer accent-violet-500"
                                                />
                                                <div class="flex justify-between text-[10px] text-muted-foreground">
                                                    <span>Strict (0.01)</span>
                                                    <span class="font-medium tabular-nums">{{ (thresholds[category] ?? 0.5).toFixed(2) }}</span>
                                                    <span>Permissive (1.0)</span>
                                                </div>
                                            </div>

                                            <p class="text-xs text-muted-foreground">
                                                Default for {{ profile.age_group_label }}: {{ (defaultThresholds[category] ?? 0.5).toFixed(2) }}
                                            </p>
                                        </div>
                                    </TooltipProvider>
                                </div>
                            </div>

                            <InputError :message="moderationErrors.thresholds" />

                            <!-- Action Buttons -->
                            <div class="flex items-center gap-2 pt-2">
                                <Button @click="saveModeration" :disabled="isSavingModeration" size="sm">
                                    <Loader2 v-if="isSavingModeration" class="mr-2 h-4 w-4 animate-spin" />
                                    <Save v-else class="mr-2 h-4 w-4" />
                                    Save Safety Settings
                                </Button>

                                <Button
                                    variant="outline"
                                    size="sm"
                                    @click="resetModeration"
                                    :disabled="isResettingModeration || !hasCustomThresholds"
                                >
                                    <Loader2 v-if="isResettingModeration" class="mr-2 h-4 w-4 animate-spin" />
                                    <RotateCcw v-else class="mr-2 h-4 w-4" />
                                    Reset to Age Defaults
                                </Button>
                            </div>
                        </CollapsibleContent>
                    </Collapsible>
                </div>

                <Separator />

                <!-- Danger Zone -->
                <div v-if="!profile.is_default" class="space-y-4">
                    <HeadingSmall
                        title="Danger Zone"
                        description="Irreversible and destructive actions"
                    />

                    <div class="rounded-lg border border-destructive/30 bg-destructive/5 p-4">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <h4 class="text-sm font-medium text-destructive">Delete this profile</h4>
                                <p class="text-sm text-muted-foreground">
                                    Once deleted, this profile and all associated data will be permanently removed.
                                </p>
                            </div>
                            <Button
                                variant="destructive"
                                size="sm"
                                @click="isDeleteDialogOpen = true"
                            >
                                <Trash2 class="mr-2 h-4 w-4" />
                                Delete Profile
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Delete Confirmation Dialog -->
            <Dialog v-model:open="isDeleteDialogOpen">
                <DialogContent class="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Delete Profile</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to delete "{{ profile.name }}"? 
                            This will permanently remove the profile and all associated reading history. 
                            This action cannot be undone.
                        </DialogDescription>
                    </DialogHeader>

                    <DialogFooter>
                        <Button variant="outline" @click="isDeleteDialogOpen = false">
                            Cancel
                        </Button>
                        <Button variant="destructive" @click="deleteProfile" :disabled="isDeleting">
                            <Loader2 v-if="isDeleting" class="mr-2 h-4 w-4 animate-spin" />
                            Delete Profile
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <!-- Profile Image Modal -->
            <Dialog v-model:open="isImageModalOpen">
                <DialogContent class="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle class="flex items-center gap-2">
                            <Camera class="h-5 w-5 text-violet-500" />
                            Profile Image
                        </DialogTitle>
                        <DialogDescription>
                            Upload a photo or generate an avatar for {{ profile.name }}
                        </DialogDescription>
                    </DialogHeader>

                    <div class="space-y-6">
                        <!-- Current Profile Image Preview -->
                        <div class="flex items-center gap-4">
                            <a
                                v-if="profileAvatar"
                                :href="profileAvatar"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="relative block cursor-pointer group shrink-0"
                                title="Click to view full image"
                            >
                                <Avatar class="h-24 w-24 rounded-full ring-2 ring-border transition-all group-hover:ring-violet-500 group-hover:ring-offset-2">
                                    <AvatarImage
                                        :src="profileAvatar"
                                        :alt="profile.name"
                                        class="object-cover"
                                    />
                                </Avatar>
                                <div class="absolute inset-0 flex items-center justify-center rounded-full bg-black/40 opacity-0 transition-opacity group-hover:opacity-100">
                                    <ExternalLink class="h-5 w-5 text-white" />
                                </div>
                            </a>
                            <div v-else class="relative shrink-0">
                                <Avatar class="h-24 w-24 rounded-full ring-2 ring-border">
                                    <AvatarFallback class="text-2xl font-medium bg-gradient-to-br from-violet-500 to-purple-600 text-white">
                                        {{ getInitials(profile.name) }}
                                    </AvatarFallback>
                                </Avatar>
                            </div>
                            <div class="flex flex-col gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    @click="selectPhoto"
                                    :disabled="uploadingPhoto || isGeneratingImage"
                                >
                                    <Upload v-if="!uploadingPhoto" class="mr-2 h-4 w-4" />
                                    <Loader2 v-else class="mr-2 h-4 w-4 animate-spin" />
                                    Upload Photo
                                </Button>
                                <Button
                                    v-if="profileAvatar"
                                    variant="ghost"
                                    size="sm"
                                    class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                    @click="removePhoto"
                                    :disabled="uploadingPhoto || isGeneratingImage"
                                >
                                    <Trash2 class="mr-2 h-4 w-4" />
                                    Remove Photo
                                </Button>
                            </div>
                        </div>

                        <!-- Error messages -->
                        <InputError v-if="photoError" :message="photoError" />

                        <!-- Divider -->
                        <div class="relative">
                            <div class="absolute inset-0 flex items-center">
                                <span class="w-full border-t border-border" />
                            </div>
                            <div class="relative flex justify-center text-xs uppercase">
                                <span class="bg-background px-2 text-muted-foreground">or</span>
                            </div>
                        </div>

                        <!-- AI Image Generation -->
                        <div class="space-y-3 rounded-lg border border-dashed border-violet-300/50 bg-violet-50/30 dark:bg-violet-950/20 p-4">
                            <div class="flex items-center gap-2 text-sm font-medium">
                                <Sparkles class="h-4 w-4 text-violet-500" />
                                Generate with AI
                                <Badge variant="secondary" class="text-xs">Graphic Novel Style</Badge>
                            </div>
                            
                            <div class="space-y-2">
                                <Label for="image-description-modal" class="text-xs text-muted-foreground">
                                    Describe your character
                                </Label>
                                <Textarea
                                    id="image-description-modal"
                                    v-model="imageDescription"
                                    placeholder="e.g., A brave young adventurer with flowing hair and determined eyes..."
                                    rows="2"
                                    class="resize-none text-sm"
                                    :disabled="isGeneratingImage"
                                />
                            </div>

                            <!-- Example Prompts -->
                            <div class="flex flex-wrap gap-1.5">
                                <button
                                    v-for="(prompt, index) in examplePrompts.slice(0, 4)"
                                    :key="index"
                                    type="button"
                                    class="text-xs px-2 py-0.5 rounded-full bg-violet-100 dark:bg-violet-900/40 text-violet-700 dark:text-violet-300 hover:bg-violet-200 dark:hover:bg-violet-800/50 transition-colors truncate max-w-[150px]"
                                    @click="useExamplePrompt(prompt)"
                                    :disabled="isGeneratingImage"
                                    :title="prompt"
                                >
                                    {{ prompt.slice(0, 28) }}...
                                </button>
                            </div>

                            <!-- Generate Button -->
                            <Button
                                @click="handleGenerateImage"
                                :disabled="imageDescription.length < 10 || isGeneratingImage"
                                variant="outline"
                                size="sm"
                                class="w-full border-violet-300 hover:bg-violet-100 dark:hover:bg-violet-900/30"
                            >
                                <Wand2 v-if="!isGeneratingImage" class="h-4 w-4 mr-2 text-violet-500" />
                                <Loader2 v-else class="h-4 w-4 mr-2 animate-spin" />
                                {{ isGeneratingImage ? 'Generating... (30-60s)' : 'Generate Avatar' }}
                            </Button>

                            <!-- Generation Error -->
                            <div v-if="generationError" class="rounded-lg bg-destructive/10 p-2 text-xs text-destructive">
                                {{ generationError }}
                            </div>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button variant="outline" @click="isImageModalOpen = false">
                            Done
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </SettingsLayout>
    </AppLayout>
</template>

<style scoped>
input[type="range"]::-webkit-slider-thumb {
    appearance: none;
    width: 1rem;
    height: 1rem;
    border-radius: 9999px;
    background-color: rgb(139 92 246);
    cursor: pointer;
    box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05);
}

input[type="range"]::-moz-range-thumb {
    width: 1rem;
    height: 1rem;
    border-radius: 9999px;
    background-color: rgb(139 92 246);
    cursor: pointer;
    border: 0;
    box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05);
}
</style>

