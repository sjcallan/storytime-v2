<?php

namespace App\Observers;

use App\Events\Character\CharacterCreatedEvent;
use App\Events\Character\CharacterDeletedEvent;
use App\Events\Character\CharacterUpdatedEvent;
use App\Models\Character;
use Illuminate\Support\Facades\Event;

class CharacterObserver
{
    /**
     * Handle the Character "created" event.
     */
    public function created(Character $character): void
    {
        Event::dispatch(new CharacterCreatedEvent($character));
    }

    /**
     * Handle the Character "updated" event.
     */
    public function updated(Character $character): void
    {
        Event::dispatch(new CharacterUpdatedEvent($character));
    }

    /**
     * Handle the Character "deleted" event.
     */
    public function deleted(Character $character): void
    {
        Event::dispatch(new CharacterDeletedEvent($character));
    }
}
