<script setup lang="ts">
import StorytimeIcon from '@/components/StorytimeIcon.vue';
</script>

<template>
    <div class="flex items-center gap-3">
        <div
            class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-[#f53003] to-[#F8B803]"
        >
            <StorytimeIcon class="h-6 w-6 text-white" />
        </div>
        <span
            class="text-2xl font-bold bg-gradient-to-r from-[#f53003] to-[#F8B803] bg-clip-text text-transparent"
        >
            Storytime
        </span>
    </div>
</template>
