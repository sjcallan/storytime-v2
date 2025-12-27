<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { toUrl, urlIsActive } from '@/lib/utils';
import { index as usersIndex } from '@/routes/admin/users';
import { type NavItem, type AppPageProps } from '@/types';
import { Link, usePage, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Users, ShieldCheck } from 'lucide-vue-next';

const page = usePage<AppPageProps>();
const isAdmin = computed(() => page.props.auth.isAdmin);

const sidebarNavItems: NavItem[] = [
    {
        title: 'Users',
        href: usersIndex(),
        icon: Users,
    },
];

const currentPath = typeof window !== 'undefined' ? window.location.pathname : '';

if (!isAdmin.value && typeof window !== 'undefined') {
    router.visit('/dashboard');
}
</script>

<template>
    <div class="px-4 py-6">
        <Heading
            title="Site Administration"
            description="Manage users and system settings"
        >
            <template #icon>
                <div class="rounded-lg bg-rose-500/10 p-2">
                    <ShieldCheck class="h-6 w-6 text-rose-600 dark:text-rose-400" />
                </div>
            </template>
        </Heading>

        <div
            v-if="isAdmin"
            class="rounded-xl border border-border bg-card p-6 shadow-sm"
        >
            <div class="flex flex-col lg:flex-row lg:gap-12">
                <aside class="w-full max-w-xl lg:w-48 shrink-0">
                    <nav class="flex flex-col gap-1">
                        <Button
                            v-for="item in sidebarNavItems"
                            :key="toUrl(item.href)"
                            variant="ghost"
                            :class="[
                                'w-full justify-start',
                                { 'bg-muted': urlIsActive(item.href, currentPath) },
                            ]"
                            as-child
                        >
                            <Link :href="item.href">
                                <component :is="item.icon" class="mr-2 h-4 w-4" />
                                {{ item.title }}
                            </Link>
                        </Button>
                    </nav>
                </aside>

                <Separator class="my-6 lg:hidden" />
                <Separator orientation="vertical" class="hidden lg:block h-auto self-stretch" />

                <div class="flex-1">
                    <section class="space-y-12">
                        <slot />
                    </section>
                </div>
            </div>
        </div>

        <div
            v-else
            class="flex h-64 items-center justify-center rounded-xl border border-border bg-card shadow-sm"
        >
            <div class="flex flex-col items-center gap-3 text-muted-foreground">
                <ShieldCheck class="h-12 w-12 text-destructive" />
                <span class="text-lg font-medium">Access Denied</span>
                <span class="text-sm">You don't have permission to access this area.</span>
            </div>
        </div>
    </div>
</template>

