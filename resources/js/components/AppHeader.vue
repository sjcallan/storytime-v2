<script setup lang="ts">
import AppLogo from '@/components/AppLogo.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    NavigationMenu,
    NavigationMenuItem,
    NavigationMenuList,
    navigationMenuTriggerStyle,
} from '@/components/ui/navigation-menu';
import { Separator } from '@/components/ui/separator';
import {
    Sheet,
    SheetClose,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { getInitials } from '@/composables/useInitials';
import { toUrl, urlIsActive } from '@/lib/utils';
import { dashboard, logout } from '@/routes';
import { edit } from '@/routes/profile';
import type { NavItem } from '@/types';
import { InertiaLinkProps, Link, router, usePage } from '@inertiajs/vue3';
import {
    BookOpen,
    ChevronRight,
    ExternalLink,
    Folder,
    LayoutGrid,
    LogOut,
    Menu,
    Search,
    Settings,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

const page = usePage();
const auth = computed(() => page.props.auth);
const mobileMenuOpen = ref(false);

const isCurrentRoute = computed(
    () => (url: NonNullable<InertiaLinkProps['href']>) =>
        urlIsActive(url, page.url),
);

const activeItemStyles = computed(
    () => (url: NonNullable<InertiaLinkProps['href']>) =>
        isCurrentRoute.value(toUrl(url))
            ? 'text-neutral-900 dark:bg-neutral-800 dark:text-neutral-100'
            : '',
);

const mobileActiveStyles = computed(
    () => (url: NonNullable<InertiaLinkProps['href']>) =>
        isCurrentRoute.value(toUrl(url))
            ? 'bg-orange-50 text-orange-600 border-l-2 border-orange-500 dark:bg-orange-950/30 dark:text-orange-400'
            : 'text-neutral-600 hover:bg-neutral-50 dark:text-neutral-400 dark:hover:bg-neutral-800/50',
);

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
];

const rightNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/vue-starter-kit',
        icon: Folder,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#vue',
        icon: BookOpen,
    },
];

const handleLogout = () => {
    router.flushAll();
    mobileMenuOpen.value = false;
};

const closeMobileMenu = () => {
    mobileMenuOpen.value = false;
};
</script>

