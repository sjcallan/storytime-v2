<script setup lang="ts">
import { ref, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { ChevronUp, ChevronDown, Code2, Cpu, Shield, Image } from 'lucide-vue-next';
import type { AiDebugConfig } from '@/types';

const page = usePage();
const isExpanded = ref(false);

const aiDebug = computed(() => page.props.aiDebug as AiDebugConfig | undefined);

const toggleExpanded = () => {
    isExpanded.value = !isExpanded.value;
};
</script>

<template>
    <div v-if="aiDebug" class="fixed inset-x-0 bottom-0 z-50">
        <!-- Collapsed Bar -->
        <button
            @click="toggleExpanded"
            class="flex w-full items-center justify-between bg-zinc-900/95 px-4 py-2 text-xs text-zinc-300 backdrop-blur-sm transition-colors hover:bg-zinc-900"
        >
            <div class="flex items-center gap-4">
                <div class="flex items-center gap-1.5">
                    <Code2 class="h-3.5 w-3.5 text-emerald-400" />
                    <span class="font-medium text-emerald-400">DEV</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <Cpu class="h-3.5 w-3.5 text-violet-400" />
                    <span>{{ aiDebug.default_provider }}</span>
                    <span class="text-zinc-500">·</span>
                    <span class="text-zinc-400">{{ aiDebug.active_provider.model }}</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <Shield 
                        class="h-3.5 w-3.5" 
                        :class="aiDebug.moderation.enabled ? 'text-amber-400' : 'text-zinc-500'" 
                    />
                    <span :class="aiDebug.moderation.enabled ? 'text-amber-400' : 'text-zinc-500'">
                        Moderation {{ aiDebug.moderation.enabled ? 'ON' : 'OFF' }}
                    </span>
                </div>
                <div class="flex items-center gap-1.5">
                    <Image 
                        class="h-3.5 w-3.5" 
                        :class="aiDebug.image_generation.use_custom_model ? 'text-fuchsia-400' : 'text-zinc-500'" 
                    />
                    <span :class="aiDebug.image_generation.use_custom_model ? 'text-fuchsia-400' : 'text-zinc-500'">
                        Custom Model {{ aiDebug.image_generation.use_custom_model ? 'ON' : 'OFF' }}
                    </span>
                </div>
            </div>
            <component 
                :is="isExpanded ? ChevronDown : ChevronUp" 
                class="h-4 w-4 text-zinc-400" 
            />
        </button>

        <!-- Expanded Panel -->
        <div 
            v-show="isExpanded"
            class="border-t border-zinc-700/50 bg-zinc-900/98 backdrop-blur-sm"
        >
            <div class="mx-auto grid max-w-7xl gap-6 p-4 sm:grid-cols-3">
                <!-- AI Provider Section -->
                <div class="space-y-3">
                    <div class="flex items-center gap-2">
                        <Cpu class="h-4 w-4 text-violet-400" />
                        <h3 class="text-sm font-semibold text-white">AI Provider</h3>
                    </div>
                    <dl class="space-y-1.5 text-xs">
                        <div class="flex justify-between">
                            <dt class="text-zinc-500">Provider</dt>
                            <dd class="font-mono text-violet-300">{{ aiDebug.default_provider }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-zinc-500">Driver</dt>
                            <dd class="font-mono text-zinc-300">{{ aiDebug.active_provider.driver }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-zinc-500">Model</dt>
                            <dd class="font-mono text-zinc-300">{{ aiDebug.active_provider.model }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-zinc-500">Max Tokens</dt>
                            <dd class="font-mono text-zinc-300">{{ aiDebug.active_provider.max_tokens.toLocaleString() }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-zinc-500">Temperature</dt>
                            <dd class="font-mono text-zinc-300">{{ aiDebug.active_provider.temperature }}</dd>
                        </div>
                        <div v-if="aiDebug.active_provider.base_url" class="flex justify-between">
                            <dt class="text-zinc-500">Base URL</dt>
                            <dd class="max-w-[180px] truncate font-mono text-zinc-300" :title="aiDebug.active_provider.base_url">
                                {{ aiDebug.active_provider.base_url }}
                            </dd>
                        </div>
                    </dl>
                </div>

                <!-- Moderation Section -->
                <div class="space-y-3">
                    <div class="flex items-center gap-2">
                        <Shield class="h-4 w-4 text-amber-400" />
                        <h3 class="text-sm font-semibold text-white">Content Moderation</h3>
                    </div>
                    <dl class="space-y-1.5 text-xs">
                        <div class="flex justify-between">
                            <dt class="text-zinc-500">Status</dt>
                            <dd>
                                <span 
                                    class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-medium"
                                    :class="aiDebug.moderation.enabled 
                                        ? 'bg-emerald-400/10 text-emerald-400' 
                                        : 'bg-zinc-600/50 text-zinc-400'"
                                >
                                    {{ aiDebug.moderation.enabled ? 'Enabled' : 'Disabled' }}
                                </span>
                            </dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-zinc-500">Model</dt>
                            <dd class="font-mono text-zinc-300">{{ aiDebug.moderation.model }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-zinc-500">Min Threshold</dt>
                            <dd class="font-mono text-zinc-300">{{ aiDebug.moderation.min_threshold }}</dd>
                        </div>
                    </dl>
                </div>

                <!-- Image Generation Section -->
                <div class="space-y-3">
                    <div class="flex items-center gap-2">
                        <Image class="h-4 w-4 text-fuchsia-400" />
                        <h3 class="text-sm font-semibold text-white">Image Generation</h3>
                    </div>
                    <dl class="space-y-1.5 text-xs">
                        <div class="flex justify-between">
                            <dt class="text-zinc-500">Provider</dt>
                            <dd class="font-mono text-fuchsia-300">{{ aiDebug.image_generation.provider }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-zinc-500">Custom Model</dt>
                            <dd>
                                <span 
                                    class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-medium"
                                    :class="aiDebug.image_generation.use_custom_model 
                                        ? 'bg-fuchsia-400/10 text-fuchsia-400' 
                                        : 'bg-zinc-600/50 text-zinc-400'"
                                >
                                    {{ aiDebug.image_generation.use_custom_model ? 'Enabled' : 'Disabled' }}
                                </span>
                            </dd>
                        </div>
                        <template v-if="aiDebug.image_generation.use_custom_model">
                            <div v-if="aiDebug.image_generation.custom_model_version" class="flex flex-col gap-0.5">
                                <dt class="text-zinc-500">Model Version</dt>
                                <dd class="truncate font-mono text-[10px] text-zinc-300" :title="aiDebug.image_generation.custom_model_version">
                                    {{ aiDebug.image_generation.custom_model_version }}
                                </dd>
                            </div>
                            <div v-if="aiDebug.image_generation.custom_model_lora" class="flex flex-col gap-0.5">
                                <dt class="text-zinc-500">LoRA</dt>
                                <dd class="truncate font-mono text-[10px] text-zinc-300" :title="aiDebug.image_generation.custom_model_lora">
                                    {{ aiDebug.image_generation.custom_model_lora }}
                                </dd>
                            </div>
                            <div v-if="aiDebug.image_generation.custom_model_lora_scale" class="flex justify-between">
                                <dt class="text-zinc-500">LoRA Scale</dt>
                                <dd class="font-mono text-zinc-300">{{ aiDebug.image_generation.custom_model_lora_scale }}</dd>
                            </div>
                        </template>
                        <div class="flex flex-col gap-1">
                            <dt class="text-zinc-500">Available Models</dt>
                            <dd class="flex flex-wrap gap-1">
                                <span 
                                    v-for="model in aiDebug.image_generation.models" 
                                    :key="model"
                                    class="inline-flex rounded bg-zinc-800 px-1.5 py-0.5 font-mono text-[10px] text-zinc-300"
                                >
                                    {{ model }}
                                </span>
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</template>
