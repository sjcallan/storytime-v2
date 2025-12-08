<script setup lang="ts">
import { computed, ref, onUnmounted } from 'vue';
import { Textarea } from '@/components/ui/textarea';
import { Spinner } from '@/components/ui/spinner';
import { Wand2, Sparkles, BookOpen, Check, Mic, Square } from 'lucide-vue-next';
import { apiFetch } from '@/composables/ApiFetch';
import type { BookType } from './types';
import { getChapterLabel, isSceneBasedBook } from './types';

interface Props {
    chapterNumber: number;
    prompt: string;
    isFinalChapter: boolean;
    isGenerating: boolean;
    bookType?: BookType;
    suggestedPlaceholder?: string | null;
    isLoadingPlaceholder?: boolean;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'update:prompt', value: string): void;
    (e: 'update:isFinalChapter', value: boolean): void;
    (e: 'generate'): void;
    (e: 'textareaFocused', value: boolean): void;
}>();

const chapterLabel = computed(() => getChapterLabel(props.bookType));
const isScript = computed(() => isSceneBasedBook(props.bookType));

// Computed placeholder - use AI suggestion or fall back to default
const placeholderText = computed(() => {
    if (props.isLoadingPlaceholder) {
        return 'Thinking of ideas...';
    }
    if (props.suggestedPlaceholder) {
        return props.suggestedPlaceholder;
    }
    // Default placeholder
    return isScript.value 
        ? 'The tension rises as the door slowly creaks open...'
        : 'The hero discovers a hidden door behind the waterfall...';
});

// Voice recording state
const isRecording = ref(false);
const isTranscribing = ref(false);
const transcribeStatus = ref('');
const transcribeError = ref('');
const mediaRecorder = ref<MediaRecorder | null>(null);
const audioChunks = ref<Blob[]>([]);
const recordingDuration = ref(0);
const recordingInterval = ref<ReturnType<typeof setInterval> | null>(null);

// Audio visualization state
const audioContext = ref<AudioContext | null>(null);
const analyser = ref<AnalyserNode | null>(null);
const animationFrameId = ref<number | null>(null);
const audioLevels = ref<number[]>(Array(12).fill(0));

const PROMPT_MAX_LENGTH = 2000;

type ApiFetchFn = (
    request: string,
    method?: string,
    data?: Record<string, unknown> | FormData | null,
    isFormData?: boolean | null,
) => Promise<{ data: unknown; error: unknown }>;

const requestApiFetch = apiFetch as ApiFetchFn;

