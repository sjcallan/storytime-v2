<?php

namespace App\Observers;

use App\Events\ChapterCharacter\ChapterCharacterCreatedEvent;
use App\Events\ChapterCharacter\ChapterCharacterDeletedEvent;
use App\Events\ChapterCharacter\ChapterCharacterUpdatedEvent;
use App\Models\ChapterCharacter;
use Illuminate\Support\Facades\Event;

class ChapterCharacterObserver
{
    /**
     * Handle the ChapterCharacter "created" event.
     */
    public function created(ChapterCharacter $chapterCharacter): void
    {
        Event::dispatch(new ChapterCharacterCreatedEvent($chapterCharacter));
    }

    /**
     * Handle the ChapterCharacter "updated" event.
     */
    public function updated(ChapterCharacter $chapterCharacter): void
    {
        Event::dispatch(new ChapterCharacterUpdatedEvent($chapterCharacter));
    }

    /**
     * Handle the ChapterCharacter "deleted" event.
     */
    public function deleted(ChapterCharacter $chapterCharacter): void
    {
        Event::dispatch(new ChapterCharacterDeletedEvent($chapterCharacter));
    }
}
