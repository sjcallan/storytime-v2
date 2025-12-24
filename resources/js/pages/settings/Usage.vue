<script setup lang="ts">
import { index as usageIndex } from '@/routes/usage';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import type { AppPageProps, Profile } from '@/types';
import {
    ChevronLeft,
    ChevronRight,
    Coins,
    Cpu,
    FileText,
    Zap,
    Calendar,
    TrendingUp,
} from 'lucide-vue-next';

interface RequestLog {
    id: string;
    created_at: string;
    model: string | null;
    item_type: string | null;
    prompt_tokens: number | string | null;
    completion_tokens: number | string | null;
    total_tokens: number | string | null;
    total_cost: number | string | null;
    profile: {
        id: string;
        name: string;
    } | null;
}

interface PaginatedLogs {
    data: RequestLog[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
}

interface UsageStats {
    prompt_tokens: number;
    completion_tokens: number;
    total_tokens: number;
    total_cost: number;
    total_requests: number;
}

interface Props {
    logs: PaginatedLogs;
    allTimeStats: UsageStats;
    last30DaysStats: UsageStats;
    selectedProfileId: string | null;
}

const props = defineProps<Props>();

const page = usePage<AppPageProps>();
const profiles = computed(() => page.props.auth.profiles as Profile[]);

const selectedProfile = ref<string>(props.selectedProfileId ?? 'all');

watch(selectedProfile, (newValue) => {
    const profileId = newValue === 'all' ? null : newValue;
    router.get(
        usageIndex.url(),
        { profile_id: profileId },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        }
    );
});

function formatCost(cost: number | string | null): string {
    if (cost === null || cost === undefined) {
        return '$0.000';
    }
    const numCost = typeof cost === 'string' ? parseFloat(cost) : cost;
    if (isNaN(numCost)) {
        return '$0.000';
    }
    return `$${numCost.toFixed(3)}`;
}

function formatTokens(tokens: number | string | null): string {
    if (tokens === null || tokens === undefined) {
        return '0';
    }
    const numTokens = typeof tokens === 'string' ? parseInt(tokens, 10) : tokens;
    if (isNaN(numTokens)) {
        return '0';
    }
    return numTokens.toLocaleString();
}

