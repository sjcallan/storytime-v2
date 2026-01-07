<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import type { BookEditFormData } from './types';
import { Check } from 'lucide-vue-next';

interface Props {
    form: BookEditFormData;
    errors: Record<string, string>;
    isSaving: boolean;
    isDeleting: boolean;
}

const emit = defineEmits<{
    (e: 'update:form', value: BookEditFormData): void;
    (e: 'submit'): void;
    (e: 'cancel'): void;
}>();

const genres = [
    { value: 'fantasy', label: '🧙 Fantasy', emoji: '🧙' },
    { value: 'adventure', label: '🗺️ Adventure', emoji: '🗺️' },
    { value: 'mystery', label: '🔍 Mystery', emoji: '🔍' },
    { value: 'science_fiction', label: '🚀 Science Fiction', emoji: '🚀' },
    { value: 'fairy_tale', label: '🧚 Fairy Tale', emoji: '🧚' },
    { value: 'historical', label: '🏰 Historical', emoji: '🏰' },
    { value: 'comedy', label: '😂 Comedy', emoji: '😂' },
    { value: 'animal_stories', label: '🐾 Animal Stories', emoji: '🐾' },
    { value: 'drama', label: '🎭 Drama', emoji: '🎭' },
    { value: 'romance', label: '💕 Romance', emoji: '💕' },
    { value: 'horror', label: '👻 Horror', emoji: '👻' },
    { value: 'erotica', label: '🍷 Erotica', emoji: '🍷' },
];

const ageLevels = [
    { value: '8', label: 'Kids', range: '7-10' },
    { value: '12', label: 'Pre-Teen', range: '11-13' },
    { value: '16', label: 'Teen', range: '14-17' },
    { value: '18', label: 'Adult', range: '18+' },
];

const bookTypes = [
    {
        value: 'chapter',
        label: '📚 Chapter Book',
    },
    {
        value: 'theatre',
        label: '🎭 Theatre Play',
    },
    {
        value: 'story',
        label: '📖 Short Story',
    },
    {
        value: 'screenplay',
        label: '🎬 Screenplay',
    },
];

const props = defineProps<Props>();

const updateField = (field: keyof BookEditFormData, value: unknown) => {
    emit('update:form', { ...props.form, [field]: String(value ?? '') });
};

const getGenreDisplay = (value: string) => {
    const genre = genres.find(g => g.value === value);
    return genre || { value, label: value, emoji: '📖' };
};

const getAgeLevelDisplay = (value: string) => {
    const age = ageLevels.find(a => a.value === value);
    return age || { value, label: `Age ${value}+`, range: '' };
};

const getBookTypeDisplay = (value: string) => {
    const type = bookTypes.find(t => t.value === value);
    return type || { value, label: value };
};
</script>

