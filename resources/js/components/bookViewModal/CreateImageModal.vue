<script setup lang="ts">
import { ref, computed, watch, onUnmounted } from 'vue';
import { echo } from '@laravel/echo-vue';
import { apiFetch } from '@/composables/ApiFetch';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Spinner } from '@/components/ui/spinner';
import InputError from '@/components/InputError.vue';
import {
    X,
    Sparkles,
    ImageIcon,
    Check,
    Plus,
    Trash2,
    Palette,
    Camera,
    Sun,
    User,
    ChevronLeft,
    ChevronRight,
} from 'lucide-vue-next';
import type { Character, Image as ImageType, ApiFetchFn } from './types';

interface Props {
    bookId: string;
    characters: Character[];
}

interface FluxSubject {
    characterId: string | null;
    description: string;
    position: string;
    action: string;
}

interface CameraSettings {
    angle: string;
    lens: string;
    depth_of_field: string;
}

interface FormData {
    scene: string;
    subjects: FluxSubject[];
    style: string;
    color_palette: string[];
    lighting: string;
    mood: string;
    background: string;
    composition: string;
    camera: CameraSettings;
    aspect_ratio: string;
}

const props = defineProps<Props>();
const isOpen = defineModel<boolean>('isOpen');

const emit = defineEmits<{
    (e: 'imageCreated', image: ImageType): void;
}>();

const requestApiFetch = apiFetch as ApiFetchFn;

// Form steps
const currentStep = ref(1);
const totalSteps = 3;

// Form state
const formData = ref<FormData>({
    scene: '',
    subjects: [],
    style: 'Photorealistic, cinematic lighting, highly detailed',
    color_palette: ['#4A90A4', '#2C3E50', '#E8D5B7'],
    lighting: 'Natural dramatic lighting with atmospheric depth',
    mood: 'Engaging and story-driven',
    background: '',
    composition: 'cinematic framing, balanced composition',
    camera: {
        angle: 'dynamic cinematic angle',
        lens: '35mm cinematic lens',
        depth_of_field: 'shallow depth of field, bokeh background',
    },
    aspect_ratio: '16:9',
});

// Color picker state
const showColorPicker = ref(false);
const activeColorIndex = ref<number | null>(null);
const newColorValue = ref('#000000');

// Processing state
const processing = ref(false);
const errors = ref<Record<string, string>>({});

// Echo channel for real-time updates
const echoChannel = ref<any>(null);

// Preset options
const stylePresets = [
    { value: 'Photorealistic, cinematic lighting, highly detailed', label: 'Cinematic' },
    { value: 'Whimsical cartoon illustration, hand-drawn aesthetic, vibrant colors', label: 'Cartoon' },
    { value: 'Oil painting style, rich textures, classical composition', label: 'Painterly' },
    { value: 'Anime style, expressive characters, vivid colors', label: 'Anime' },
    { value: 'Watercolor illustration, soft edges, dreamy atmosphere', label: 'Watercolor' },
];

const lightingPresets = [
    { value: 'Natural dramatic lighting with atmospheric depth', label: 'Dramatic' },
    { value: 'Soft, warm golden hour lighting', label: 'Golden Hour' },
    { value: 'Ethereal magical glow with shimmering highlights', label: 'Magical' },
    { value: 'Moody film noir lighting with deep shadows', label: 'Noir' },
    { value: 'Bright, cheerful daylight', label: 'Daylight' },
];

const moodPresets = [
    { value: 'Engaging and story-driven', label: 'Engaging' },
    { value: 'Mysterious and atmospheric', label: 'Mysterious' },
    { value: 'Exciting and adventurous', label: 'Adventurous' },
    { value: 'Warm and heartwarming', label: 'Heartwarming' },
    { value: 'Magical and enchanting', label: 'Magical' },
];

const cameraAnglePresets = [
    { value: 'dynamic cinematic angle', label: 'Dynamic' },
    { value: 'eye-level shot', label: 'Eye Level' },
    { value: 'low angle for dramatic effect', label: 'Low Angle' },
    { value: 'high angle overview', label: 'High Angle' },
    { value: 'close-up intimate shot', label: 'Close-up' },
];

