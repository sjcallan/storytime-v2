<script setup lang="ts">
import { ref, watch, nextTick, onBeforeUnmount, computed } from 'vue';
import { apiFetch } from '@/composables/ApiFetch';
import { echo } from '@laravel/echo-vue';
import { X, Send, MessageCircle, User, Loader2 } from 'lucide-vue-next';
import type { Character } from './types';

interface Props {
    visible: boolean;
    character: Character;
}

interface ChatMessage {
    id: string;
    message: string;
    response: string | null;
    created_at: string;
}

interface Conversation {
    id: string;
    character_id: string;
    character_name: string;
    messages: ChatMessage[];
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'close'): void;
}>();

const conversation = ref<Conversation | null>(null);
const messages = ref<ChatMessage[]>([]);
const newMessage = ref('');
const isLoading = ref(false);
const isSending = ref(false);
const messagesContainer = ref<HTMLElement | null>(null);
const inputRef = ref<HTMLTextAreaElement | null>(null);

 
const chatChannel = ref<any>(null);

// Auto-resize textarea
const textareaHeight = computed(() => {
    const lineCount = (newMessage.value.match(/\n/g) || []).length + 1;
    const baseHeight = 44;
    const lineHeight = 24;
    return Math.min(baseHeight + (lineCount - 1) * lineHeight, 120);
});

const scrollToBottom = async () => {
    await nextTick();
    if (messagesContainer.value) {
        messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
    }
};

const getAvatarGradient = (characterId: string): string => {
    const gradients = [
        'from-violet-500 to-purple-600',
        'from-blue-500 to-cyan-600',
        'from-emerald-500 to-teal-600',
        'from-amber-500 to-orange-600',
        'from-rose-500 to-pink-600',
        'from-indigo-500 to-blue-600',
        'from-fuchsia-500 to-pink-600',
        'from-sky-500 to-blue-600',
    ];
    
    const hash = characterId.split('').reduce((acc, char) => acc + char.charCodeAt(0), 0);
    return gradients[hash % gradients.length];
};

const getInitials = (name: string): string => {
    return name
        .split(' ')
        .map(word => word[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);
};

const loadConversation = async () => {
    if (!props.character?.id) return;
    
    isLoading.value = true;
    
    try {
        const { data, error } = await apiFetch(`/api/characters/${props.character.id}/chat`, 'GET');
        
        if (error) {
            console.error('Failed to load conversation:', error);
            return;
        }
        
        const result = data as { conversation: Conversation };
        conversation.value = result.conversation;
        messages.value = result.conversation.messages || [];
        
        subscribeToChat(result.conversation.id);
        await scrollToBottom();
    } catch (err) {
        console.error('Error loading conversation:', err);
    } finally {
        isLoading.value = false;
    }
};

const sendMessage = async () => {
    if (!newMessage.value.trim() || isSending.value || !conversation.value) return;
    
    const messageText = newMessage.value.trim();
    newMessage.value = '';
    isSending.value = true;
    
    // Optimistically add message to UI
    const tempId = `temp-${Date.now()}`;
    const tempMessage: ChatMessage = {
        id: tempId,
        message: messageText,
        response: null,
        created_at: new Date().toISOString(),
    };
    messages.value.push(tempMessage);
    await scrollToBottom();
    
    try {
        const { data, error } = await apiFetch(
            `/api/conversations/${conversation.value.id}/chat`,
            'POST',
            { message: messageText }
        );
        
        if (error) {
            console.error('Failed to send message:', error);
            // Remove temp message on error
            messages.value = messages.value.filter(m => m.id !== tempId);
            newMessage.value = messageText;
            return;
        }
        
        const result = data as { message: ChatMessage };
        // Replace temp message with real one
        const tempIndex = messages.value.findIndex(m => m.id === tempId);
        if (tempIndex !== -1) {
            messages.value[tempIndex] = result.message;
        }
    } catch (err) {
        console.error('Error sending message:', err);
        messages.value = messages.value.filter(m => m.id !== tempId);
        newMessage.value = messageText;
    } finally {
        isSending.value = false;
    }
};

const handleKeyDown = (event: KeyboardEvent) => {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        sendMessage();
    }
};

