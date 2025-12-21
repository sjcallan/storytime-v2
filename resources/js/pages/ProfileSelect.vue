<script setup lang="ts">
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import StorytimeIcon from '@/components/StorytimeIcon.vue';
import { useInitials } from '@/composables/useInitials';
import { switchMethod as switchProfile } from '@/routes/profiles';
import { logout, dashboard } from '@/routes';
import type { Profile } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { LogOut, Plus, Settings } from 'lucide-vue-next';
import { ref, onMounted } from 'vue';

interface Props {
    profiles: Profile[];
    currentProfileId: string | null;
}

const props = defineProps<Props>();

const page = usePage();
const { getInitials } = useInitials();
const isVisible = ref(false);

onMounted(() => {
    setTimeout(() => {
        isVisible.value = true;
    }, 100);
});

const handleSelectProfile = (profile: Profile) => {
    router.post(switchProfile.url(profile.id), {}, {
        preserveScroll: true,
        preserveState: false,
        onSuccess: () => {
            router.visit(dashboard());
        },
    });
};

const handleLogout = () => {
    router.flushAll();
};

const getProfileGradient = (index: number): string => {
    const gradients = [
        'from-violet-500 to-purple-600',
        'from-rose-400 to-pink-500',
        'from-amber-400 to-orange-500',
        'from-emerald-400 to-teal-500',
        'from-sky-400 to-blue-500',
        'from-fuchsia-400 to-purple-500',
    ];
    return gradients[index % gradients.length];
};
</script>

