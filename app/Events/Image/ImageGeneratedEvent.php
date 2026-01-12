<?php

namespace App\Events\Image;

use App\Models\Image;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ImageGeneratedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public Image $image) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [];

        // Always broadcast on book channel if book_id exists
        if ($this->image->book_id) {
            $channels[] = new PrivateChannel('book.'.$this->image->book_id);
        }

        // Also broadcast on chapter channel for chapter-specific images
        if ($this->image->chapter_id) {
            $channels[] = new PrivateChannel('chapter.'.$this->image->chapter_id);
        }

        // Also broadcast on character channel for character-specific images
        if ($this->image->character_id) {
            $channels[] = new PrivateChannel('character.'.$this->image->character_id);
        }

        return $channels;
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'image.generated';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->image->id,
            'book_id' => $this->image->book_id,
            'chapter_id' => $this->image->chapter_id,
            'character_id' => $this->image->character_id,
            'type' => $this->image->type->value,
            'image_url' => $this->image->image_url,
            'full_url' => $this->image->full_url,
            'prompt' => $this->image->prompt,
            'error' => $this->image->error,
            'status' => $this->image->status->value,
            'paragraph_index' => $this->image->paragraph_index,
            'aspect_ratio' => $this->image->aspect_ratio,
            'updated_at' => $this->image->updated_at?->toISOString(),
        ];
    }
}
