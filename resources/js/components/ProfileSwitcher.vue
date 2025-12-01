<script setup lang="ts">
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useInitials } from '@/composables/useInitials';
import { logout } from '@/routes';
import { edit as editProfile } from '@/routes/profile';
import { switchMethod as switchProfile, index as manageProfiles } from '@/routes/profiles';
import type { Profile } from '@/types';
import { Link, router } from '@inertiajs/vue3';
import { ChevronDown, Check, Settings, LogOut, Users } from 'lucide-vue-next';

interface Props {
    profiles: Profile[];
    currentProfile: Profile | null;
}

const props = defineProps<Props>();

const { getInitials } = useInitials();

const handleSwitchProfile = (profile: Profile) => {
    if (profile.id === props.currentProfile?.id) return;
    
    router.post(switchProfile.url(profile.id), {}, {
        preserveScroll: true,
        preserveState: false,
    });
};

const handleLogout = () => {
    router.flushAll();
};

const getAgeGroupEmoji = (ageGroup: string): string => {
    const emojis: Record<string, string> = {
        '8': '👶',
        '12': '🧒',
        '16': '🧑',
        '18': '🧑‍🦱',
    };
    return emojis[ageGroup] || '👤';
};
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <button
                class="flex cursor-pointer items-center gap-2 rounded-full p-1 transition-colors hover:bg-accent focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                data-test="profile-menu-trigger"
            >
                <Avatar class="h-8 w-8 ring-2 ring-border">
                    <AvatarImage
                        v-if="currentProfile?.avatar"
                        :src="currentProfile.avatar"
                        :alt="currentProfile.name"
                        class="object-cover"
                    />
                    <AvatarFallback class="text-xs font-medium bg-gradient-to-br from-violet-500 to-purple-600 text-white">
                        {{ getInitials(currentProfile?.name || 'P') }}
                    </AvatarFallback>
                </Avatar>
                <span class="hidden sm:inline max-w-28 truncate text-sm font-medium">{{ currentProfile?.name || 'Select Profile' }}</span>
                <ChevronDown class="h-4 w-4 text-muted-foreground" />
            </button>
        </DropdownMenuTrigger>

        <DropdownMenuContent class="w-64" align="end" :side-offset="8">
            <!-- Current Profile Header -->
            <DropdownMenuLabel class="p-0 font-normal">
                <div class="flex items-center gap-3 px-2 py-2">
                    <Avatar class="h-10 w-10 ring-2 ring-border">
                        <AvatarImage
                            v-if="currentProfile?.avatar"
                            :src="currentProfile.avatar"
                            :alt="currentProfile.name"
                            class="object-cover"
                        />
                        <AvatarFallback class="text-sm font-medium bg-gradient-to-br from-violet-500 to-purple-600 text-white">
                            {{ getInitials(currentProfile?.name || 'P') }}
                        </AvatarFallback>
                    </Avatar>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold truncate">{{ currentProfile?.name || 'No Profile' }}</p>
                        <p class="text-xs text-muted-foreground">
                            {{ getAgeGroupEmoji(currentProfile?.age_group || '18') }} {{ currentProfile?.age_group_label || 'Adult' }}
                        </p>
                    </div>
                </div>
            </DropdownMenuLabel>

            <DropdownMenuSeparator />

            <!-- Switch Profile Section -->
            <DropdownMenuLabel class="text-xs font-normal text-muted-foreground px-2 py-1.5">
                Switch Profile
            </DropdownMenuLabel>

            <DropdownMenuGroup>
                <DropdownMenuItem
                    v-for="profile in profiles"
                    :key="profile.id"
                    class="cursor-pointer gap-3 py-2"
                    @click="handleSwitchProfile(profile)"
                >
                    <Avatar 
                        class="h-8 w-8 ring-2"
                        :class="currentProfile?.id === profile.id ? 'ring-orange-500' : 'ring-border'"
                    >
                        <AvatarImage
                            v-if="profile.avatar"
                            :src="profile.avatar"
                            :alt="profile.name"
                            class="object-cover"
                        />
                        <AvatarFallback class="text-xs font-medium bg-gradient-to-br from-violet-500 to-purple-600 text-white">
                            {{ getInitials(profile.name) }}
                        </AvatarFallback>
                    </Avatar>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium truncate">{{ profile.name }}</p>
                        <p class="text-xs text-muted-foreground">
                            {{ getAgeGroupEmoji(profile.age_group) }} {{ profile.age_group_label }}
                        </p>
                    </div>
                    <Check
                        v-if="currentProfile?.id === profile.id"
                        class="h-4 w-4 text-primary shrink-0"
                    />
                </DropdownMenuItem>
            </DropdownMenuGroup>

            <DropdownMenuSeparator />

            <!-- Account Actions -->
            <DropdownMenuGroup>
                <DropdownMenuItem as-child>
                    <Link :href="manageProfiles.url()" class="cursor-pointer gap-3">
                        <Users class="h-4 w-4" />
                        Manage Profiles
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuItem as-child>
                    <Link :href="editProfile()" class="cursor-pointer gap-3" prefetch>
                        <Settings class="h-4 w-4" />
                        Settings
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuGroup>

            <DropdownMenuSeparator />

            <DropdownMenuItem as-child>
                <Link
                    :href="logout()"
                    @click="handleLogout"
                    class="cursor-pointer gap-3"
                    data-test="logout-button"
                >
                    <LogOut class="h-4 w-4" />
                    Log out
                </Link>
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
