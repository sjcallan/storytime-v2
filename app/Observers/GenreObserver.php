<?php

namespace App\Observers;

use App\Events\Genre\GenreCreatedEvent;
use App\Events\Genre\GenreDeletedEvent;
use App\Events\Genre\GenreUpdatedEvent;
use App\Models\Genre;
use Illuminate\Support\Facades\Event;

class GenreObserver
{
    /**
     * Handle the Genre "created" event.
     */
    public function created(Genre $genre): void
    {
        Event::dispatch(new GenreCreatedEvent($genre));
    }

    /**
     * Handle the Genre "updated" event.
     */
    public function updated(Genre $genre): void
    {
        Event::dispatch(new GenreUpdatedEvent($genre));
    }

    /**
     * Handle the Genre "deleted" event.
     */
    public function deleted(Genre $genre): void
    {
        Event::dispatch(new GenreDeletedEvent($genre));
    }
}
