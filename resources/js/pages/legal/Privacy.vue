<script setup lang="ts">
import LegalLayout from '@/layouts/LegalLayout.vue';
import { marked } from 'marked';
import { computed } from 'vue';

const props = defineProps<{
    content: string;
}>();

const renderedContent = computed(() => {
    return marked.parse(props.content);
});
</script>

<template>
    <LegalLayout title="Privacy Policy">
        <div v-html="renderedContent" />
    </LegalLayout>
</template>

