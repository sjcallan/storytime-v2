<?php

namespace App\Listeners\Character;

use App\Jobs\Character\CreateCharacterPortraitJob;

class GenerateCharacterPortraitListener
{
    /**
     * Handle the event.
     */
    public function handle(object $event): void
    {
        $character = $event->character;

        if (! $character) {
            return;
        }

        CreateCharacterPortraitJob::dispatch($character);
    }
}