const subscribeToChat = (conversationId: string) => {
    if (chatChannel.value) {
        unsubscribeFromChat();
    }
    
    try {
        const channel = echo().private(`conversation.${conversationId}`);
        channel.listen('.character.chat.response', handleChatResponse);
        chatChannel.value = channel;
    } catch (err) {
        console.error('[Echo] Failed to subscribe to conversation:', err);
    }
};

const handleChatResponse = (payload: ChatMessage) => {
    // Find and update the message with the response
    const messageIndex = messages.value.findIndex(m => m.id === payload.id);
    if (messageIndex !== -1) {
        messages.value[messageIndex] = {
            ...messages.value[messageIndex],
            response: payload.response,
        };
    } else {
        // Message not found, might be from another tab/device, add it
        const existingMessage = messages.value.find(m => m.message === payload.message);
        if (existingMessage) {
            existingMessage.response = payload.response;
        }
    }
    scrollToBottom();
};

const unsubscribeFromChat = () => {
    if (!chatChannel.value || !conversation.value) return;
    
    try {
        chatChannel.value.stopListening('.character.chat.response');
        echo().leave(`conversation.${conversation.value.id}`);
    } catch {
        // Ignore cleanup errors
    }
    chatChannel.value = null;
};

const handleClose = () => {
    unsubscribeFromChat();
    emit('close');
};

// Watch for visibility changes
watch(() => props.visible, async (isVisible) => {
    if (isVisible) {
        await loadConversation();
        await nextTick();
        inputRef.value?.focus();
    } else {
        unsubscribeFromChat();
    }
});

// Watch for character changes
watch(() => props.character?.id, async (newId, oldId) => {
    if (newId && newId !== oldId && props.visible) {
        unsubscribeFromChat();
        conversation.value = null;
        messages.value = [];
        await loadConversation();
    }
});

onBeforeUnmount(() => {
    unsubscribeFromChat();
});
</script>

