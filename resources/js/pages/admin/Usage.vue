<script setup lang="ts">
import { index as usageIndex } from '@/routes/admin/usage';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import AdminLayout from '@/layouts/admin/Layout.vue';
import type { AppPageProps } from '@/types';
import {
    BarChart3,
    BookOpen,
    Calendar,
    Coins,
    Cpu,
    FileText,
    Image,
    TrendingUp,
    Zap,
} from 'lucide-vue-next';

interface Stats {
    total_cost: number;
    total_tokens: number;
    prompt_tokens: number;
    completion_tokens: number;
    total_images: number;
    output_images: number;
    total_requests: number;
    text_cost: number;
    text_requests: number;
    image_cost: number;
    image_requests: number;
    avg_cost_per_book: number;
    avg_cost_per_chapter: number;
    unique_books: number;
    unique_chapters: number;
    total_books: number;
    total_chapters: number;
}

interface ChartDataPoint {
    date: string;
    cost: number;
}

interface CostByType {
    type: string;
    cost: number;
    count: number;
}

interface Filters {
    start_date: string;
    end_date: string;
}

interface Props {
    stats: Stats;
    chartData: ChartDataPoint[];
    costByType: CostByType[];
    filters: Filters;
}

const props = defineProps<Props>();

const page = usePage<AppPageProps>();

const startDate = ref(props.filters.start_date);
const endDate = ref(props.filters.end_date);

function applyFilters(): void {
    router.get(
        usageIndex.url(),
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

function formatTokens(tokens: number): string {
    return tokens.toLocaleString();
}

function formatDate(dateString: string): string {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
    });
}

