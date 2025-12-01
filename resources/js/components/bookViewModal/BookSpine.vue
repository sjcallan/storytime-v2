<script setup lang="ts">
// Book spine/center seam visual element
</script>

<template>
    <div class="pointer-events-none absolute left-1/2 top-0 bottom-0 z-30 w-24 -translate-x-1/2">
        <!-- Wide soft shadow for page curve effect -->
        <div class="h-full w-full bg-gradient-to-r from-transparent via-amber-950/20 to-transparent" />
        <!-- Inner darker shadow -->
        <div class="absolute inset-y-0 left-1/2 w-10 -translate-x-1/2 bg-gradient-to-r from-transparent via-amber-950/25 to-transparent" />
        <!-- Core shadow at binding -->
        <div class="absolute inset-y-0 left-1/2 w-4 -translate-x-1/2 bg-gradient-to-r from-transparent via-amber-950/30 to-transparent" />
        <!-- Highlight line in center -->
        <div class="absolute inset-y-0 left-1/2 w-px -translate-x-1/2 bg-gradient-to-b from-amber-200/5 via-amber-100/20 to-amber-200/5" />
    </div>
</template>