<template>
    <Head title="Who's Reading?" />
    
    <div class="relative min-h-screen w-full overflow-hidden bg-gradient-to-br from-[#1D0002] via-[#0a0a0a] to-[#1a0f00]">
        <!-- Animated background elements -->
        <div class="absolute inset-0 overflow-hidden">
            <div class="absolute -top-40 -left-40 h-96 w-96 animate-blob rounded-full bg-[#f53003]/20 blur-3xl"></div>
            <div class="absolute top-1/4 -right-20 h-72 w-72 animate-blob animation-delay-2000 rounded-full bg-[#F8B803]/20 blur-3xl"></div>
            <div class="absolute bottom-20 left-1/3 h-80 w-80 animate-blob animation-delay-4000 rounded-full bg-[#F0ACB8]/20 blur-3xl"></div>
        </div>
        
        <!-- Header -->
        <header class="relative z-20 flex items-center justify-between px-8 py-6">
            <Link :href="dashboard()" class="flex items-center gap-3 group">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-[#f53003] to-[#F8B803] transition-transform group-hover:scale-110">
                    <StorytimeIcon class="h-6 w-6 text-white" />
                </div>
                <span class="text-2xl font-bold bg-gradient-to-r from-[#f53003] to-[#F8B803] bg-clip-text text-transparent">
                    Storytime
                </span>
            </Link>
            
            <div class="flex items-center gap-3">
                <Link 
                    :href="logout()" 
                    @click="handleLogout"
                    class="flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-medium text-white/70 transition-all hover:bg-white/10 hover:text-white"
                >
                    <LogOut class="h-4 w-4" />
                    <span class="hidden sm:inline">Sign Out</span>
                </Link>
            </div>
        </header>
        
        <!-- Main Content -->
        <main class="relative z-10 flex min-h-[calc(100vh-88px)] flex-col items-center justify-center px-6 py-12">
            <!-- Title -->
            <div 
                :class="[
                    'mb-16 text-center transition-all duration-700',
                    isVisible ? 'translate-y-0 opacity-100' : 'translate-y-8 opacity-0'
                ]"
            >
                <h1 class="mb-4 text-4xl font-bold text-white sm:text-5xl lg:text-6xl">
                    Who's the
                    <span class="bg-gradient-to-r from-[#f53003] via-[#F8B803] to-[#F0ACB8] bg-clip-text text-transparent">
                        Author
                    </span>
                    ?
                </h1>
                <p class="text-lg text-white/60">
                    Select your profile to continue
                </p>
            </div>
            
            <!-- Profiles Grid -->
            <div 
                :class="[
                    'grid gap-8 transition-all duration-700 delay-200',
                    profiles.length <= 3 ? 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3' : 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-4',
                    isVisible ? 'translate-y-0 opacity-100' : 'translate-y-8 opacity-0'
                ]"
            >
                <button
                    v-for="(profile, index) in profiles"
                    :key="profile.id"
                    @click="handleSelectProfile(profile)"
                    :style="{ animationDelay: `${index * 100 + 300}ms` }"
                    class="group relative flex flex-col items-center gap-4 rounded-3xl p-6 transition-all duration-300 hover:scale-110 focus:outline-none focus-visible:ring-4 focus-visible:ring-[#F8B803]/50"
                >
                    <!-- Glow effect on hover -->
                    <div 
                        class="absolute inset-0 rounded-3xl bg-gradient-to-br from-[#f53003]/0 via-[#F8B803]/0 to-[#F0ACB8]/0 opacity-0 blur-xl transition-opacity duration-300 group-hover:opacity-50"
                        :class="`group-hover:from-[#f53003]/30 group-hover:via-[#F8B803]/20 group-hover:to-[#F0ACB8]/30`"
                    ></div>
                    
                    <!-- Avatar container with ring -->
                    <div class="relative">
                        <!-- Animated ring on hover -->
                        <div class="absolute -inset-2 rounded-full bg-gradient-to-r from-[#f53003] via-[#F8B803] to-[#F0ACB8] opacity-0 blur-sm transition-opacity duration-300 group-hover:opacity-100"></div>
                        <div class="absolute -inset-2 rounded-full bg-gradient-to-r from-[#f53003] via-[#F8B803] to-[#F0ACB8] opacity-0 transition-opacity duration-300 group-hover:opacity-100 animate-spin-slow"></div>
                        
                        <!-- Current profile indicator -->
                        <div 
                            v-if="currentProfileId === profile.id"
                            class="absolute -inset-2 rounded-full bg-gradient-to-r from-[#f53003] via-[#F8B803] to-[#F0ACB8] opacity-60"
                        ></div>
                        
                        <!-- Avatar -->
                        <Avatar class="relative h-32 w-32 ring-4 ring-white/10 transition-all duration-300 group-hover:ring-transparent sm:h-36 sm:w-36 lg:h-40 lg:w-40">
                            <AvatarImage
                                v-if="profile.avatar"
                                :src="profile.avatar"
                                :alt="profile.name"
                                class="object-cover"
                            />
                            <AvatarFallback 
                                :class="[
                                    'text-3xl font-bold text-white bg-gradient-to-br',
                                    getProfileGradient(index)
                                ]"
                            >
                                {{ getInitials(profile.name) }}
                            </AvatarFallback>
                        </Avatar>
                        
                        <!-- Current badge -->
                        <div 
                            v-if="currentProfileId === profile.id"
                            class="absolute -bottom-1 left-1/2 -translate-x-1/2 rounded-full bg-gradient-to-r from-[#f53003] to-[#F8B803] px-3 py-1 text-xs font-semibold text-white shadow-lg"
                        >
                            Current
                        </div>
                    </div>
                    
                    <!-- Profile info -->
                    <div class="relative text-center">
                        <p class="text-lg font-semibold text-white transition-colors group-hover:text-[#F8B803] sm:text-xl">
                            {{ profile.name }}
                        </p>
                        <p class="mt-1 text-sm text-white/50">
                            {{ profile.age_group_label }}
                        </p>
                    </div>
                </button>
                
                <!-- Add Profile Button -->
                <Link
                    href="/settings/profiles"
                    class="group relative flex flex-col items-center gap-4 rounded-3xl p-6 transition-all duration-300 hover:scale-110 focus:outline-none focus-visible:ring-4 focus-visible:ring-white/20"
                >
                    <!-- Avatar placeholder -->
                    <div class="relative flex h-32 w-32 items-center justify-center rounded-full border-4 border-dashed border-white/20 transition-all duration-300 group-hover:border-[#F8B803]/50 sm:h-36 sm:w-36 lg:h-40 lg:w-40">
                        <Plus class="h-12 w-12 text-white/30 transition-all duration-300 group-hover:text-[#F8B803] group-hover:scale-110" />
                    </div>
                    
                    <!-- Label -->
                    <div class="text-center">
                        <p class="text-lg font-semibold text-white/50 transition-colors group-hover:text-white sm:text-xl">
                            Add Profile
                        </p>
                    </div>
                </Link>
            </div>
            
            <!-- Manage Profiles Link -->
            <Link
                href="/settings/profiles"
                :class="[
                    'mt-16 flex items-center gap-2 rounded-xl border border-white/10 px-6 py-3 text-sm font-medium text-white/70 transition-all duration-500 delay-500 hover:border-white/30 hover:bg-white/5 hover:text-white',
                    isVisible ? 'translate-y-0 opacity-100' : 'translate-y-4 opacity-0'
                ]"
            >
                <Settings class="h-4 w-4" />
                Manage Profiles
            </Link>
        </main>
    </div>
</template>

<style scoped>
@keyframes blob {
    0%, 100% {
        transform: translate(0, 0) scale(1);
    }
    25% {
        transform: translate(20px, -30px) scale(1.1);
    }
    50% {
        transform: translate(-20px, 20px) scale(0.9);
    }
    75% {
        transform: translate(30px, 10px) scale(1.05);
    }
}

.animate-blob {
    animation: blob 12s ease-in-out infinite;
}

.animation-delay-2000 {
    animation-delay: 2s;
}

.animation-delay-4000 {
    animation-delay: 4s;
}

@keyframes spin-slow {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

.animate-spin-slow {
    animation: spin-slow 3s linear infinite;
}
</style>

