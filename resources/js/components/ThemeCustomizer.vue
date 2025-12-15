<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useTheme } from '@/composables/useTheme';
import type { ProfileTheme } from '@/types';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Palette, Check, Trash2, Plus, Edit2, X, Sparkles, Image, Wand2, Loader2 } from 'lucide-vue-next';
import axios from 'axios';
import { router } from '@inertiajs/vue3';

const { activeTheme, themes, backgroundImage, previewTheme, previewBackgroundImage, clearPreview } = useTheme();

const isDropdownOpen = ref(false);
const isCreateDialogOpen = ref(false);
const isEditDialogOpen = ref(false);
const isBackgroundDialogOpen = ref(false);
const editingTheme = ref<ProfileTheme | null>(null);
const isSaving = ref(false);
const isDeleting = ref(false);
const isGeneratingImage = ref(false);
const generatedImageUrl = ref<string | null>(null);
const generationError = ref<string | null>(null);

// Form state
const themeName = ref('');
const backgroundColor = ref('#1a1a2e');
const textColor = ref('#eaeaea');
const backgroundDescription = ref('');

// Preset colors for quick selection
const presetBackgrounds = [
    { name: 'Midnight', color: '#1a1a2e' },
    { name: 'Forest', color: '#1e3a29' },
    { name: 'Ocean', color: '#0a2647' },
    { name: 'Sunset', color: '#3d1e1e' },
    { name: 'Lavender', color: '#2d2640' },
    { name: 'Snow', color: '#f5f5f5' },
    { name: 'Cream', color: '#faf3e0' },
    { name: 'Mint', color: '#e8f5e9' },
];

const presetTextColors = [
    { name: 'Light', color: '#eaeaea' },
    { name: 'White', color: '#ffffff' },
    { name: 'Warm', color: '#f5f0e1' },
    { name: 'Dark', color: '#1a1a1a' },
    { name: 'Charcoal', color: '#2d2d2d' },
    { name: 'Navy', color: '#1e3a5f' },
];

// Example prompts for background images
const examplePrompts = [
    'Dramatic mountain peaks at golden hour with snow caps and alpine meadows stretching into the distance',
    'A lush enchanted forest with towering ancient trees, rays of sunlight breaking through the canopy',
    'Northern lights dancing over a frozen lake with snow-covered pine trees',
    'Vast ocean horizon at sunset with dramatic cloud formations in orange and purple',
    'Rolling hills of lavender fields under a clear blue sky with distant mountains',
    'Japanese cherry blossom trees along a peaceful river with traditional bridges',
];

// Watch for color changes to preview
watch([backgroundColor, textColor], ([bg, text]) => {
    if (isCreateDialogOpen.value || isEditDialogOpen.value) {
        previewTheme(bg, text);
    }
});

const resetForm = () => {
    themeName.value = '';
    backgroundColor.value = '#1a1a2e';
    textColor.value = '#eaeaea';
    editingTheme.value = null;
};

const resetBackgroundForm = () => {
    backgroundDescription.value = '';
    generatedImageUrl.value = null;
    generationError.value = null;
};

const openCreateDialog = () => {
    resetForm();
    isCreateDialogOpen.value = true;
};

const openEditDialog = (theme: ProfileTheme) => {
    editingTheme.value = theme;
    themeName.value = theme.name;
    backgroundColor.value = theme.background_color;
    textColor.value = theme.text_color;
    isEditDialogOpen.value = true;
};

const openBackgroundDialog = () => {
    resetBackgroundForm();
    isBackgroundDialogOpen.value = true;
};

const closeDialogs = () => {
    isCreateDialogOpen.value = false;
    isEditDialogOpen.value = false;
    isBackgroundDialogOpen.value = false;
    clearPreview();
    resetForm();
    resetBackgroundForm();
};

