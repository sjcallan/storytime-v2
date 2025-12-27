<?php

namespace App\Events\Character;

use App\Models\Character;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CharacterCreatedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public Character $character)
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
            new PrivateChannel('book.'.$this->character->book_id),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'character.created';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->character->id,
            'book_id' => $this->character->book_id,
            'name' => $this->character->name,
            'gender' => $this->character->gender,
            'description' => $this->character->description,
            'type' => $this->character->type,
            'age' => $this->character->age,
            'nationality' => $this->character->nationality,
            'backstory' => $this->character->backstory,
            'portrait_image' => $this->character->portrait_image,
            'created_at' => $this->character->created_at?->toIso8601String(),
        ];
    }
}