function formatItemType(itemType: string): string {
    return itemType
        .split('_')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
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

const showAllBars = computed(() => props.chartData.length <= 31);
const skipInterval = computed(() => Math.ceil(props.chartData.length / 15));
</script>

<template>
    <AppLayout>
        <Head title="Usage - Administration" />

        <AdminLayout>
            <div class="space-y-6">
                <!-- Header Section -->
                <div class="space-y-4">
                    <HeadingSmall
                        title="Usage Statistics"
                        description="Monitor total application usage and costs"
                    />

                    <!-- Date Range Filter -->
                    <div class="flex flex-wrap items-end gap-4">
                        <div class="space-y-1.5">
                            <Label for="start_date" class="text-xs">Start Date</Label>
                            <Input
                                id="start_date"
                                v-model="startDate"
                                type="date"
                                class="w-40"
                            />
                        </div>
                        <div class="space-y-1.5">
                            <Label for="end_date" class="text-xs">End Date</Label>
                            <Input
                                id="end_date"
                                v-model="endDate"
                                type="date"
                                class="w-40"
                            />
                        </div>
                        <Button @click="applyFilters" size="sm">
                            Apply
                        </Button>
                        <div class="flex gap-2">
                            <Button variant="outline" size="sm" @click="setPreset(7)">
                                7 days
                            </Button>
                            <Button variant="outline" size="sm" @click="setPreset(30)">
                                30 days
                            </Button>
                            <Button variant="outline" size="sm" @click="setPreset(90)">
                                90 days
                            </Button>
                        </div>
                    </div>
                </div>

                <!-- Main Stats Cards -->
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <!-- Total Cost Card -->
                    <Card class="overflow-hidden pt-0">
                        <CardHeader class="border-b border-border/50 bg-gradient-to-br from-emerald-500/10 to-transparent p-4">
                            <div class="flex items-center gap-2">
                                <div class="rounded-lg bg-emerald-500/20 p-2">
                                    <Coins class="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
                                </div>
                                <CardTitle class="text-base font-semibold">Total Cost</CardTitle>
                            </div>
                        </CardHeader>
                        <CardContent class="pt-4">
                            <p class="text-2xl font-bold tabular-nums text-emerald-600 dark:text-emerald-400">
                                {{ formatCost(stats.total_cost) }}
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ formatTokens(stats.total_requests) }} requests
                            </p>
                        </CardContent>
                    </Card>

                    <!-- Total Tokens Card -->
                    <Card class="overflow-hidden pt-0">
                        <CardHeader class="border-b border-border/50 bg-gradient-to-br from-blue-500/10 to-transparent p-4">
                            <div class="flex items-center gap-2">
                                <div class="rounded-lg bg-blue-500/20 p-2">
                                    <Cpu class="h-4 w-4 text-blue-600 dark:text-blue-400" />
                                </div>
                                <CardTitle class="text-base font-semibold">Total Tokens</CardTitle>
                            </div>
                        </CardHeader>
                        <CardContent class="pt-4">
                            <p class="text-2xl font-bold tabular-nums">
                                {{ formatTokens(stats.total_tokens) }}
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ formatTokens(stats.prompt_tokens) }} in / {{ formatTokens(stats.completion_tokens) }} out
                            </p>
                        </CardContent>
                    </Card>

                    <!-- Total Images Card -->
                    <Card class="overflow-hidden pt-0">
                        <CardHeader class="border-b border-border/50 bg-gradient-to-br from-purple-500/10 to-transparent p-4">
                            <div class="flex items-center gap-2">
                                <div class="rounded-lg bg-purple-500/20 p-2">
                                    <Image class="h-4 w-4 text-purple-600 dark:text-purple-400" />
                                </div>
                                <CardTitle class="text-base font-semibold">Images Generated</CardTitle>
                            </div>
                        </CardHeader>
                        <CardContent class="pt-4">
                            <p class="text-2xl font-bold tabular-nums">
                                {{ formatTokens(stats.output_images) }}
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ formatCost(stats.image_cost) }} spent on images
                            </p>
                        </CardContent>
                    </Card>

                    <!-- Text Inference Card -->
                    <Card class="overflow-hidden pt-0">
                        <CardHeader class="border-b border-border/50 bg-gradient-to-br from-amber-500/10 to-transparent p-4">
                            <div class="flex items-center gap-2">
                                <div class="rounded-lg bg-amber-500/20 p-2">
                                    <FileText class="h-4 w-4 text-amber-600 dark:text-amber-400" />
                                </div>
                                <CardTitle class="text-base font-semibold">Text Inference</CardTitle>
                            </div>
                        </CardHeader>
                        <CardContent class="pt-4">
                            <p class="text-2xl font-bold tabular-nums text-amber-600 dark:text-amber-400">
                                {{ formatCost(stats.text_cost) }}
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ formatTokens(stats.text_requests) }} text requests
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <!-- Average Cost Cards -->
                <div class="grid gap-4 sm:grid-cols-2">
                    <!-- Avg Cost Per Book -->
                    <Card class="overflow-hidden pt-0">
                        <CardHeader class="border-b border-border/50 bg-gradient-to-br from-rose-500/10 to-transparent p-4">
                            <div class="flex items-center gap-2">
                                <div class="rounded-lg bg-rose-500/20 p-2">
                                    <BookOpen class="h-4 w-4 text-rose-600 dark:text-rose-400" />
                                </div>
                                <CardTitle class="text-base font-semibold">Avg Cost Per Book</CardTitle>
                            </div>
                        </CardHeader>
                        <CardContent class="pt-4">
                            <div class="flex items-baseline justify-between">
                                <p class="text-2xl font-bold tabular-nums text-rose-600 dark:text-rose-400">
                                    {{ formatCost(stats.avg_cost_per_book) }}
                                </p>
                                <p class="text-sm text-muted-foreground">
                                    {{ stats.unique_books }} books in period
                                </p>
                            </div>
                            <p class="mt-2 text-xs text-muted-foreground">
                                {{ formatTokens(stats.total_books) }} total books in system
                            </p>
                        </CardContent>
                    </Card>

                    <!-- Avg Cost Per Chapter -->
                    <Card class="overflow-hidden pt-0">
                        <CardHeader class="border-b border-border/50 bg-gradient-to-br from-cyan-500/10 to-transparent p-4">
                            <div class="flex items-center gap-2">
                                <div class="rounded-lg bg-cyan-500/20 p-2">
                                    <FileText class="h-4 w-4 text-cyan-600 dark:text-cyan-400" />
                                </div>
                                <CardTitle class="text-base font-semibold">Avg Cost Per Chapter</CardTitle>
                            </div>
                        </CardHeader>
                        <CardContent class="pt-4">
                            <div class="flex items-baseline justify-between">
                                <p class="text-2xl font-bold tabular-nums text-cyan-600 dark:text-cyan-400">
                                    {{ formatCost(stats.avg_cost_per_chapter) }}
                                </p>
                                <p class="text-sm text-muted-foreground">
                                    {{ stats.unique_chapters }} chapters in period
                                </p>
                            </div>
                            <p class="mt-2 text-xs text-muted-foreground">
                                {{ formatTokens(stats.total_chapters) }} total chapters in system
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <!-- Daily Cost Chart -->
                <Card class="overflow-hidden pt-0">
                    <CardHeader class="border-b border-border/50 p-4">
                        <div class="flex items-center gap-2">
                            <div class="rounded-lg bg-muted p-2">
                                <BarChart3 class="h-4 w-4 text-muted-foreground" />
                            </div>
                            <CardTitle class="text-base font-semibold">Daily Cost</CardTitle>
                        </div>
                    </CardHeader>
                    <CardContent class="pt-4">
                        <div v-if="chartData.length === 0" class="flex h-48 items-center justify-center text-muted-foreground">
                            No data for selected period
                        </div>
                        <div v-else class="space-y-2">
                            <!-- Chart -->
                            <div class="relative h-48 ml-12">
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
                                        <!-- Tooltip -->
                                        <div class="pointer-events-none absolute bottom-full left-1/2 z-10 mb-2 -translate-x-1/2 whitespace-nowrap opacity-0 transition-opacity group-hover:opacity-100">
                                            <div class="rounded-md bg-popover border border-border px-2 py-1 text-xs shadow-md">
                                                <p class="font-medium">{{ bar.formattedDate }}</p>
                                                <p class="text-emerald-600 dark:text-emerald-400">{{ formatCost(bar.cost) }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- Y-axis labels -->
                                <div class="absolute left-0 top-0 h-full flex flex-col justify-between text-[10px] text-muted-foreground -translate-x-full pr-2">
                                    <span>{{ formatCost(maxChartValue) }}</span>
                                    <span>{{ formatCost(maxChartValue / 2) }}</span>
                                    <span>$0</span>
                                </div>
                                <!-- Grid lines -->
                                <div class="absolute inset-0 flex flex-col justify-between pointer-events-none">
                                    <div class="border-t border-border/30"></div>
                                    <div class="border-t border-border/30"></div>
                                    <div class="border-t border-border/30"></div>
                                </div>
                            </div>
                            <!-- X-axis labels -->
                            <div class="flex justify-between text-[10px] text-muted-foreground ml-12 px-1">
                                <span>{{ formatDate(chartData[0]?.date) }}</span>
                                <span v-if="chartData.length > 1">{{ formatDate(chartData[chartData.length - 1]?.date) }}</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Cost Breakdown by Type -->
                <Card class="overflow-hidden pt-0">
                    <CardHeader class="border-b border-border/50 p-4">
                        <div class="flex items-center gap-2">
                            <div class="rounded-lg bg-muted p-2">
                                <TrendingUp class="h-4 w-4 text-muted-foreground" />
                            </div>
                            <CardTitle class="text-base font-semibold">Cost by Request Type</CardTitle>
                        </div>
                    </CardHeader>
                    <CardContent class="pt-4">
                        <div v-if="costByType.length === 0" class="flex h-24 items-center justify-center text-muted-foreground">
                            No data for selected period
                        </div>
                        <div v-else class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-border">
                                        <th class="pb-2 text-left font-medium text-muted-foreground">Type</th>
                                        <th class="pb-2 text-right font-medium text-muted-foreground">Requests</th>
                                        <th class="pb-2 text-right font-medium text-muted-foreground">Cost</th>
                                        <th class="pb-2 text-right font-medium text-muted-foreground">% of Total</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border">
                                    <tr v-for="item in costByType" :key="item.type">
                                        <td class="py-2 font-medium">{{ formatItemType(item.type) }}</td>
                                        <td class="py-2 text-right tabular-nums text-muted-foreground">
                                            {{ formatTokens(item.count) }}
                                        </td>
                                        <td class="py-2 text-right tabular-nums text-emerald-600 dark:text-emerald-400">
                                            {{ formatCost(item.cost) }}
                                        </td>
                                        <td class="py-2 text-right tabular-nums text-muted-foreground">
                                            {{ stats.total_cost > 0 ? ((item.cost / stats.total_cost) * 100).toFixed(1) : 0 }}%
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    </AppLayout>
</template>

