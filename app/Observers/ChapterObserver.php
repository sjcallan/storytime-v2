<?php

namespace App\Observers;

use App\Events\Chapter\ChapterCreatedEvent;
use App\Events\Chapter\ChapterDeletedEvent;
use App\Events\Chapter\ChapterUpdatedEvent;
use App\Models\Chapter;
use Illuminate\Support\Facades\Event;

class ChapterObserver
{
    /**
     * Handle the Chapter "created" event.
     */
    public function created(Chapter $chapter): void
    {
        Event::dispatch(new ChapterCreatedEvent($chapter));
    }

    /**
     * Handle the Chapter "updated" event.
     */
    public function updated(Chapter $chapter): void
    {
        Event::dispatch(new ChapterUpdatedEvent($chapter));
    }

    /**
     * Handle the Chapter "deleted" event.
     */
    public function deleted(Chapter $chapter): void
    {
        Event::dispatch(new ChapterDeletedEvent($chapter));
    }
}