<template>
    <div class="relative z-10 max-h-[calc(100vh-6rem)] overflow-y-auto p-8 pt-16">
        <form @submit.prevent="emit('submit')" class="space-y-6">
            <!-- Story Details Section -->
            <div class="space-y-4 rounded-2xl border-2 border-gray-200 bg-white/50 p-5 dark:border-gray-700 dark:bg-gray-900/50">
                <h3 class="flex items-center gap-2 text-lg font-bold text-gray-900 dark:text-white">
                    <span class="text-2xl">📖</span>
                    Story Details
                </h3>
                
                <!-- Book Type (Read-only) -->
                <div class="space-y-2">
                    <Label class="text-sm font-semibold text-gray-700 dark:text-gray-300">Story Type</Label>
                    <div class="rounded-xl border-2 border-orange-500/30 bg-orange-50/50 px-4 py-3 dark:bg-orange-950/20">
                        <span class="text-base font-semibold text-gray-900 dark:text-white">
                            {{ getBookTypeDisplay(form.type).label }}
                        </span>
                    </div>
                </div>

                <!-- Genre (Read-only) -->
                <div class="space-y-2">
                    <Label class="text-sm font-semibold text-gray-700 dark:text-gray-300">Genre</Label>
                    <div class="rounded-xl border-2 border-orange-500/30 bg-orange-50/50 px-4 py-3 dark:bg-orange-950/20">
                        <div class="flex items-center gap-2">
                            <span class="text-2xl">{{ getGenreDisplay(form.genre).emoji }}</span>
                            <span class="text-base font-semibold text-gray-900 dark:text-white">
                                {{ getGenreDisplay(form.genre).label.replace(getGenreDisplay(form.genre).emoji + ' ', '') }}
                            </span>
                            <Check class="ml-auto h-5 w-5 text-orange-500" />
                        </div>
                    </div>
                </div>

                <!-- Age Level (Read-only) -->
                <div class="space-y-2">
                    <Label class="text-sm font-semibold text-gray-700 dark:text-gray-300">Age Level</Label>
                    <div class="rounded-xl border-2 border-orange-500/30 bg-orange-50/50 px-4 py-3 dark:bg-orange-950/20">
                        <div class="flex items-center justify-between">
                            <div class="flex flex-col">
                                <span class="text-base font-bold text-gray-900 dark:text-white">
                                    {{ getAgeLevelDisplay(form.age_level).label }}
                                </span>
                                <span class="text-sm text-gray-600 dark:text-gray-400">
                                    {{ getAgeLevelDisplay(form.age_level).range }}
                                </span>
                            </div>
                            <Check class="h-5 w-5 text-orange-500" />
                        </div>
                    </div>
                </div>

                <!-- Author (Read-only) -->
                <div v-if="form.author" class="space-y-2">
                    <Label class="text-sm font-semibold text-gray-700 dark:text-gray-300">Author</Label>
                    <div class="rounded-xl border-2 border-gray-200 bg-white/70 px-4 py-3 dark:border-gray-700 dark:bg-gray-950/50">
                        <span class="text-base text-gray-700 dark:text-gray-300">
                            {{ form.author }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Editable Fields Section -->
            <div class="space-y-4 rounded-2xl border-2 border-blue-200 bg-blue-50/30 p-5 dark:border-blue-800 dark:bg-blue-950/20">
                <h3 class="flex items-center gap-2 text-lg font-bold text-gray-900 dark:text-white">
                    <span class="text-2xl">✏️</span>
                    Edit Your Story Details
                </h3>

                <!-- Title (Editable) -->
                <div class="grid gap-2">
                    <Label for="edit-title" class="text-sm font-semibold text-gray-900 dark:text-white">
                        Story Title
                    </Label>
                    <Input
                        id="edit-title"
                        :model-value="form.title"
                        @update:model-value="updateField('title', $event)"
                        placeholder="Enter your story title"
                        :disabled="isSaving || isDeleting"
                        class="h-11 rounded-xl border-2 border-gray-200 bg-white text-base text-gray-900 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                    />
                    <InputError :message="errors.title" />
                </div>

                <!-- Plot (Editable) -->
                <div class="grid gap-2">
                    <Label for="edit-plot" class="text-sm font-semibold text-gray-900 dark:text-white">
                        Plot Summary
                    </Label>
                    <Textarea
                        id="edit-plot"
                        :model-value="form.plot"
                        @update:model-value="updateField('plot', $event)"
                        placeholder="Describe your story's plot..."
                        rows="6"
                        :disabled="isSaving || isDeleting"
                        class="resize-none rounded-xl border-2 border-gray-200 bg-white text-base leading-relaxed text-gray-900 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                    />
                    <InputError :message="errors.plot" />
                </div>
            </div>

            <InputError v-if="errors.general" :message="errors.general" />

            <!-- Action Buttons -->
            <div class="flex items-center justify-end gap-3 pt-2">
                <Button
                    type="button"
                    variant="outline"
                    size="default"
                    @click="emit('cancel')"
                    :disabled="isSaving || isDeleting"
                    class="h-11 cursor-pointer rounded-xl px-6 transition-all duration-200 hover:scale-[1.02] active:scale-[0.98]"
                >
                    Cancel
                </Button>
                <Button
                    type="submit"
                    size="default"
                    :disabled="isSaving || isDeleting"
                    class="h-11 cursor-pointer gap-2 rounded-xl bg-linear-to-r from-blue-500 to-cyan-500 px-6 text-white shadow-lg transition-all duration-200 hover:scale-[1.02] hover:from-blue-600 hover:to-cyan-600 hover:shadow-xl hover:shadow-blue-500/25 active:scale-[0.98]"
                >
                    <Spinner v-if="isSaving" class="h-4 w-4" />
                    {{ isSaving ? 'Saving...' : 'Save Changes' }}
                </Button>
            </div>
        </form>
    </div>
</template>

