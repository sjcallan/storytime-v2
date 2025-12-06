<?php

namespace App\Events\Book;

use App\Models\Book;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BookUpdatedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public Book $book)
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
            new PrivateChannel('book.'.$this->book->id),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'book.updated';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->book->id,
            'title' => $this->book->title,
            'genre' => $this->book->genre,
            'author' => $this->book->author,
            'age_level' => $this->book->age_level,
            'status' => $this->book->status,
            'cover_image' => $this->book->cover_image,
            'plot' => $this->book->plot,
            'is_published' => $this->book->is_published,
            'updated_at' => $this->book->updated_at,
        ];
    }
}