const aspectRatioPresets = [
    { value: '16:9', label: 'Landscape (16:9)', icon: '🖼️', description: 'Wide cinematic format' },
    { value: '4:3', label: 'Standard (4:3)', icon: '📺', description: 'Classic photo format' },
    { value: '1:1', label: 'Square (1:1)', icon: '⬜', description: 'Instagram-style' },
    { value: '3:4', label: 'Portrait (3:4)', icon: '📱', description: 'Vertical format' },
    { value: '9:16', label: 'Tall (9:16)', icon: '📲', description: 'Story/Reel format' },
];

const positionPresets = [
    'center of frame',
    'left side of frame',
    'right side of frame',
    'foreground',
    'background',
    'mid-ground',
];

// Step info
const stepInfo = computed(() => {
    switch (currentStep.value) {
        case 1:
            return {
                title: 'Scene & Characters',
                description: 'Describe your scene and select characters',
                color: 'from-purple-500 to-indigo-600',
                icon: ImageIcon,
            };
        case 2:
            return {
                title: 'Style & Mood',
                description: 'Choose the visual style and atmosphere',
                color: 'from-amber-500 to-orange-600',
                icon: Palette,
            };
        case 3:
            return {
                title: 'Camera & Composition',
                description: 'Fine-tune the camera and framing',
                color: 'from-emerald-500 to-teal-600',
                icon: Camera,
            };
        default:
            return {
                title: 'Create Image',
                description: 'Generate a custom image',
                color: 'from-purple-500 to-indigo-600',
                icon: ImageIcon,
            };
    }
});

// Computed for character selection
const selectedCharacterIds = computed(() => {
    return formData.value.subjects
        .filter(s => s.characterId)
        .map(s => s.characterId);
});

// Character methods
const isCharacterSelected = (characterId: string): boolean => {
    return formData.value.subjects.some(s => s.characterId === characterId);
};

const toggleCharacter = (character: Character) => {
    const existingIndex = formData.value.subjects.findIndex(
        s => s.characterId === character.id
    );
    
    if (existingIndex !== -1) {
        // Remove the character
        formData.value.subjects.splice(existingIndex, 1);
    } else {
        // Add the character with default description from database
        formData.value.subjects.push({
            characterId: character.id,
            description: character.description || `${character.name}${character.age ? `, ${character.age} years old` : ''}${character.gender ? `, ${character.gender}` : ''}`,
            position: 'center of frame',
            action: 'engaged in the scene',
        });
    }
};

const getSubjectForCharacter = (characterId: string): FluxSubject | undefined => {
    return formData.value.subjects.find(s => s.characterId === characterId);
};

const updateSubjectField = (characterId: string, field: keyof FluxSubject, value: string) => {
    const subject = formData.value.subjects.find(s => s.characterId === characterId);
    if (subject) {
        (subject[field] as string) = value;
    }
};

// Color palette methods
const addColor = () => {
    if (formData.value.color_palette.length < 6) {
        formData.value.color_palette.push('#808080');
    }
};

const removeColor = (index: number) => {
    if (formData.value.color_palette.length > 1) {
        formData.value.color_palette.splice(index, 1);
    }
};

const updateColor = (index: number, color: string) => {
    formData.value.color_palette[index] = color;
};

// Navigation
const canGoNext = computed(() => {
    switch (currentStep.value) {
        case 1:
            return formData.value.scene.trim().length > 0;
        case 2:
            return formData.value.style.trim().length > 0;
        case 3:
            return true;
        default:
            return false;
    }
});

const nextStep = () => {
    if (currentStep.value < totalSteps && canGoNext.value) {
        currentStep.value++;
    }
};

const prevStep = () => {
    if (currentStep.value > 1) {
        currentStep.value--;
    }
};

const goToStep = (step: number) => {
    if (step <= currentStep.value) {
        currentStep.value = step;
    }
};

// Build the Flux 2 JSON prompt
const buildFlux2Prompt = (): string => {
    const subjects = formData.value.subjects.map(s => ({
        description: s.description,
        position: s.position,
        action: s.action,
    }));

    // If no characters selected, add a generic subject
    if (subjects.length === 0) {
        subjects.push({
            description: 'Main subject of the scene',
            position: 'center of frame',
            action: 'as described in scene',
        });
    }

    const promptData = {
        scene: formData.value.scene,
        subjects,
        style: formData.value.style,
        color_palette: formData.value.color_palette,
        lighting: formData.value.lighting,
        mood: formData.value.mood,
        background: formData.value.background || 'Contextual background matching the scene description',
        composition: formData.value.composition,
        camera: formData.value.camera,
    };

    return JSON.stringify(promptData);
};

