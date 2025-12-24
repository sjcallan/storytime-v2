<?php

namespace App\Events\ConversationMessage;

use App\Models\ConversationMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CharacterChatResponseEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public ConversationMessage $conversationMessage)
    {
        //
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('conversation.'.$this->conversationMessage->conversation_id),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'character.chat.response';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->conversationMessage->id,
            'conversation_id' => $this->conversationMessage->conversation_id,
            'message' => $this->conversationMessage->message,
            'response' => $this->conversationMessage->response,
            'character_id' => $this->conversationMessage->character_id,
            'created_at' => $this->conversationMessage->created_at?->toISOString(),
            'updated_at' => $this->conversationMessage->updated_at?->toISOString(),
        ];
    }
}
