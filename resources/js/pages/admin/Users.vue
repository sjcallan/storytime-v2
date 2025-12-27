<script setup lang="ts">
import { index as usersIndex } from '@/routes/admin/users';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useDebounceFn } from '@vueuse/core';

import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import AdminLayout from '@/layouts/admin/Layout.vue';
import type { AppPageProps } from '@/types';
import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    BookOpen,
    ChevronLeft,
    ChevronRight,
    FileText,
    Search,
    Users,
    X,
} from 'lucide-vue-next';

interface AdminUser {
    id: string;
    name: string;
    email: string;
    created_at: string;
    books_count: number;
    chapters_count: number;
}

interface PaginatedUsers {
    data: AdminUser[];
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

interface Filters {
    search: string;
    sort_by: string;
    sort_direction: string;
}

interface Props {
    users: PaginatedUsers;
    filters: Filters;
}

const props = defineProps<Props>();

const page = usePage<AppPageProps>();
const isAdmin = computed(() => page.props.auth.isAdmin);

const searchQuery = ref(props.filters.search);
const sortBy = ref(props.filters.sort_by);
const sortDirection = ref(props.filters.sort_direction);

const debouncedSearch = useDebounceFn(() => {
    applyFilters();
}, 300);

watch(searchQuery, () => {
    debouncedSearch();
});

function applyFilters(): void {
    router.get(
        usersIndex.url(),
        {
            search: searchQuery.value || null,
            sort_by: sortBy.value,
            sort_direction: sortDirection.value,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        }
    );
}

function toggleSort(column: string): void {
    if (sortBy.value === column) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortBy.value = column;
        sortDirection.value = 'desc';
    }
    applyFilters();
}

function getSortIcon(column: string) {
    if (sortBy.value !== column) {
        return ArrowUpDown;
    }
    return sortDirection.value === 'asc' ? ArrowUp : ArrowDown;
}

function clearSearch(): void {
    searchQuery.value = '';
    applyFilters();
}

function formatDate(dateString: string): string {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

function formatNumber(num: number): string {
    return num.toLocaleString();
}

function goToPage(url: string | null): void {
    if (url) {
        router.get(url, {}, { preserveState: true, preserveScroll: true });
    }
}

type SortableColumn = 'name' | 'email' | 'created_at' | 'books_count' | 'chapters_count';

const columns: { key: SortableColumn; label: string; align?: 'left' | 'right' }[] = [
    { key: 'name', label: 'Name' },
    { key: 'email', label: 'Email' },
    { key: 'created_at', label: 'Registered' },
    { key: 'books_count', label: 'Books', align: 'right' },
    { key: 'chapters_count', label: 'Chapters', align: 'right' },
];
</script>

<template>
    <AppLayout>
        <Head title="Users - Administration" />

        <AdminLayout>
            <div class="space-y-6">
                <!-- Header Section -->
                <div class="space-y-4">
                    <HeadingSmall
                        title="Users"
                        description="View and manage all registered users"
                    />

                    <!-- Search Input -->
                    <div class="flex items-center gap-3">
                        <div class="relative flex-1 max-w-md">
                            <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Search by name or email..."
                                class="pl-10 pr-10"
                            />
                            <button
                                v-if="searchQuery"
                                @click="clearSearch"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                            >
                                <X class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Stats Summary -->
                <div class="flex items-center gap-6 text-sm text-muted-foreground">
                    <div class="flex items-center gap-2">
                        <Users class="h-4 w-4" />
                        <span>{{ formatNumber(users.total) }} total users</span>
                    </div>
                </div>

                <!-- Empty State -->
                <div
                    v-if="users.data.length === 0"
                    class="flex flex-col items-center justify-center rounded-lg border border-dashed border-border bg-muted/30 py-12"
                >
                    <div class="rounded-full bg-muted p-3">
                        <Users class="h-6 w-6 text-muted-foreground" />
                    </div>
                    <h4 class="mt-4 text-sm font-medium text-foreground">No users found</h4>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ searchQuery ? 'Try adjusting your search query.' : 'No users have registered yet.' }}
                    </p>
                </div>

                <!-- Users Table -->
                <div
                    v-else
                    class="overflow-hidden rounded-lg border border-border"
                >
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[600px] text-sm">
                            <thead class="border-b border-border bg-muted/50">
                                <tr>
                                    <th
                                        v-for="column in columns"
                                        :key="column.key"
                                        :class="[
                                            'px-4 py-3 font-medium text-muted-foreground',
                                            column.align === 'right' ? 'text-right' : 'text-left',
                                        ]"
                                    >
                                        <button
                                            @click="toggleSort(column.key)"
                                            class="inline-flex items-center gap-1.5 hover:text-foreground transition-colors"
                                        >
                                            {{ column.label }}
                                            <component
                                                :is="getSortIcon(column.key)"
                                                :class="[
                                                    'h-4 w-4',
                                                    sortBy === column.key ? 'text-foreground' : 'text-muted-foreground/50',
                                                ]"
                                            />
                                        </button>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr
                                    v-for="user in users.data"
                                    :key="user.id"
                                    class="transition-colors hover:bg-muted/30"
                                >
                                    <td class="px-4 py-3 font-medium text-foreground">
                                        {{ user.name }}
                                    </td>
                                    <td class="px-4 py-3 text-muted-foreground">
                                        {{ user.email }}
                                    </td>
                                    <td class="px-4 py-3 text-muted-foreground">
                                        {{ formatDate(user.created_at) }}
                                    </td>
                                    <td class="px-4 py-3 text-right tabular-nums">
                                        <span class="inline-flex items-center gap-1.5">
                                            <BookOpen class="h-3.5 w-3.5 text-muted-foreground" />
                                            {{ formatNumber(user.books_count) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right tabular-nums">
                                        <span class="inline-flex items-center gap-1.5">
                                            <FileText class="h-3.5 w-3.5 text-muted-foreground" />
                                            {{ formatNumber(user.chapters_count) }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div
                        v-if="users.last_page > 1"
                        class="flex items-center justify-between border-t border-border bg-muted/30 px-4 py-3"
                    >
                        <p class="text-sm text-muted-foreground">
                            Showing {{ users.from }} to {{ users.to }} of {{ users.total }} users
                        </p>
                        <div class="flex items-center gap-1">
                            <Button
                                variant="outline"
                                size="sm"
                                :disabled="users.current_page === 1"
                                @click="goToPage(users.links[0]?.url)"
                            >
                                <ChevronLeft class="h-4 w-4" />
                            </Button>
                            <template v-for="link in users.links" :key="link.label">
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
                                :disabled="users.current_page === users.last_page"
                                @click="goToPage(users.links[users.links.length - 1]?.url)"
                            >
                                <ChevronRight class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </AdminLayout>
    </AppLayout>
</template>