// Get selected character portrait URLs
const getSelectedCharacterImageUrls = (): string[] => {
    return formData.value.subjects
        .filter(s => s.characterId)
        .map(s => {
            const character = props.characters.find(c => c.id === s.characterId);
            return character?.portrait_image_url;
        })
        .filter((url): url is string => !!url);
};

// Submit
const handleSubmit = async () => {
    processing.value = true;
    errors.value = {};

    try {
        const prompt = buildFlux2Prompt();
        const characterImageUrls = getSelectedCharacterImageUrls();
        
        const payload = {
            prompt,
            character_image_urls: characterImageUrls,
            aspect_ratio: formData.value.aspect_ratio,
        };

        const { data, error } = await requestApiFetch(
            `/api/books/${props.bookId}/images/custom`,
            'POST',
            payload
        );

        if (error) {
            const errorMessage = typeof error === 'object' && error && 'message' in error
                ? String((error as { message: unknown }).message)
                : 'Failed to create image. Please try again.';
            errors.value = { general: errorMessage };
            processing.value = false;
            return;
        }

        if (data) {
            const image = data as { image: ImageType };
            emit('imageCreated', image.image);
            resetForm();
            isOpen.value = false;
        }
    } catch (err) {
        errors.value = { general: 'An unexpected error occurred. Please try again.' };
    } finally {
        processing.value = false;
    }
};

// Reset form
const resetForm = () => {
    currentStep.value = 1;
    formData.value = {
        scene: '',
        subjects: [],
        style: 'Photorealistic, cinematic lighting, highly detailed',
        color_palette: ['#4A90A4', '#2C3E50', '#E8D5B7'],
        lighting: 'Natural dramatic lighting with atmospheric depth',
        mood: 'Engaging and story-driven',
        background: '',
        composition: 'cinematic framing, balanced composition',
        camera: {
            angle: 'dynamic cinematic angle',
            lens: '35mm cinematic lens',
            depth_of_field: 'shallow depth of field, bokeh background',
        },
        aspect_ratio: '16:9',
    };
    errors.value = {};
};

// Handle dialog open/close
const handleOpenChange = (open: boolean) => {
    if (open) {
        resetForm();
    }
    isOpen.value = open;
};

// Close confirmation
const showCloseConfirm = ref(false);

const requestClose = () => {
    const hasData = formData.value.scene.trim() || formData.value.subjects.length > 0;
    if (hasData && !processing.value) {
        showCloseConfirm.value = true;
    } else {
        isOpen.value = false;
    }
};

const confirmClose = () => {
    showCloseConfirm.value = false;
    resetForm();
    isOpen.value = false;
};

const cancelClose = () => {
    showCloseConfirm.value = false;
};

// Cleanup on unmount
onUnmounted(() => {
    if (echoChannel.value) {
        try {
            echoChannel.value = null;
        } catch {
            // Ignore cleanup errors
        }
    }
});
</script>