const formatRecordingTime = (seconds: number): string => {
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins}:${secs.toString().padStart(2, '0')}`;
};

const updateAudioLevels = () => {
    if (!analyser.value) return;
    
    const dataArray = new Uint8Array(analyser.value.frequencyBinCount);
    analyser.value.getByteFrequencyData(dataArray);
    
    // Sample different frequency ranges for visualization
    const binSize = Math.floor(dataArray.length / 12);
    const newLevels = Array(12).fill(0).map((_, i) => {
        const start = i * binSize;
        const end = start + binSize;
        let sum = 0;
        for (let j = start; j < end; j++) {
            sum += dataArray[j];
        }
        // Normalize to 0-100 range with some boosting for visibility
        return Math.min(100, (sum / binSize) * 0.6);
    });
    
    audioLevels.value = newLevels;
    
    if (isRecording.value) {
        animationFrameId.value = requestAnimationFrame(updateAudioLevels);
    }
};

const startRecording = async () => {
    transcribeError.value = '';
    transcribeStatus.value = '';
    
    try {
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        
        // Set up audio context for visualization
        audioContext.value = new AudioContext();
        analyser.value = audioContext.value.createAnalyser();
        analyser.value.fftSize = 256;
        
        const source = audioContext.value.createMediaStreamSource(stream);
        source.connect(analyser.value);
        
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
            
            if (animationFrameId.value) {
                cancelAnimationFrame(animationFrameId.value);
                animationFrameId.value = null;
            }
            
            if (audioContext.value) {
                audioContext.value.close();
                audioContext.value = null;
            }
            
            audioLevels.value = Array(12).fill(0);
            
            if (audioChunks.value.length > 0) {
                await transcribeAudio();
            }
        };
        
        mediaRecorder.value.start(1000);
        isRecording.value = true;
        
        // Start visualization
        updateAudioLevels();
        
        recordingInterval.value = setInterval(() => {
            recordingDuration.value++;
            if (recordingDuration.value >= 60) {
                stopRecording();
            }
        }, 1000);
        
    } catch (err) {
        console.error('Failed to start recording:', err);
        transcribeError.value = 'Could not access microphone. Please check your permissions.';
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
    transcribeError.value = '';
    
    try {
        const mimeType = mediaRecorder.value?.mimeType || 'audio/webm';
        const audioBlob = new Blob(audioChunks.value, { type: mimeType });
        
        const formDataUpload = new FormData();
        const extension = mimeType.includes('webm') ? 'webm' : 'mp4';
        formDataUpload.append('audio', audioBlob, `recording.${extension}`);
        
        transcribeStatus.value = 'Converting speech to text...';
        
        const { data, error } = await requestApiFetch('/api/transcribe', 'POST', formDataUpload, true);
        
        if (error) {
            const message = error instanceof Error ? error.message : 'Failed to transcribe audio. Please try again or type your prompt.';
            transcribeError.value = message;
            transcribeStatus.value = '';
        } else if (data && typeof data === 'object' && 'text' in data) {
            const transcribedText = (data as { text: string }).text;
            if (transcribedText) {
                transcribeStatus.value = 'Done! Text added below.';
                const currentPrompt = props.prompt || '';
                const newPrompt = currentPrompt 
                    ? currentPrompt + ' ' + transcribedText 
                    : transcribedText;
                emit('update:prompt', newPrompt.slice(0, PROMPT_MAX_LENGTH));
                
                setTimeout(() => {
                    transcribeStatus.value = '';
                }, 2000);
            } else {
                transcribeError.value = "We couldn't hear anything. Please try again.";
                transcribeStatus.value = '';
            }
        } else if (data && typeof data === 'object' && 'error' in data) {
            transcribeError.value = (data as { error: string }).error;
            transcribeStatus.value = '';
        } else {
            transcribeError.value = 'Unexpected response. Please try again.';
            transcribeStatus.value = '';
        }
    } catch (err) {
        console.error('Transcription error:', err);
        transcribeError.value = 'Failed to transcribe audio. Please try again or type your prompt.';
        transcribeStatus.value = '';
    } finally {
        isTranscribing.value = false;
        audioChunks.value = [];
    }
};

// Cleanup on unmount
onUnmounted(() => {
    if (recordingInterval.value) {
        clearInterval(recordingInterval.value);
    }
    if (animationFrameId.value) {
        cancelAnimationFrame(animationFrameId.value);
    }
    if (audioContext.value) {
        audioContext.value.close();
    }
    if (mediaRecorder.value && isRecording.value) {
        mediaRecorder.value.stop();
    }
});
</script>

<template>
    <div class="relative z-10 flex h-full flex-col items-center justify-center p-8">
        <!-- Decorative top flourish -->
        <div class="absolute top-8 left-1/2 -translate-x-1/2 flex items-center gap-3 opacity-40">
            <div class="h-px w-16 bg-linear-to-r from-transparent to-amber-700 dark:to-amber-600" />
            <Sparkles class="h-4 w-4 text-amber-700 dark:text-amber-600" />
            <div class="h-px w-16 bg-linear-to-l from-transparent to-amber-700 dark:to-amber-600" />
        </div>

        <!-- Centered content container -->
        <div class="w-full max-w-sm space-y-6">
            <!-- Header -->
            <div class="text-center">
                
                <h2 class="font-serif text-3xl font-bold text-stone-800 dark:text-stone-900">
                    {{ isScript ? 'Continue the Script...' : 'Continue the Story...' }}
                </h2>
                <p class="mt-2 text-base text-stone-600 dark:text-stone-700">
                    {{ isScript 
                        ? `What happens in the next scene? Share your ideas below. (or leave empty for a surprise!)`
                        : `What adventure awaits in the next chapter? Share your ideas below. (or leave empty for a surprise!)` 
                    }}
                </p>
            </div>
            
            <!-- Transcription Status Message -->
            <div 
                v-if="transcribeStatus" 
                class="flex items-center gap-2 rounded-xl bg-blue-50 px-4 py-3 text-sm text-blue-700 dark:bg-blue-100 dark:text-blue-800"
            >
                <Spinner v-if="isTranscribing" class="h-4 w-4" />
                <Check v-else class="h-4 w-4 text-green-600" />
                {{ transcribeStatus }}
            </div>
            
            <!-- Transcription Error Message -->
            <div 
                v-if="transcribeError" 
                class="flex items-center gap-2 rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700 dark:bg-red-100 dark:text-red-800"
            >
                {{ transcribeError }}
            </div>
            
            <!-- Prompt textarea -->
            <div class="space-y-2">
                <Textarea
                    :model-value="prompt"
                    @update:model-value="emit('update:prompt', String($event))"
                    @focus="emit('textareaFocused', true)"
                    @blur="emit('textareaFocused', false)"
                    :placeholder="placeholderText"
                    rows="4"
                    :disabled="isGenerating || isRecording || isTranscribing"
                    :class="[
                        'w-full resize-none border-stone-300 bg-white font-serif text-lg text-stone-800 focus:border-amber-600 focus:ring-amber-600/30 dark:border-stone-400 dark:bg-white/90 dark:text-stone-900',
                        props.isLoadingPlaceholder 
                            ? 'placeholder:text-amber-500 placeholder:animate-pulse placeholder:italic' 
                            : props.suggestedPlaceholder 
                                ? 'placeholder:text-amber-700 placeholder:italic dark:placeholder:text-amber-600' 
                                : 'placeholder:text-stone-400 dark:placeholder:text-stone-500'
                    ]"
                />
            </div>
            
            <!-- Voice Recording Section -->
            <div class="flex flex-col items-center gap-3">
                <!-- Idle State: Speak Button -->
                <button
                    v-if="!isRecording && !isTranscribing"
                    type="button"
                    @click="startRecording"
                    :disabled="isGenerating"
                    class="group flex cursor-pointer items-center gap-2 rounded-full bg-linear-to-r from-amber-600 to-orange-500 px-5 py-2.5 text-sm font-medium text-white shadow-lg transition-all duration-300 hover:scale-105 hover:shadow-amber-500/25 hover:shadow-xl active:scale-95 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <Mic class="h-5 w-5" />
                    <span>Speak Your Ideas</span>
                </button>
                
                <!-- Recording State: Waveform + Stop Button -->
                <div v-if="isRecording" class="flex w-full flex-col items-center gap-3">
                    <!-- Audio Waveform Visualization -->
                    <div class="flex h-12 w-full items-center justify-center gap-1 rounded-xl bg-red-50 px-4 dark:bg-red-100">
                        <div 
                            v-for="(level, index) in audioLevels" 
                            :key="index"
                            class="w-2 rounded-full bg-linear-to-t from-red-500 to-red-400 transition-all duration-75"
                            :style="{ 
                                height: `${Math.max(4, level * 0.4)}px`,
                                opacity: level > 5 ? 1 : 0.4
                            }"
                        />
                    </div>
                    
                    <!-- Recording Controls -->
                    <div class="flex items-center gap-3">
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
                    <p class="text-xs text-stone-500">Tap the square to stop recording</p>
                </div>
                
                <!-- Transcribing State -->
                <div v-if="isTranscribing" class="flex items-center gap-2 rounded-full bg-blue-500 px-4 py-2 text-sm font-medium text-white shadow-lg">
                    <Spinner class="h-4 w-4" />
                    <span>Processing...</span>
                </div>
            </div>
            
            <!-- Final chapter question -->
            <div class="rounded-xl border border-stone-200 bg-stone-50 p-5 dark:border-stone-300 dark:bg-stone-100">
                <p class="mb-4 text-center text-base font-medium text-stone-700 dark:text-stone-800">
                    Will this be the final {{ chapterLabel.toLowerCase() }}?
                </p>
                <div class="flex items-center justify-center gap-4">
                    <button 
                        type="button"
                        @click="emit('update:isFinalChapter', true)"
                        :disabled="isGenerating"
                        :class="[
                            'group relative flex items-center gap-2 rounded-full px-6 py-2.5 text-base font-medium transition-all duration-200',
                            isFinalChapter 
                                ? 'bg-amber-800 text-white shadow-md' 
                                : 'bg-white text-stone-700 hover:bg-stone-100 border border-stone-300 dark:bg-white dark:text-stone-800 dark:border-stone-400 dark:hover:bg-stone-50',
                            'disabled:opacity-50 disabled:cursor-not-allowed'
                        ]"
                    >
                        <Check v-if="isFinalChapter" class="h-4 w-4" />
                        <span>Yes</span>
                    </button>
                    <button 
                        type="button"
                        @click="emit('update:isFinalChapter', false)"
                        :disabled="isGenerating"
                        :class="[
                            'group relative flex items-center gap-2 rounded-full px-6 py-2.5 text-base font-medium transition-all duration-200',
                            !isFinalChapter 
                                ? 'bg-amber-800 text-white shadow-md' 
                                : 'bg-white text-stone-700 hover:bg-stone-100 border border-stone-300 dark:bg-white dark:text-stone-800 dark:border-stone-400 dark:hover:bg-stone-50',
                            'disabled:opacity-50 disabled:cursor-not-allowed'
                        ]"
                    >
                        <Check v-if="!isFinalChapter" class="h-4 w-4" />
                        <span>No</span>
                    </button>
                </div>
            </div>
            
            <!-- Generate button -->
            <button 
                @click="emit('generate')"
                :disabled="isGenerating"
                class="group w-full cursor-pointer rounded-full bg-amber-800 px-6 py-3.5 text-base font-semibold text-white shadow-lg transition-all duration-300 hover:bg-amber-900 hover:shadow-xl hover:scale-[1.02] active:scale-[0.98] disabled:opacity-60 disabled:cursor-not-allowed disabled:hover:scale-100"
            >
                <span class="flex items-center justify-center gap-2">
                    <Wand2 :class="['h-5 w-5 transition-transform duration-300', isGenerating ? 'animate-pulse' : 'group-hover:rotate-12']" />
                    <span>{{ isGenerating 
                        ? (isScript ? 'Writing your script...' : 'Crafting your story...') 
                        : `Create ${chapterLabel}` 
                    }}</span>
                </span>
            </button>
        </div>

        <!-- Decorative bottom flourish -->
        <div class="absolute bottom-8 left-1/2 -translate-x-1/2 flex items-center gap-3 opacity-30">
            <div class="h-px w-12 bg-linear-to-r from-transparent to-amber-700 dark:to-amber-600" />
            <div class="h-1.5 w-1.5 rounded-full bg-amber-700 dark:bg-amber-600" />
            <div class="h-px w-12 bg-linear-to-l from-transparent to-amber-700 dark:to-amber-600" />
        </div>
    </div>
</template>