const handleSaveTheme = async () => {
    if (!themeName.value.trim()) return;

    isSaving.value = true;
    try {
        if (editingTheme.value) {
            // Update existing theme
            await axios.patch(`/themes/${editingTheme.value.id}`, {
                name: themeName.value,
                background_color: backgroundColor.value,
                text_color: textColor.value,
            });
        } else {
            // Create new theme
            await axios.post('/themes', {
                name: themeName.value,
                background_color: backgroundColor.value,
                text_color: textColor.value,
            });
        }
        
        // Refresh the page to get updated profile data
        router.reload({ only: ['auth'] });
        closeDialogs();
    } catch (error) {
        console.error('Failed to save theme:', error);
    } finally {
        isSaving.value = false;
    }
};

const handleSetActiveTheme = async (themeId: string | null) => {
    try {
        await axios.post('/themes/active', { theme_id: themeId });
        router.reload({ only: ['auth'] });
    } catch (error) {
        console.error('Failed to set active theme:', error);
    }
};

const handleDeleteTheme = async (themeId: string) => {
    isDeleting.value = true;
    try {
        await axios.delete(`/themes/${themeId}`);
        router.reload({ only: ['auth'] });
    } catch (error) {
        console.error('Failed to delete theme:', error);
    } finally {
        isDeleting.value = false;
    }
};

const handleGenerateBackground = async () => {
    if (!backgroundDescription.value.trim() || backgroundDescription.value.length < 10) {
        generationError.value = 'Please provide a description of at least 10 characters.';
        return;
    }

    isGeneratingImage.value = true;
    generationError.value = null;
    generatedImageUrl.value = null;

    try {
        const response = await axios.post('/themes/generate-background', {
            description: backgroundDescription.value,
        });

        if (response.data.success) {
            generatedImageUrl.value = response.data.background_image;
            // Preview the generated image
            previewBackgroundImage(response.data.background_image);
        } else {
            generationError.value = response.data.error || 'Failed to generate image.';
        }
    } catch (error: any) {
        console.error('Failed to generate background:', error);
        generationError.value = error.response?.data?.error || 'An error occurred while generating the image.';
    } finally {
        isGeneratingImage.value = false;
    }
};

const handleSaveBackgroundImage = async () => {
    if (!generatedImageUrl.value) return;

    isSaving.value = true;
    try {
        await axios.post('/themes/background-image', {
            background_image: generatedImageUrl.value,
        });
        
        router.reload({ only: ['auth'] });
        closeDialogs();
    } catch (error) {
        console.error('Failed to save background image:', error);
    } finally {
        isSaving.value = false;
    }
};

const handleRemoveBackgroundImage = async () => {
    isSaving.value = true;
    try {
        await axios.post('/themes/background-image', {
            background_image: null,
        });
        
        router.reload({ only: ['auth'] });
    } catch (error) {
        console.error('Failed to remove background image:', error);
    } finally {
        isSaving.value = false;
    }
};

const useExamplePrompt = (prompt: string) => {
    backgroundDescription.value = prompt;
};

const contrastPreview = computed(() => {
    return {
        background: backgroundColor.value,
        color: textColor.value,
    };
});
</script>