<template>
    <Dialog :open="isOpen" @update:open="handleOpenChange">
        <DialogContent
            class="create-image-modal-content theme-reset z-10000 max-w-2xl overflow-visible rounded-3xl border-2 border-gray-200 bg-white p-0 sm:max-w-2xl dark:border-gray-700 dark:bg-gray-900 [&>button[data-slot]]:hidden"
            :class="processing ? 'pointer-events-none' : ''"
        >
            <!-- Custom Close Button -->
            <button
                type="button"
                @click="requestClose"
                :disabled="processing"
                class="absolute -top-2 -right-2 z-60 flex h-10 w-10 cursor-pointer items-center justify-center rounded-full border-2 border-white bg-gray-800 text-white shadow-xl transition-all duration-200 hover:scale-110 hover:bg-gray-700 hover:shadow-2xl active:scale-95 sm:-top-3 sm:-right-3 sm:h-12 sm:w-12"
            >
                <X class="h-5 w-5 sm:h-6 sm:w-6" />
            </button>

            <!-- Close Confirmation Modal -->
            <Transition
                enter-active-class="transition-all duration-300 ease-out"
                enter-from-class="opacity-0 scale-75"
                enter-to-class="opacity-100 scale-100"
                leave-active-class="transition-all duration-200 ease-in"
                leave-from-class="opacity-100 scale-100"
                leave-to-class="opacity-0 scale-75"
            >
                <div
                    v-if="showCloseConfirm"
                    class="absolute inset-0 z-70 flex items-center justify-center rounded-3xl bg-black/50 backdrop-blur-sm"
                >
                    <div class="animate-bounce-in mx-4 w-full max-w-sm rounded-2xl border-2 border-orange-200 bg-white p-6 shadow-2xl dark:border-orange-800 dark:bg-gray-900">
                        <div class="mb-4 text-center">
                            <div class="mx-auto mb-3 flex h-16 w-16 items-center justify-center rounded-full bg-orange-100 dark:bg-orange-900/30">
                                <span class="text-3xl">🎨</span>
                            </div>
                            <h3 class="text-xl font-bold text-gray-900 dark:text-white">
                                Discard your image?
                            </h3>
                            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                                Your image settings will be lost!
                            </p>
                        </div>
                        <div class="flex gap-3">
                            <Button
                                type="button"
                                variant="outline"
                                @click="cancelClose"
                                class="flex-1 cursor-pointer rounded-xl border-gray-200 text-gray-900 transition-all duration-200 hover:scale-[1.02] active:scale-[0.98] dark:border-gray-700 dark:text-white"
                            >
                                Keep Editing ✨
                            </Button>
                            <Button
                                type="button"
                                @click="confirmClose"
                                class="flex-1 cursor-pointer rounded-xl bg-linear-to-r from-red-500 to-rose-500 text-white transition-all duration-200 hover:scale-[1.02] hover:from-red-600 hover:to-rose-600 active:scale-[0.98]"
                            >
                                Discard
                            </Button>
                        </div>
                    </div>
                </div>
            </Transition>

            <!-- Content wrapper -->
            <div class="overflow-hidden rounded-3xl bg-white dark:bg-gray-900">
                <!-- Animated Header -->
                <div
                    class="relative overflow-hidden bg-linear-to-r px-6 py-8 text-white transition-all duration-500"
                    :class="stepInfo.color"
                >
                    <!-- Floating decorative elements -->
                    <div class="absolute -top-4 -right-4 h-24 w-24 rounded-full bg-white/10 blur-2xl" />
                    <div class="absolute -bottom-8 -left-8 h-32 w-32 rounded-full bg-white/10 blur-3xl" />

                    <DialogHeader class="relative z-10">
                        <div class="mb-3 flex items-center gap-4">
                            <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-white/20 backdrop-blur-sm">
                                <component :is="stepInfo.icon" class="h-8 w-8" />
                            </div>
                            <DialogTitle class="flex-1 text-2xl font-bold tracking-tight sm:text-3xl">
                                {{ stepInfo.title }}
                            </DialogTitle>
                            <!-- Step indicator dots -->
                            <div class="flex shrink-0 gap-2">
                                <button
                                    v-for="step in totalSteps"
                                    :key="step"
                                    type="button"
                                    @click="goToStep(step)"
                                    class="h-3 w-3 rounded-full transition-all duration-300"
                                    :class="[
                                        step === currentStep
                                            ? 'scale-125 bg-white shadow-lg'
                                            : step < currentStep
                                              ? 'cursor-pointer bg-white/80 hover:scale-110 hover:bg-white'
                                              : 'cursor-not-allowed bg-white/30',
                                    ]"
                                    :disabled="step > currentStep"
                                />
                            </div>
                        </div>
                        <DialogDescription class="hidden text-base text-white/90 sm:block">
                            {{ stepInfo.description }}
                        </DialogDescription>
                    </DialogHeader>
                </div>

                <!-- Form Content -->
                <div class="max-h-[60vh] overflow-y-auto bg-white px-6 py-6 text-gray-900 dark:bg-gray-900 dark:text-white">
                    <form @submit.prevent="currentStep === totalSteps ? handleSubmit() : nextStep()">
                        <!-- Step 1: Scene & Characters -->
                        <div v-show="currentStep === 1" class="space-y-6">
                            <!-- Scene Description -->
                            <div class="space-y-3">
                                <Label for="scene" class="text-lg font-semibold text-gray-900 dark:text-white">
                                    Describe your scene 🎬
                                </Label>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    What's happening in this image? Be descriptive!
                                </p>
                                <Textarea
                                    id="scene"
                                    v-model="formData.scene"
                                    placeholder="Example: A dramatic sunset over the ocean, with waves crashing against ancient rocks..."
                                    rows="4"
                                    :disabled="processing"
                                    class="resize-none rounded-2xl border-2 border-gray-200 bg-white text-base leading-relaxed text-gray-900 focus:ring-2 focus:ring-purple-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                                />
                                <InputError :message="errors.scene" />
                            </div>

                            <!-- Background -->
                            <div class="space-y-3">
                                <Label for="background" class="text-base font-semibold text-gray-900 dark:text-white">
                                    Background Details (optional)
                                </Label>
                                <Input
                                    id="background"
                                    v-model="formData.background"
                                    placeholder="e.g., Ancient forest, misty mountains in the distance"
                                    :disabled="processing"
                                    class="rounded-xl border-2 border-gray-200 dark:border-gray-700"
                                />
                            </div>

                            <!-- Character Selection -->
                            <div class="space-y-3">
                                <Label class="text-lg font-semibold text-gray-900 dark:text-white">
                                    Select Characters 👥
                                </Label>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    Choose which characters appear in this scene
                                </p>
                                
                                <div v-if="characters.length > 0" class="grid grid-cols-2 gap-3">
                                    <button
                                        v-for="character in characters"
                                        :key="character.id"
                                        type="button"
                                        @click="toggleCharacter(character)"
                                        :disabled="processing"
                                        class="group relative flex cursor-pointer items-center gap-3 rounded-2xl border-2 p-3 text-left transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md active:scale-[0.98]"
                                        :class="isCharacterSelected(character.id)
                                            ? 'border-purple-500 bg-purple-50 ring-2 ring-purple-500/20 dark:bg-purple-950/30'
                                            : 'border-gray-200 hover:border-purple-400/50 hover:bg-purple-50 dark:border-gray-700 dark:hover:bg-purple-950/20'"
                                    >
                                        <!-- Character Avatar -->
                                        <div class="h-12 w-12 shrink-0 overflow-hidden rounded-xl bg-gray-100 dark:bg-gray-800">
                                            <img
                                                v-if="character.portrait_image_url"
                                                :src="character.portrait_image_url"
                                                :alt="character.name"
                                                class="h-full w-full object-cover"
                                            />
                                            <div v-else class="flex h-full w-full items-center justify-center">
                                                <User class="h-6 w-6 text-gray-400" />
                                            </div>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="font-semibold text-gray-900 dark:text-white truncate">
                                                {{ character.name }}
                                            </p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
                                                {{ character.description || 'No description' }}
                                            </p>
                                        </div>
                                        <!-- Selected indicator -->
                                        <div
                                            v-if="isCharacterSelected(character.id)"
                                            class="absolute top-2 right-2 flex h-6 w-6 items-center justify-center rounded-full bg-purple-500 text-white"
                                        >
                                            <Check class="h-4 w-4" />
                                        </div>
                                    </button>
                                </div>
                                
                                <div v-else class="rounded-xl bg-gray-100 p-4 text-center dark:bg-gray-800">
                                    <User class="mx-auto h-8 w-8 text-gray-400" />
                                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                        No characters in this book yet
                                    </p>
                                </div>
                            </div>

                            <!-- Selected Character Details -->
                            <div v-if="formData.subjects.length > 0" class="space-y-4">
                                <Label class="text-base font-semibold text-gray-900 dark:text-white">
                                    Character Details
                                </Label>
                                
                                <div
                                    v-for="subject in formData.subjects.filter(s => s.characterId)"
                                    :key="subject.characterId"
                                    class="rounded-2xl border-2 border-purple-200 bg-purple-50/50 p-4 dark:border-purple-800 dark:bg-purple-950/20"
                                >
                                    <p class="mb-3 font-semibold text-purple-800 dark:text-purple-200">
                                        {{ characters.find(c => c.id === subject.characterId)?.name }}
                                    </p>
                                    
                                    <div class="grid gap-3">
                                        <div>
                                            <Label class="text-sm text-gray-600 dark:text-gray-400">Description</Label>
                                            <Textarea
                                                :model-value="subject.description"
                                                @update:model-value="updateSubjectField(subject.characterId!, 'description', $event)"
                                                rows="2"
                                                placeholder="How they appear in this scene..."
                                                :disabled="processing"
                                                class="mt-1 resize-none rounded-xl border-gray-200 text-sm dark:border-gray-700"
                                            />
                                        </div>
                                        
                                        <div class="grid grid-cols-2 gap-3">
                                            <div>
                                                <Label class="text-sm text-gray-600 dark:text-gray-400">Position</Label>
                                                <select
                                                    :value="subject.position"
                                                    @change="updateSubjectField(subject.characterId!, 'position', ($event.target as HTMLSelectElement).value)"
                                                    :disabled="processing"
                                                    class="mt-1 w-full rounded-xl border-2 border-gray-200 bg-white px-3 py-2 text-sm focus:ring-2 focus:ring-purple-500/20 dark:border-gray-700 dark:bg-gray-900"
                                                >
                                                    <option v-for="pos in positionPresets" :key="pos" :value="pos">
                                                        {{ pos }}
                                                    </option>
                                                </select>
                                            </div>
                                            <div>
                                                <Label class="text-sm text-gray-600 dark:text-gray-400">Action</Label>
                                                <Input
                                                    :model-value="subject.action"
                                                    @update:model-value="updateSubjectField(subject.characterId!, 'action', $event)"
                                                    placeholder="What they're doing..."
                                                    :disabled="processing"
                                                    class="mt-1 rounded-xl border-gray-200 text-sm dark:border-gray-700"
                                                />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Step 2: Style & Mood -->
                        <div v-show="currentStep === 2" class="space-y-6">
                            <!-- Style Selection -->
                            <div class="space-y-3">
                                <Label class="text-lg font-semibold text-gray-900 dark:text-white">
                                    Visual Style 🎨
                                </Label>
                                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                                    <button
                                        v-for="preset in stylePresets"
                                        :key="preset.value"
                                        type="button"
                                        @click="formData.style = preset.value"
                                        :disabled="processing"
                                        class="relative cursor-pointer rounded-xl border-2 p-3 text-center transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md active:scale-95"
                                        :class="formData.style === preset.value
                                            ? 'border-amber-500 bg-amber-50 ring-2 ring-amber-500/20 dark:bg-amber-950/30'
                                            : 'border-gray-200 hover:border-amber-400/50 hover:bg-amber-50 dark:border-gray-700 dark:hover:bg-amber-950/20'"
                                    >
                                        <span class="text-sm font-medium text-gray-900 dark:text-white">{{ preset.label }}</span>
                                        <div
                                            v-if="formData.style === preset.value"
                                            class="absolute top-1 right-1 flex h-5 w-5 items-center justify-center rounded-full bg-amber-500 text-white"
                                        >
                                            <Check class="h-3 w-3" />
                                        </div>
                                    </button>
                                </div>
                                <Textarea
                                    v-model="formData.style"
                                    placeholder="Or describe your own style..."
                                    rows="2"
                                    :disabled="processing"
                                    class="resize-none rounded-xl border-gray-200 text-sm dark:border-gray-700"
                                />
                            </div>

                            <!-- Color Palette -->
                            <div class="space-y-3">
                                <Label class="text-lg font-semibold text-gray-900 dark:text-white">
                                    Color Palette 🌈
                                </Label>
                                <div class="flex flex-wrap items-center gap-3">
                                    <div
                                        v-for="(color, index) in formData.color_palette"
                                        :key="index"
                                        class="group relative"
                                    >
                                        <div class="relative">
                                            <input
                                                type="color"
                                                :value="color"
                                                @input="updateColor(index, ($event.target as HTMLInputElement).value)"
                                                :disabled="processing"
                                                class="h-12 w-12 cursor-pointer rounded-xl border-2 border-gray-200 p-1 dark:border-gray-700"
                                            />
                                            <button
                                                v-if="formData.color_palette.length > 1"
                                                type="button"
                                                @click="removeColor(index)"
                                                class="absolute -top-2 -right-2 flex h-5 w-5 cursor-pointer items-center justify-center rounded-full bg-red-500 text-white opacity-0 transition-opacity group-hover:opacity-100"
                                            >
                                                <X class="h-3 w-3" />
                                            </button>
                                        </div>
                                        <p class="mt-1 text-center text-xs text-gray-500 uppercase">{{ color }}</p>
                                    </div>
                                    <button
                                        v-if="formData.color_palette.length < 6"
                                        type="button"
                                        @click="addColor"
                                        :disabled="processing"
                                        class="flex h-12 w-12 cursor-pointer items-center justify-center rounded-xl border-2 border-dashed border-gray-300 text-gray-400 transition-all hover:border-amber-400 hover:text-amber-500 dark:border-gray-600"
                                    >
                                        <Plus class="h-6 w-6" />
                                    </button>
                                </div>
                            </div>

                            <!-- Lighting -->
                            <div class="space-y-3">
                                <Label class="text-base font-semibold text-gray-900 dark:text-white">
                                    <Sun class="inline h-4 w-4 mr-1" /> Lighting
                                </Label>
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        v-for="preset in lightingPresets"
                                        :key="preset.value"
                                        type="button"
                                        @click="formData.lighting = preset.value"
                                        :disabled="processing"
                                        class="cursor-pointer rounded-full px-3 py-1.5 text-sm font-medium transition-all"
                                        :class="formData.lighting === preset.value
                                            ? 'bg-amber-500 text-white'
                                            : 'bg-gray-100 text-gray-700 hover:bg-amber-100 dark:bg-gray-800 dark:text-gray-300'"
                                    >
                                        {{ preset.label }}
                                    </button>
                                </div>
                                <Input
                                    v-model="formData.lighting"
                                    placeholder="Or describe custom lighting..."
                                    :disabled="processing"
                                    class="rounded-xl border-gray-200 text-sm dark:border-gray-700"
                                />
                            </div>

                            <!-- Mood -->
                            <div class="space-y-3">
                                <Label class="text-base font-semibold text-gray-900 dark:text-white">
                                    Mood & Atmosphere
                                </Label>
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        v-for="preset in moodPresets"
                                        :key="preset.value"
                                        type="button"
                                        @click="formData.mood = preset.value"
                                        :disabled="processing"
                                        class="cursor-pointer rounded-full px-3 py-1.5 text-sm font-medium transition-all"
                                        :class="formData.mood === preset.value
                                            ? 'bg-amber-500 text-white'
                                            : 'bg-gray-100 text-gray-700 hover:bg-amber-100 dark:bg-gray-800 dark:text-gray-300'"
                                    >
                                        {{ preset.label }}
                                    </button>
                                </div>
                                <Input
                                    v-model="formData.mood"
                                    placeholder="Or describe custom mood..."
                                    :disabled="processing"
                                    class="rounded-xl border-gray-200 text-sm dark:border-gray-700"
                                />
                            </div>
                        </div>

                        <!-- Step 3: Camera & Composition -->
                        <div v-show="currentStep === 3" class="space-y-6">
                            <!-- Aspect Ratio -->
                            <div class="space-y-3">
                                <Label class="text-lg font-semibold text-gray-900 dark:text-white">
                                    Image Ratio 📐
                                </Label>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    Choose the shape and orientation of your image
                                </p>
                                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                                    <button
                                        v-for="preset in aspectRatioPresets"
                                        :key="preset.value"
                                        type="button"
                                        @click="formData.aspect_ratio = preset.value"
                                        :disabled="processing"
                                        class="group relative cursor-pointer rounded-xl border-2 p-3 text-center transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md active:scale-95"
                                        :class="formData.aspect_ratio === preset.value
                                            ? 'border-emerald-500 bg-emerald-50 ring-2 ring-emerald-500/20 dark:bg-emerald-950/30'
                                            : 'border-gray-200 hover:border-emerald-400/50 hover:bg-emerald-50 dark:border-gray-700 dark:hover:bg-emerald-950/20'"
                                    >
                                        <span class="text-2xl">{{ preset.icon }}</span>
                                        <p class="mt-1 text-sm font-medium text-gray-900 dark:text-white">{{ preset.label }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ preset.description }}</p>
                                        <div
                                            v-if="formData.aspect_ratio === preset.value"
                                            class="absolute top-1 right-1 flex h-5 w-5 items-center justify-center rounded-full bg-emerald-500 text-white"
                                        >
                                            <Check class="h-3 w-3" />
                                        </div>
                                    </button>
                                </div>
                            </div>

                            <!-- Camera Angle -->
                            <div class="space-y-3">
                                <Label class="text-lg font-semibold text-gray-900 dark:text-white">
                                    Camera Angle 📷
                                </Label>
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        v-for="preset in cameraAnglePresets"
                                        :key="preset.value"
                                        type="button"
                                        @click="formData.camera.angle = preset.value"
                                        :disabled="processing"
                                        class="cursor-pointer rounded-full px-3 py-1.5 text-sm font-medium transition-all"
                                        :class="formData.camera.angle === preset.value
                                            ? 'bg-emerald-500 text-white'
                                            : 'bg-gray-100 text-gray-700 hover:bg-emerald-100 dark:bg-gray-800 dark:text-gray-300'"
                                    >
                                        {{ preset.label }}
                                    </button>
                                </div>
                                <Input
                                    v-model="formData.camera.angle"
                                    placeholder="Or describe custom camera angle..."
                                    :disabled="processing"
                                    class="rounded-xl border-gray-200 text-sm dark:border-gray-700"
                                />
                            </div>

                            <!-- Lens -->
                            <div class="space-y-3">
                                <Label class="text-base font-semibold text-gray-900 dark:text-white">
                                    Lens Type
                                </Label>
                                <Input
                                    v-model="formData.camera.lens"
                                    placeholder="e.g., 35mm cinematic lens, 85mm portrait lens"
                                    :disabled="processing"
                                    class="rounded-xl border-gray-200 dark:border-gray-700"
                                />
                            </div>

                            <!-- Depth of Field -->
                            <div class="space-y-3">
                                <Label class="text-base font-semibold text-gray-900 dark:text-white">
                                    Depth of Field
                                </Label>
                                <Input
                                    v-model="formData.camera.depth_of_field"
                                    placeholder="e.g., shallow depth of field, bokeh background"
                                    :disabled="processing"
                                    class="rounded-xl border-gray-200 dark:border-gray-700"
                                />
                            </div>

                            <!-- Composition -->
                            <div class="space-y-3">
                                <Label class="text-base font-semibold text-gray-900 dark:text-white">
                                    Composition & Framing
                                </Label>
                                <Textarea
                                    v-model="formData.composition"
                                    placeholder="e.g., 16:9 landscape, rule of thirds, centered subject"
                                    rows="2"
                                    :disabled="processing"
                                    class="resize-none rounded-xl border-gray-200 text-sm dark:border-gray-700"
                                />
                            </div>

                            <!-- Preview Summary -->
                            <div class="rounded-2xl border-2 border-emerald-200 bg-emerald-50/50 p-4 dark:border-emerald-800 dark:bg-emerald-950/20">
                                <h4 class="mb-2 font-semibold text-emerald-800 dark:text-emerald-200">
                                    <Sparkles class="inline h-4 w-4 mr-1" /> Image Summary
                                </h4>
                                <ul class="space-y-1 text-sm text-emerald-700 dark:text-emerald-300">
                                    <li><strong>Scene:</strong> {{ formData.scene || 'Not set' }}</li>
                                    <li><strong>Characters:</strong> {{ formData.subjects.length }} selected</li>
                                    <li><strong>Style:</strong> {{ stylePresets.find(s => s.value === formData.style)?.label || 'Custom' }}</li>
                                    <li><strong>Ratio:</strong> {{ aspectRatioPresets.find(a => a.value === formData.aspect_ratio)?.label || formData.aspect_ratio }}</li>
                                    <li><strong>Mood:</strong> {{ moodPresets.find(m => m.value === formData.mood)?.label || formData.mood }}</li>
                                </ul>
                            </div>

                            <!-- General Error -->
                            <InputError :message="errors.general" />
                        </div>

                        <!-- Navigation Buttons -->
                        <div class="mt-8 flex items-center justify-between">
                            <Button
                                v-if="currentStep > 1"
                                type="button"
                                variant="outline"
                                @click="prevStep"
                                :disabled="processing"
                                class="cursor-pointer rounded-xl border-gray-200 px-6 py-3 text-gray-900 transition-all duration-200 hover:scale-[1.02] active:scale-[0.98] dark:border-gray-700 dark:text-white"
                            >
                                <ChevronLeft class="mr-2 h-4 w-4" />
                                Back
                            </Button>
                            <div v-else />

                            <Button
                                type="submit"
                                :disabled="processing || !canGoNext"
                                class="cursor-pointer rounded-xl bg-linear-to-r px-8 py-3 text-white shadow-lg transition-all duration-200 hover:scale-[1.02] hover:shadow-xl active:scale-[0.98] disabled:opacity-50"
                                :class="stepInfo.color"
                            >
                                <Spinner v-if="processing" class="mr-2 h-4 w-4" />
                                <template v-else-if="currentStep === totalSteps">
                                    <Sparkles class="mr-2 h-4 w-4" />
                                    Generate Image
                                </template>
                                <template v-else>
                                    Next
                                    <ChevronRight class="ml-2 h-4 w-4" />
                                </template>
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>

<style>
/* Ensure the CreateImageModal appears above the BookViewModal (z-9998) */
/* Using non-scoped styles because DialogPortal renders outside the component tree */
/* Target the overlay that is a sibling of our high z-index content */
[data-slot="dialog-overlay"]:has(+ .create-image-modal-content) {
    z-index: 9999 !important;
}

.create-image-modal-content {
    z-index: 10000 !important;
}
</style>
