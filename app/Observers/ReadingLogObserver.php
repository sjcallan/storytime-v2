<?php

namespace App\Observers;

use App\Events\ReadingLog\ReadingLogCreatedEvent;
use App\Events\ReadingLog\ReadingLogDeletedEvent;
use App\Events\ReadingLog\ReadingLogUpdatedEvent;
use App\Models\ReadingLog;
use Illuminate\Support\Facades\Event;

class ReadingLogObserver
{
    /**
     * Handle the ReadingLog "created" event.
     */
    public function created(ReadingLog $readingLog): void
    {
        Event::dispatch(new ReadingLogCreatedEvent($readingLog));
    }

    /**
     * Handle the ReadingLog "updated" event.
     */
    public function updated(ReadingLog $readingLog): void
    {
        Event::dispatch(new ReadingLogUpdatedEvent($readingLog));
    }

    /**
     * Handle the ReadingLog "deleted" event.
     */
    public function deleted(ReadingLog $readingLog): void
    {
        Event::dispatch(new ReadingLogDeletedEvent($readingLog));
    }
}