<template>
    <DropdownMenu v-model:open="isDropdownOpen">
        <DropdownMenuTrigger as-child>
            <button
                class="theme-button group relative flex h-10 w-10 cursor-pointer items-center justify-center rounded-full border border-border/50 bg-background/80 shadow-sm backdrop-blur transition-all duration-300 hover:border-violet-400/50 hover:shadow-[0_0_20px_rgba(167,139,250,0.3)] focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                aria-label="Customize theme"
            >
                <!-- Rainbow gradient ring on hover -->
                <span class="absolute inset-0 rounded-full bg-gradient-to-r from-red-500 via-yellow-500 via-green-500 via-blue-500 to-purple-500 opacity-0 blur-sm transition-opacity duration-300 group-hover:opacity-50" />
                
                <!-- Color wheel icon with gradient -->
                <Palette class="relative z-10 h-5 w-5 text-foreground/70 transition-transform duration-300 group-hover:scale-110 group-hover:text-foreground" />
                
                <!-- Active theme indicator dot -->
                <span
                    v-if="activeTheme"
                    class="absolute -bottom-0.5 -right-0.5 h-3 w-3 rounded-full border-2 border-background"
                    :style="{ backgroundColor: activeTheme.background_color }"
                />
            </button>
        </DropdownMenuTrigger>

        <DropdownMenuContent class="w-72" align="end" :side-offset="8">
            <!-- Header -->
            <DropdownMenuLabel class="flex items-center gap-2 font-semibold">
                <Sparkles class="h-4 w-4 text-violet-500" />
                Theme Customizer
            </DropdownMenuLabel>
            
            <DropdownMenuSeparator />

            <!-- Current Theme Status -->
            <div class="px-2 py-2">
                <p class="text-xs text-muted-foreground">
                    {{ activeTheme ? `Active: ${activeTheme.name}` : 'Using default theme' }}
                </p>
                <p v-if="backgroundImage" class="text-xs text-muted-foreground mt-1">
                    ✓ Custom background image active
                </p>
            </div>

            <!-- Reset to Default -->
            <DropdownMenuItem
                v-if="activeTheme"
                class="cursor-pointer gap-2"
                @click="handleSetActiveTheme(null)"
            >
                <X class="h-4 w-4" />
                Reset to Default Theme
            </DropdownMenuItem>

            <!-- Remove Background Image -->
            <DropdownMenuItem
                v-if="backgroundImage"
                class="cursor-pointer gap-2"
                @click="handleRemoveBackgroundImage"
            >
                <X class="h-4 w-4" />
                Remove Background Image
            </DropdownMenuItem>

            <DropdownMenuSeparator v-if="themes && themes.length > 0" />

            <!-- Saved Themes -->
            <DropdownMenuLabel v-if="themes && themes.length > 0" class="text-xs font-normal text-muted-foreground">
                Your Themes
            </DropdownMenuLabel>

            <DropdownMenuGroup v-if="themes && themes.length > 0">
                <DropdownMenuItem
                    v-for="theme in themes"
                    :key="theme.id"
                    class="group/item cursor-pointer gap-3 py-2"
                    @click="handleSetActiveTheme(theme.id)"
                >
                    <!-- Color preview swatch -->
                    <div
                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md border border-border shadow-sm"
                        :style="{
                            backgroundColor: theme.background_color,
                            color: theme.text_color,
                        }"
                    >
                        <span class="text-xs font-bold">Aa</span>
                    </div>
                    
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium truncate">{{ theme.name }}</p>
                        <p class="text-xs text-muted-foreground">
                            {{ theme.background_color }} / {{ theme.text_color }}
                        </p>
                    </div>
                    
                    <!-- Active indicator -->
                    <Check
                        v-if="activeTheme?.id === theme.id"
                        class="h-4 w-4 text-violet-500 shrink-0"
                    />
                    
                    <!-- Edit & Delete buttons (show on hover) -->
                    <div class="hidden gap-1 group-hover/item:flex">
                        <button
                            class="rounded p-1 hover:bg-accent"
                            @click.stop="openEditDialog(theme)"
                        >
                            <Edit2 class="h-3 w-3" />
                        </button>
                        <button
                            class="rounded p-1 text-destructive hover:bg-destructive/10"
                            @click.stop="handleDeleteTheme(theme.id)"
                            :disabled="isDeleting"
                        >
                            <Trash2 class="h-3 w-3" />
                        </button>
                    </div>
                </DropdownMenuItem>
            </DropdownMenuGroup>

            <DropdownMenuSeparator />

            <!-- Create New Theme -->
            <DropdownMenuItem class="cursor-pointer gap-2" @click="openCreateDialog">
                <Plus class="h-4 w-4" />
                Create New Theme
            </DropdownMenuItem>

            <!-- Generate Background Image -->
            <DropdownMenuItem class="cursor-pointer gap-2" @click="openBackgroundDialog">
                <Image class="h-4 w-4" />
                Generate Background Image
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>

    <!-- Create Theme Dialog -->
    <Dialog v-model:open="isCreateDialogOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <Palette class="h-5 w-5 text-violet-500" />
                    Create New Theme
                </DialogTitle>
                <DialogDescription>
                    Design your own custom color scheme for your reading experience.
                </DialogDescription>
            </DialogHeader>

            <div class="grid gap-6 py-4">
                <!-- Theme Name -->
                <div class="grid gap-2">
                    <Label for="theme-name">Theme Name</Label>
                    <Input
                        id="theme-name"
                        v-model="themeName"
                        placeholder="My Custom Theme"
                        maxlength="50"
                    />
                </div>

                <!-- Live Preview -->
                <div class="grid gap-2">
                    <Label>Preview</Label>
                    <div
                        class="rounded-lg border p-4 transition-colors duration-200"
                        :style="contrastPreview"
                    >
                        <p class="text-lg font-semibold">Sample Text</p>
                        <p class="text-sm opacity-80">
                            This is how your stories will look with this theme applied.
                        </p>
                    </div>
                </div>

                <!-- Background Color -->
                <div class="grid gap-2">
                    <Label>Background Color</Label>
                    <div class="flex gap-2">
                        <input
                            type="color"
                            v-model="backgroundColor"
                            class="h-10 w-16 cursor-pointer rounded border border-border bg-transparent"
                        />
                        <Input
                            v-model="backgroundColor"
                            placeholder="#1a1a2e"
                            class="flex-1 font-mono text-sm"
                        />
                    </div>
                    <!-- Preset backgrounds -->
                    <div class="flex flex-wrap gap-1.5">
                        <button
                            v-for="preset in presetBackgrounds"
                            :key="preset.color"
                            class="h-6 w-6 rounded-full border-2 transition-transform hover:scale-110"
                            :class="backgroundColor === preset.color ? 'border-violet-500 ring-2 ring-violet-500/30' : 'border-transparent'"
                            :style="{ backgroundColor: preset.color }"
                            :title="preset.name"
                            @click="backgroundColor = preset.color"
                        />
                    </div>
                </div>

                <!-- Text Color -->
                <div class="grid gap-2">
                    <Label>Text Color</Label>
                    <div class="flex gap-2">
                        <input
                            type="color"
                            v-model="textColor"
                            class="h-10 w-16 cursor-pointer rounded border border-border bg-transparent"
                        />
                        <Input
                            v-model="textColor"
                            placeholder="#eaeaea"
                            class="flex-1 font-mono text-sm"
                        />
                    </div>
                    <!-- Preset text colors -->
                    <div class="flex flex-wrap gap-1.5">
                        <button
                            v-for="preset in presetTextColors"
                            :key="preset.color"
                            class="h-6 w-6 rounded-full border-2 transition-transform hover:scale-110"
                            :class="textColor === preset.color ? 'border-violet-500 ring-2 ring-violet-500/30' : 'border-border'"
                            :style="{ backgroundColor: preset.color }"
                            :title="preset.name"
                            @click="textColor = preset.color"
                        />
                    </div>
                </div>
            </div>

            <DialogFooter class="gap-2">
                <Button variant="outline" @click="closeDialogs">
                    Cancel
                </Button>
                <Button
                    @click="handleSaveTheme"
                    :disabled="!themeName.trim() || isSaving"
                    class="bg-gradient-to-r from-violet-600 to-fuchsia-600 text-white hover:from-violet-700 hover:to-fuchsia-700"
                >
                    {{ isSaving ? 'Saving...' : 'Save Theme' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- Edit Theme Dialog -->
    <Dialog v-model:open="isEditDialogOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <Edit2 class="h-5 w-5 text-violet-500" />
                    Edit Theme
                </DialogTitle>
                <DialogDescription>
                    Update your custom theme settings.
                </DialogDescription>
            </DialogHeader>

            <div class="grid gap-6 py-4">
                <!-- Theme Name -->
                <div class="grid gap-2">
                    <Label for="edit-theme-name">Theme Name</Label>
                    <Input
                        id="edit-theme-name"
                        v-model="themeName"
                        placeholder="My Custom Theme"
                        maxlength="50"
                    />
                </div>

                <!-- Live Preview -->
                <div class="grid gap-2">
                    <Label>Preview</Label>
                    <div
                        class="rounded-lg border p-4 transition-colors duration-200"
                        :style="contrastPreview"
                    >
                        <p class="text-lg font-semibold">Sample Text</p>
                        <p class="text-sm opacity-80">
                            This is how your stories will look with this theme applied.
                        </p>
                    </div>
                </div>

                <!-- Background Color -->
                <div class="grid gap-2">
                    <Label>Background Color</Label>
                    <div class="flex gap-2">
                        <input
                            type="color"
                            v-model="backgroundColor"
                            class="h-10 w-16 cursor-pointer rounded border border-border bg-transparent"
                        />
                        <Input
                            v-model="backgroundColor"
                            placeholder="#1a1a2e"
                            class="flex-1 font-mono text-sm"
                        />
                    </div>
                    <div class="flex flex-wrap gap-1.5">
                        <button
                            v-for="preset in presetBackgrounds"
                            :key="preset.color"
                            class="h-6 w-6 rounded-full border-2 transition-transform hover:scale-110"
                            :class="backgroundColor === preset.color ? 'border-violet-500 ring-2 ring-violet-500/30' : 'border-transparent'"
                            :style="{ backgroundColor: preset.color }"
                            :title="preset.name"
                            @click="backgroundColor = preset.color"
                        />
                    </div>
                </div>

                <!-- Text Color -->
                <div class="grid gap-2">
                    <Label>Text Color</Label>
                    <div class="flex gap-2">
                        <input
                            type="color"
                            v-model="textColor"
                            class="h-10 w-16 cursor-pointer rounded border border-border bg-transparent"
                        />
                        <Input
                            v-model="textColor"
                            placeholder="#eaeaea"
                            class="flex-1 font-mono text-sm"
                        />
                    </div>
                    <div class="flex flex-wrap gap-1.5">
                        <button
                            v-for="preset in presetTextColors"
                            :key="preset.color"
                            class="h-6 w-6 rounded-full border-2 transition-transform hover:scale-110"
                            :class="textColor === preset.color ? 'border-violet-500 ring-2 ring-violet-500/30' : 'border-border'"
                            :style="{ backgroundColor: preset.color }"
                            :title="preset.name"
                            @click="textColor = preset.color"
                        />
                    </div>
                </div>
            </div>

            <DialogFooter class="gap-2">
                <Button variant="outline" @click="closeDialogs">
                    Cancel
                </Button>
                <Button
                    @click="handleSaveTheme"
                    :disabled="!themeName.trim() || isSaving"
                    class="bg-gradient-to-r from-violet-600 to-fuchsia-600 text-white hover:from-violet-700 hover:to-fuchsia-700"
                >
                    {{ isSaving ? 'Saving...' : 'Update Theme' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- Generate Background Image Dialog -->
    <Dialog v-model:open="isBackgroundDialogOpen">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <Wand2 class="h-5 w-5 text-violet-500" />
                    Generate Background Image
                </DialogTitle>
                <DialogDescription>
                    Describe the background you'd like for your dashboard. Our AI will create a beautiful, subtle wallpaper that won't distract from your content.
                </DialogDescription>
            </DialogHeader>

            <div class="grid gap-6 py-4">
                <!-- Description Input -->
                <div class="grid gap-2">
                    <Label for="bg-description">Describe Your Background</Label>
                    <Textarea
                        id="bg-description"
                        v-model="backgroundDescription"
                        placeholder="e.g., A peaceful mountain landscape at sunset with soft purple and orange hues..."
                        rows="3"
                        class="resize-none"
                        :disabled="isGeneratingImage"
                    />
                    <p class="text-xs text-muted-foreground">
                        Minimum 10 characters. The AI will optimize your description for a wallpaper-style image.
                    </p>
                </div>

                <!-- Example Prompts -->
                <div class="grid gap-2">
                    <Label class="text-xs text-muted-foreground">Try an example:</Label>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="(prompt, index) in examplePrompts.slice(0, 3)"
                            :key="index"
                            class="text-xs px-2 py-1 rounded-full bg-accent text-accent-foreground hover:bg-accent/80 transition-colors truncate max-w-[200px]"
                            @click="useExamplePrompt(prompt)"
                            :disabled="isGeneratingImage"
                            :title="prompt"
                        >
                            {{ prompt.slice(0, 40) }}...
                        </button>
                    </div>
                </div>

                <!-- Generated Image Preview -->
                <div v-if="generatedImageUrl || isGeneratingImage" class="grid gap-2">
                    <Label>Generated Image</Label>
                    <div class="relative aspect-video rounded-lg border overflow-hidden bg-muted">
                        <div
                            v-if="isGeneratingImage"
                            class="absolute inset-0 flex flex-col items-center justify-center gap-3"
                        >
                            <Loader2 class="h-8 w-8 animate-spin text-violet-500" />
                            <p class="text-sm text-muted-foreground">Generating your background...</p>
                            <p class="text-xs text-muted-foreground">This may take 30-60 seconds</p>
                        </div>
                        <img
                            v-else-if="generatedImageUrl"
                            :src="generatedImageUrl"
                            alt="Generated background"
                            class="w-full h-full object-cover"
                        />
                    </div>
                </div>

                <!-- Error Message -->
                <div v-if="generationError" class="rounded-lg bg-destructive/10 p-3 text-sm text-destructive">
                    {{ generationError }}
                </div>
            </div>

            <DialogFooter class="gap-2 flex-col sm:flex-row">
                <Button variant="outline" @click="closeDialogs" :disabled="isGeneratingImage">
                    Cancel
                </Button>
                <Button
                    v-if="!generatedImageUrl"
                    @click="handleGenerateBackground"
                    :disabled="backgroundDescription.length < 10 || isGeneratingImage"
                    class="bg-gradient-to-r from-violet-600 to-fuchsia-600 text-white hover:from-violet-700 hover:to-fuchsia-700"
                >
                    <Wand2 v-if="!isGeneratingImage" class="h-4 w-4 mr-2" />
                    <Loader2 v-else class="h-4 w-4 mr-2 animate-spin" />
                    {{ isGeneratingImage ? 'Generating...' : 'Generate Image' }}
                </Button>
                <template v-else>
                    <Button
                        variant="outline"
                        @click="handleGenerateBackground"
                        :disabled="isGeneratingImage"
                    >
                        <Wand2 class="h-4 w-4 mr-2" />
                        Regenerate
                    </Button>
                    <Button
                        @click="handleSaveBackgroundImage"
                        :disabled="isSaving"
                        class="bg-gradient-to-r from-violet-600 to-fuchsia-600 text-white hover:from-violet-700 hover:to-fuchsia-700"
                    >
                        {{ isSaving ? 'Saving...' : 'Use This Background' }}
                    </Button>
                </template>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

<style scoped>
/* Color input styling */
input[type="color"] {
    -webkit-appearance: none;
    padding: 0;
}

input[type="color"]::-webkit-color-swatch-wrapper {
    padding: 2px;
}

input[type="color"]::-webkit-color-swatch {
    border: none;
    border-radius: 4px;
}
</style>
