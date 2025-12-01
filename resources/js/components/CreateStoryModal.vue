<script setup lang="ts">
import { ref, computed, watch, inject, onUnmounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { apiFetch } from '@/composables/ApiFetch';
import { echo } from '@laravel/echo-vue';
import type { Profile } from '@/types';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Spinner } from '@/components/ui/spinner';
import { 
    Sparkles, 
    BookOpen, 
    Users, 
    Wand2, 
    ChevronLeft, 
    ChevronRight, 
    Plus, 
    Trash2,
    User,
    Check,
    Mic,
    MicOff,
    Square,
    X
} from 'lucide-vue-next';

const props = defineProps<{
    defaultGenre: string | null;
}>();

const isOpen = defineModel<boolean>('isOpen');

// Get current profile from page props for default age group
const page = usePage();
const currentProfile = computed(() => page.props.auth?.currentProfile as Profile | null);

type ApiFetchFn = (
    request: string,
    method?: string,
    data?: Record<string, unknown> | FormData | null,
    isFormData?: boolean | null,
) => Promise<{ data: unknown; error: unknown }>;

const requestApiFetch = apiFetch as ApiFetchFn;

interface Character {
    id: string;
    name: string;
    age: string;
    gender: string;
    description: string;
    backstory: string;
    portrait_image?: string | null;
}

interface PortraitCreatedPayload {
    id: string;
    portrait_image: string;
}

const currentStep = ref(1);
const totalSteps = 4;

const formData = ref({
    type: '',
    genre: '',
    age_level: '',
    plot: '',
    first_chapter_prompt: '',
    scene: '',
});

const characters = ref<Character[]>([]);
const editingCharacterIndex = ref<number | null>(null);
const isAddingNewCharacter = ref(false);
const newCharacter = ref<Character>({
    id: '',
    name: '',
    age: '',
    gender: '',
    description: '',
    backstory: '',
});

// Echo channel subscription for portrait updates
// eslint-disable-next-line @typescript-eslint/no-explicit-any
const echoChannel = ref<any>(null);
const activeListeners = ref<Set<string>>(new Set());

const setupPortraitListener = (characterId: string) => {
    // Don't set up listeners for temp IDs
    if (characterId.startsWith('temp-') || characterId.startsWith('extracted-')) {
        console.log('[Echo] Skipping listener for temp ID:', characterId);
        return;
    }
    
    // Don't add duplicate listeners
    if (activeListeners.value.has(characterId)) {
        console.log('[Echo] Listener already exists for:', characterId);
        return;
    }
    
    try {
        // Subscribe to the private characters channel if not already
        if (!echoChannel.value) {
            console.log('[Echo] Subscribing to private channel: characters');
            echoChannel.value = echo().private('characters');
            
            // Log when successfully subscribed
            echoChannel.value.subscribed(() => {
                console.log('[Echo] Successfully subscribed to characters channel');
            });
            
            // Log subscription errors
            echoChannel.value.error((error: unknown) => {
                console.error('[Echo] Channel subscription error:', error);
            });
        }
        
        // Listen for this character's portrait-created event
        const eventName = `.character.${characterId}.portrait-created`;
        console.log('[Echo] Setting up listener for event:', eventName);
        
        echoChannel.value.listen(eventName, (payload: PortraitCreatedPayload) => {
            console.log('[Echo] Received portrait-created event:', payload);
            // Find and update the character's portrait_image
            const charIndex = characters.value.findIndex(c => c.id === payload.id);
            if (charIndex !== -1) {
                console.log('[Echo] Updating character portrait at index:', charIndex);
                characters.value[charIndex].portrait_image = payload.portrait_image;
            } else {
                console.log('[Echo] Character not found in list for ID:', payload.id);
            }
        });
        
        activeListeners.value.add(characterId);
        console.log('[Echo] Listener setup complete for:', characterId);
    } catch (err) {
        console.error('[Echo] Failed to setup portrait listener:', err);
    }
};

const cleanupPortraitListener = (characterId: string) => {
    if (!echoChannel.value || !activeListeners.value.has(characterId)) {
        return;
    }
    
    try {
        const eventName = `.character.${characterId}.portrait-created`;
        echoChannel.value.stopListening(eventName);
        activeListeners.value.delete(characterId);
    } catch (err) {
        console.error('Failed to cleanup portrait listener:', err);
    }
};

const cleanupAllListeners = () => {
    if (echoChannel.value) {
        activeListeners.value.forEach(characterId => {
            const eventName = `.character.${characterId}.portrait-created`;
            try {
                echoChannel.value?.stopListening(eventName);
            } catch (err) {
                // Ignore errors during cleanup
            }
        });
        activeListeners.value.clear();
        
        try {
            echo().leave('characters');
        } catch (err) {
            // Ignore errors during cleanup
        }
        echoChannel.value = null;
    }
};

// Watch for character changes to setup/cleanup listeners
watch(characters, (newChars, oldChars) => {
    // Setup listeners for new characters
    newChars.forEach(char => {
        if (!char.id.startsWith('temp-') && !char.id.startsWith('extracted-')) {
            setupPortraitListener(char.id);
        }
    });
    
    // Cleanup listeners for removed characters
    if (oldChars) {
        const newIds = new Set(newChars.map(c => c.id));
        oldChars.forEach(char => {
            if (!newIds.has(char.id)) {
                cleanupPortraitListener(char.id);
            }
        });
    }
}, { deep: true });

// Cleanup on unmount
onUnmounted(() => {
    cleanupAllListeners();
});

const errors = ref<Record<string, string>>({});
const processing = ref(false);

// Book ID for progressive saving
const bookId = ref<string | null>(null);
const isSaving = ref(false);

// Close confirmation state
const showCloseConfirm = ref(false);

const requestClose = () => {
    // Check if user has entered any data
    const hasData = formData.value.type || 
                    formData.value.genre || 
                    formData.value.age_level || 
                    formData.value.plot || 
                    formData.value.first_chapter_prompt || 
                    formData.value.scene || 
                    characters.value.length > 0;
    
    if (hasData && !processing.value) {
        showCloseConfirm.value = true;
    } else {
        isOpen.value = false;
    }
};

const confirmClose = () => {
    showCloseConfirm.value = false;
    const hadBook = bookId.value !== null;
    resetForm();
    isOpen.value = false;
    // Reload if a book was created so user can see it
    if (hadBook) {
        router.reload();
    }
};

const cancelClose = () => {
    showCloseConfirm.value = false;
};

// Voice recording state
const isRecording = ref(false);
const isTranscribing = ref(false);
const transcribeStatus = ref('');
const mediaRecorder = ref<MediaRecorder | null>(null);
const audioChunks = ref<Blob[]>([]);
const recordingDuration = ref(0);
const recordingInterval = ref<ReturnType<typeof setInterval> | null>(null);

const PLOT_MAX_LENGTH = 2000;

// Character extraction state
const isExtractingCharacters = ref(false);
const extractionStatus = ref('');

const bookTypes = [
    { value: 'chapter', label: '📚 Chapter Book', description: 'A longer story with multiple chapters' },
    { value: 'theatre', label: '🎭 Theatre Play', description: 'A story written as a play with acts' },
    { value: 'story', label: '📖 Short Story', description: 'A quick adventure in one sitting' },
    { value: 'screenplay', label: '🎬 Screenplay', description: 'A story written for the screen' },
];

