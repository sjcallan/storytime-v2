<?php

namespace App\Observers;

use App\Events\Book\BookCreatedEvent;
use App\Events\Book\BookDeletedEvent;
use App\Events\Book\BookUpdatedEvent;
use App\Models\Book;
use Illuminate\Support\Facades\Event;

class BookObserver
{
    /**
     * Handle the Book "created" event.
     */
    public function created(Book $book): void
    {
        Event::dispatch(new BookCreatedEvent($book));
    }

    /**
     * Handle the Book "updated" event.
     */
    public function updated(Book $book): void
    {
        Event::dispatch(new BookUpdatedEvent($book));
    }

    /**
     * Handle the Book "deleted" event.
     */
    public function deleted(Book $book): void
    {
        Event::dispatch(new BookDeletedEvent($book));
    }
}
