<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SendCharacterChatMessageRequest;
use App\Jobs\Conversation\SendCharacterChatMessageJob;
use App\Models\Character;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use Illuminate\Http\JsonResponse;

class CharacterChatController extends Controller
{
    /**
     * Get or create a conversation with a character.
     */
    public function getOrCreateConversation(Character $character): JsonResponse
    {
        $userId = auth()->id();
        $profileId = session('current_profile_id');

        $conversation = Conversation::query()
            ->where('user_id', $userId)
            ->where('character_id', $character->id)
            ->when($profileId, function ($query, $profileId) {
                return $query->where('profile_id', $profileId);
            })
            ->first();

        if (! $conversation) {
            $conversation = Conversation::create([
                'user_id' => $userId,
                'profile_id' => $profileId,
                'character_id' => $character->id,
                'type' => 'character_chat',
                'character_name' => $character->name,
                'character_age' => $character->age,
                'character_gender' => $character->gender,
                'character_nationality' => $character->nationality,
                'character_description' => $character->description,
                'character_backstory' => $character->backstory,
            ]);
        }

        $conversation->load(['messages' => function ($query) {
            $query->orderBy('created_at', 'asc');
        }]);

        return response()->json([
            'conversation' => [
                'id' => $conversation->id,
                'character_id' => $conversation->character_id,
                'character_name' => $conversation->character_name,
                'messages' => $conversation->messages->map(function ($message) {
                    return [
                        'id' => $message->id,
                        'message' => $message->message,
                        'response' => $message->response,
                        'created_at' => $message->created_at?->toISOString(),
                    ];
                }),
            ],
        ]);
    }

    /**
     * Send a message to a character.
     */
    public function sendMessage(SendCharacterChatMessageRequest $request, Conversation $conversation): JsonResponse
    {
        if ($conversation->user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $conversationHistory = $this->buildConversationHistory($conversation);

        $message = ConversationMessage::create([
            'conversation_id' => $conversation->id,
            'character_id' => $conversation->character_id,
            'message' => $request->validated('message'),
            'response' => null,
        ]);

        dispatch(new SendCharacterChatMessageJob($message, $conversationHistory));

        return response()->json([
            'message' => [
                'id' => $message->id,
                'conversation_id' => $message->conversation_id,
                'message' => $message->message,
                'response' => null,
                'created_at' => $message->created_at?->toISOString(),
            ],
        ], 201);
    }

    /**
     * Get conversation history.
     */
    public function getHistory(Conversation $conversation): JsonResponse
    {
        if ($conversation->user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $conversation->load(['messages' => function ($query) {
            $query->orderBy('created_at', 'asc');
        }, 'character']);

        return response()->json([
            'conversation' => [
                'id' => $conversation->id,
                'character_id' => $conversation->character_id,
                'character_name' => $conversation->character_name,
                'character' => $conversation->character ? [
                    'id' => $conversation->character->id,
                    'name' => $conversation->character->name,
                    'portrait_image' => $conversation->character->portrait_image,
                ] : null,
                'messages' => $conversation->messages->map(function ($message) {
                    return [
                        'id' => $message->id,
                        'message' => $message->message,
                        'response' => $message->response,
                        'created_at' => $message->created_at?->toISOString(),
                    ];
                }),
            ],
        ]);
    }

    /**
     * Build conversation history for the AI.
     *
     * @return array<int, array{role: string, content: string}>
     */
    protected function buildConversationHistory(Conversation $conversation): array
    {
        $history = [];
        $messages = $conversation->messages()
            ->whereNotNull('response')
            ->orderBy('created_at', 'asc')
            ->limit(20)
            ->get();

        foreach ($messages as $msg) {
            if ($msg->message) {
                $history[] = [
                    'role' => 'user',
                    'content' => $msg->message,
                ];
            }
            if ($msg->response) {
                $history[] = [
                    'role' => 'assistant',
                    'content' => $msg->response,
                ];
            }
        }

        return $history;
    }
}