const baseGenres = [
    { value: 'fantasy', label: '🧙 Fantasy', emoji: '🧙' },
    { value: 'adventure', label: '🗺️ Adventure', emoji: '🗺️' },
    { value: 'mystery', label: '🔍 Mystery', emoji: '🔍' },
    { value: 'science_fiction', label: '🚀 Science Fiction', emoji: '🚀' },
    { value: 'fairy_tale', label: '🧚 Fairy Tale', emoji: '🧚' },
    { value: 'historical', label: '🏰 Historical', emoji: '🏰' },
    { value: 'comedy', label: '😂 Comedy', emoji: '😂' },
    { value: 'animal_stories', label: '🐾 Animal Stories', emoji: '🐾' },
];

const matureGenres = [
    { value: 'drama', label: '🎭 Drama', emoji: '🎭' },
    { value: 'romance', label: '💕 Romance', emoji: '💕' },
    { value: 'horror', label: '👻 Horror', emoji: '👻' },
    { value: 'erotica', label: '🍷 Erotica', emoji: '🍷' },
];

const genres = computed(() => {
    const isMatureAge = formData.value.age_level === '16' || formData.value.age_level === '18';
    return isMatureAge ? [...baseGenres, ...matureGenres] : baseGenres;
});

const ageLevels = [
    { value: '8', label: 'Kids', range: '7-10' },
    { value: '12', label: 'Pre-Teen', range: '11-13' },
    { value: '16', label: 'Teen', range: '14-17' },
    { value: '18', label: 'Adult', range: '18+' },
];

// Check if an age level is allowed based on the current profile's age group
const isAgeLevelAllowed = (ageValue: string): boolean => {
    const profileAge = currentProfile.value?.age_group;
    if (!profileAge) return true; // Allow all if no profile age is set
    return parseInt(ageValue) <= parseInt(profileAge);
};

const genderOptions = [
    { value: 'male', label: '👦 Boy' },
    { value: 'female', label: '👧 Girl' },
    { value: 'non-binary', label: '🌟 Non-binary' },
    { value: 'other', label: '✨ Other' },
];

const stepInfo = computed(() => {
    switch (currentStep.value) {
        case 1:
            return {
                icon: Sparkles,
                title: "Let's Start Your Adventure!",
                description: "First, tell us what kind of story you want to create.",
                color: 'from-violet-500 to-purple-600',
            };
        case 2:
            return {
                icon: BookOpen,
                title: "What's Your Story About?",
                description: "Every great story starts with an idea. What's yours?",
                color: 'from-blue-500 to-cyan-600',
            };
        case 3:
            return {
                icon: Users,
                title: 'Create Your Characters!',
                description: "Who will be in your story? Add as many characters as you'd like!",
                color: 'from-emerald-500 to-teal-600',
            };
        case 4:
            return {
                icon: Wand2,
                title: 'Set the Scene!',
                description: "How does your adventure begin? Set up the opening scene.",
                color: 'from-amber-500 to-orange-600',
            };
        default:
            return {
                icon: Sparkles,
                title: 'Create Your Story',
                description: '',
                color: 'from-violet-500 to-purple-600',
            };
    }
});

const canProceed = computed(() => {
    switch (currentStep.value) {
        case 1:
            return formData.value.type && formData.value.genre && formData.value.age_level;
        case 2:
            return formData.value.plot.trim().length > 0;
        case 3:
            return true; // Characters are optional
        case 4:
            return true; // First chapter prompt is optional
        default:
            return false;
    }
});

const resetForm = (genre: string | null = null) => {
    // Clean up Echo listeners before resetting characters
    cleanupAllListeners();
    
    formData.value = {
        type: '',
        genre: genre ?? '',
        age_level: currentProfile.value?.age_group ?? '',
        plot: '',
        first_chapter_prompt: '',
        scene: '',
    };
    characters.value = [];
    editingCharacterIndex.value = null;
    isAddingNewCharacter.value = false;
    resetCharacterForm();
    errors.value = {};
    currentStep.value = 1;
    bookId.value = null;
    isSaving.value = false;
};

// Create book with initial data (after step 1)
const createBook = async (): Promise<boolean> => {
    console.log('[CreateBook] Starting book creation...');
    isSaving.value = true;
    errors.value = {};

    try {
        const { data, error } = await requestApiFetch('/api/books', 'POST', {
            type: formData.value.type,
            genre: formData.value.genre,
            age_level: formData.value.age_level ? parseInt(formData.value.age_level) : null,
            status: 'draft',
        });

        if (error) {
            const message = extractErrorMessage(error);
            console.error('[CreateBook] API error:', message);
            errors.value = { general: message ?? 'Failed to create book. Please try again.' };
            return false;
        }

        if (data && typeof data === 'object' && 'id' in data) {
            bookId.value = (data as { id: string }).id;
            console.log('[CreateBook] Book created successfully, bookId:', bookId.value);
            return true;
        }

        console.error('[CreateBook] Unexpected response format:', data);
        return false;
    } catch (err) {
        console.error('[CreateBook] Exception:', err);
        errors.value = { general: 'An unexpected error occurred. Please try again.' };
        return false;
    } finally {
        isSaving.value = false;
    }
};

// Update book with current data
const updateBook = async (data: Record<string, unknown>): Promise<boolean> => {
    if (!bookId.value) {
        return false;
    }

    isSaving.value = true;

    try {
        const { error } = await requestApiFetch(`/api/books/${bookId.value}`, 'PUT', data);

        if (error) {
            const message = extractErrorMessage(error);
            console.error('Error updating book:', message);
            return false;
        }

        return true;
    } catch (err) {
        console.error('Error updating book:', err);
        return false;
    } finally {
        isSaving.value = false;
    }
};

// Save a single character to the book
const saveCharacterToBook = async (character: Omit<Character, 'id'>): Promise<Character | null> => {
    console.log('[SaveCharacter] Attempting to save character:', character.name, 'bookId:', bookId.value);
    
    if (!bookId.value) {
        console.error('[SaveCharacter] Cannot save character - bookId is not set!');
        return null;
    }

    try {
        const { data, error } = await requestApiFetch('/api/characters', 'POST', {
            ...character,
            book_id: bookId.value,
            type: 'user',
        });

        if (error) {
            console.error('[SaveCharacter] API error saving character:', error);
            return null;
        }

        if (data && typeof data === 'object' && 'id' in data) {
            console.log('[SaveCharacter] Character saved successfully:', (data as Character).id);
            return data as Character;
        }

        console.error('[SaveCharacter] Unexpected response format:', data);
        return null;
    } catch (err) {
        console.error('[SaveCharacter] Exception saving character:', err);
        return null;
    }
};

// Update an existing character
const updateCharacterInBook = async (characterId: string, character: Omit<Character, 'id'>): Promise<boolean> => {
    try {
        const { error } = await requestApiFetch(`/api/characters/${characterId}`, 'PUT', {
            ...character,
        });

        if (error) {
            console.error('Error updating character:', error);
            return false;
        }

        return true;
    } catch (err) {
        console.error('Error updating character:', err);
        return false;
    }
};

// Delete a character from the book
const deleteCharacterFromBook = async (characterId: string): Promise<boolean> => {
    // Only delete if it's a real ID (not temp-)
    if (characterId.startsWith('temp-') || characterId.startsWith('extracted-')) {
        return true;
    }

    try {
        const { error } = await requestApiFetch(`/api/characters/${characterId}`, 'DELETE');

        if (error) {
            console.error('Error deleting character:', error);
            return false;
        }

        return true;
    } catch (err) {
        console.error('Error deleting character:', err);
        return false;
    }
};

