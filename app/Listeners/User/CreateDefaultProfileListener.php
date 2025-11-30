<?php

namespace App\Listeners\User;

use App\Events\User\UserCreatedEvent;

class CreateDefaultProfileListener
{
    /**
     * Handle the event.
     */
    public function handle(UserCreatedEvent $event): void
    {
        $user = $event->user;

        if ($user->profiles()->count() === 0) {
            $user->createDefaultProfile();
        }
    }
}
