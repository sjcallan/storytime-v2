<?php

namespace App\Events\Chapter;

use App\Models\Chapter;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChapterUpdatedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public Chapter $chapter)
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
            new PrivateChannel('chapter.'.$this->chapter->id),
            new PrivateChannel('book.'.$this->chapter->book_id),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'chapter.updated';
    }

    /**
     * Get the data to broadcast.
     *
     * Broadcasts only lightweight metadata to stay within Pusher's 10KB limit.
     * The frontend fetches full chapter data via API when needed.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->chapter->id,
            'book_id' => $this->chapter->book_id,
            'title' => $this->chapter->title,
            'sort' => $this->chapter->sort,
            'status' => $this->chapter->status,
            'image' => $this->chapter->image,
            'final_chapter' => $this->chapter->final_chapter,
            'updated_at' => $this->chapter->updated_at,
        ];
    }
}