const resetCharacterForm = () => {
    newCharacter.value = {
        id: '',
        name: '',
        age: '',
        gender: '',
        description: '',
        backstory: '',
    };
};

resetForm(props.defaultGenre ?? null);

const extractErrorMessage = (value: unknown): string | null => {
    if (typeof value === 'object' && value !== null && 'message' in value) {
        const message = (value as { message?: unknown }).message;
        return typeof message === 'string' ? message : null;
    }
    return null;
};

// Voice recording functions
const formatRecordingTime = (seconds: number): string => {
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins}:${secs.toString().padStart(2, '0')}`;
};

const startRecording = async () => {
    try {
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        
        mediaRecorder.value = new MediaRecorder(stream, {
            mimeType: MediaRecorder.isTypeSupported('audio/webm') ? 'audio/webm' : 'audio/mp4'
        });
        
        audioChunks.value = [];
        recordingDuration.value = 0;
        
        mediaRecorder.value.ondataavailable = (event) => {
            if (event.data.size > 0) {
                audioChunks.value.push(event.data);
            }
        };
        
        mediaRecorder.value.onstop = async () => {
            stream.getTracks().forEach(track => track.stop());
            
            if (recordingInterval.value) {
                clearInterval(recordingInterval.value);
                recordingInterval.value = null;
            }
            
            if (audioChunks.value.length > 0) {
                await transcribeAudio();
            }
        };
        
        mediaRecorder.value.start(1000);
        isRecording.value = true;
        
        recordingInterval.value = setInterval(() => {
            recordingDuration.value++;
            if (recordingDuration.value >= 60) {
                stopRecording();
            }
        }, 1000);
        
    } catch (err) {
        console.error('Failed to start recording:', err);
        errors.value = { plot: 'Could not access microphone. Please check your permissions.' };
    }
};

const stopRecording = () => {
    if (mediaRecorder.value && isRecording.value) {
        mediaRecorder.value.stop();
        isRecording.value = false;
    }
};

const transcribeAudio = async () => {
    isTranscribing.value = true;
    transcribeStatus.value = 'Preparing your recording...';
    
    try {
        const mimeType = mediaRecorder.value?.mimeType || 'audio/webm';
        const audioBlob = new Blob(audioChunks.value, { type: mimeType });
        
        const formDataUpload = new FormData();
        const extension = mimeType.includes('webm') ? 'webm' : 'mp4';
        formDataUpload.append('audio', audioBlob, `recording.${extension}`);
        
        transcribeStatus.value = 'Converting speech to text...';
        
        const { data, error } = await requestApiFetch('/api/transcribe', 'POST', formDataUpload, true);
        
        if (error) {
            const message = extractErrorMessage(error);
            errors.value = { plot: message || 'Failed to transcribe audio. Please try again or type your story.' };
        } else if (data && typeof data === 'object' && 'text' in data) {
            const transcribedText = (data as { text: string }).text;
            if (transcribedText) {
                transcribeStatus.value = 'Done! You can edit the text below.';
                const newPlot = formData.value.plot 
                    ? formData.value.plot + ' ' + transcribedText 
                    : transcribedText;
                formData.value.plot = newPlot.slice(0, PLOT_MAX_LENGTH);
                
                setTimeout(() => {
                    transcribeStatus.value = '';
                }, 2000);
            } else {
                errors.value = { plot: "We couldn't hear anything. Please try again." };
            }
        }
    } catch (err) {
        console.error('Transcription error:', err);
        errors.value = { plot: 'Failed to transcribe audio. Please try again or type your story.' };
    } finally {
        isTranscribing.value = false;
        audioChunks.value = [];
    }
};

const plotCharacterCount = computed(() => formData.value.plot.length);
const plotCharacterCountClass = computed(() => {
    const ratio = plotCharacterCount.value / PLOT_MAX_LENGTH;
    if (ratio >= 1) return 'text-destructive';
    if (ratio >= 0.9) return 'text-amber-500';
    return 'text-muted-foreground';
});

const nextStep = async () => {
    if (currentStep.value < totalSteps && canProceed.value) {
        // Step 1 -> 2: Create the book with basic details
        if (currentStep.value === 1) {
            const created = await createBook();
            if (!created) {
                return; // Don't proceed if book creation failed
            }
        }
        
        // Step 2 -> 3: Save the plot and extract characters
        if (currentStep.value === 2) {
            // Save plot to the book
            if (bookId.value && formData.value.plot.trim()) {
                await updateBook({ plot: formData.value.plot });
            }
            // Extract characters from plot
            if (formData.value.plot.trim()) {
                await extractCharactersFromPlot();
            }
        }
        
        // Step 3 -> 4: Save any unsaved characters
        if (currentStep.value === 3) {
            // Characters are saved as they're added, so nothing special needed here
        }
        
        currentStep.value++;
    }
};

const extractCharactersFromPlot = async () => {
    console.log('[ExtractCharacters] Starting extraction, bookId:', bookId.value, 'existing characters:', characters.value.length);
    
    // Only extract if we don't already have characters
    if (characters.value.length > 0) {
        console.log('[ExtractCharacters] Skipping - already have characters');
        return;
    }

    isExtractingCharacters.value = true;
    extractionStatus.value = 'Reading your story idea...';

    try {
        extractionStatus.value = 'Creating characters for your story...';
        
        console.log('[ExtractCharacters] Calling API to extract characters...');
        const { data, error } = await requestApiFetch('/api/extract-characters', 'POST', {
            plot: formData.value.plot,
            genre: formData.value.genre,
            age_level: formData.value.age_level ? parseInt(formData.value.age_level) : 10,
        });

        if (error) {
            console.error('[ExtractCharacters] API error:', error);
            extractionStatus.value = '';
            return;
        }

        console.log('[ExtractCharacters] Got response:', data);
        
        if (data && typeof data === 'object' && 'characters' in data) {
            const extractedCharacters = (data as { characters: Array<{
                name: string;
                age: string;
                gender: string;
                description: string;
                backstory: string;
            }> }).characters;

            console.log('[ExtractCharacters] Extracted characters:', extractedCharacters?.length);

            if (extractedCharacters && extractedCharacters.length > 0) {
                extractionStatus.value = `Saving ${extractedCharacters.length} characters...`;
                console.log('[ExtractCharacters] About to save characters, bookId:', bookId.value);
                
                // Save each character to the book and get real IDs
                const savedCharacters: Character[] = [];
                for (const char of extractedCharacters) {
                    const savedChar = await saveCharacterToBook({
                        name: char.name || '',
                        age: String(char.age ?? ''),
                        gender: char.gender || '',
                        description: char.description || '',
                        backstory: char.backstory || '',
                    });
                    
                    if (savedChar) {
                        savedCharacters.push(savedChar);
                        console.log('[ExtractCharacters] Character saved:', savedChar.name);
                    } else {
                        console.error('[ExtractCharacters] Failed to save character:', char.name);
                    }
                }
                
                characters.value = savedCharacters;
                console.log('[ExtractCharacters] Total saved:', savedCharacters.length, 'out of', extractedCharacters.length);
                extractionStatus.value = `Found ${characters.value.length} characters!`;
            }
        }
    } catch (err) {
        console.error('[ExtractCharacters] Exception:', err);
    } finally {
        setTimeout(() => {
            isExtractingCharacters.value = false;
            extractionStatus.value = '';
        }, 1500);
    }
};

const prevStep = () => {
    if (currentStep.value > 1) {
        currentStep.value--;
    }
};

const goToStep = (step: number) => {
    if (step >= 1 && step <= totalSteps) {
        // Only allow going to previous steps or current step
        if (step <= currentStep.value) {
            currentStep.value = step;
        }
    }
};

const saveCharacter = async (index: number) => {
    if (!newCharacter.value.name.trim()) {
        errors.value = { character_name: 'Please give your character a name!' };
        return;
    }
    
    errors.value = {};
    isSaving.value = true;
    
    const existingId = characters.value[index].id;
    
    // Update the character in the API
    const success = await updateCharacterInBook(existingId, {
        name: newCharacter.value.name,
        age: newCharacter.value.age,
        gender: newCharacter.value.gender,
        description: newCharacter.value.description,
        backstory: newCharacter.value.backstory,
    });
    
    if (success) {
        characters.value[index] = {
            ...newCharacter.value,
            id: existingId,
        };
    }
    
    resetCharacterForm();
    editingCharacterIndex.value = null;
    isSaving.value = false;
};

const addNewCharacter = async () => {
    if (!newCharacter.value.name.trim()) {
        errors.value = { character_name: 'Please give your character a name!' };
        return;
    }
    
    errors.value = {};
    isSaving.value = true;
    
    // Save character to the API
    const savedChar = await saveCharacterToBook({
        name: newCharacter.value.name,
        age: newCharacter.value.age,
        gender: newCharacter.value.gender,
        description: newCharacter.value.description,
        backstory: newCharacter.value.backstory,
    });
    
    if (savedChar) {
        characters.value.push(savedChar);
    } else {
        // Fallback to local only if save failed
        characters.value.push({
            ...newCharacter.value,
            id: `temp-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`,
        });
    }
    
    resetCharacterForm();
    isAddingNewCharacter.value = false;
    isSaving.value = false;
};

const toggleEditCharacter = (index: number) => {
    // If clicking the same character, close it
    if (editingCharacterIndex.value === index) {
        editingCharacterIndex.value = null;
        resetCharacterForm();
        errors.value = {};
        return;
    }
    
    // Close new character form if open
    isAddingNewCharacter.value = false;
    
    // Open the clicked character for editing
    editingCharacterIndex.value = index;
    newCharacter.value = { ...characters.value[index] };
    errors.value = {};
};

const startAddingCharacter = () => {
    // Close any open character edit
    editingCharacterIndex.value = null;
    resetCharacterForm();
    isAddingNewCharacter.value = true;
    errors.value = {};
};

const removeCharacter = async (index: number) => {
    const characterId = characters.value[index].id;
    
    // Delete from API
    await deleteCharacterFromBook(characterId);
    
    // Remove locally
    characters.value.splice(index, 1);
    
    // If we removed the character being edited, reset
    if (editingCharacterIndex.value === index) {
        editingCharacterIndex.value = null;
        resetCharacterForm();
    } else if (editingCharacterIndex.value !== null && editingCharacterIndex.value > index) {
        // Adjust index if we removed a character before the one being edited
        editingCharacterIndex.value--;
    }
};

const cancelCharacterEdit = () => {
    resetCharacterForm();
    editingCharacterIndex.value = null;
    isAddingNewCharacter.value = false;
    errors.value = {};
};

const isGeneratingCover = ref(false);
const coverGenerationStatus = ref('');

const handleSubmit = async () => {
    processing.value = true;
    errors.value = {};

    try {
        // Book should already exist from step 1
        if (!bookId.value) {
            errors.value = { general: 'Book not found. Please start over.' };
            processing.value = false;
            return;
        }

        // Update book with final details (first_chapter_prompt, scene, and mark as in_progress)
        const updateSuccess = await updateBook({
            first_chapter_prompt: formData.value.first_chapter_prompt,
            scene: formData.value.scene,
            status: 'in_progress',
        });

        if (!updateSuccess) {
            errors.value = { general: 'Failed to save your story details. Please try again.' };
            processing.value = false;
            return;
        }

        // Generate cover image in the background
        isGeneratingCover.value = true;
        coverGenerationStatus.value = 'Creating your book title and cover...';
        
        try {
            await requestApiFetch(`/api/books/${bookId.value}/generate-cover`, 'POST');
        } catch (coverError) {
            // Cover generation is optional, don't block the flow
            console.error('Cover generation failed:', coverError);
        }
        
        isGeneratingCover.value = false;
        coverGenerationStatus.value = '';
        
        resetForm();
        isOpen.value = false;
        router.reload();
    } catch (err) {
        errors.value = { general: 'An unexpected error occurred. Please try again.' };
    } finally {
        processing.value = false;
        isGeneratingCover.value = false;
    }
};

const handleOpenChange = (open: boolean) => {
    if (open && !processing.value) {
        resetForm(props.defaultGenre ?? null);
    }

    if (!open && !processing.value) {
        resetForm();
    }
    isOpen.value = open;
};

watch(() => props.defaultGenre, (newGenre) => {
    if (newGenre && isOpen.value) {
        formData.value.genre = newGenre;
    }
});
</script>

<template>
    <Dialog :open="isOpen" @update:open="handleOpenChange">
        <DialogContent 
            class="max-w-2xl overflow-visible rounded-3xl border-2 p-0 sm:max-w-2xl [&>button[data-slot]]:hidden"
            :class="processing ? 'pointer-events-none' : ''"
        >
            <!-- Custom Close Button - Offset in corner with white circle -->
            <button
                type="button"
                @click="requestClose"
                :disabled="processing"
                class="absolute -right-2 -top-2 z-[60] flex h-10 w-10 cursor-pointer items-center justify-center rounded-full border-2 border-white bg-gray-800 text-white shadow-xl transition-all duration-200 hover:scale-110 hover:bg-gray-700 hover:shadow-2xl active:scale-95 sm:-right-3 sm:-top-3 sm:h-12 sm:w-12"
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
                    class="absolute inset-0 z-[70] flex items-center justify-center rounded-3xl bg-black/50 backdrop-blur-sm"
                >
                    <div class="mx-4 w-full max-w-sm animate-bounce-in rounded-2xl border-2 border-orange-200 bg-white p-6 shadow-2xl dark:border-orange-800 dark:bg-gray-900">
                        <div class="mb-4 text-center">
                            <div class="mx-auto mb-3 flex h-16 w-16 items-center justify-center rounded-full bg-orange-100 dark:bg-orange-900/30">
                                <span class="text-3xl">{{ bookId ? '📖' : '🤔' }}</span>
                            </div>
                            <h3 class="text-xl font-bold text-gray-900 dark:text-white">
                                {{ bookId ? 'Close and continue later?' : 'Wait! Are you sure?' }}
                            </h3>
                            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                                {{ bookId 
                                    ? "Your story has been saved! You can find it in your library and continue anytime." 
                                    : "Your amazing story ideas will disappear into the void!" 
                                }}
                            </p>
                        </div>
                        <div class="flex gap-3">
                            <Button
                                type="button"
                                variant="outline"
                                @click="cancelClose"
                                class="flex-1 cursor-pointer rounded-xl transition-all duration-200 hover:scale-[1.02] active:scale-[0.98]"
                            >
                                Keep Creating! ✨
                            </Button>
                            <Button
                                type="button"
                                @click="confirmClose"
                                class="flex-1 cursor-pointer rounded-xl bg-gradient-to-r text-white transition-all duration-200 hover:scale-[1.02] active:scale-[0.98]"
                                :class="bookId 
                                    ? 'from-blue-500 to-indigo-500 hover:from-blue-600 hover:to-indigo-600' 
                                    : 'from-red-500 to-rose-500 hover:from-red-600 hover:to-rose-600'"
                            >
                                {{ bookId ? 'Close for Now' : 'Close Anyway' }}
                            </Button>
                        </div>
                    </div>
                </div>
            </Transition>

            <!-- Content wrapper with overflow hidden for rounded corners -->
            <div class="overflow-hidden rounded-3xl">
                <!-- Animated Header -->
                <div 
                    class="relative overflow-hidden bg-gradient-to-r px-6 py-8 text-white transition-all duration-500"
                    :class="stepInfo.color"
                >
                <!-- Floating decorative elements -->
                <div class="absolute -right-4 -top-4 h-24 w-24 rounded-full bg-white/10 blur-2xl" />
                <div class="absolute -bottom-8 -left-8 h-32 w-32 rounded-full bg-white/10 blur-3xl" />
                <div class="absolute right-1/4 top-1/2 h-16 w-16 rounded-full bg-white/5 blur-xl" />
                
                <!-- Step indicator dots -->
                <div class="absolute bottom-4 right-6 flex gap-2">
                    <button
                        v-for="step in totalSteps"
                        :key="step"
                        type="button"
                        @click="goToStep(step)"
                        class="h-3 w-3 rounded-full transition-all duration-300"
                        :class="[
                            step === currentStep 
                                ? 'bg-white scale-125 shadow-lg' 
                                : step < currentStep 
                                    ? 'bg-white/80 hover:bg-white hover:scale-110 cursor-pointer' 
                                    : 'bg-white/30 cursor-not-allowed'
                        ]"
                        :disabled="step > currentStep"
                    />
                </div>

                <DialogHeader class="relative z-10">
                    <div class="mb-4 flex items-center gap-3">
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/20 backdrop-blur-sm">
                            <component :is="stepInfo.icon" class="h-8 w-8" />
                        </div>
                        <div class="text-sm font-medium opacity-90">
                            Step {{ currentStep }} of {{ totalSteps }}
                        </div>
                    </div>
                    <DialogTitle class="text-2xl font-bold tracking-tight sm:text-3xl">
                        {{ stepInfo.title }}
                    </DialogTitle>
                    <DialogDescription class="mt-2 text-base text-white/90">
                        {{ stepInfo.description }}
                </DialogDescription>
            </DialogHeader>
            </div>

            <!-- Form Content -->
            <div class="max-h-[60vh] overflow-y-auto px-6 py-6">
                <form @submit.prevent="currentStep === totalSteps ? handleSubmit() : nextStep()">
                    
                    <!-- Step 1: Book Type, Genre, Age Level -->
                    <div v-show="currentStep === 1" class="space-y-6">
                        <!-- Book Type Selection -->
                        <div class="space-y-3">
                            <Label class="text-lg font-semibold">What kind of story?</Label>
                            <div class="grid grid-cols-2 gap-3">
                                <button
                                v-for="bookType in bookTypes"
                                :key="bookType.value"
                                    type="button"
                                    @click="formData.type = bookType.value"
                                    :disabled="processing"
                                    class="group relative flex cursor-pointer flex-col items-start gap-1 rounded-2xl border-2 p-4 text-left transition-all duration-200 hover:-translate-y-0.5 hover:border-orange-400/50 hover:bg-orange-50 hover:shadow-md active:scale-[0.98] dark:hover:bg-orange-950/20"
                                    :class="formData.type === bookType.value 
                                        ? 'border-orange-500 bg-orange-50 ring-2 ring-orange-500/20 dark:bg-orange-950/30' 
                                        : 'border-border'"
                                >
                                    <span class="text-lg font-semibold">{{ bookType.label }}</span>
                                    <span class="text-sm text-muted-foreground">{{ bookType.description }}</span>
                                    <div 
                                        v-if="formData.type === bookType.value"
                                        class="absolute right-3 top-3 flex h-6 w-6 items-center justify-center rounded-full bg-orange-500 text-white"
                                    >
                                        <Check class="h-4 w-4" />
                                    </div>
                                </button>
                            </div>
                    <InputError :message="errors.type" />
                </div>

                        <!-- Age Level Selection -->
                        <div class="space-y-3">
                            <Label class="text-lg font-semibold">Who is this story for?</Label>
                            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                                <button
                                v-for="age in ageLevels"
                                :key="age.value"
                                    type="button"
                                    @click="isAgeLevelAllowed(age.value) && (formData.age_level = age.value, !['16', '18'].includes(age.value) && ['drama', 'romance', 'horror'].includes(formData.genre) && (formData.genre = ''))"
                                    :disabled="processing || !isAgeLevelAllowed(age.value)"
                                    class="relative flex flex-col items-center gap-1 rounded-2xl border-2 p-4 transition-all duration-200"
                                    :class="[
                                        !isAgeLevelAllowed(age.value) 
                                            ? 'cursor-not-allowed border-border/50 bg-muted/30 opacity-50' 
                                            : 'cursor-pointer hover:-translate-y-0.5 hover:border-orange-400/50 hover:bg-orange-50 hover:shadow-md active:scale-95 dark:hover:bg-orange-950/20',
                                        formData.age_level === age.value 
                                            ? 'border-orange-500 bg-orange-50 ring-2 ring-orange-500/20 dark:bg-orange-950/30' 
                                            : 'border-border'
                                    ]"
                                >
                                    <span class="text-sm font-bold">{{ age.label }}</span>
                                    <span class="text-xs text-muted-foreground">{{ age.range }}</span>
                                    <div 
                                        v-if="formData.age_level === age.value"
                                        class="absolute right-2 top-2 flex h-5 w-5 items-center justify-center rounded-full bg-orange-500 text-white"
                                    >
                                        <Check class="h-3 w-3" />
                                    </div>
                                </button>
                            </div>
                            <InputError :message="errors.age_level" />
                        </div>

                        <!-- Genre Selection -->
                        <div class="space-y-3">
                            <Label class="text-lg font-semibold">Pick a genre!</Label>
                            <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                                <button
                                v-for="genre in genres"
                                :key="genre.value"
                                    type="button"
                                    @click="formData.genre = genre.value"
                                    :disabled="processing"
                                    class="relative flex cursor-pointer flex-col items-center gap-2 rounded-xl border-2 p-3 transition-all duration-200 hover:-translate-y-0.5 hover:border-orange-400/50 hover:bg-orange-50 hover:shadow-md active:scale-95 dark:hover:bg-orange-950/20"
                                    :class="formData.genre === genre.value 
                                        ? 'border-orange-500 bg-orange-50 ring-2 ring-orange-500/20 dark:bg-orange-950/30' 
                                        : 'border-border'"
                                >
                                    <span class="text-2xl">{{ genre.emoji }}</span>
                                    <span class="text-xs font-bold">{{ genre.label.replace(genre.emoji + ' ', '') }}</span>
                                    <div 
                                        v-if="formData.genre === genre.value"
                                        class="absolute right-1 top-1 flex h-4 w-4 items-center justify-center rounded-full bg-orange-500 text-white"
                                    >
                                        <Check class="h-2.5 w-2.5" />
                                    </div>
                                </button>
                            </div>
                    <InputError :message="errors.genre" />
                </div>
                    </div>

                    <!-- Step 2: Plot/Story Description -->
                    <div v-show="currentStep === 2" class="space-y-6">
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                
                                
                                
                            </div>
                            
                            <!-- Transcription Status Message -->
                            <div 
                                v-if="transcribeStatus" 
                                class="flex items-center gap-2 rounded-xl bg-blue-50 px-4 py-3 text-sm text-blue-700 dark:bg-blue-950/30 dark:text-blue-300"
                            >
                                <Spinner v-if="isTranscribing" class="h-4 w-4" />
                                <Check v-else class="h-4 w-4 text-green-500" />
                                {{ transcribeStatus }}
                </div>

                            <div class="relative">
    <Textarea
        id="plot"
        v-model="formData.plot"
                                    placeholder="Example: A young wizard discovers a magical map that leads to a hidden kingdom where dragons and unicorns live together in harmony..."
                                    rows="8"
                                    :maxlength="PLOT_MAX_LENGTH"
                                    :disabled="processing || isRecording || isTranscribing"
                                    class="resize-none rounded-2xl border-2 pb-8 text-base leading-relaxed focus:ring-2 focus:ring-primary/20"
                                />
                                <div class="absolute bottom-3 right-3 text-xs" :class="plotCharacterCountClass">
                                    {{ plotCharacterCount.toLocaleString() }} / {{ PLOT_MAX_LENGTH.toLocaleString() }}
                                </div>
                            </div>
    <InputError :message="errors.plot" />
                            
                            <!-- Microphone Button -->
                            <div class="flex items-center gap-2 mt-5">
                                    <button
                                        v-if="!isRecording && !isTranscribing"
                                        type="button"
                                        @click="startRecording"
                                        :disabled="processing"
                                        class="group flex cursor-pointer items-center gap-2 rounded-full bg-gradient-to-r from-orange-500 to-amber-500 px-4 py-2 text-sm font-medium text-white shadow-lg transition-all duration-300 hover:scale-105 hover:shadow-orange-500/25 hover:shadow-xl active:scale-95"
                                    >
                                        <Mic class="h-5 w-5" />
                                        <span class="hidden sm:inline">Speak Your Story</span>
                                    </button>
                                    
                                    <!-- Recording State -->
                                    <div v-if="isRecording" class="flex items-center gap-3">
                                        <div class="flex items-center gap-2 rounded-full bg-red-500 px-4 py-2 text-sm font-medium text-white shadow-lg">
                                            <span class="relative flex h-3 w-3">
                                                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-white opacity-75"></span>
                                                <span class="relative inline-flex h-3 w-3 rounded-full bg-white"></span>
                                            </span>
                                            <span>{{ formatRecordingTime(recordingDuration) }}</span>
                                        </div>
                                        <button
                                            type="button"
                                            @click="stopRecording"
                                            class="flex h-10 w-10 cursor-pointer items-center justify-center rounded-full bg-red-600 text-white shadow-lg transition-all hover:scale-110 hover:bg-red-700 hover:shadow-red-500/30 hover:shadow-xl active:scale-95"
                                        >
                                            <Square class="h-4 w-4 fill-current" />
                                        </button>
                                    </div>
                                    
                                    <!-- Transcribing State -->
                                    <div v-if="isTranscribing" class="flex items-center gap-2 rounded-full bg-blue-500 px-4 py-2 text-sm font-medium text-white shadow-lg">
                                        <Spinner class="h-4 w-4" />
                                        <span class="hidden sm:inline">Processing...</span>
                                    </div>
                                </div>
                        </div>
                    </div>

                    <!-- Step 3: Characters -->
                    <div v-show="currentStep === 3" class="space-y-6">
                        <!-- AI-generated characters notice -->
                        <div 
                            v-if="characters.length > 0 && characters.some(c => c.id.startsWith('extracted-'))" 
                            class="flex items-start gap-3 rounded-2xl border-2 border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-800 dark:bg-emerald-950/30"
                        >
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-500 text-xl">
                                ✨
                            </div>
                            <div>
                                <p class="font-semibold text-emerald-800 dark:text-emerald-200">
                                    We found some characters in your story!
                                </p>
                                <p class="mt-1 text-sm text-emerald-700 dark:text-emerald-300">
                                    Based on your plot, we've suggested these characters. Click on any to edit or add your own!
                                </p>
                            </div>
                        </div>

                        <!-- Character List with Accordion -->
                        <div v-if="characters.length > 0" class="space-y-3">
                            <Label class="text-lg font-semibold">Your Characters</Label>
                            <div class="grid gap-3">
                                <div
                                    v-for="(character, index) in characters"
                                    :key="character.id"
                                    class="overflow-hidden rounded-2xl border-2 transition-all duration-300"
                                    :class="editingCharacterIndex === index 
                                        ? 'border-orange-400 bg-orange-50/50 shadow-lg shadow-orange-500/10 dark:bg-orange-950/20' 
                                        : 'border-border bg-card hover:border-primary/30'"
                                >
                                    <!-- Character Header (Always Visible) -->
                                    <div 
                                        class="flex cursor-pointer items-center gap-4 p-4 transition-all"
                                        @click="toggleEditCharacter(index)"
                                    >
                                        <!-- Character Avatar: Portrait, Loading Spinner, or Initial -->
                                        <div class="relative h-14 w-14 shrink-0 overflow-hidden rounded-xl shadow-lg">
                                            <!-- Portrait Image -->
                                            <img 
                                                v-if="character.portrait_image" 
                                                :src="character.portrait_image" 
                                                :alt="character.name"
                                                class="h-full w-full object-cover"
                                            />
                                            <!-- Loading State (no portrait yet) -->
                                            <div 
                                                v-else 
                                                class="flex h-full w-full items-center justify-center bg-gradient-to-br from-violet-500 to-purple-600"
                                            >
                                                <!-- Show spinner if character has real ID (portrait is being generated) -->
                                                <Spinner 
                                                    v-if="!character.id.startsWith('temp-') && !character.id.startsWith('extracted-')" 
                                                    class="h-6 w-6 text-white/80"
                                                />
                                                <!-- Show initial for temp characters -->
                                                <span v-else class="text-2xl font-bold text-white">
                                                    {{ character.name.charAt(0).toUpperCase() }}
                                                </span>
                                            </div>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-2">
                                                <span class="text-lg font-semibold">{{ character.name }}</span>
                                                <span v-if="character.age" class="text-sm text-muted-foreground">
                                                    ({{ character.age }} years old)
                                                </span>
                                            </div>
                                            <p v-if="character.description && editingCharacterIndex !== index" class="line-clamp-1 text-sm text-muted-foreground">
                                                {{ character.description }}
                                            </p>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                @click.stop="removeCharacter(index)"
                                                class="h-9 w-9 cursor-pointer rounded-xl p-0 text-destructive transition-all duration-200 hover:scale-110 hover:bg-destructive/10 hover:text-destructive active:scale-95"
                                            >
                                                <Trash2 class="h-4 w-4" />
                                            </Button>
                                            <ChevronRight 
                                                class="h-5 w-5 text-muted-foreground transition-transform duration-300"
                                                :class="editingCharacterIndex === index ? 'rotate-90' : ''"
                                            />
                                        </div>
                                    </div>

                                    <!-- Character Edit Form (Accordion Content) -->
                                    <Transition
                                        enter-active-class="transition-all duration-300 ease-out"
                                        enter-from-class="max-h-0 opacity-0"
                                        enter-to-class="max-h-[600px] opacity-100"
                                        leave-active-class="transition-all duration-200 ease-in"
                                        leave-from-class="max-h-[600px] opacity-100"
                                        leave-to-class="max-h-0 opacity-0"
                                    >
                                        <div v-if="editingCharacterIndex === index" class="overflow-hidden">
                                            <div class="space-y-4 border-t border-orange-200 bg-white/50 p-4 dark:border-orange-800 dark:bg-gray-900/50">
                                                <div class="grid gap-4 sm:grid-cols-2">
                                                    <div class="space-y-2">
                                                        <Label :for="'char_name_' + index" class="font-medium">Name *</Label>
                                                        <Input
                                                            :id="'char_name_' + index"
                                                            v-model="newCharacter.name"
                                                            placeholder="What's their name?"
                                                            class="h-12 rounded-xl border-2 bg-white text-base dark:bg-gray-950"
                                                        />
                                                        <InputError :message="errors.character_name" />
                                                    </div>

                                                    <div class="space-y-2">
                                                        <Label :for="'char_age_' + index" class="font-medium">Age</Label>
                                                        <Input
                                                            :id="'char_age_' + index"
                                                            v-model="newCharacter.age"
                                                            placeholder="How old are they?"
                                                            class="h-12 rounded-xl border-2 bg-white text-base dark:bg-gray-950"
                                                        />
                                                    </div>
                                                </div>

                                                <div class="space-y-2">
                                                    <Label class="font-medium">Gender</Label>
                                                    <div class="flex flex-wrap gap-2">
                                                        <button
                                                            v-for="gender in genderOptions"
                                                            :key="gender.value"
                                                            type="button"
                                                            @click="newCharacter.gender = gender.value"
                                                            class="cursor-pointer rounded-xl border-2 px-4 py-2 text-sm font-medium transition-all hover:-translate-y-0.5 hover:shadow-md active:scale-95"
                                                            :class="newCharacter.gender === gender.value 
                                                                ? 'border-orange-500 bg-orange-500 text-white' 
                                                                : 'border-border bg-white hover:border-orange-400/50 hover:bg-orange-50 dark:bg-gray-950 dark:hover:bg-orange-950/20'"
                                                        >
                                                            {{ gender.label }}
                                                        </button>
                                                    </div>
                                                </div>

                                                <div class="space-y-2">
                                                    <Label :for="'char_description_' + index" class="font-medium">What are they like?</Label>
                                                    <Textarea
                                                        :id="'char_description_' + index"
                                                        v-model="newCharacter.description"
                                                        placeholder="Describe their personality, appearance, or special abilities..."
                                                        rows="2"
                                                        class="resize-none rounded-xl border-2 bg-white text-base dark:bg-gray-950"
                                                    />
                                                </div>

                                                <div class="space-y-2">
                                                    <Label :for="'char_backstory_' + index" class="font-medium">Their story so far</Label>
                                                    <Textarea
                                                        :id="'char_backstory_' + index"
                                                        v-model="newCharacter.backstory"
                                                        placeholder="What's their background? Where do they come from?"
                                                        rows="2"
                                                        class="resize-none rounded-xl border-2 bg-white text-base dark:bg-gray-950"
                                                    />
                                                </div>

                                                <div class="flex gap-2">
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        @click="cancelCharacterEdit"
                                                        :disabled="isSaving"
                                                        class="flex-1 cursor-pointer rounded-xl transition-all duration-200 hover:scale-[1.01] active:scale-[0.99]"
                                                    >
                                                        Cancel
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        @click="saveCharacter(index)"
                                                        :disabled="isSaving"
                                                        class="flex-1 cursor-pointer gap-2 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 text-white shadow-lg transition-all duration-200 hover:scale-[1.01] hover:from-emerald-600 hover:to-teal-600 hover:shadow-emerald-500/25 hover:shadow-xl active:scale-[0.99]"
                                                    >
                                                        <Spinner v-if="isSaving" class="h-4 w-4" />
                                                        <Check v-else class="h-4 w-4" />
                                                        {{ isSaving ? 'Saving...' : 'Save Changes' }}
                                                    </Button>
                                                </div>
                                            </div>
                                        </div>
                                    </Transition>
                                </div>
                            </div>
                        </div>

                        <!-- Add New Character Accordion -->
                        <div class="overflow-hidden rounded-2xl border-2 transition-all duration-300"
                            :class="isAddingNewCharacter 
                                ? 'border-orange-400 bg-orange-50/50 shadow-lg shadow-orange-500/10 dark:bg-orange-950/20' 
                                : 'border-dashed border-border hover:border-orange-400/50 hover:bg-orange-50/30 dark:hover:bg-orange-950/10'"
                        >
                            <!-- Add New Character Header -->
                            <div 
                                class="flex cursor-pointer items-center justify-center gap-3 p-6 transition-all"
                                @click="startAddingCharacter"
                            >
                                <Plus class="h-6 w-6 text-orange-500" />
                                <span class="text-lg font-medium">
                                    {{ characters.length === 0 ? 'Add Your First Character' : 'Add Another Character' }}
                                </span>
                            </div>

                            <!-- New Character Form (Accordion Content) -->
                            <Transition
                                enter-active-class="transition-all duration-300 ease-out"
                                enter-from-class="max-h-0 opacity-0"
                                enter-to-class="max-h-[600px] opacity-100"
                                leave-active-class="transition-all duration-200 ease-in"
                                leave-from-class="max-h-[600px] opacity-100"
                                leave-to-class="max-h-0 opacity-0"
                            >
                                <div v-if="isAddingNewCharacter" class="overflow-hidden">
                                    <div class="space-y-4 border-t border-orange-200 bg-white/50 p-4 dark:border-orange-800 dark:bg-gray-900/50">
                                        <div class="grid gap-4 sm:grid-cols-2">
                                            <div class="space-y-2">
                                                <Label for="new_char_name" class="font-medium">Name *</Label>
                                                <Input
                                                    id="new_char_name"
                                                    v-model="newCharacter.name"
                                                    placeholder="What's their name?"
                                                    class="h-12 rounded-xl border-2 bg-white text-base dark:bg-gray-950"
                                                />
                                                <InputError :message="errors.character_name" />
                                            </div>

                                            <div class="space-y-2">
                                                <Label for="new_char_age" class="font-medium">Age</Label>
                                                <Input
                                                    id="new_char_age"
                                                    v-model="newCharacter.age"
                                                    placeholder="How old are they?"
                                                    class="h-12 rounded-xl border-2 bg-white text-base dark:bg-gray-950"
                                                />
                                            </div>
                                        </div>

                                        <div class="space-y-2">
                                            <Label class="font-medium">Gender</Label>
                                            <div class="flex flex-wrap gap-2">
                                                <button
                                                    v-for="gender in genderOptions"
                                                    :key="gender.value"
                                                    type="button"
                                                    @click="newCharacter.gender = gender.value"
                                                    class="cursor-pointer rounded-xl border-2 px-4 py-2 text-sm font-medium transition-all hover:-translate-y-0.5 hover:shadow-md active:scale-95"
                                                    :class="newCharacter.gender === gender.value 
                                                        ? 'border-orange-500 bg-orange-500 text-white' 
                                                        : 'border-border bg-white hover:border-orange-400/50 hover:bg-orange-50 dark:bg-gray-950 dark:hover:bg-orange-950/20'"
                                                >
                                                    {{ gender.label }}
                                                </button>
                                            </div>
                                        </div>

                                        <div class="space-y-2">
                                            <Label for="new_char_description" class="font-medium">What are they like?</Label>
                                            <Textarea
                                                id="new_char_description"
                                                v-model="newCharacter.description"
                                                placeholder="Describe their personality, appearance, or special abilities..."
                                                rows="2"
                                                class="resize-none rounded-xl border-2 bg-white text-base dark:bg-gray-950"
                                            />
                                        </div>

                                        <div class="space-y-2">
                                            <Label for="new_char_backstory" class="font-medium">Their story so far</Label>
                                            <Textarea
                                                id="new_char_backstory"
                                                v-model="newCharacter.backstory"
                                                placeholder="What's their background? Where do they come from?"
                                                rows="2"
                                                class="resize-none rounded-xl border-2 bg-white text-base dark:bg-gray-950"
                                            />
                                        </div>

                                        <div class="flex gap-2">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                @click="cancelCharacterEdit"
                                                :disabled="isSaving"
                                                class="flex-1 cursor-pointer rounded-xl transition-all duration-200 hover:scale-[1.01] active:scale-[0.99]"
                                            >
                                                Cancel
                                            </Button>
                                            <Button
                                                type="button"
                                                @click="addNewCharacter"
                                                :disabled="isSaving"
                                                class="flex-1 cursor-pointer gap-2 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 text-white shadow-lg transition-all duration-200 hover:scale-[1.01] hover:from-emerald-600 hover:to-teal-600 hover:shadow-emerald-500/25 hover:shadow-xl active:scale-[0.99]"
                                            >
                                                <Spinner v-if="isSaving" class="h-4 w-4" />
                                                <Plus v-else class="h-4 w-4" />
                                                {{ isSaving ? 'Saving...' : 'Add Character' }}
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </Transition>
                        </div>

                        <p v-if="characters.length === 0 && !isAddingNewCharacter" class="text-center text-sm text-muted-foreground">
                            Characters are optional - you can always add them later!
                        </p>
</div>

                    <!-- Step 4: First Chapter Setup -->
                    <div v-show="currentStep === 4" class="space-y-6">
                        <div class="space-y-3">
                            <Label for="first_chapter_prompt" class="text-lg font-semibold">
                                How does your story begin? 🌟
                            </Label>
                            <p class="text-sm text-muted-foreground">
                                Set the scene for your opening chapter. Where does it take place? What's happening?
                            </p>
                    <Textarea
                        id="first_chapter_prompt"
                        v-model="formData.first_chapter_prompt"
                                placeholder="Example: The story begins on a stormy night when our hero finds a mysterious letter on their doorstep..."
                                rows="5"
                        :disabled="processing"
                                class="resize-none rounded-2xl border-2 text-base leading-relaxed focus:ring-2 focus:ring-primary/20"
                    />
                    <InputError :message="errors.first_chapter_prompt" />
                </div>

                        <div class="space-y-3">
                            <Label for="scene" class="text-lg font-semibold">
                                Where does it happen? 🏔️
                            </Label>
                            <p class="text-sm text-muted-foreground">
                                Describe the setting - is it a magical forest, a busy city, or somewhere else entirely?
                            </p>
                    <Textarea
                                id="scene"
                                v-model="formData.scene"
                                placeholder="Example: A cozy cottage at the edge of an enchanted forest, where fireflies dance at night..."
                                rows="4"
                        :disabled="processing"
                                class="resize-none rounded-2xl border-2 text-base leading-relaxed focus:ring-2 focus:ring-primary/20"
                            />
                            <InputError :message="errors.scene" />
                        </div>
                    </div>

                    <InputError v-if="errors.general" :message="errors.general" class="mt-4" />
                </form>
                </div>

            <!-- Footer Navigation -->
            <div class="flex items-center justify-between border-t bg-muted/30 px-6 py-5">
                <Button
                    v-if="currentStep > 1"
                    type="button"
                    variant="ghost"
                    @click="prevStep"
                    :disabled="processing || isSaving || isExtractingCharacters"
                    class="h-14 cursor-pointer gap-3 rounded-2xl px-6 text-lg font-semibold transition-all duration-200 hover:-translate-x-0.5 hover:bg-muted"
                >
                    <ChevronLeft class="h-6 w-6" />
                    Back
                </Button>
                <div v-else />

                <div class="flex gap-4">
                    <Button
                        type="button"
                        variant="outline"
                        @click="requestClose"
                        :disabled="processing || isSaving"
                        class="h-14 cursor-pointer rounded-2xl px-8 text-lg font-semibold transition-all duration-200 hover:scale-[1.02] active:scale-[0.98]"
                    >
                        Cancel
                    </Button>
                    
                    <Button
                        v-if="currentStep < totalSteps"
                        type="button"
                        @click="nextStep"
                        :disabled="!canProceed || processing || isExtractingCharacters || isSaving"
                        class="h-14 cursor-pointer gap-3 rounded-2xl bg-gradient-to-r from-orange-500 to-amber-500 px-10 text-lg font-semibold text-white shadow-lg transition-all duration-200 hover:translate-x-0.5 hover:from-orange-600 hover:to-amber-600 hover:shadow-orange-500/25 hover:shadow-xl active:scale-[0.98] disabled:opacity-50 disabled:shadow-none"
                    >
                        <Spinner v-if="isExtractingCharacters || isSaving" class="h-5 w-5" />
                        <template v-if="isSaving && currentStep === 1">
                            Creating Book...
                        </template>
                        <template v-else-if="isSaving && currentStep === 2">
                            Saving Plot...
                        </template>
                        <template v-else-if="isExtractingCharacters">
                            Finding Characters...
                        </template>
                        <template v-else>
                            Next
                            <ChevronRight class="h-6 w-6 transition-transform group-hover:translate-x-0.5" />
                        </template>
                    </Button>
                    
                    <Button
                        v-else
                        type="button"
                        @click="handleSubmit"
                        :disabled="processing || isGeneratingCover"
                        class="h-14 cursor-pointer gap-3 rounded-2xl bg-gradient-to-r from-violet-600 to-purple-600 px-10 text-lg font-semibold shadow-lg transition-all duration-200 hover:scale-[1.02] hover:from-violet-700 hover:to-purple-700 hover:shadow-violet-500/25 hover:shadow-xl active:scale-[0.98]"
                    >
                        <Spinner v-if="processing || isGeneratingCover" class="h-5 w-5" />
                        <Sparkles v-else class="h-5 w-5" />
                        <template v-if="isGeneratingCover">
                            {{ coverGenerationStatus || 'Creating cover...' }}
                        </template>
                        <template v-else-if="processing">
                            Creating...
                        </template>
                        <template v-else>
                            Create My Story!
                        </template>
                    </Button>
                </div>
            </div>
            </div><!-- End content wrapper -->
        </DialogContent>
    </Dialog>
</template>

<style scoped>
@keyframes bounce-in {
    0% {
        opacity: 0;
        transform: scale(0.3) translateY(-20px);
    }
    50% {
        opacity: 1;
        transform: scale(1.05) translateY(0);
    }
    70% {
        transform: scale(0.95);
    }
    100% {
        transform: scale(1);
    }
}

.animate-bounce-in {
    animation: bounce-in 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
}
</style>
