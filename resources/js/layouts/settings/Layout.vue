<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import PinVerificationModal from '@/components/PinVerificationModal.vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { toUrl, urlIsActive } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editProfile } from '@/routes/profile';
import { index as profilesIndex } from '@/routes/profiles';
import { show } from '@/routes/two-factor';
import { edit as editPassword } from '@/routes/user-password';
import { status as pinStatus } from '@/actions/App/Http/Controllers/Settings/PinController';
import { type NavItem, type AppPageProps } from '@/types';
import { Link, usePage, router } from '@inertiajs/vue3';
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';

const page = usePage<AppPageProps>();
const hasPin = computed(() => page.props.auth.hasPin);

const sidebarNavItems: NavItem[] = [
    {
        title: 'Account',
        href: editProfile(),
    },
    {
        title: 'Manage Profiles',
        href: profilesIndex(),
    },
    {
        title: 'Password',
        href: editPassword(),
    },
    {
        title: 'Two-Factor Auth',
        href: show(),
    },
    {
        title: 'Appearance',
        href: editAppearance(),
    },
];

const currentPath = typeof window !== undefined ? window.location.pathname : '';

const showPinModal = ref(false);
const isPinVerified = ref(false);
const isCheckingPin = ref(true);

const checkPinStatus = async () => {
    if (!hasPin.value) {
        isPinVerified.value = true;
        isCheckingPin.value = false;
        return;
    }

    try {
        const response = await axios.get(pinStatus.url());
        isPinVerified.value = !response.data.requires_verification;
        
        if (response.data.requires_verification) {
            showPinModal.value = true;
        }
    } catch {
        isPinVerified.value = false;
        showPinModal.value = true;
    } finally {
        isCheckingPin.value = false;
    }
};

const handlePinVerified = () => {
    isPinVerified.value = true;
    showPinModal.value = false;
};

const handlePinCancelled = () => {
    router.visit('/dashboard');
};

onMounted(() => {
    checkPinStatus();
});
</script>

<template>
    <div class="px-4 py-6">
        <Heading
            title="Settings"
            description="Manage your profile and account settings"
        />

        <!-- Loading State -->
        <div
            v-if="isCheckingPin"
            class="flex h-64 items-center justify-center rounded-xl border border-border bg-card shadow-sm"
        >
            <div class="flex flex-col items-center gap-3 text-muted-foreground">
                <div class="h-8 w-8 animate-spin rounded-full border-2 border-muted-foreground border-t-transparent" />
                <span class="text-sm">Verifying access...</span>
            </div>
        </div>

        <!-- Settings Content (only shown when verified or no PIN) -->
        <div
            v-else-if="isPinVerified"
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
                                <component :is="item.icon" class="h-4 w-4" />
                                {{ item.title }}
                            </Link>
                        </Button>
                    </nav>
                </aside>

                <Separator class="my-6 lg:hidden" />
                <Separator orientation="vertical" class="hidden lg:block h-auto self-stretch" />

                <div class="flex-1 md:max-w-2xl">
                    <section class="max-w-xl space-y-12">
                        <slot />
                    </section>
                </div>
            </div>
        </div>

        <!-- PIN Verification Modal -->
        <PinVerificationModal
            v-model:is-open="showPinModal"
            @verified="handlePinVerified"
            @cancelled="handlePinCancelled"
        />
    </div>
</template>