function formatDate(dateString: string): string {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function formatModel(model: string | null): string {
    if (!model) {
        return '-';
    }
    if (model.length > 30) {
        return model.substring(0, 27) + '...';
    }
    return model;
}

function formatItemType(itemType: string | null): string {
    if (!itemType) {
        return '-';
    }
    return itemType
        .split('_')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
}

function goToPage(url: string | null): void {
    if (url) {
        router.get(url, {}, { preserveState: true, preserveScroll: true });
    }
}
</script>

<template>
    <AppLayout>
        <Head title="Usage statistics" />

        <SettingsLayout>
            <div class="space-y-8">
                <!-- Header Section -->
                <div class="space-y-6">
                    <HeadingSmall
                        title="Usage Statistics"
                        description="Monitor your AI token usage and costs across all profiles"
                    />

                    <!-- Profile Filter -->
                    <div class="flex items-center gap-4">
                        <label class="text-sm font-medium text-muted-foreground">
                            Filter by profile:
                        </label>
                        <Select v-model="selectedProfile">
                            <SelectTrigger class="w-[200px]">
                                <SelectValue placeholder="All profiles" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All profiles</SelectItem>
                                <SelectItem
                                    v-for="profile in profiles"
                                    :key="profile.id"
                                    :value="profile.id"
                                >
                                    {{ profile.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="grid gap-4 sm:grid-cols-2">
                    <!-- Last 30 Days Card -->
                    <Card class="overflow-hidden">
                        <CardHeader class="border-b border-border/50 bg-gradient-to-br from-violet-500/10 to-transparent p-4">
                            <div class="flex items-center gap-2">
                                <div class="rounded-lg bg-violet-500/20 p-2">
                                    <Calendar class="h-4 w-4 text-violet-600 dark:text-violet-400" />
                                </div>
                                <CardTitle class="text-base font-semibold">Last 30 Days</CardTitle>
                            </div>
                        </CardHeader>
                        <CardContent class="pt-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div class="space-y-1">
                                    <div class="flex items-center gap-1.5 text-xs text-muted-foreground">
                                        <Zap class="h-3 w-3" />
                                        <span>Requests</span>
                                    </div>
                                    <p class="text-xl font-bold tabular-nums">
                                        {{ formatTokens(last30DaysStats.total_requests) }}
                                    </p>
                                </div>
                                <div class="space-y-1">
                                    <div class="flex items-center gap-1.5 text-xs text-muted-foreground">
                                        <Cpu class="h-3 w-3" />
                                        <span>Total Tokens</span>
                                    </div>
                                    <p class="text-xl font-bold tabular-nums">
                                        {{ formatTokens(last30DaysStats.total_tokens) }}
                                    </p>
                                </div>
                                <div class="space-y-1">
                                    <div class="flex items-center gap-1.5 text-xs text-muted-foreground">
                                        <FileText class="h-3 w-3" />
                                        <span>Prompt / Completion</span>
                                    </div>
                                    <p class="text-sm font-medium tabular-nums text-muted-foreground">
                                        {{ formatTokens(last30DaysStats.prompt_tokens) }} / {{ formatTokens(last30DaysStats.completion_tokens) }}
                                    </p>
                                </div>
                                <div class="space-y-1">
                                    <div class="flex items-center gap-1.5 text-xs text-muted-foreground">
                                        <Coins class="h-3 w-3" />
                                        <span>Total Cost</span>
                                    </div>
                                    <p class="text-xl font-bold tabular-nums text-emerald-600 dark:text-emerald-400">
                                        {{ formatCost(last30DaysStats.total_cost) }}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- All Time Card -->
                    <Card class="overflow-hidden">
                        <CardHeader class="border-b border-border/50 bg-gradient-to-br from-amber-500/10 to-transparent p-4">
                            <div class="flex items-center gap-2">
                                <div class="rounded-lg bg-amber-500/20 p-2">
                                    <TrendingUp class="h-4 w-4 text-amber-600 dark:text-amber-400" />
                                </div>
                                <CardTitle class="text-base font-semibold">All Time</CardTitle>
                            </div>
                        </CardHeader>
                        <CardContent class="pt-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div class="space-y-1">
                                    <div class="flex items-center gap-1.5 text-xs text-muted-foreground">
                                        <Zap class="h-3 w-3" />
                                        <span>Requests</span>
                                    </div>
                                    <p class="text-xl font-bold tabular-nums">
                                        {{ formatTokens(allTimeStats.total_requests) }}
                                    </p>
                                </div>
                                <div class="space-y-1">
                                    <div class="flex items-center gap-1.5 text-xs text-muted-foreground">
                                        <Cpu class="h-3 w-3" />
                                        <span>Total Tokens</span>
                                    </div>
                                    <p class="text-xl font-bold tabular-nums">
                                        {{ formatTokens(allTimeStats.total_tokens) }}
                                    </p>
                                </div>
                                <div class="space-y-1">
                                    <div class="flex items-center gap-1.5 text-xs text-muted-foreground">
                                        <FileText class="h-3 w-3" />
                                        <span>Prompt / Completion</span>
                                    </div>
                                    <p class="text-sm font-medium tabular-nums text-muted-foreground">
                                        {{ formatTokens(allTimeStats.prompt_tokens) }} / {{ formatTokens(allTimeStats.completion_tokens) }}
                                    </p>
                                </div>
                                <div class="space-y-1">
                                    <div class="flex items-center gap-1.5 text-xs text-muted-foreground">
                                        <Coins class="h-3 w-3" />
                                        <span>Total Cost</span>
                                    </div>
                                    <p class="text-xl font-bold tabular-nums text-emerald-600 dark:text-emerald-400">
                                        {{ formatCost(allTimeStats.total_cost) }}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Request Logs Table -->
                <div class="space-y-4">
                    <h3 class="text-sm font-medium text-foreground">Request History</h3>

                    <!-- Empty State -->
                    <div
                        v-if="logs.data.length === 0"
                        class="flex flex-col items-center justify-center rounded-lg border border-dashed border-border bg-muted/30 py-12"
                    >
                        <div class="rounded-full bg-muted p-3">
                            <FileText class="h-6 w-6 text-muted-foreground" />
                        </div>
                        <h4 class="mt-4 text-sm font-medium text-foreground">No usage data</h4>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Usage statistics will appear here as you use the app.
                        </p>
                    </div>

                    <!-- Table -->
                    <div
                        v-else
                        class="overflow-hidden rounded-lg border border-border"
                    >
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[600px] text-sm">
                                <thead class="border-b border-border bg-muted/50">
                                    <tr>
                                        <th class="px-4 py-3 text-left font-medium text-muted-foreground">
                                            Date & Time
                                        </th>
                                        <th class="px-4 py-3 text-left font-medium text-muted-foreground">
                                            Type
                                        </th>
                                        <th class="px-4 py-3 text-left font-medium text-muted-foreground">
                                            Model
                                        </th>
                                        <th class="px-4 py-3 text-left font-medium text-muted-foreground">
                                            Profile
                                        </th>
                                        <th class="px-4 py-3 text-right font-medium text-muted-foreground">
                                            Tokens
                                        </th>
                                        <th class="px-4 py-3 text-right font-medium text-muted-foreground">
                                            Cost
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border">
                                    <tr
                                        v-for="log in logs.data"
                                        :key="log.id"
                                        class="transition-colors hover:bg-muted/30"
                                    >
                                        <td class="px-4 py-3 text-foreground">
                                            {{ formatDate(log.created_at) }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex items-center rounded-md bg-muted px-2 py-1 text-xs font-medium text-muted-foreground">
                                                {{ formatItemType(log.item_type) }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 font-mono text-xs text-muted-foreground">
                                            {{ formatModel(log.model) }}
                                        </td>
                                        <td class="px-4 py-3 text-muted-foreground">
                                            {{ log.profile?.name ?? '-' }}
                                        </td>
                                        <td class="px-4 py-3 text-right tabular-nums text-foreground">
                                            {{ formatTokens(log.total_tokens) }}
                                        </td>
                                        <td class="px-4 py-3 text-right tabular-nums font-medium text-emerald-600 dark:text-emerald-400">
                                            {{ formatCost(log.total_cost) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div
                            v-if="logs.last_page > 1"
                            class="flex items-center justify-between border-t border-border bg-muted/30 px-4 py-3"
                        >
                            <p class="text-sm text-muted-foreground">
                                Showing {{ logs.from }} to {{ logs.to }} of {{ logs.total }} entries
                            </p>
                            <div class="flex items-center gap-1">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    :disabled="logs.current_page === 1"
                                    @click="goToPage(logs.links[0]?.url)"
                                >
                                    <ChevronLeft class="h-4 w-4" />
                                </Button>
                                <template v-for="link in logs.links" :key="link.label">
                                    <Button
                                        v-if="!link.label.includes('Previous') && !link.label.includes('Next')"
                                        variant="outline"
                                        size="sm"
                                        :class="{
                                            'bg-primary text-primary-foreground hover:bg-primary/90': link.active,
                                        }"
                                        :disabled="!link.url"
                                        @click="goToPage(link.url)"
                                    >
                                        {{ link.label }}
                                    </Button>
                                </template>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    :disabled="logs.current_page === logs.last_page"
                                    @click="goToPage(logs.links[logs.links.length - 1]?.url)"
                                >
                                    <ChevronRight class="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>