<template>
    <Transition
        enter-active-class="transition-all duration-300 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition-all duration-200 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div 
            v-if="visible" 
            class="fixed inset-0 z-[100] flex items-center justify-center bg-black/60 backdrop-blur-sm p-4"
            @click.self="handleClose"
        >
            <Transition
                enter-active-class="transition-all duration-300 ease-out delay-75"
                enter-from-class="opacity-0 scale-95 translate-y-4"
                enter-to-class="opacity-100 scale-100 translate-y-0"
                leave-active-class="transition-all duration-200 ease-in"
                leave-from-class="opacity-100 scale-100"
                leave-to-class="opacity-0 scale-95"
            >
                <div 
                    v-if="visible"
                    class="relative flex h-[85vh] max-h-[700px] w-full max-w-lg flex-col overflow-hidden rounded-2xl bg-gradient-to-br from-amber-50 via-orange-50 to-yellow-50 shadow-2xl dark:from-gray-900 dark:via-gray-900 dark:to-gray-800"
                >
                    <!-- Header -->
                    <div class="relative flex items-center gap-3 border-b border-amber-200/50 bg-gradient-to-r from-amber-100/80 to-orange-100/80 px-4 py-3 dark:border-gray-700 dark:from-gray-800/80 dark:to-gray-800/80">
                        <!-- Character Avatar -->
                        <div 
                            :class="[
                                'h-12 w-12 shrink-0 rounded-full overflow-hidden ring-2 ring-amber-300 dark:ring-amber-600 shadow-md',
                            ]"
                        >
                            <img
                                v-if="character.portrait_image"
                                :src="character.portrait_image"
                                :alt="character.name"
                                class="h-full w-full object-cover"
                            />
                            <div
                                v-else
                                :class="[
                                    'h-full w-full flex items-center justify-center bg-gradient-to-br',
                                    getAvatarGradient(character.id)
                                ]"
                            >
                                <span class="text-lg font-bold text-white">
                                    {{ getInitials(character.name) }}
                                </span>
                            </div>
                        </div>
                        
                        <!-- Character Info -->
                        <div class="min-w-0 flex-1">
                            <h3 class="truncate font-serif text-lg font-bold text-amber-900 dark:text-amber-100">
                                {{ character.name }}
                            </h3>
                            <p class="flex items-center gap-1 text-xs text-amber-700/80 dark:text-amber-300/70">
                                <MessageCircle class="h-3 w-3" />
                                <span>Chat with character</span>
                            </p>
                        </div>
                        
                        <!-- Close Button -->
                        <button
                            @click="handleClose"
                            class="rounded-full p-2 text-amber-700 transition-all hover:bg-amber-200/50 hover:text-amber-900 dark:text-amber-300 dark:hover:bg-gray-700 dark:hover:text-amber-100"
                        >
                            <X class="h-5 w-5" />
                        </button>
                    </div>
                    
                    <!-- Messages Area -->
                    <div 
                        ref="messagesContainer"
                        class="flex-1 overflow-y-auto p-4 space-y-4 scrollbar-thin scrollbar-thumb-amber-300 scrollbar-track-transparent"
                    >
                        <!-- Loading State -->
                        <div v-if="isLoading" class="flex h-full items-center justify-center">
                            <div class="flex flex-col items-center gap-3">
                                <Loader2 class="h-8 w-8 animate-spin text-amber-600" />
                                <p class="text-sm text-amber-700 dark:text-amber-300">Loading conversation...</p>
                            </div>
                        </div>
                        
                        <!-- Empty State -->
                        <div 
                            v-else-if="messages.length === 0" 
                            class="flex h-full flex-col items-center justify-center text-center px-4"
                        >
                            <div 
                                :class="[
                                    'h-20 w-20 rounded-full overflow-hidden ring-4 ring-amber-200/50 dark:ring-amber-700/50 shadow-lg mb-4',
                                ]"
                            >
                                <img
                                    v-if="character.portrait_image"
                                    :src="character.portrait_image"
                                    :alt="character.name"
                                    class="h-full w-full object-cover"
                                />
                                <div
                                    v-else
                                    :class="[
                                        'h-full w-full flex items-center justify-center bg-gradient-to-br',
                                        getAvatarGradient(character.id)
                                    ]"
                                >
                                    <span class="text-2xl font-bold text-white">
                                        {{ getInitials(character.name) }}
                                    </span>
                                </div>
                            </div>
                            <h4 class="font-serif text-lg font-semibold text-amber-900 dark:text-amber-100 mb-2">
                                Start a conversation with {{ character.name }}
                            </h4>
                            <p class="text-sm text-amber-700/80 dark:text-amber-300/70 max-w-xs">
                                Say hello and ask them about their story, adventures, or anything you'd like to know!
                            </p>
                        </div>
                        
                        <!-- Messages -->
                        <template v-else>
                            <div 
                                v-for="msg in messages" 
                                :key="msg.id"
                                class="space-y-3"
                            >
                                <!-- User Message -->
                                <div class="flex justify-end">
                                    <div class="max-w-[80%] rounded-2xl rounded-br-md bg-gradient-to-r from-amber-500 to-orange-500 px-4 py-2.5 text-white shadow-md">
                                        <p class="text-sm whitespace-pre-wrap">{{ msg.message }}</p>
                                    </div>
                                </div>
                                
                                <!-- Character Response -->
                                <div class="flex items-start gap-2">
                                    <!-- Mini Avatar -->
                                    <div 
                                        :class="[
                                            'h-8 w-8 shrink-0 rounded-full overflow-hidden ring-2 ring-amber-200 dark:ring-amber-700',
                                        ]"
                                    >
                                        <img
                                            v-if="character.portrait_image"
                                            :src="character.portrait_image"
                                            :alt="character.name"
                                            class="h-full w-full object-cover"
                                        />
                                        <div
                                            v-else
                                            :class="[
                                                'h-full w-full flex items-center justify-center bg-gradient-to-br',
                                                getAvatarGradient(character.id)
                                            ]"
                                        >
                                            <span class="text-xs font-bold text-white">
                                                {{ getInitials(character.name).charAt(0) }}
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <!-- Response Bubble -->
                                    <div 
                                        v-if="msg.response"
                                        class="max-w-[80%] rounded-2xl rounded-bl-md bg-white px-4 py-2.5 shadow-md dark:bg-gray-800"
                                    >
                                        <p class="text-sm text-gray-800 dark:text-gray-200 whitespace-pre-wrap">{{ msg.response }}</p>
                                    </div>
                                    
                                    <!-- Typing Indicator -->
                                    <div 
                                        v-else
                                        class="rounded-2xl rounded-bl-md bg-white px-4 py-3 shadow-md dark:bg-gray-800"
                                    >
                                        <div class="flex items-center gap-1">
                                            <span class="h-2 w-2 rounded-full bg-amber-400 animate-bounce" style="animation-delay: 0ms"></span>
                                            <span class="h-2 w-2 rounded-full bg-amber-400 animate-bounce" style="animation-delay: 150ms"></span>
                                            <span class="h-2 w-2 rounded-full bg-amber-400 animate-bounce" style="animation-delay: 300ms"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                    
                    <!-- Input Area -->
                    <div class="border-t border-amber-200/50 bg-white/80 p-3 dark:border-gray-700 dark:bg-gray-800/80">
                        <div class="flex items-end gap-2">
                            <div class="relative flex-1">
                                <textarea
                                    ref="inputRef"
                                    v-model="newMessage"
                                    @keydown="handleKeyDown"
                                    :disabled="isLoading || isSending"
                                    :style="{ height: `${textareaHeight}px` }"
                                    placeholder="Type a message..."
                                    class="w-full resize-none rounded-xl border-2 border-amber-300 bg-white px-4 py-2.5 text-sm text-gray-900 placeholder-amber-600/60 transition-all focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-400/40 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-500 dark:bg-gray-700 dark:text-gray-100 dark:placeholder-gray-400 dark:focus:border-amber-500 dark:focus:ring-amber-500/30"
                                ></textarea>
                            </div>
                            <button
                                @click="sendMessage"
                                :disabled="!newMessage.trim() || isLoading || isSending"
                                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-gradient-to-r from-amber-500 to-orange-500 text-white shadow-md transition-all hover:from-amber-600 hover:to-orange-600 hover:shadow-lg disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:shadow-md active:scale-95"
                            >
                                <Loader2 v-if="isSending" class="h-5 w-5 animate-spin" />
                                <Send v-else class="h-5 w-5" />
                            </button>
                        </div>
                        <p class="mt-2 text-center text-xs text-amber-600/60 dark:text-amber-400/50">
                            Press Enter to send, Shift+Enter for new line
                        </p>
                    </div>
                </div>
            </Transition>
        </div>
    </Transition>
</template>

<style scoped>
/* Custom scrollbar styling */
.scrollbar-thin::-webkit-scrollbar {
    width: 6px;
}

.scrollbar-thin::-webkit-scrollbar-track {
    background: transparent;
}

.scrollbar-thin::-webkit-scrollbar-thumb {
    background-color: rgb(252 211 77 / 0.5);
    border-radius: 3px;
}

.scrollbar-thin::-webkit-scrollbar-thumb:hover {
    background-color: rgb(252 211 77 / 0.7);
}
</style>

