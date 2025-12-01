<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import type { BookEditFormData } from './types';

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
    { value: 'fantasy', label: 'Fantasy' },
    { value: 'adventure', label: 'Adventure' },
    { value: 'mystery', label: 'Mystery' },
    { value: 'science_fiction', label: 'Science Fiction' },
    { value: 'fairy_tale', label: 'Fairy Tale' },
    { value: 'historical', label: 'Historical' },
    { value: 'comedy', label: 'Comedy' },
    { value: 'animal_stories', label: 'Animal Stories' },
];

const ageLevels = Array.from({ length: 15 }, (_, index) => ({
    value: String(index + 4),
    label: `Age ${index + 4}+`,
}));

const props = defineProps<Props>();

const updateField = (field: keyof BookEditFormData, value: unknown) => {
    emit('update:form', { ...props.form, [field]: String(value ?? '') });
};
</script>

<template>
    <div class="relative z-10 p-8 pt-16">
        <form @submit.prevent="emit('submit')" class="space-y-5">
            <div class="grid gap-2">
                <Label for="edit-title" class="text-sm font-semibold text-foreground">Story Title</Label>
                <Input
                    id="edit-title"
                    :model-value="form.title"
                    @update:model-value="updateField('title', $event)"
                    placeholder="Enter your story title"
                    :disabled="isSaving || isDeleting"
                    class="h-10 bg-white/70 dark:bg-white/5"
                />
                <InputError :message="errors.title" />
            </div>

            <div class="grid gap-2">
                <Label for="edit-genre" class="text-sm font-semibold text-foreground">Genre</Label>
                <Select 
                    :model-value="form.genre" 
                    @update:model-value="updateField('genre', $event)"
                    :disabled="isSaving || isDeleting"
                >
                    <SelectTrigger id="edit-genre" class="h-10 text-left">
                        <SelectValue placeholder="Select a genre" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="genre in genres"
                            :key="genre.value"
                            :value="genre.value"
                        >
                            {{ genre.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="errors.genre" />
            </div>

            <div class="grid gap-2">
                <Label for="edit-age-level" class="text-sm font-semibold text-foreground">Age Level</Label>
                <Select 
                    :model-value="form.age_level" 
                    @update:model-value="updateField('age_level', $event)"
                    :disabled="isSaving || isDeleting"
                >
                    <SelectTrigger id="edit-age-level" class="h-10 text-left">
                        <SelectValue placeholder="Select age level" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="age in ageLevels"
                            :key="age.value"
                            :value="age.value"
                        >
                            {{ age.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="errors.age_level" />
            </div>

            <div class="grid gap-2">
                <Label for="edit-author" class="text-sm font-semibold text-foreground">Author</Label>
                <Input
                    id="edit-author"
                    :model-value="form.author"
                    @update:model-value="updateField('author', $event)"
                    placeholder="Author name"
                    :disabled="isSaving || isDeleting"
                    class="h-10 bg-white/70 dark:bg-white/5"
                />
                <InputError :message="errors.author" />
            </div>

            <div class="grid gap-2">
                <Label for="edit-plot" class="text-sm font-semibold text-foreground">Plot Summary</Label>
                <Textarea
                    id="edit-plot"
                    :model-value="form.plot"
                    @update:model-value="updateField('plot', $event)"
                    placeholder="Briefly describe your story's plot..."
                    rows="4"
                    :disabled="isSaving || isDeleting"
                    class="min-h-[100px] text-sm leading-relaxed"
                />
                <InputError :message="errors.plot" />
            </div>

            <InputError v-if="errors.general" :message="errors.general" />

            <div class="flex items-center justify-end gap-3 pt-2">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="emit('cancel')"
                    :disabled="isSaving || isDeleting"
                >
                    Cancel
                </Button>
                <Button
                    type="submit"
                    size="sm"
                    :disabled="isSaving || isDeleting"
                >
                    <Spinner v-if="isSaving" class="mr-2 h-4 w-4" />
                    {{ isSaving ? 'Saving...' : 'Save Changes' }}
                </Button>
            </div>
        </form>
    </div>
</template>