<template>
    <div>
        <div class="border-b border-sidebar-border/80">
            <div class="mx-auto flex h-16 items-center px-4 md:max-w-7xl">
                <!-- Logo -->
                <Link :href="dashboard()" class="flex items-center gap-x-2">
                    <AppLogo />
                </Link>

                <!-- Desktop Menu -->
                <div class="hidden h-full lg:flex lg:flex-1">
                    <NavigationMenu class="ml-10 flex h-full items-stretch">
                        <NavigationMenuList
                            class="flex h-full items-stretch space-x-2"
                        >
                            <NavigationMenuItem
                                v-for="(item, index) in mainNavItems"
                                :key="index"
                                class="relative flex h-full items-center"
                            >
                                <Link
                                    :class="[
                                        navigationMenuTriggerStyle(),
                                        activeItemStyles(item.href),
                                        'h-9 cursor-pointer px-3',
                                    ]"
                                    :href="item.href"
                                >
                                    <component
                                        v-if="item.icon"
                                        :is="item.icon"
                                        class="mr-2 h-4 w-4"
                                    />
                                    {{ item.title }}
                                </Link>
                                <div
                                    v-if="isCurrentRoute(item.href)"
                                    class="absolute bottom-0 left-0 h-0.5 w-full translate-y-px bg-black dark:bg-white"
                                ></div>
                            </NavigationMenuItem>
                        </NavigationMenuList>
                    </NavigationMenu>
                </div>

                <div class="ml-auto flex items-center space-x-2">
                    <!-- Desktop: Search and right nav items -->
                    <div class="relative hidden items-center space-x-1 lg:flex">
                        <Button
                            variant="ghost"
                            size="icon"
                            class="group h-9 w-9 cursor-pointer"
                        >
                            <Search
                                class="size-5 opacity-80 group-hover:opacity-100"
                            />
                        </Button>

                        <div class="flex space-x-1">
                            <template
                                v-for="item in rightNavItems"
                                :key="item.title"
                            >
                                <TooltipProvider :delay-duration="0">
                                    <Tooltip>
                                        <TooltipTrigger>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                as-child
                                                class="group h-9 w-9 cursor-pointer"
                                            >
                                                <a
                                                    :href="toUrl(item.href)"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                >
                                                    <span class="sr-only">{{
                                                        item.title
                                                    }}</span>
                                                    <component
                                                        :is="item.icon"
                                                        class="size-5 opacity-80 group-hover:opacity-100"
                                                    />
                                                </a>
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            <p>{{ item.title }}</p>
                                        </TooltipContent>
                                    </Tooltip>
                                </TooltipProvider>
                            </template>
                        </div>
                    </div>

                    <!-- Desktop: User dropdown -->
                    <DropdownMenu>
                        <DropdownMenuTrigger :as-child="true" class="hidden lg:flex">
                            <Button
                                variant="ghost"
                                size="icon"
                                class="relative size-10 w-auto rounded-full p-1 focus-within:ring-2 focus-within:ring-primary"
                            >
                                <Avatar
                                    class="size-8 overflow-hidden rounded-full"
                                >
                                    <AvatarImage
                                        v-if="auth.user.avatar"
                                        :src="auth.user.avatar"
                                        :alt="auth.user.name"
                                    />
                                    <AvatarFallback
                                        class="rounded-lg bg-neutral-200 font-semibold text-black dark:bg-neutral-700 dark:text-white"
                                    >
                                        {{ getInitials(auth.user?.name) }}
                                    </AvatarFallback>
                                </Avatar>
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-56">
                            <UserMenuContent :user="auth.user" />
                        </DropdownMenuContent>
                    </DropdownMenu>

                    <!-- Mobile: Hamburger menu -->
                    <div class="lg:hidden">
                        <Sheet v-model:open="mobileMenuOpen">
                            <SheetTrigger :as-child="true">
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="h-9 w-9"
                                >
                                    <Menu class="h-5 w-5" />
                                </Button>
                            </SheetTrigger>
                            <SheetContent
                                side="right"
                                class="flex w-[320px] flex-col p-0"
                            >
                                <SheetTitle class="sr-only">Menu</SheetTitle>

                                <!-- User Profile Section -->
                                <SheetHeader
                                    class="border-b border-neutral-200 bg-linear-to-br from-orange-50 to-amber-50/50 p-5 dark:border-neutral-800 dark:from-neutral-900 dark:to-neutral-900"
                                >
                                    <div class="flex items-center gap-3">
                                        <Avatar
                                            class="size-12 overflow-hidden rounded-full ring-2 ring-white shadow-md dark:ring-neutral-700"
                                        >
                                            <AvatarImage
                                                v-if="auth.user.avatar"
                                                :src="auth.user.avatar"
                                                :alt="auth.user.name"
                                            />
                                            <AvatarFallback
                                                class="bg-linear-to-br from-orange-400 to-amber-500 text-lg font-semibold text-white"
                                            >
                                                {{ getInitials(auth.user?.name) }}
                                            </AvatarFallback>
                                        </Avatar>
                                        <div class="min-w-0 flex-1">
                                            <p
                                                class="truncate font-semibold text-neutral-900 dark:text-neutral-100"
                                            >
                                                {{ auth.user.name }}
                                            </p>
                                            <p
                                                class="truncate text-sm text-neutral-500 dark:text-neutral-400"
                                            >
                                                {{ auth.user.email }}
                                            </p>
                                        </div>
                                    </div>
                                </SheetHeader>

                                <!-- Menu Content -->
                                <div class="flex flex-1 flex-col overflow-y-auto">
                                    <!-- Quick Actions -->
                                    <div class="p-3">
                                        <Button
                                            variant="outline"
                                            class="w-full justify-start gap-2 border-neutral-200 bg-white text-neutral-700 hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-300 dark:hover:bg-neutral-700"
                                        >
                                            <Search class="h-4 w-4" />
                                            <span>Search</span>
                                            <kbd
                                                class="ml-auto rounded bg-neutral-100 px-1.5 py-0.5 text-xs text-neutral-500 dark:bg-neutral-700 dark:text-neutral-400"
                                            >
                                                ⌘K
                                            </kbd>
                                        </Button>
                                    </div>

                                    <Separator class="mx-3" />

                                    <!-- Navigation Section -->
                                    <div class="p-3">
                                        <p
                                            class="mb-2 px-3 text-xs font-medium uppercase tracking-wider text-neutral-400 dark:text-neutral-500"
                                        >
                                            Navigation
                                        </p>
                                        <nav class="space-y-1">
                                            <SheetClose :as-child="true">
                                                <Link
                                                    v-for="item in mainNavItems"
                                                    :key="item.title"
                                                    :href="item.href"
                                                    class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors"
                                                    :class="mobileActiveStyles(item.href)"
                                                    @click="closeMobileMenu"
                                                >
                                                    <component
                                                        v-if="item.icon"
                                                        :is="item.icon"
                                                        class="h-5 w-5"
                                                    />
                                                    {{ item.title }}
                                                    <ChevronRight
                                                        v-if="isCurrentRoute(item.href)"
                                                        class="ml-auto h-4 w-4 text-orange-500"
                                                    />
                                                </Link>
                                            </SheetClose>
                                        </nav>
                                    </div>

                                    <Separator class="mx-3" />

                                    <!-- Account Section -->
                                    <div class="p-3">
                                        <p
                                            class="mb-2 px-3 text-xs font-medium uppercase tracking-wider text-neutral-400 dark:text-neutral-500"
                                        >
                                            Account
                                        </p>
                                        <nav class="space-y-1">
                                            <SheetClose :as-child="true">
                                                <Link
                                                    :href="edit()"
                                                    class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-neutral-600 transition-colors hover:bg-neutral-50 dark:text-neutral-400 dark:hover:bg-neutral-800/50"
                                                    @click="closeMobileMenu"
                                                >
                                                    <Settings class="h-5 w-5" />
                                                    Settings
                                                </Link>
                                            </SheetClose>
                                        </nav>
                                    </div>

                                    <Separator class="mx-3" />

                                    <!-- Resources Section -->
                                    <div class="p-3">
                                        <p
                                            class="mb-2 px-3 text-xs font-medium uppercase tracking-wider text-neutral-400 dark:text-neutral-500"
                                        >
                                            Resources
                                        </p>
                                        <nav class="space-y-1">
                                            <a
                                                v-for="item in rightNavItems"
                                                :key="item.title"
                                                :href="toUrl(item.href)"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-neutral-600 transition-colors hover:bg-neutral-50 dark:text-neutral-400 dark:hover:bg-neutral-800/50"
                                            >
                                                <component
                                                    v-if="item.icon"
                                                    :is="item.icon"
                                                    class="h-5 w-5"
                                                />
                                                {{ item.title }}
                                                <ExternalLink
                                                    class="ml-auto h-3.5 w-3.5 text-neutral-400"
                                                />
                                            </a>
                                        </nav>
                                    </div>
                                </div>

                                <!-- Logout Section -->
                                <div
                                    class="mt-auto border-t border-neutral-200 p-3 dark:border-neutral-800"
                                >
                                    <nav>
                                        <SheetClose :as-child="true">
                                            <Link
                                                :href="logout()"
                                                as="button"
                                                class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-red-600 transition-colors hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/30"
                                                @click="handleLogout"
                                            >
                                                <LogOut class="h-5 w-5" />
                                                Log out
                                            </Link>
                                        </SheetClose>
                                    </nav>
                                </div>
                            </SheetContent>
                        </Sheet>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
