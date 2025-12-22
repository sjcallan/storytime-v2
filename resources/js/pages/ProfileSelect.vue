<script setup lang="ts">
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
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
import InputError from '@/components/InputError.vue';
import PinVerificationModal from '@/components/PinVerificationModal.vue';
import StorytimeIcon from '@/components/StorytimeIcon.vue';
import { useInitials } from '@/composables/useInitials';
import { switchMethod as switchProfile } from '@/routes/profiles';
import ProfilesController from '@/actions/App/Http/Controllers/Settings/ProfilesController';
import { logout, dashboard } from '@/routes';
import type { Profile } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { LogOut, Plus, Settings, Loader2 } from 'lucide-vue-next';
import { ref, onMounted, computed } from 'vue';

interface AgeGroup {
    label: string;
    range: string;
    emoji: string;
}

interface Props {
    profiles: Profile[];
    currentProfileId: string | null;
    ageGroups: Record<string, AgeGroup>;
}

const props = defineProps<Props>();

const page = usePage();
const { getInitials } = useInitials();
const isVisible = ref(false);

const hasPin = computed(() => page.props.auth.hasPin);

const showPinModal = ref(false);
const isCreateDialogOpen = ref(false);
const newProfileName = ref('');
const newProfileAgeGroup = ref('8');
const createErrors = ref<Record<string, string>>({});
const isCreating = ref(false);

const ageGroupOptions = computed(() => {
    return Object.entries(props.ageGroups).map(([value, data]) => ({
        value,
        label: `${data.emoji} ${data.label}`,
        range: data.range,
    }));
});

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

const handleAddProfileClick = () => {
    if (hasPin.value) {
        showPinModal.value = true;
    } else {
        openCreateDialog();
    }
};

const handlePinVerified = () => {
    showPinModal.value = false;
    openCreateDialog();
};

const handlePinCancelled = () => {
    showPinModal.value = false;
};

const openCreateDialog = () => {
    newProfileName.value = '';
    newProfileAgeGroup.value = '8';
    createErrors.value = {};
    isCreateDialogOpen.value = true;
};

const createProfile = () => {
    isCreating.value = true;
    createErrors.value = {};

    router.post(
        ProfilesController.store.url(),
        {
            name: newProfileName.value,
            age_group: newProfileAgeGroup.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                isCreateDialogOpen.value = false;
                newProfileName.value = '';
                newProfileAgeGroup.value = '8';
            },
            onError: (errors) => {
                createErrors.value = errors;
            },
            onFinish: () => {
                isCreating.value = false;
            },
        },
    );
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
                    class="group relative flex flex-col items-center gap-4 rounded-3xl p-6 transition-all duration-300 hover:scale-110 focus:outline-none focus-visible:ring-4 focus-visible:ring-[#F8B803]/50 cursor-pointer"
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
                <button
                    @click="handleAddProfileClick"
                    class="group relative flex flex-col items-center gap-4 rounded-3xl p-6 transition-all duration-300 hover:scale-110 focus:outline-none focus-visible:ring-4 focus-visible:ring-white/20 cursor-pointer"
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
                </button>
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
        
        <!-- PIN Verification Modal -->
        <PinVerificationModal
            v-model:is-open="showPinModal"
            @verified="handlePinVerified"
            @cancelled="handlePinCancelled"
        />
        
        <!-- Create Profile Dialog -->
        <Dialog v-model:open="isCreateDialogOpen">
            <DialogContent class="sm:max-w-md border-white/10 bg-[#1a1a1a]">
                <DialogHeader>
                    <DialogTitle class="text-white">Create Profile</DialogTitle>
                    <DialogDescription class="text-white/60">
                        Add a new viewer profile with age-appropriate content settings
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4 py-4">
                    <div class="space-y-2">
                        <Label for="create-name" class="text-white/80">Profile Name</Label>
                        <Input
                            id="create-name"
                            v-model="newProfileName"
                            placeholder="e.g., Kids, Teens, Family"
                            class="border-white/10 bg-white/5 text-white placeholder:text-white/40 focus:border-[#F8B803]/50 focus:ring-[#F8B803]/20"
                            @keyup.enter="createProfile"
                        />
                        <InputError :message="createErrors.name" />
                    </div>

                    <div class="space-y-2">
                        <Label for="create-age-group" class="text-white/80">Age Group</Label>
                        <Select v-model="newProfileAgeGroup">
                            <SelectTrigger class="border-white/10 bg-white/5 text-white focus:border-[#F8B803]/50 focus:ring-[#F8B803]/20">
                                <SelectValue placeholder="Select age group" />
                            </SelectTrigger>
                            <SelectContent class="border-white/10 bg-[#1a1a1a]">
                                <SelectItem
                                    v-for="option in ageGroupOptions"
                                    :key="option.value"
                                    :value="option.value"
                                    class="text-white/80 focus:bg-white/10 focus:text-white"
                                >
                                    {{ option.label }} ({{ option.range }})
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="createErrors.age_group" />
                    </div>
                </div>

                <DialogFooter class="gap-2 sm:gap-0">
                    <Button 
                        variant="outline" 
                        @click="isCreateDialogOpen = false"
                        class="border-white/10 bg-transparent text-white/70 hover:bg-white/5 hover:text-white"
                    >
                        Cancel
                    </Button>
                    <Button 
                        @click="createProfile" 
                        :disabled="isCreating || !newProfileName"
                        class="bg-gradient-to-r from-[#f53003] to-[#F8B803] text-white hover:opacity-90"
                    >
                        <Loader2 v-if="isCreating" class="mr-2 h-4 w-4 animate-spin" />
                        Create Profile
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
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

